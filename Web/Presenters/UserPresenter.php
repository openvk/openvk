<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use Nette\InvalidStateException;
use openvk\Web\Util\Sms;
use openvk\Web\Themes\Themepacks;
use openvk\Web\Models\Entities\{Photo, Post, EmailChangeVerification};
use openvk\Web\Models\Entities\Notifications\{CoinsTransferNotification, RatingUpNotification};
use openvk\Web\Models\Repositories\{Users, Clubs, Albums, Videos, Notes, Vouchers, EmailChangeVerifications};
use openvk\Web\Models\Exceptions\InvalidUserNameException;
use openvk\Web\Util\Validator;
use Chandler\Security\Authenticator;
use lfkeitel\phptotp\{Base32, Totp};
use chillerlan\QRCode\{QRCode, QROptions};
use Nette\Database\UniqueConstraintViolationException;

final class UserPresenter extends OpenVKPresenter
{
    private $users;
    public $deactivationTolerant = false;
    protected $presenterName = "user";

    function __construct(Users $users)
    {
        $this->users = $users;

        parent::__construct();
    }
    
    function renderView(int $id): void
    {
        $user = $this->users->get($id);
        if(!$user || $user->isDeleted()) {
            if(!is_null($user) && $user->isDeactivated()) {
                $this->template->_template = "User/deactivated.xml";
                
                $this->template->user = $user;
            } else {
                $this->template->_template = "User/deleted.xml";
            }
        } else {
            $this->template->albums      = (new Albums)->getUserAlbums($user);
            $this->template->avatarAlbum = (new Albums)->getUserAvatarAlbum($user);
            $this->template->albumsCount = (new Albums)->getUserAlbumsCount($user);
            $this->template->videos      = (new Videos)->getByUser($user, 1, 2);
            $this->template->videosCount = (new Videos)->getUserVideosCount($user);
            $this->template->notes       = (new Notes)->getUserNotes($user, 1, 4);
            $this->template->notesCount  = (new Notes)->getUserNotesCount($user);
            
            $this->template->user = $user;
        }
    }
    
    function renderFriends(int $id): void
    {
        $this->assertUserLoggedIn();
        
        $user = $this->users->get($id);
        $page = abs($this->queryParam("p") ?? 1);
        if(!$user)
            $this->notFound();
        elseif (!$user->getPrivacyPermission('friends.read', $this->user->identity ?? NULL))
            $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));
        else
            $this->template->user = $user;
        
        $this->template->mode = in_array($this->queryParam("act"), [
            "incoming", "outcoming", "friends"
        ]) ? $this->queryParam("act")
           : "friends";
        $this->template->page = $page;
        
        if(!is_null($this->user)) {
            if($this->template->mode !== "friends" && $this->user->id !== $id) {
                $name = $user->getFullName();
                $this->flash("err", tr("error_access_denied_short"), tr("error_viewing_subs", $name));
                
                $this->redirect($user->getURL());
            }
        }
    }
    
    function renderGroups(int $id): void
    {
        $this->assertUserLoggedIn();
        
        $user = $this->users->get($id);
        if(!$user)
            $this->notFound();
        elseif (!$user->getPrivacyPermission('groups.read', $this->user->identity ?? NULL))
            $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));
        else {
            if($this->queryParam("act") === "managed" && $this->user->id !== $user->getId())
                $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));

            $this->template->user = $user;
            $this->template->page = (int) ($this->queryParam("p") ?? 1);
            $this->template->admin = $this->queryParam("act") == "managed";
        }
    }

    function renderPinClub(): void
    {
        $this->assertUserLoggedIn();

        $club = (new Clubs)->get((int) $this->queryParam("club"));
        if(!$club)
            $this->notFound();

        if(!$club->canBeModifiedBy($this->user->identity ?? NULL))
            $this->flashFail("err", tr("error_access_denied_short"), tr("error_access_denied"), NULL, true);

        $isClubPinned = $this->user->identity->isClubPinned($club);
        if(!$isClubPinned && $this->user->identity->getPinnedClubCount() > 10)
            $this->flashFail("err", tr("error"), tr("error_max_pinned_clubs"), NULL, true);

        if($club->getOwner()->getId() === $this->user->identity->getId()) {
            $club->setOwner_Club_Pinned(!$isClubPinned);
            $club->save();
        } else {
            $manager = $club->getManager($this->user->identity);
            if(!is_null($manager)) {
                $manager->setClub_Pinned(!$isClubPinned);
                $manager->save();
            }
        }

        $this->returnJson([
            "success" => true
        ]);
    }
    
    function renderEdit(): void
    {
        $this->assertUserLoggedIn();
        
        $id = $this->user->id; #TODO: when ACL'll be done, allow admins to edit users via ?GUID=(chandler guid)
        
        if(!$id)
            $this->notFound();

        $user = $this->users->get($id);
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            $this->willExecuteWriteAction($_GET['act'] === "status");
            
            if($_GET['act'] === "main" || $_GET['act'] == NULL) {
                try {
                    $user->setFirst_Name(empty($this->postParam("first_name")) ? $user->getFirstName() : $this->postParam("first_name"));
                    $user->setLast_Name(empty($this->postParam("last_name")) ? "" : $this->postParam("last_name"));
                } catch(InvalidUserNameException $ex) {
                    $this->flashFail("err", tr("error"), tr("invalid_real_name"));
                }
                
                $user->setPseudo(empty($this->postParam("pseudo")) ? NULL : $this->postParam("pseudo"));
                $user->setStatus(empty($this->postParam("status")) ? NULL : $this->postParam("status"));
				$user->setHometown(empty($this->postParam("hometown")) ? NULL : $this->postParam("hometown"));
                

                if (strtotime($this->postParam("birthday")) < time())
                $user->setBirthday(empty($this->postParam("birthday")) ? NULL : strtotime($this->postParam("birthday")));

                if ($this->postParam("birthday_privacy") <= 1 && $this->postParam("birthday_privacy") >= 0)
                $user->setBirthday_Privacy($this->postParam("birthday_privacy"));

                if ($this->postParam("marialstatus") <= 8 && $this->postParam("marialstatus") >= 0)
                $user->setMarital_Status($this->postParam("marialstatus"));
                
                if ($this->postParam("politViews") <= 9 && $this->postParam("politViews") >= 0)
                $user->setPolit_Views($this->postParam("politViews"));
                
                if ($this->postParam("gender") <= 1 && $this->postParam("gender") >= 0)
                $user->setSex($this->postParam("gender"));
                
                if(!empty($this->postParam("phone")) && $this->postParam("phone") !== $user->getPhone()) {
                    if(!OPENVK_ROOT_CONF["openvk"]["credentials"]["smsc"]["enable"])
                        $this->flashFail("err", tr("error_segmentation"), "котлетки");
                    
                    $code = $user->setPhoneWithVerification($this->postParam("phone"));
                    
                    if(!Sms::send($this->postParam("phone"), "OPENVK - Your verification code is: $code"))
                        $this->flashFail("err", tr("error_segmentation"), "котлетки: Remote err!");
                }
            } elseif($_GET['act'] === "contacts") {
                if(empty($this->postParam("email_contact")) || Validator::i()->emailValid($this->postParam("email_contact")))
                    $user->setEmail_Contact(empty($this->postParam("email_contact")) ? NULL : $this->postParam("email_contact"));
                else
                    $this->flashFail("err", tr("invalid_email_address"), tr("invalid_email_address_comment"));

                $telegram = $this->postParam("telegram");
                if(empty($telegram) || Validator::i()->telegramValid($telegram))
                    if(strpos($telegram, "t.me/") === 0)
                        $user->setTelegram(empty($telegram) ? NULL : substr($telegram, 5));
                    else
                        $user->setTelegram(empty($telegram) ? NULL : ltrim($telegram, "@"));
                else
                    $this->flashFail("err", tr("invalid_telegram_name"), tr("invalid_telegram_name_comment"));

                $user->setCity(empty($this->postParam("city")) ? NULL : $this->postParam("city"));
                $user->setAddress(empty($this->postParam("address")) ? NULL : $this->postParam("address"));
				
                $website = $this->postParam("website") ?? "";
                if(empty($website))
                    $user->setWebsite(NULL);
                else
                    $user->setWebsite((!parse_url($website, PHP_URL_SCHEME) ? "https://" : "") . $website);
            } elseif($_GET['act'] === "interests") {
                $user->setInterests(empty($this->postParam("interests")) ? NULL : ovk_proc_strtr($this->postParam("interests"), 300));
                $user->setFav_Music(empty($this->postParam("fav_music")) ? NULL : ovk_proc_strtr($this->postParam("fav_music"), 300));
                $user->setFav_Films(empty($this->postParam("fav_films")) ? NULL : ovk_proc_strtr($this->postParam("fav_films"), 300));
                $user->setFav_Shows(empty($this->postParam("fav_shows")) ? NULL : ovk_proc_strtr($this->postParam("fav_shows"), 300));
                $user->setFav_Books(empty($this->postParam("fav_books")) ? NULL : ovk_proc_strtr($this->postParam("fav_books"), 300));
                $user->setFav_Quote(empty($this->postParam("fav_quote")) ? NULL : ovk_proc_strtr($this->postParam("fav_quote"), 300));
                $user->setAbout(empty($this->postParam("about")) ? NULL : ovk_proc_strtr($this->postParam("about"), 300));
            } elseif($_GET["act"] === "backdrop") {
                if($this->postParam("subact") === "remove") {
                    $user->unsetBackDropPictures();
                    $user->save();
                    $this->flashFail("succ", tr("backdrop_succ_rem"), tr("backdrop_succ_desc")); # will exit
                }
    
                $pic1 = $pic2 = NULL;
                try {
                    if($_FILES["backdrop1"]["error"] !== UPLOAD_ERR_NO_FILE)
                        $pic1 = Photo::fastMake($user->getId(), "Profile backdrop (system)", $_FILES["backdrop1"]);
    
                    if($_FILES["backdrop2"]["error"] !== UPLOAD_ERR_NO_FILE)
                        $pic2 = Photo::fastMake($user->getId(), "Profile backdrop (system)", $_FILES["backdrop2"]);
                } catch(InvalidStateException $e) {
                    $this->flashFail("err", tr("backdrop_error_title"), tr("backdrop_error_no_media"));
                }
                
                if($pic1 == $pic2 && is_null($pic1))
                    $this->flashFail("err", tr("backdrop_error_title"), tr("backdrop_error_no_media"));
                
                $user->setBackDropPictures($pic1, $pic2);
                $user->save();
                $this->flashFail("succ", tr("backdrop_succ"), tr("backdrop_succ_desc"));
            } elseif($_GET['act'] === "status") {
                if(mb_strlen($this->postParam("status")) > 255) {
                    $statusLength = (string) mb_strlen($this->postParam("status"));
                    $this->flashFail("err", tr("error"), tr("error_status_too_long", $statusLength), NULL, true);
                }

                $user->setStatus(empty($this->postParam("status")) ? NULL : $this->postParam("status"));
                $user->save();

                $this->returnJson([
                    "success" => true
                ]);
            }
            
            try {
                $user->save();
            } catch(\PDOException $ex) {
                if($ex->getCode() == 23000)
                    $this->flashFail("err", tr("error"), tr("error_shorturl"));
                else
                    throw $ex;
            }
            
            $this->flash("succ", tr("changes_saved"), tr("changes_saved_comment"));
        }
        
        $this->template->mode = in_array($this->queryParam("act"), [
            "main", "contacts", "interests", "avatar", "backdrop"
        ]) ? $this->queryParam("act")
            : "main";
        
        $this->template->user = $user;
    }
    
    function renderVerifyPhone(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $user = $this->user->identity;
        if(!$user->hasPendingNumberChange())
            exit;
        else
            $this->template->change = $user->getPendingPhoneVerification();
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            if(!$user->verifyNumber($this->postParam("code") ?? 0))
                $this->flashFail("err", tr("error"), tr("invalid_code"));
        
            $this->flash("succ", tr("changes_saved"), tr("changes_saved_comment"));
        }
    }
    
    function renderSub(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        if($_SERVER["REQUEST_METHOD"] !== "POST") exit("Invalid state");
        
        $user = $this->users->get((int) $this->postParam("id"));
        if(!$user) exit("Invalid state");
        
        $user->toggleSubscription($this->user->identity);
        
        $this->redirect($user->getURL());
    }
    
    function renderSetAvatar()
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $photo = new Photo;
        try {
            $photo->setOwner($this->user->id);
            $photo->setDescription("Profile image");
            $photo->setFile($_FILES["blob"]);
            $photo->setCreated(time());
            $photo->save();
        } catch(ISE $ex) {
            $this->flashFail("err", tr("error"), tr("error_upload_failed"));
        }
        
        $album = (new Albums)->getUserAvatarAlbum($this->user->identity);
        $album->addPhoto($photo);
        $album->setEdited(time());
        $album->save();

        $flags = 0;
        $flags |= 0b00010000;

        $post = new Post;
        $post->setOwner($this->user->id);
        $post->setWall($this->user->id);
        $post->setCreated(time());
        $post->setContent("");
        $post->setFlags($flags);
        $post->save();
        $post->attach($photo);
        if($this->postParam("ava", true) == (int)1) {
            $this->returnJson([
                "url" => $photo->getURL(),
                "id" => $photo->getPrettyId()
            ]);
        } else {
            $this->flashFail("succ", tr("photo_saved"), tr("photo_saved_comment"));
        }
    }
    
    function renderSettings(): void
    {
        $this->assertUserLoggedIn();
        
        $id = $this->user->id; #TODO: when ACL'll be done, allow admins to edit users via ?GUID=(chandler guid)
        
        if(!$id)
            $this->notFound();

        if(in_array($this->queryParam("act"), ["finance", "finance.top-up"]) && !OPENVK_ROOT_CONF["openvk"]["preferences"]["commerce"])
            $this->flashFail("err", tr("error"), tr("feature_disabled"));
        
        $user = $this->users->get($id);
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            $this->willExecuteWriteAction();
            
            if($_GET['act'] === "main" || $_GET['act'] == NULL) {
                if($this->postParam("old_pass") && $this->postParam("new_pass") && $this->postParam("repeat_pass")) {
                    if($this->postParam("new_pass") === $this->postParam("repeat_pass")) {
                        if($this->user->identity->is2faEnabled()) {
                            $code = $this->postParam("password_change_code");
                            if(!($code === (new Totp)->GenerateToken(Base32::decode($this->user->identity->get2faSecret())) || $this->user->identity->use2faBackupCode((int) $code)))
                                $this->flashFail("err", tr("error"), tr("incorrect_2fa_code"));
                        }

                        if(!$this->user->identity->getChandlerUser()->updatePassword($this->postParam("new_pass"), $this->postParam("old_pass")))
                            $this->flashFail("err", tr("error"), tr("error_old_password"));
                    } else {
                        $this->flashFail("err", tr("error"), tr("error_new_password"));
                    }
                }

                if($this->postParam("new_email")) {
                    if(!Validator::i()->emailValid($this->postParam("new_email")))
                        $this->flashFail("err", tr("invalid_email_address"), tr("invalid_email_address_comment"));

                    if(!Authenticator::verifyHash($this->postParam("email_change_pass"), $user->getChandlerUser()->getRaw()->passwordHash))
                        $this->flashFail("err", tr("error"), tr("incorrect_password"));
                    
                    if($user->is2faEnabled()) {
                        $code = $this->postParam("email_change_code");
                        if(!($code === (new Totp)->GenerateToken(Base32::decode($user->get2faSecret())) || $user->use2faBackupCode((int) $code)))
                            $this->flashFail("err", tr("error"), tr("incorrect_2fa_code"));
                    }

                    if($this->postParam("new_email") !== $user->getEmail()) {
                        if (OPENVK_ROOT_CONF['openvk']['preferences']['security']['requireEmail']) {    
                            $request = (new EmailChangeVerifications)->getLatestByUser($user);
                            if(!is_null($request) && $request->isNew())
                                $this->flashFail("err", tr("forbidden"), tr("email_rate_limit_error"));
                            
                            $verification = new EmailChangeVerification;
                            $verification->setProfile($user->getId());
                            $verification->setNew_Email($this->postParam("new_email"));
                            $verification->save();
                            
                            $params = [
                                "key"   => $verification->getKey(),
                                "name"  => $user->getCanonicalName(),
                            ];
                            $this->sendmail($this->postParam("new_email"), "change-email", $params); #Vulnerability possible
                            $this->flashFail("succ", tr("information_-1"), tr("email_change_confirm_message"));
                        }
    
                        try {
                            $user->changeEmail($this->postParam("new_email"));
                        } catch(UniqueConstraintViolationException $ex) {
                            $this->flashFail("err", tr("error"), tr("user_already_exists"));
                        }   
                    }
                }
                
                if(!$user->setShortCode(empty($this->postParam("sc")) ? NULL : $this->postParam("sc")))
                    $this->flashFail("err", tr("error"), tr("error_shorturl_incorrect"));
            } else if($_GET['act'] === "privacy") {
                $settings = [
                    "page.read",
                    "page.info.read",
                    "groups.read",
                    "photos.read",
                    "videos.read",
                    "notes.read",
                    "friends.read",
                    "friends.add",
                    "wall.write",
                    "messages.write",
                ];
                foreach($settings as $setting) {
                    $input = $this->postParam(str_replace(".", "_", $setting));
                    $user->setPrivacySetting($setting, min(3, abs($input ?? $user->getPrivacySetting($setting))));
                }
            } else if($_GET['act'] === "finance.top-up") {
                $token   = $this->postParam("key0") . $this->postParam("key1") . $this->postParam("key2") . $this->postParam("key3");
                $voucher = (new Vouchers)->getByToken($token);
                if(!$voucher)
                    $this->flashFail("err", tr("invalid_voucher"), tr("voucher_bad"));
                
                $perm = $voucher->willUse($user);
                if(!$perm)
                    $this->flashFail("err", tr("invalid_voucher"), tr("voucher_bad"));
                
                $user->setCoins($user->getCoins() + $voucher->getCoins());
                $user->setRating($user->getRating() + $voucher->getRating());
                $user->save();
                
                $this->flashFail("succ", tr("voucher_good"), tr("voucher_redeemed"));
            } else if($_GET['act'] === "interface") {
                if (isset(Themepacks::i()[$this->postParam("style")]) || $this->postParam("style") === Themepacks::DEFAULT_THEME_ID)
				{
					if ($this->postParam("theme_for_session") != "1") $user->setStyle($this->postParam("style"));
					$this->setSessionTheme($this->postParam("style"));
				}
                
                if ($this->postParam("style_avatar") <= 2 && $this->postParam("style_avatar") >= 0)
                    $user->setStyle_Avatar((int)$this->postParam("style_avatar"));
                
                if (in_array($this->postParam("rating"), [0, 1]))
                    $user->setShow_Rating((int) $this->postParam("rating"));

                if (in_array($this->postParam("microblog"), [0, 1]))
                    $user->setMicroblog((int) $this->postParam("microblog"));
                
                if(in_array($this->postParam("nsfw"), [0, 1, 2]))
                    $user->setNsfwTolerance((int) $this->postParam("nsfw"));

                if(in_array($this->postParam("main_page"), [0, 1]))
                    $user->setMain_Page((int) $this->postParam("main_page"));
            } else if($_GET['act'] === "lMenu") {
                $settings = [
                    "menu_bildoj"    => "photos",
                    "menu_filmetoj"  => "videos",
                    "menu_mesagoj"   => "messages",
                    "menu_notatoj"   => "notes",
                    "menu_grupoj"    => "groups",
                    "menu_novajoj"   => "news",
                    "menu_ligiloj"   => "links",
                    "menu_standardo" => "poster",
                    "menu_aplikoj"   => "apps"
                ];
                foreach($settings as $checkbox => $setting)
                    $user->setLeftMenuItemStatus($setting, $this->checkbox($checkbox));
            }
            
            try {
                $user->save();
            } catch(\PDOException $ex) {
                if($ex->getCode() == 23000)
                    $this->flashFail("err", tr("error"), tr("error_shorturl"));
                else
                    throw $ex;
            }
            
			$this->flash("succ", tr("changes_saved"), tr("changes_saved_comment"));
        }
        $this->template->mode = in_array($this->queryParam("act"), [
            "main", "security", "privacy", "finance", "finance.top-up", "interface"
        ]) ? $this->queryParam("act")
            : "main";

        if($this->template->mode == "finance") {
            $address = OPENVK_ROOT_CONF["openvk"]["preferences"]["ton"]["address"];
            $text    = str_replace("$1", (string) $this->user->identity->getId(), OPENVK_ROOT_CONF["openvk"]["preferences"]["ton"]["hint"]);
            $qrCode  = explode("base64,", (new QRCode(new QROptions([
                "imageTransparent" => false
            ])))->render("ton://transfer/$address?text=$text"));

            $this->template->qrCodeType = substr($qrCode[0], 5);
            $this->template->qrCodeData = $qrCode[1];
        }
        
        $this->template->user   = $user;
        $this->template->themes = Themepacks::i()->getThemeList();
    }

    function renderDeactivate(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();

        $flags = 0;
        $reason = $this->postParam("deactivate_reason");
        $share = $this->postParam("deactivate_share");

        if($share) {
            $flags |= 0b00100000;

            $post = new Post;
            $post->setOwner($this->user->id);
            $post->setWall($this->user->id);
            $post->setCreated(time());
            $post->setContent($reason);
            $post->setFlags($flags);
            $post->save();
        }

        $this->user->identity->deactivate($reason);

        $this->redirect("/");
    }

    function renderTwoFactorAuthSettings(): void
    {
        $this->assertUserLoggedIn();

        if($this->user->identity->is2faEnabled()) {
            if($_SERVER["REQUEST_METHOD"] === "POST") {
                if(!Authenticator::verifyHash($this->postParam("password"), $this->user->identity->getChandlerUser()->getRaw()->passwordHash))
                    $this->flashFail("err", tr("error"), tr("incorrect_password"));

                $this->user->identity->generate2faBackupCodes();
                $this->template->_template = "User/TwoFactorAuthCodes.xml";
                $this->template->codes = $this->user->identity->get2faBackupCodes();
                return;
            }

            $this->redirect("/settings");
        }

        $secret = Base32::encode(Totp::GenerateSecret(16));
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            $this->willExecuteWriteAction();

            if(!Authenticator::verifyHash($this->postParam("password"), $this->user->identity->getChandlerUser()->getRaw()->passwordHash))
                $this->flashFail("err", tr("error"), tr("incorrect_password"));

            $secret = $this->postParam("secret");
            $code   = $this->postParam("code");

            if($code === (new Totp)->GenerateToken(Base32::decode($secret))) {
                $this->user->identity->set2fa_secret($secret);
                $this->user->identity->save();

                $this->flash("succ", tr("two_factor_authentication_enabled_message"), tr("two_factor_authentication_enabled_message_description"));
                $this->redirect("/settings");
            }

            $this->template->secret = $secret;
            $this->flash("err", tr("error"), tr("incorrect_code"));
        } else {
            $this->template->secret = $secret;
        }

        # Why are these crutch? For some reason, the QR code is not displayed if you just pass the render output to the view

        $issuer = OPENVK_ROOT_CONF["openvk"]["appearance"]["name"];
        $email  = $this->user->identity->getEmail();
        $qrCode = explode("base64,", (new QRCode(new QROptions([
            "imageTransparent" => false
        ])))->render("otpauth://totp/$issuer:$email?secret=$secret&issuer=$issuer"));

        $this->template->qrCodeType = substr($qrCode[0], 5);
        $this->template->qrCodeData = $qrCode[1];
    }

    function renderDisableTwoFactorAuth(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();

        if(!Authenticator::verifyHash($this->postParam("password"), $this->user->identity->getChandlerUser()->getRaw()->passwordHash))
            $this->flashFail("err", tr("error"), tr("incorrect_password"));

        $this->user->identity->set2fa_secret(NULL);
        $this->user->identity->save();
        $this->flashFail("succ", tr("information_-1"), tr("two_factor_authentication_disabled_message"));
    }

    function renderResetThemepack(): void
    {
        $this->assertNoCSRF();

        $this->setSessionTheme(Themepacks::DEFAULT_THEME_ID);

        if($this->user) {
            $this->willExecuteWriteAction();

            $this->user->identity->setStyle(Themepacks::DEFAULT_THEME_ID);
            $this->user->identity->save();
        }

        $this->redirect("/");
    }

    function renderCoinsTransfer(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();

        if(!OPENVK_ROOT_CONF["openvk"]["preferences"]["commerce"])
            $this->flashFail("err", tr("error"), tr("feature_disabled"));

        $receiverAddress = $this->postParam("receiver");
        $value           = (int) $this->postParam("value");
        $message         = $this->postParam("message");

        if(!$receiverAddress || !$value)
            $this->flashFail("err", tr("failed_to_tranfer_points"), tr("not_all_information_has_been_entered"));

        if($value < 0)
            $this->flashFail("err", tr("failed_to_tranfer_points"), tr("negative_transfer_value"));

        if(iconv_strlen($message) > 255)
            $this->flashFail("err", tr("failed_to_tranfer_points"), tr("message_is_too_long"));

        $receiver = $this->users->getByAddress($receiverAddress);
        if(!$receiver)
            $this->flashFail("err", tr("failed_to_tranfer_points"), tr("receiver_not_found"));

        if($this->user->identity->getCoins() < $value)
            $this->flashFail("err", tr("failed_to_tranfer_points"), tr("you_dont_have_enough_points"));

        if($this->user->id !== $receiver->getId()) {
            $this->user->identity->setCoins($this->user->identity->getCoins() - $value);
            $this->user->identity->save();

            $receiver->setCoins($receiver->getCoins() + $value);
            $receiver->save();

            (new CoinsTransferNotification($receiver, $this->user->identity, $value, $message))->emit();
        }

        $this->flashFail("succ", tr("information_-1"), tr("points_transfer_successful", tr("points_amount", $value), $receiver->getURL(), htmlentities($receiver->getCanonicalName())));
    }

    function renderIncreaseRating(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();

        if(!OPENVK_ROOT_CONF["openvk"]["preferences"]["commerce"])
            $this->flashFail("err", tr("error"), tr("feature_disabled"));

        $receiverAddress = $this->postParam("receiver");
        $value           = (int) $this->postParam("value");
        $message         = $this->postParam("message");

        if(!$receiverAddress || !$value)
            $this->flashFail("err", tr("failed_to_increase_rating"), tr("not_all_information_has_been_entered"));

        if($value < 0)
            $this->flashFail("err", tr("failed_to_increase_rating"), tr("negative_rating_value"));

        if(iconv_strlen($message) > 255)
            $this->flashFail("err", tr("failed_to_increase_rating"), tr("message_is_too_long"));

        $receiver = $this->users->getByAddress($receiverAddress);
        if(!$receiver)
            $this->flashFail("err", tr("failed_to_increase_rating"), tr("receiver_not_found"));

        if($this->user->identity->getCoins() < $value)
            $this->flashFail("err", tr("failed_to_increase_rating"), tr("you_dont_have_enough_points"));

        $this->user->identity->setCoins($this->user->identity->getCoins() - $value);
        $this->user->identity->save();

        $receiver->setRating($receiver->getRating() + $value);
        $receiver->save();

        if($this->user->id !== $receiver->getId())
            (new RatingUpNotification($receiver, $this->user->identity, $value, $message))->emit();

        $this->flashFail("succ", tr("information_-1"), tr("rating_increase_successful", $receiver->getURL(), htmlentities($receiver->getCanonicalName()), $value));
    }

    function renderEmailChangeFinish(): void
    {
        $request = (new EmailChangeVerifications)->getByToken(str_replace(" ", "+", $this->queryParam("key")));
        if(!$request || !$request->isStillValid()) {
            $this->flash("err", tr("token_manipulation_error"), tr("token_manipulation_error_comment"));
            $this->redirect("/settings");
        } else {
            $request->delete(false);

            try {
                $request->getUser()->changeEmail($request->getNewEmail());
            } catch(UniqueConstraintViolationException $ex) {
                $this->flashFail("err", tr("error"), tr("user_already_exists"));
            }

            $this->flash("succ", tr("changes_saved"), tr("changes_saved_comment"));
            $this->redirect("/settings");
        }
    }
}
