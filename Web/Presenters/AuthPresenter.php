<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Entities\{IP, User, PasswordReset, EmailVerification};
use openvk\Web\Models\Repositories\{Bans, IPs, Users, Restores, Verifications};
use openvk\Web\Models\Exceptions\InvalidUserNameException;
use openvk\Web\Util\Validator;
use Chandler\Session\Session;
use Chandler\Security\User as ChandlerUser;
use Chandler\Security\Authenticator;
use Chandler\Database\DatabaseConnection;
use lfkeitel\phptotp\{Base32, Totp};

final class AuthPresenter extends OpenVKPresenter
{
    protected $banTolerant = true;
    protected $activationTolerant = true;
    protected $deactivationTolerant = true;
    
    private $authenticator;
    private $db;
    private $users;
    private $restores;
    private $verifications;
    
    function __construct(Users $users, Restores $restores, Verifications $verifications)
    {
        $this->authenticator = Authenticator::i();
        $this->db = DatabaseConnection::i()->getContext();
        
        $this->users    = $users;
        $this->restores = $restores;
        $this->verifications = $verifications;
        
        parent::__construct();
    }
    
    private function ipValid(): bool
    {
        $ip  = (new IPs)->get(CONNECTING_IP);
        $res = $ip->rateLimit(0);
        
        return $res === IP::RL_RESET || $res === IP::RL_CANEXEC;
    }
    
    function renderRegister(): void
    {
        if(!is_null($this->user))
            $this->redirect($this->user->identity->getURL());
        
        if(!$this->hasPermission("user", "register", -1)) exit("Вас забанили");
        
        $referer = NULL;
        if(!is_null($refLink = $this->queryParam("ref"))) {
            $pieces = explode(" ", $refLink, 2);
            if(sizeof($pieces) !== 2)
                $this->flashFail("err", tr("error"), tr("referral_link_invalid"));
            
            [$ref, $hash] = $pieces;
            $ref  = hexdec($ref);
            $hash = base64_decode($hash);
            
            $referer = (new Users)->get($ref);
            if(!$referer)
                $this->flashFail("err", tr("error"), tr("referral_link_invalid"));
            
            if($referer->getRefLinkId() !== $refLink)
                $this->flashFail("err", tr("error"), tr("referral_link_invalid"));
        }
        
        $this->template->referer = $referer;
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            $this->assertCaptchaCheckPassed();

            if(!OPENVK_ROOT_CONF['openvk']['preferences']['registration']['enable'] && !$referer)
                $this->flashFail("err", tr("failed_to_register"), tr("registration_disabled"));
            
            if(!$this->ipValid())
                $this->flashFail("err", tr("suspicious_registration_attempt"), tr("suspicious_registration_attempt_comment"));
            
            if(!Validator::i()->emailValid($this->postParam("email")))
                $this->flashFail("err", tr("invalid_email_address"), tr("invalid_email_address_comment"));

            if(OPENVK_ROOT_CONF['openvk']['preferences']['security']['forceStrongPassword'])
                if(!Validator::i()->passwordStrong($this->postParam("password")))
                    $this->flashFail("err", tr("error"), tr("error_weak_password"));

            if (strtotime($this->postParam("birthday")) > time())
                $this->flashFail("err", tr("invalid_birth_date"), tr("invalid_birth_date_comment"));

            if (!$this->postParam("confirmation"))
                $this->flashFail("err", tr("error"), tr("checkbox_in_registration_unchecked"));

            try {
                $user = new User;
                $user->setFirst_Name($this->postParam("first_name"));
                $user->setLast_Name($this->postParam("last_name"));
                switch ($this->postParam("pronouns")) {
                    case 'male':
                        $user->setSex(0);
                        break;
                    case 'female':
                        $user->setSex(1);
                        break;
                    case 'neutral':
                        $user->setSex(2);
                        break;
                }
                $user->setEmail($this->postParam("email"));
                $user->setSince(date("Y-m-d H:i:s"));
                $user->setRegistering_Ip(CONNECTING_IP);
                $user->setBirthday(empty($this->postParam("birthday")) ? NULL : strtotime($this->postParam("birthday")));
                $user->setActivated((int)!OPENVK_ROOT_CONF['openvk']['preferences']['security']['requireEmail']);
            } catch(InvalidUserNameException $ex) {
                $this->flashFail("err", tr("error"), tr("invalid_real_name"));
            }

            $chUser = ChandlerUser::create($this->postParam("email"), $this->postParam("password"));
            if(!$chUser)
                $this->flashFail("err", tr("failed_to_register"), tr("user_already_exists"));

            $user->setUser($chUser->getId());
            $user->save(false);
            
            if(!is_null($referer)) {
                $user->toggleSubscription($referer);
                $referer->toggleSubscription($user);
            }

            if (OPENVK_ROOT_CONF['openvk']['preferences']['security']['requireEmail']) {
                $verification = new EmailVerification;
                $verification->setProfile($user->getId());
                $verification->save();
                
                $params = [
                    "key"   => $verification->getKey(),
                    "name"  => $user->getCanonicalName(),
                ];
                $this->sendmail($user->getEmail(), "verify-email", $params); #Vulnerability possible
            }
            
            $this->authenticator->authenticate($chUser->getId());
            $this->redirect("/id" . $user->getId());
            $user->save();
        }
    }
    
    function renderLogin(): void
    {
        $redirUrl = $this->requestParam("jReturnTo");
        
        if(!is_null($this->user))
            $this->redirect($redirUrl ?? $this->user->identity->getURL());
        
        if(!$this->hasPermission("user", "login", -1)) exit("Вас забанили");
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            $user = $this->db->table("ChandlerUsers")->where("login", $this->postParam("login"))->fetch();
            if(!$user)
                $this->flashFail("err", tr("login_failed"), tr("invalid_username_or_password"));
            
            if(!$this->authenticator->verifyCredentials($user->id, $this->postParam("password")))
                $this->flashFail("err", tr("login_failed"), tr("invalid_username_or_password"));

            $ovkUser = new User($user->related("profiles.user")->fetch());
            if($ovkUser->isDeleted() && !$ovkUser->isDeactivated())
                $this->flashFail("err", tr("login_failed"), tr("invalid_username_or_password"));

            $secret = $user->related("profiles.user")->fetch()["2fa_secret"];
            $code   = $this->postParam("code");
            if(!is_null($secret)) {
                $this->template->_template = "Auth/LoginSecondFactor.xml";
                $this->template->login     = $this->postParam("login");
                $this->template->password  = $this->postParam("password");

                if(is_null($code))
                    return;

                if(!($code === (new Totp)->GenerateToken(Base32::decode($secret)) || $ovkUser->use2faBackupCode((int) $code))) {
                    $this->flash("err", tr("login_failed"), tr("incorrect_2fa_code"));
                    return;
                }
            }
            
            $this->authenticator->authenticate($user->id);
            $this->redirect($redirUrl ?? $ovkUser->getURL());
        }
    }
    
    function renderSu(string $uuid): void
    {
        $this->assertNoCSRF();
        $this->assertUserLoggedIn();
        
        if($uuid === "unset") {
            Session::i()->set("_su", NULL);
            $this->redirect("/");
        }
        
        if(!$this->db->table("ChandlerUsers")->where("id", $uuid))
            $this->flashFail("err", tr("token_manipulation_error"), tr("profile_not_found"));
        
        $this->assertPermission('openvk\Web\Models\Entities\User', 'substitute', 0);
        Session::i()->set("_su", $uuid);
        $this->flash("succ", tr("profile_changed"), tr("profile_changed_comment"));
        $this->redirect("/");
    }
    
    function renderLogout(): void
    {
        $this->assertUserLoggedIn();
        $this->assertNoCSRF();
        $this->authenticator->logout();
        Session::i()->set("_su", NULL);
        
        $this->redirect("/");
    }
    
    function renderFinishRestoringPassword(): void
    {
        if(OPENVK_ROOT_CONF['openvk']['preferences']['security']['disablePasswordRestoring'])
            $this->notFound();

        $request = $this->restores->getByToken(str_replace(" ", "+", $this->queryParam("key")));
        if(!$request || !$request->isStillValid()) {
            $this->flash("err", tr("token_manipulation_error"), tr("token_manipulation_error_comment"));
            $this->redirect("/");
            return;
        }

        $this->template->is2faEnabled = $request->getUser()->is2faEnabled();
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            if($request->getUser()->is2faEnabled()) {
                $user = $request->getUser();
                $code = $this->postParam("code");
                $secret = $user->get2faSecret();
                if(!($code === (new Totp)->GenerateToken(Base32::decode($secret)) || $user->use2faBackupCode((int) $code))) {
                    $this->flash("err", tr("error"), tr("incorrect_2fa_code"));
                    return;
                }
            }

            $user = $request->getUser()->getChandlerUser();
            $this->db->table("ChandlerTokens")->where("user", $user->getId())->delete(); #Logout from everywhere
            
            $user->updatePassword($this->postParam("password"));
            $this->authenticator->authenticate($user->getId());
            
            $request->delete(false);
            $this->flash("succ", tr("information_-1"), tr("password_successfully_reset"));
            $this->redirect("/settings");
        }
    }
    
    function renderRestore(): void
    {
        if(OPENVK_ROOT_CONF['openvk']['preferences']['security']['disablePasswordRestoring'])
            $this->notFound();

        if(!is_null($this->user))
            $this->redirect($this->user->identity->getURL());

        if(($this->queryParam("act") ?? "default") === "finish")
            $this->pass("openvk!Auth->finishRestoringPassword");
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            $uRow = $this->db->table("ChandlerUsers")->where("login", $this->postParam("login"))->fetch();
            if(!$uRow) {
                #Privacy of users must be protected. We will not tell if email is bound to a user or not.
                $this->flashFail("succ", tr("information_-1"), tr("password_reset_email_sent"));
            }
            
            $user = $this->users->getByChandlerUser(new ChandlerUser($uRow));
            if(!$user || $user->isDeleted())
                $this->flashFail("err", tr("error"), tr("password_reset_error"));
            
            $request = $this->restores->getLatestByUser($user);
            if(!is_null($request) && $request->isNew())
                $this->flashFail("err", tr("forbidden"), tr("password_reset_rate_limit_error"));
            
            $resetObj = new PasswordReset;
            $resetObj->setProfile($user->getId());
            $resetObj->save();
            
            $params = [
                "key"   => $resetObj->getKey(),
                "name"  => $user->getCanonicalName(),
            ];
            $this->sendmail($uRow->login, "password-reset", $params); #Vulnerability possible
            
            $this->flashFail("succ", tr("information_-1"), tr("password_reset_email_sent"));
        }
    }

    function renderResendEmail(): void
    {
        if(!is_null($this->user) && $this->user->identity->isActivated())
            $this->redirect($this->user->identity->getURL());

        if($_SERVER["REQUEST_METHOD"] === "POST") {
            $user = $this->user->identity;
            if(!$user || $user->isDeleted() || $user->isActivated())
                $this->flashFail("err", tr("error"), tr("email_error"));
            
            $request = $this->verifications->getLatestByUser($user);
            if(!is_null($request) && $request->isNew())
                $this->flashFail("err", tr("forbidden"), tr("email_rate_limit_error"));
            
            $verification = new EmailVerification;
            $verification->setProfile($user->getId());
            $verification->save();
            
            $params = [
                "key"   => $verification->getKey(),
                "name"  => $user->getCanonicalName(),
            ];
            $this->sendmail($user->getEmail(), "verify-email", $params); #Vulnerability possible
            
            $this->flashFail("succ", tr("information_-1"), tr("email_sent"));
        }
    }

    function renderVerifyEmail(): void
    {
        $request = $this->verifications->getByToken(str_replace(" ", "+", $this->queryParam("key")));
        if(!$request || !$request->isStillValid()) {
            $this->flash("err", tr("token_manipulation_error"), tr("token_manipulation_error_comment"));
            $this->redirect("/");
        } else {
            $user = $request->getUser();
            $user->setActivated(1);
            $user->save();

            $this->flash("success", tr("email_verify_success"));
            $this->redirect("/");
        }
    }

    function renderReactivatePage(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();

        $this->user->identity->reactivate();

        $this->redirect("/");
    }

    function renderUnbanThemself(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();

        if(!$this->user->identity->canUnbanThemself())
            $this->flashFail("err", tr("error"), tr("forbidden"));

        $user = $this->users->get($this->user->id);
        $ban = (new Bans)->get((int)$user->getRawBanReason());
        if (!$ban || $ban->isOver() || $ban->isPermanent())
            $this->flashFail("err", tr("error"), tr("forbidden"));

        $ban->setRemoved_Manually(2);
        $ban->setRemoved_By($this->user->identity->getId());
        $ban->save();

        $user->setBlock_Reason(NULL);
        // $user->setUnblock_Time(NULL);
        $user->save();

        $this->flashFail("succ", tr("banned_unban_title"), tr("banned_unban_description"));
    }
    
    /*
     * This function will revoke all tokens, including API and Web tokens and except active one
     * 
     * OF COURSE it requires CSRF
     */ 
    function renderRevokeAllTokens(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        $this->assertNoCSRF();

        // API tokens
        $this->db->table("api_tokens")->where("user", $this->user->identity->getId())->delete();
        // Web tokens
        $this->db->table("ChandlerTokens")->where("user", $this->user->identity->getChandlerGUID())->where("token != ?", Session::i()->get("tok"))->delete();
        $this->flashFail("succ", tr("information_-1"), tr("end_all_sessions_done"));
    }
} 
