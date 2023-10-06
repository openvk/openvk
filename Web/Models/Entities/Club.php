<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Util\DateTime;
use openvk\Web\Models\RowModel;
use openvk\Web\Models\Entities\{User, Manager};
use openvk\Web\Models\Repositories\{Users, Clubs, Albums, Managers};
use Nette\Database\Table\{ActiveRow, GroupedSelection};
use Chandler\Database\DatabaseConnection as DB;
use Chandler\Security\User as ChandlerUser;

class Club extends RowModel
{
    protected $tableName = "groups";
    
    const TYPE_GROUP  = 1;
    const TYPE_PUBLIC = 1;
    const TYPE_EVENT  = 2;
    
    const OPEN    = 0;
    const CLOSED  = 1;
    const PRIVATE = 2;
    
    const NOT_RELATED  = 0;
    const SUBSCRIBED   = 1;
    const REQUEST_SENT = 2;
    
    function getId(): int
    {
        return $this->getRecord()->id;
    }
    
    function getAvatarPhoto(): ?Photo
    {
        $avAlbum  = (new Albums)->getClubAvatarAlbum($this);
        $avCount  = $avAlbum->getPhotosCount();
        $avPhotos = $avAlbum->getPhotos($avCount, 1);
        
        return iterator_to_array($avPhotos)[0] ?? NULL;
    }
    
    function getAvatarUrl(string $size = "miniscule"): string
    {
        $serverUrl = ovk_scheme(true) . $_SERVER["HTTP_HOST"];
        $avPhoto   = $this->getAvatarPhoto();
        
        return is_null($avPhoto) ? "$serverUrl/assets/packages/static/openvk/img/camera_200.png" : $avPhoto->getURLBySizeId($size);
    }
    
    function getAvatarLink(): string
    {
        $avPhoto = $this->getAvatarPhoto();
        if(!$avPhoto) return "javascript:void(0)";
        
        $pid = $avPhoto->getPrettyId();
        $aid = (new Albums)->getClubAvatarAlbum($this)->getId();
        
        return "/photo$pid?from=album$aid";
    }
    
    function getURL(): string
    {
        if(!is_null($this->getShortCode()))
            return "/" . $this->getShortCode();
        else
            return "/club" . $this->getId();
    }
    
    function getName(): string
    {
        return $this->getRecord()->name;
    }
    
    function getCanonicalName(): string
    {
        return $this->getName();
    }
    
    function getOwner(): ?User
    {
        return (new Users)->get($this->getRecord()->owner);
    }

    function getOwnerComment(): string
    {
        return is_null($this->getRecord()->owner_comment) ? "" : $this->getRecord()->owner_comment;
    }

    function isOwnerHidden(): bool
    {
        return (bool) $this->getRecord()->owner_hidden;
    }
    
    function isOwnerClubPinned(): bool
    {
        return (bool) $this->getRecord()->owner_club_pinned;
    }

    function getDescription(): ?string
    {
        return $this->getRecord()->about;
    }

    function getDescriptionHtml(): ?string
    {
        if(!is_null($this->getDescription()))
            return nl2br(htmlspecialchars($this->getDescription(), ENT_DISALLOWED | ENT_XHTML));
        else
            return NULL;
    }
    
    function getShortCode(): ?string
    {
        return $this->getRecord()->shortcode;
    }
    
    function getBanReason(): ?string
    {
        return $this->getRecord()->block_reason;
    }
    
    function getOpennesStatus(): int
    {
        return $this->getRecord()->closed;
    }

    function getAdministratorsListDisplay(): int
    {
        return $this->getRecord()->administrators_list_display;
    }
    
    function isEveryoneCanCreateTopics(): bool
    {
        return (bool) $this->getRecord()->everyone_can_create_topics;
    }

    function isDisplayTopicsAboveWallEnabled(): bool
    {
        return (bool) $this->getRecord()->display_topics_above_wall;
    }

    function isHideFromGlobalFeedEnabled(): bool
    {
        return (bool) $this->getRecord()->hide_from_global_feed;
    }

    function getType(): int
    {
        return $this->getRecord()->type;
    }
    
    function isVerified(): bool
    {
        return (bool) $this->getRecord()->verified;
    }
    
    function isBanned(): bool
    {
        return !is_null($this->getBanReason());
    }

    function canPost(): bool
    {
	    return (bool) $this->getRecord()->wall;
    }

    
    function setShortCode(?string $code = NULL): ?bool
    {
        if(!is_null($code)) {
            if(!preg_match("%^[a-z][a-z0-9\\.\\_]{0,30}[a-z0-9]$%", $code))
                return false;
            if(in_array($code, OPENVK_ROOT_CONF["openvk"]["preferences"]["shortcodes"]["forbiddenNames"]))
                return false;
            if(\Chandler\MVC\Routing\Router::i()->getMatchingRoute("/$code")[0]->presenter !== "UnknownTextRouteStrategy")
                return false;
            
            $pUser = DB::i()->getContext()->table("profiles")->where("shortcode", $code)->fetch();
            if(!is_null($pUser))
                return false;
        }
        
        $this->stateChanges("shortcode", $code);
        return true;
    }
    
    function isSubscriptionAccepted(User $user): bool
    {
        return !is_null($this->getRecord()->related("subscriptions.follower")->where([
            "follower" => $this->getId(),
            "target"   => $user->getId(),
        ])->fetch());;
    }
    
    function getPostViewStats(bool $unique = false): ?array
    {
        $edb = eventdb();
        if(!$edb)
            return NULL;
        
        $subs  = [];
        $viral = [];
        $total = [];
        for($i = 1; $i < 8; $i++) {
            $begin = strtotime("-" . $i . "day midnight");
            $end   = $i === 1 ? time() + 10 : strtotime("-" . ($i - 1) . "day midnight");
            
            $query  = "SELECT COUNT(" . ($unique ? "DISTINCT profile" : "*") . ") AS cnt FROM postViews";
            $query .= " WHERE `group`=1 AND owner=" . $this->getId();
            $query .= " AND timestamp > $begin AND timestamp < $end";
            
            $sub = $edb->getConnection()->query("$query AND NOT subscribed=0")->fetch()->cnt;
            $vir = $edb->getConnection()->query("$query AND subscribed=0")->fetch()->cnt;
            $subs[]  = $sub;
            $viral[] = $vir;
            $total[] = $sub + $vir;
        }
        
        return [
            "total" => [
                "x" => array_reverse(range(1, 7)),
                "y" => $total,
                "type" => "scatter",
                "line" => [
                    "shape" => "spline",
                    "color" => "#597da3",
                ],
                "name" => $unique ? tr("full_coverage") : tr("all_views"),
            ],
            "subs"  => [
                "x" => array_reverse(range(1, 7)),
                "y" => $subs,
                "type" => "scatter",
                "line" => [
                    "shape" => "spline",
                    "color" => "#b05c91",
                ],
                "fill" => "tozeroy",
                "name" => $unique ? tr("subs_coverage") : tr("subs_views"),
            ],
            "viral" => [
                "x" => array_reverse(range(1, 7)),
                "y" => $viral,
                "type" => "scatter",
                "line" => [
                    "shape" => "spline",
                    "color" => "#4d9fab",
                ],
                "fill" => "tozeroy",
                "name" => $unique ? tr("viral_coverage") : tr("viral_views"),
            ],
        ];
    }
    
    function getSubscriptionStatus(User $user): bool
    {
        $subbed = !is_null($this->getRecord()->related("subscriptions.target")->where([
            "target"   => $this->getId(),
            "model"    => static::class,
            "follower" => $user->getId(),
        ])->fetch());
        
        return $subbed && ($this->getOpennesStatus() === static::CLOSED ? $this->isSubscriptionAccepted($user) : true);
    }
    
    function getFollowersQuery(string $sort = "follower ASC"): GroupedSelection
    {
        $query = $this->getRecord()->related("subscriptions.target");
        
        if($this->getOpennesStatus() === static::OPEN) {
            $query = $query->where("model", "openvk\\Web\\Models\\Entities\\Club")->order($sort);
        } else {
            return false;
        }
        
        return $query->group("follower");
    }
    
    function getFollowersCount(): int
    {
        return sizeof($this->getFollowersQuery());
    }
    
    function getFollowers(int $page = 1, int $perPage = 6, string $sort = "follower ASC"): \Traversable
    {
        $rels = $this->getFollowersQuery($sort)->page($page, $perPage);
        
        foreach($rels as $rel) {
            $rel = (new Users)->get($rel->follower);
            if(!$rel) continue;
            
            yield $rel;
        }
    }
    
    function getManagers(int $page = 1, bool $ignoreHidden = false): \Traversable
    {
        $rels = $this->getRecord()->related("group_coadmins.club")->page($page, 6);
        if($ignoreHidden)
            $rels = $rels->where("hidden", false);
        
        foreach($rels as $rel) {
            $rel = (new Managers)->get($rel->id);
            if(!$rel) continue;

            yield $rel;
        }
    }

    function getManager(User $user, bool $ignoreHidden = false): ?Manager
    {
        $manager = (new Managers)->getByUserAndClub($user->getId(), $this->getId());

        if ($ignoreHidden && $manager !== NULL && $manager->isHidden())
            return NULL;

        return $manager;
    }
    
    function getManagersCount(bool $ignoreHidden = false): int
    {
        if($ignoreHidden)
            return sizeof($this->getRecord()->related("group_coadmins.club")->where("hidden", false)) + (int) !$this->isOwnerHidden();

        return sizeof($this->getRecord()->related("group_coadmins.club")) + 1;
    }
    
    function addManager(User $user, ?string $comment = NULL): void
    {
        DB::i()->getContext()->table("group_coadmins")->insert([
            "club" => $this->getId(),
            "user" => $user->getId(),
            "comment" => $comment,
        ]);
    }
    
    function removeManager(User $user): void
    {
        DB::i()->getContext()->table("group_coadmins")->where([
            "club" => $this->getId(),
            "user" => $user->getId(),
        ])->delete();
    }
    
    function canBeModifiedBy(User $user): bool
    {
        $id = $user->getId();
        if($this->getOwner()->getId() === $id)
            return true;
        
        return !is_null($this->getRecord()->related("group_coadmins.club")->where("user", $id)->fetch());
    }

    function getWebsite(): ?string
	  {
		  return $this->getRecord()->website;
	  }

    function ban(string $reason): void
    {
        $this->setBlock_Reason($reason);
        $this->save();
    }

    function unban(): void
    {
        $this->setBlock_Reason(null);
        $this->save();
    }

    function getAlert(): ?string
    {
        return $this->getRecord()->alert;
    }
    
    function toVkApiStruct(?User $user = NULL): object
    {
        $res = [];

        $res->id          = $this->getId();
        $res->name        = $this->getName();
        $res->screen_name = $this->getShortCode();
        $res->is_closed   = 0;
        $res->deactivated = NULL;
        $res->is_admin    = $this->canBeModifiedBy($user);

        if($this->canBeModifiedBy($user)) {
            $res->admin_level = 3;
        }

        $res->is_member  = $this->getSubscriptionStatus($user) ? 1 : 0;

        $res->type       = "group";
        $res->photo_50   = $this->getAvatarUrl("miniscule");
        $res->photo_100  = $this->getAvatarUrl("tiny");
        $res->photo_200  = $this->getAvatarUrl("normal");

        $res->can_create_topic = $this->canBeModifiedBy($user) ? 1 : ($this->isEveryoneCanCreateTopics() ? 1 : 0);

        $res->can_post         = $this->canBeModifiedBy($user) ? 1 : ($this->canPost() ? 1 : 0);

        return (object) $res;
    }

    use Traits\TBackDrops;
    use Traits\TSubscribable;
}
