<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use Chandler\Database\Log;
use Chandler\Database\Logs;
use openvk\Web\Models\Entities\{Voucher, Gift, GiftCategory, User, BannedLink};
use openvk\Web\Models\Repositories\{Bans, ChandlerGroups, ChandlerUsers, Photos, Posts, Users, Clubs, Videos, Vouchers, Gifts, BannedLinks};
use Chandler\Database\DatabaseConnection;

final class AdminPresenter extends OpenVKPresenter
{
    private $users;
    private $clubs;
    private $vouchers;
    private $gifts;
    private $bannedLinks;
    private $chandlerGroups;
    private $logs;

    function __construct(Users $users, Clubs $clubs, Vouchers $vouchers, Gifts $gifts, BannedLinks $bannedLinks, ChandlerGroups $chandlerGroups)
    {
        $this->users    = $users;
        $this->clubs    = $clubs;
        $this->vouchers = $vouchers;
        $this->gifts    = $gifts;
        $this->bannedLinks = $bannedLinks;
        $this->chandlerGroups = $chandlerGroups;
        $this->logs = DatabaseConnection::i()->getContext()->table("ChandlerLogs");
        
        parent::__construct();
    }
    
    private function warnIfNoCommerce(): void
    {
        if(!OPENVK_ROOT_CONF["openvk"]["preferences"]["commerce"])
            $this->flash("warn", tr("admin_commerce_disabled"), tr("admin_commerce_disabled_desc"));
    }
    
    private function searchResults(object $repo, &$count)
    {
        $query = $this->queryParam("q") ?? "";
        $page  = (int) ($this->queryParam("p") ?? 1);
        
        $count = $repo->find($query)->size();
        return $repo->find($query)->page($page, 20);
    }
    
    function onStartup(): void
    {
        parent::onStartup();
        
        $this->assertPermission("admin", "access", -1);
    }
    
    function renderIndex(): void
    {
        
    }
    
    function renderUsers(): void
    {
        $this->template->users = $this->searchResults($this->users, $this->template->count);
    }
    
    function renderUser(int $id): void
    {
        $user = $this->users->get($id);
        if(!$user)
            $this->notFound();
        
        $this->template->user = $user;
        $this->template->c_groups_list = (new ChandlerGroups)->getList();
        $this->template->c_memberships = $this->chandlerGroups->getUsersMemberships($user->getChandlerGUID());

        if($_SERVER["REQUEST_METHOD"] !== "POST")
            return;
        
        switch($_POST["act"] ?? "info") {
            default:
            case "info":
                $user->setFirst_Name($this->postParam("first_name"));
                $user->setLast_Name($this->postParam("last_name"));
                $user->setPseudo($this->postParam("nickname"));
                $user->setStatus($this->postParam("status"));
                if(!$user->setShortCode(empty($this->postParam("shortcode")) ? NULL : $this->postParam("shortcode")))
                    $this->flash("err", tr("error"), tr("error_shorturl_incorrect"));
                $user->changeEmail($this->postParam("email"));
                if($user->onlineStatus() != $this->postParam("online")) $user->setOnline(intval($this->postParam("online")));
                $user->setVerified(empty($this->postParam("verify") ? 0 : 1));
                if($this->postParam("add-to-group")) {
                    $query = "INSERT INTO `ChandlerACLRelations` (`user`, `group`) VALUES ('" . $user->getChandlerGUID() . "', '" . $this->postParam("add-to-group") . "')";
                    DatabaseConnection::i()->getConnection()->query($query);
                }
                if($this->postParam("password")) {
                    $user->getChandlerUser()->updatePassword($this->postParam("password"));
                }

                $user->save();

                break;
        }
    }
    
    function renderClubs(): void
    {
        $this->template->clubs = $this->searchResults($this->clubs, $this->template->count);
    }
    
    function renderClub(int $id): void
    {
        $club = $this->clubs->get($id);
        if(!$club)
            $this->notFound();
        
        $this->template->mode = in_array($this->queryParam("act"), ["main", "ban", "followers"]) ? $this->queryParam("act") : "main";

        $this->template->club = $club;

        $this->template->followers = $this->template->club->getFollowers((int) ($this->queryParam("p") ?? 1));

        if($_SERVER["REQUEST_METHOD"] !== "POST")
            return;
        
        switch($this->queryParam("act")) {
            default:
            case "main":
                $club->setOwner($this->postParam("id_owner"));
                $club->setName($this->postParam("name"));
                $club->setAbout($this->postParam("about"));
                $club->setShortCode($this->postParam("shortcode"));
                $club->setVerified(empty($this->postParam("verify") ? 0 : 1));
                $club->setHide_From_Global_Feed(empty($this->postParam("hide_from_global_feed") ? 0 : 1));
                $club->save();
                break;
            case "ban":
                $reason = mb_strlen(trim($this->postParam("ban_reason"))) > 0 ? $this->postParam("ban_reason") : NULL;
                $club->setBlock_reason($reason);
                $club->save();
                break;
        }
    }
    
    function renderVouchers(): void
    {
        $this->warnIfNoCommerce();
        
        $this->template->count    = $this->vouchers->size();
        $this->template->vouchers = iterator_to_array($this->vouchers->enumerate((int) ($this->queryParam("p") ?? 1)));
    }
    
    function renderVoucher(int $id): void
    {
        $this->warnIfNoCommerce();
        
        $voucher = NULL;
        $this->template->form = (object) [];
        if($id === 0) {
            $this->template->form->id     = 0;
            $this->template->form->token  = NULL;
            $this->template->form->coins  = 0;
            $this->template->form->rating = 0;
            $this->template->form->usages = -1;
            $this->template->form->users  = [];
        } else {
            $voucher = $this->vouchers->get($id);
            if(!$voucher)
                $this->notFound();
            
            $this->template->form->id     = $voucher->getId();
            $this->template->form->token  = $voucher->getToken();
            $this->template->form->coins  = $voucher->getCoins();
            $this->template->form->rating = $voucher->getRating();
            $this->template->form->usages = $voucher->getRemainingUsages();
            $this->template->form->users  = iterator_to_array($voucher->getUsers());
            
            if($this->template->form->usages === INF)
                $this->template->form->usages = -1;
            else
                $this->template->form->usages = (int) $this->template->form->usages;
        }
        
        if($_SERVER["REQUEST_METHOD"] !== "POST")
            return;
        
        $voucher ??= new Voucher;
        $voucher->setCoins((int) $this->postParam("coins"));
        $voucher->setRating((int) $this->postParam("rating"));
        $voucher->setRemainingUsages($this->postParam("usages") === '-1' ? INF : ((int) $this->postParam("usages")));
        if(!empty($tok = $this->postParam("token")) && strlen($tok) === 24)
            $voucher->setToken($tok);
        
        $voucher->save();
        
        $this->redirect("/admin/vouchers/id" . $voucher->getId());
    }
    
    function renderGiftCategories(): void
    {
        $this->warnIfNoCommerce();
        
        $this->template->act        = $this->queryParam("act") ?? "list";
        $this->template->categories = iterator_to_array($this->gifts->getCategories((int) ($this->queryParam("p") ?? 1), NULL, $this->template->count));
    }
    
    function renderGiftCategory(string $slug, int $id): void
    {
        $this->warnIfNoCommerce();
        
        $cat;
        $gen = false;
        if($id !== 0) {
            $cat = $this->gifts->getCat($id);
            if(!$cat)
                $this->notFound();
            else if($cat->getSlug() !== $slug)
                $this->redirect("/admin/gifts/" . $cat->getSlug() . "." . $id . ".meta");
        } else {
            $gen = true;
            $cat = new GiftCategory;
        }
        
        $this->template->form = (object) [];
        $this->template->form->id        = $id;
        $this->template->form->languages = [];
        foreach(getLanguages() as $language) {
            $language = (object) $language;
            $this->template->form->languages[$language->code] = (object) [];
            
            $this->template->form->languages[$language->code]->name        = $gen ? "" : ($cat->getName($language->code, true) ?? "");
            $this->template->form->languages[$language->code]->description = $gen ? "" : ($cat->getDescription($language->code, true) ?? "");
        }
        
        $this->template->form->languages["master"] = (object) [
            "name"        => $gen ? "Unknown Name" : $cat->getName(),
            "description" => $gen ?             "" : $cat->getDescription(),
        ];
        
        if($_SERVER["REQUEST_METHOD"] !== "POST")
            return;
        
        if($gen) {
            $cat->setAutoQuery(NULL);
            $cat->save();
        }
        
        $cat->setName("_", $this->postParam("name_master"));
        $cat->setDescription("_", $this->postParam("description_master"));
        foreach(getLanguages() as $language) {
            $code = $language["code"];
            if(!empty($this->postParam("name_$code") ?? NULL))
                $cat->setName($code, $this->postParam("name_$code"));
                
            if(!empty($this->postParam("description_$code") ?? NULL))
                $cat->setDescription($code, $this->postParam("description_$code"));
        }
        
        $this->redirect("/admin/gifts/" . $cat->getSlug() . "." . $cat->getId() . ".meta");
    }
    
    function renderGifts(string $catSlug, int $catId): void
    {
        $this->warnIfNoCommerce();
        
        $cat = $this->gifts->getCat($catId);
        if(!$cat)
            $this->notFound();
        else if($cat->getSlug() !== $catSlug)
            $this->redirect("/admin/gifts/" . $cat->getSlug() . "." . $catId . "/");
        
        $this->template->cat   = $cat;
        $this->template->gifts = iterator_to_array($cat->getGifts((int) ($this->queryParam("p") ?? 1), NULL, $this->template->count));
    }
    
    function renderGift(int $id): void
    {
        $this->warnIfNoCommerce();
        
        $gift = $this->gifts->get($id);
        $act  = $this->queryParam("act") ?? "edit";
        switch($act) {
            case "delete":
                $this->assertNoCSRF();
                if(!$gift)
                    $this->notFound();
                
                $gift->delete();
                $this->flashFail("succ", tr("admin_gift_moved_successfully"), tr("admin_gift_moved_to_recycle"));
                break;
            case "copy":
            case "move":
                $this->assertNoCSRF();
                if(!$gift)
                    $this->notFound();
                
                $catFrom = $this->gifts->getCat((int) ($this->queryParam("from") ?? 0));
                $catTo   = $this->gifts->getCat((int) ($this->queryParam("to") ?? 0));
                if(!$catFrom || !$catTo || !$catFrom->hasGift($gift))
                    $this->badRequest();
                
                if($act === "move")
                    $catFrom->removeGift($gift);
                
                $catTo->addGift($gift);
                
                $name = $catTo->getName();
                $this->flash("succ", tr("admin_gift_moved_successfully"), "This gift will now be in <b>$name</b>.");
                $this->redirect("/admin/gifts/" . $catTo->getSlug() . "." . $catTo->getId() . "/");
                break;
            default:
            case "edit":
                $gen = false;
                if(!$gift) {
                    $gen  = true;
                    $gift = new Gift;
                }
                
                $this->template->form = (object) [];
                $this->template->form->id     = $id;
                $this->template->form->name   = $gen ? "New Gift (1)" : $gift->getName();
                $this->template->form->price  = $gen ?              0 : $gift->getPrice();
                $this->template->form->usages = $gen ?              0 : $gift->getUsages();
                $this->template->form->limit  = $gen ?             -1 : ($gift->getLimit() === INF ? -1 : $gift->getLimit());
                $this->template->form->pic    = $gen ?           NULL : $gift->getImage(Gift::IMAGE_URL);
                
                if($_SERVER["REQUEST_METHOD"] !== "POST")
                    return;
                
                $limit = $this->postParam("limit") ?? $this->template->form->limit;
                $limit = $limit == "-1" ? INF : (float) $limit;
                $gift->setLimit($limit, is_null($this->postParam("reset_limit")) ? Gift::PERIOD_SET_IF_NONE : Gift::PERIOD_SET);
                
                $gift->setName($this->postParam("name"));
                $gift->setPrice((int) $this->postParam("price"));
                $gift->setUsages((int) $this->postParam("usages"));
                if(isset($_FILES["pic"]) && $_FILES["pic"]["error"] === UPLOAD_ERR_OK) {
                    if(!$gift->setImage($_FILES["pic"]["tmp_name"]))
                        $this->flashFail("err", tr("error_when_saving_gift"), tr("error_when_saving_gift_bad_image"));
                } else if($gen) {
                    # If there's no gift pic but it's newly created
                    $this->flashFail("err", tr("error_when_saving_gift"), tr("error_when_saving_gift_no_image"));
                }
                
                $gift->save();
                
                if($gen && !is_null($cat = $this->postParam("_cat"))) {
                    $cat = $this->gifts->getCat((int) $cat);
                    if(!is_null($cat))
                        $cat->addGift($gift);
                }
                
                $this->redirect("/admin/gifts/id" . $gift->getId());
        }
    }
    
    function renderFiles(): void
    {
        
    }
    
    function renderQuickBan(int $id): void
    {
        $this->assertNoCSRF();

        if (str_contains($this->queryParam("reason"), "*"))
            exit(json_encode([ "error" => "Incorrect reason" ]));

        $unban_time = strtotime($this->queryParam("date")) ?: "permanent";

        $user = $this->users->get($id);
        if(!$user)
            exit(json_encode([ "error" => "User does not exist" ]));

        if ($this->queryParam("incr"))
            $unban_time = time() + $user->getNewBanTime();

        $user->ban($this->queryParam("reason"), true, $unban_time, $this->user->identity->getId());
        exit(json_encode([ "success" => true, "reason" => $this->queryParam("reason") ]));
    }

    function renderQuickUnban(int $id): void
    {
        $this->assertNoCSRF();
        
        $user = $this->users->get($id);
        if(!$user)
            exit(json_encode([ "error" => "User does not exist" ]));

        $ban = (new Bans)->get((int)$user->getRawBanReason());
        if (!$ban || $ban->isOver())
            exit(json_encode([ "error" => "User is not banned" ]));

        $ban->setRemoved_Manually(true);
        $ban->setRemoved_By($this->user->identity->getId());
        $ban->save();

        $user->setBlock_Reason(NULL);
        // $user->setUnblock_time(NULL);
        $user->save();
        exit(json_encode([ "success" => true ]));
    }
    
    function renderQuickWarn(int $id): void
    {
        $this->assertNoCSRF();
        
        $user = $this->users->get($id);
        if(!$user)
            exit(json_encode([ "error" => "User does not exist" ]));
        
        $user->adminNotify("⚠️ " . $this->queryParam("message"));
        exit(json_encode([ "message" => $this->queryParam("message") ]));
    }

    function renderBannedLinks(): void
    {
        $this->template->links = $this->bannedLinks->getList((int) $this->queryParam("p") ?: 1);
        $this->template->users = new Users;
    }

    function renderBannedLink(int $id): void
    {
        $this->template->form = (object) [];

        if($id === 0) {
            $this->template->form->id     = 0;
            $this->template->form->link   = NULL;
            $this->template->form->reason = NULL;
        } else {
            $link = (new BannedLinks)->get($id);
            if(!$link)
                $this->notFound();

            $this->template->form->id     = $link->getId();
            $this->template->form->link   = $link->getDomain();
            $this->template->form->reason = $link->getReason();
            $this->template->form->regexp = $link->getRawRegexp();
        }

        if($_SERVER["REQUEST_METHOD"] !== "POST")
            return;

        $link = (new BannedLinks)->get($id);

        $new_domain = parse_url($this->postParam("link"))["host"];
        $new_reason = $this->postParam("reason") ?: NULL;

        $lid = $id;

        if ($link) {
            $link->setDomain($new_domain ?? $this->postParam("link"));
            $link->setReason($new_reason);
            $link->setRegexp_rule($this->postParam("regexp"));
            $link->save();
        } else {
            if (!$new_domain)
                $this->flashFail("err", tr("error"), tr("admin_banned_link_not_specified"));

            $link = new BannedLink;
            $link->setDomain($new_domain);
            $link->setReason($new_reason);
            $link->setRegexp_rule($this->postParam("regexp"));
            $link->setInitiator($this->user->identity->getId());
            $link->save();

            $lid = $link->getId();
        }

        $this->redirect("/admin/bannedLink/id" . $lid);
    }

    function renderUnbanLink(int $id): void
    {
        $link = (new BannedLinks)->get($id);

        if (!$link)
            $this->flashFail("err", tr("error"), tr("admin_banned_link_not_found"));

        $link->delete(false);

        $this->redirect("/admin/bannedLinks");
    }

    function renderBansHistory(int $user_id) :void
    {
        $user = (new Users)->get($user_id);
        if (!$user) $this->notFound();

        $this->template->bans = (new Bans)->getByUser($user_id);
    }

    function renderChandlerGroups(): void
    {
        $this->template->groups = (new ChandlerGroups)->getList();

        if($_SERVER["REQUEST_METHOD"] !== "POST")
            return;

        $req = "INSERT INTO `ChandlerGroups` (`name`) VALUES ('" . $this->postParam("name") . "')";
        DatabaseConnection::i()->getConnection()->query($req);
    }

    function renderChandlerGroup(string $UUID): void
    {
        $DB = DatabaseConnection::i()->getConnection();

        if(is_null($DB->query("SELECT * FROM `ChandlerGroups` WHERE `id` = '$UUID'")->fetch()))
            $this->flashFail("err", tr("error"), tr("c_group_not_found"));

        $this->template->group = (new ChandlerGroups)->get($UUID);
        $this->template->mode = in_array(
            $this->queryParam("act"),
            [
                "main",
                "members",
                "permissions",
                "removeMember",
                "removePermission",
                "delete"
            ]) ? $this->queryParam("act") : "main";
        $this->template->members = (new ChandlerGroups)->getMembersById($UUID);
        $this->template->perms = (new ChandlerGroups)->getPermissionsById($UUID);

        if($this->template->mode == "removeMember") {
            $where = "`user` = '" . $this->queryParam("uid") . "' AND `group` = '$UUID'";

            if(is_null($DB->query("SELECT * FROM `ChandlerACLRelations` WHERE " . $where)->fetch()))
                $this->flashFail("err", tr("error"), tr("c_user_is_not_in_group"));

            $DB->query("DELETE FROM `ChandlerACLRelations` WHERE " . $where);
            $this->flashFail("succ", tr("changes_saved"), tr("c_user_removed_from_group"));
        } elseif($this->template->mode == "removePermission") {
            $where = "`model` = '" . trim(addslashes($this->queryParam("model"))) . "' AND `permission` = '". $this->queryParam("perm") ."' AND `group` = '$UUID'";

            if(is_null($DB->query("SELECT * FROM `ChandlerACLGroupsPermissions WHERE $where`")))
                $this->flashFail("err", tr("error"), tr("c_permission_not_found"));

            $DB->query("DELETE FROM `ChandlerACLGroupsPermissions` WHERE $where");
            $this->flashFail("succ", tr("changes_saved"), tr("c_permission_removed_from_group"));
        } elseif($this->template->mode == "delete") {
            $DB->query("DELETE FROM `ChandlerGroups` WHERE `id` = '$UUID'");
            $DB->query("DELETE FROM `ChandlerACLGroupsPermissions` WHERE `group` = '$UUID'");
            $DB->query("DELETE FROM `ChandlerACLRelations` WHERE `group` = '$UUID'");

            $this->flashFail("succ", tr("changes_saved"), tr("c_group_removed"));
        }

        if ($_SERVER["REQUEST_METHOD"] !== "POST") return;

        $req = "";

        if($this->template->mode == "main")
            if($this->postParam("delete"))
                $req = "DELETE FROM `ChandlerGroups` WHERE `id`='$UUID'";
            else
                $req = "UPDATE `ChandlerGroups` SET `name`='". $this->postParam('name') ."' , `color`='". $this->postParam("color") ."' WHERE `id`='$UUID'";

        if($this->template->mode == "members")
            if($this->postParam("uid"))
                if(!is_null($DB->query("SELECT * FROM `ChandlerACLRelations` WHERE `user` = '" . $this->postParam("uid") . "'")))
                    $this->flashFail("err", tr("error"), tr("c_user_is_already_in_group"));

                $req = "INSERT INTO `ChandlerACLRelations` (`user`, `group`, `priority`) VALUES ('". $this->postParam("uid") ."', '$UUID', 32)";

        if($this->template->mode == "permissions")
            $req = "INSERT INTO `ChandlerACLGroupsPermissions` (`group`, `model`, `permission`, `context`) VALUES ('$UUID', '". trim(addslashes($this->postParam("model"))) ."', '". $this->postParam("permission") ."', 0)";

        $DB->query($req);
        $this->flashFail("succ", tr("changes_saved"));
    }

    function renderChandlerUser(string $UUID): void
    {
        if(!$UUID) $this->notFound();

        $c_user = (new ChandlerUsers())->getById($UUID);
        $user = $this->users->getByChandlerUser($c_user);
        if(!$user) $this->notFound();

        $this->redirect("/admin/users/id" . $user->getId());
    }

    function renderLogs(): void
    {
        $filter = [];

        if ($this->queryParam("id")) {
            $id = (int) $this->queryParam("id");
            $filter["id"] = $id;
            $this->template->id = $id;
        }
        if ($this->queryParam("type") !== NULL && $this->queryParam("type") !== "any") {
            $type = in_array($this->queryParam("type"), [0, 1, 2, 3]) ? (int) $this->queryParam("type") : 0;
            $filter["type"] = $type;
            $this->template->type = $type;
        }
        if ($this->queryParam("uid")) {
            $user = $this->queryParam("uid");
            $filter["user"] = $user;
            $this->template->user = $user;
        }
        if ($this->queryParam("obj_id")) {
            $obj_id = (int) $this->queryParam("obj_id");
            $filter["object_id"] = $obj_id;
            $this->template->obj_id = $obj_id;
        }
        if ($this->queryParam("obj_type") !== NULL && $this->queryParam("obj_type") !== "any") {
            $obj_type = "openvk\\Web\\Models\\Entities\\" . $this->queryParam("obj_type");
            $filter["object_model"] = $obj_type;
            $this->template->obj_type = $obj_type;
        }

        $this->template->logs = (new Logs)->search($filter);
        $this->template->object_types = (new Logs)->getTypes();
    }
}
