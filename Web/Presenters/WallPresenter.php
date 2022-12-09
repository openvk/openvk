<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Exceptions\TooMuchOptionsException;
use openvk\Web\Models\Entities\{Poll, Post, Photo, Video, Club, User};
use openvk\Web\Models\Entities\Notifications\{RepostNotification, WallPostNotification};
use openvk\Web\Models\Repositories\{Posts, Users, Clubs, Albums};
use Chandler\Database\DatabaseConnection;
use Nette\InvalidStateException as ISE;
use Bhaktaraz\RSSGenerator\Item;
use Bhaktaraz\RSSGenerator\Feed;
use Bhaktaraz\RSSGenerator\Channel;

final class WallPresenter extends OpenVKPresenter
{
    private $posts;
    
    function __construct(Posts $posts)
    {
        $this->posts = $posts;
        
        parent::__construct();
    }
    
    private function logPostView(Post $post, int $wall): void
    {
        if(is_null($this->user))
            return;
        
        $this->logEvent("postView", [
            "profile"    => $this->user->identity->getId(),
            "post"       => $post->getId(),
            "owner"      => abs($wall),
            "group"      => $wall < 0,
            "subscribed" => $wall < 0 ? $post->getOwner()->getSubscriptionStatus($this->user->identity) : false,
        ]);
    }
    
    private function logPostsViewed(array &$posts, int $wall): void
    {
        $x = array_values($posts); # clone array (otherwise Nette DB objects will become kinda gay)
        
        foreach($x as $post)
            $this->logPostView($post, $wall);
    }
    
    function renderWall(int $user, bool $embedded = false): void
    {
        $owner = ($user < 0 ? (new Clubs) : (new Users))->get(abs($user));
        if(is_null($this->user)) {
            $canPost = false;
        } else if($user > 0) {
            if(!$owner->isBanned())
                $canPost = $owner->getPrivacyPermission("wall.write", $this->user->identity);
            else
                $this->flashFail("err", tr("error"), tr("forbidden"));
        } else if($user < 0) {
            if($owner->canBeModifiedBy($this->user->identity))
                $canPost = true;
            else
                $canPost = $owner->canPost();
        } else {
            $canPost = false;
        }
        
        if ($embedded == true) $this->template->_template = "components/wall.xml";
        $this->template->oObj = $owner;
        if($user < 0)
            $this->template->club = $owner;
        
        $this->template->owner   = $user;
        $this->template->canPost = $canPost;
        $this->template->count   = $this->posts->getPostCountOnUserWall($user);
        $this->template->posts   = iterator_to_array($this->posts->getPostsFromUsersWall($user, (int) ($_GET["p"] ?? 1)));
        $this->template->paginatorConf = (object) [
            "count"   => $this->template->count,
            "page"    => (int) ($_GET["p"] ?? 1),
            "amount"  => sizeof($this->template->posts),
            "perPage" => OPENVK_DEFAULT_PER_PAGE,
        ];

        
        $this->logPostsViewed($this->template->posts, $user);
    }

    function renderWallEmbedded(int $user): void
    {
        $this->renderWall($user, true);
    }

    function renderRSS(int $user): void
    {
        $owner = ($user < 0 ? (new Clubs) : (new Users))->get(abs($user));
        if(is_null($this->user)) {
            $canPost = false;
        } else if($user > 0) {
            if(!$owner->isBanned())
                $canPost = $owner->getPrivacyPermission("wall.write", $this->user->identity);
            else
                $this->flashFail("err", tr("error"), tr("forbidden"));
        } else if($user < 0) {
            if($owner->canBeModifiedBy($this->user->identity))
                $canPost = true;
            else
                $canPost = $owner->canPost();
        } else {
            $canPost = false;
        }

        $posts = iterator_to_array($this->posts->getPostsFromUsersWall($user));

        $feed = new Feed();

        $channel = new Channel();
        $channel->title($owner->getCanonicalName() . " — " . OPENVK_ROOT_CONF['openvk']['appearance']['name'])->url(ovk_scheme(true) . $_SERVER["HTTP_HOST"])->appendTo($feed);

        foreach($posts as $post) {
            $item = new Item();
            $item
                ->title($post->getOwner()->getCanonicalName())
                ->description($post->getText())
                ->url(ovk_scheme(true).$_SERVER["HTTP_HOST"]."/wall{$post->getPrettyId()}")
                ->pubDate($post->getPublicationTime()->timestamp())
                ->appendTo($channel);
        }

        header("Content-Type: application/rss+xml");
        exit($feed);
    }
    
    function renderFeed(): void
    {
        $this->assertUserLoggedIn();
        
        $id    = $this->user->id;
        $subs  = DatabaseConnection::i()
                 ->getContext()
                 ->table("subscriptions")
                 ->where("follower", $id);
        $ids   = array_map(function($rel) {
            return $rel->target * ($rel->model === "openvk\Web\Models\Entities\User" ? 1 : -1);
        }, iterator_to_array($subs));
        $ids[] = $this->user->id;
        
        $perPage = min((int) ($_GET["posts"] ?? OPENVK_DEFAULT_PER_PAGE), 50);
        $posts   = DatabaseConnection::i()
                   ->getContext()
                   ->table("posts")
                   ->select("id")
                   ->where("wall IN (?)", $ids)
                   ->where("deleted", 0)
                   ->order("created DESC");
        $this->template->paginatorConf = (object) [
            "count"   => sizeof($posts),
            "page"    => (int) ($_GET["p"] ?? 1),
            "amount"  => sizeof($posts->page((int) ($_GET["p"] ?? 1), $perPage)),
            "perPage" => $perPage,
        ];
        $this->template->posts = [];
        foreach($posts->page((int) ($_GET["p"] ?? 1), $perPage) as $post)
            $this->template->posts[] = $this->posts->get($post->id);
    }
    
    function renderGlobalFeed(): void
    {
        $this->assertUserLoggedIn();
        
        $page  = (int) ($_GET["p"] ?? 1);
        $pPage = min((int) ($_GET["posts"] ?? OPENVK_DEFAULT_PER_PAGE), 50);

        $queryBase = "FROM `posts` LEFT JOIN `groups` ON GREATEST(`posts`.`wall`, 0) = 0 AND `groups`.`id` = ABS(`posts`.`wall`) WHERE (`groups`.`hide_from_global_feed` = 0 OR `groups`.`name` IS NULL) AND `posts`.`deleted` = 0";

        if($this->user->identity->getNsfwTolerance() === User::NSFW_INTOLERANT)
            $queryBase .= " AND `nsfw` = 0";

        $posts = DatabaseConnection::i()->getConnection()->query("SELECT `posts`.`id` " . $queryBase . " ORDER BY `created` DESC LIMIT " . $pPage . " OFFSET " . ($page - 1) * $pPage);
        $count = DatabaseConnection::i()->getConnection()->query("SELECT COUNT(*) " . $queryBase)->fetch()->{"COUNT(*)"};
        
        $this->template->_template     = "Wall/Feed.xml";
        $this->template->globalFeed    = true;
        $this->template->paginatorConf = (object) [
            "count"   => $count,
            "page"    => (int) ($_GET["p"] ?? 1),
            "amount"  => sizeof($posts),
            "perPage" => $pPage,
        ];
        foreach($posts as $post)
            $this->template->posts[] = $this->posts->get($post->id);
    }
    
    function renderHashtagFeed(string $hashtag): void
    {
        $hashtag = rawurldecode($hashtag);
        
        $page  = (int) ($_GET["p"] ?? 1);
        $posts = $this->posts->getPostsByHashtag($hashtag, $page);
        $count = $this->posts->getPostCountByHashtag($hashtag);
        
        $this->template->hashtag       = $hashtag;
        $this->template->posts         = $posts;
        $this->template->paginatorConf = (object) [
            "count"   => 0,
            "page"    => $page,
            "amount"  => $count,
            "perPage" => OPENVK_DEFAULT_PER_PAGE,
        ];
    }
    
    function renderMakePost(int $wall): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $wallOwner = ($wall > 0 ? (new Users)->get($wall) : (new Clubs)->get($wall * -1))
                     ?? $this->flashFail("err", tr("failed_to_publish_post"), tr("error_4"));
        if($wall > 0) {
            if(!$wallOwner->isBanned())
                $canPost = $wallOwner->getPrivacyPermission("wall.write", $this->user->identity);
            else
                $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));
        } else if($wall < 0) {
            if($wallOwner->canBeModifiedBy($this->user->identity))
                $canPost = true;
            else
                $canPost = $wallOwner->canPost();
        } else {
            $canPost = false; 
        }
	
        if(!$canPost)
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));

        $anon = OPENVK_ROOT_CONF["openvk"]["preferences"]["wall"]["anonymousPosting"]["enable"];
        if($wallOwner instanceof Club && $this->postParam("as_group") === "on" && $this->postParam("force_sign") !== "on" && $anon) {
            $manager = $wallOwner->getManager($this->user->identity);
            if($manager)
                $anon = $manager->isHidden();
            elseif($this->user->identity->getId() === $wallOwner->getOwner()->getId())
                $anon = $wallOwner->isOwnerHidden();
        } else {
            $anon = $anon && $this->postParam("anon") === "on";
        }
        
        $flags = 0;
        if($this->postParam("as_group") === "on" && $wallOwner instanceof Club && $wallOwner->canBeModifiedBy($this->user->identity))
            $flags |= 0b10000000;
        if($this->postParam("force_sign") === "on")
            $flags |= 0b01000000;
        
        try {
            $photo = NULL;
            $video = NULL;
            if($_FILES["_pic_attachment"]["error"] === UPLOAD_ERR_OK) {
                $album = NULL;
                if(!$anon && $wall > 0 && $wall === $this->user->id)
                    $album = (new Albums)->getUserWallAlbum($wallOwner);
                
                $photo = Photo::fastMake($this->user->id, $this->postParam("text"), $_FILES["_pic_attachment"], $album, $anon);
            }
            
            if($_FILES["_vid_attachment"]["error"] === UPLOAD_ERR_OK)
                $video = Video::fastMake($this->user->id, $this->postParam("text"), $_FILES["_vid_attachment"], $anon);
        } catch(\DomainException $ex) {
            $this->flashFail("err", tr("failed_to_publish_post"), tr("media_file_corrupted"));
        } catch(ISE $ex) {
            $this->flashFail("err", tr("failed_to_publish_post"), tr("media_file_corrupted_or_too_large"));
        }
        
        try {
            $poll = NULL;
            $xml = $this->postParam("poll");
            if (!is_null($xml) && $xml != "none")
                $poll = Poll::import($this->user->identity, $xml);
        } catch(TooMuchOptionsException $e) {
            $this->flashFail("err", tr("failed_to_publish_post"), tr("poll_err_to_much_options"));
        } catch(\UnexpectedValueException $e) {
            $this->flashFail("err", tr("failed_to_publish_post"), "Poll format invalid");
        }
        
        if(empty($this->postParam("text")) && !$photo && !$video && !$poll)
            $this->flashFail("err", tr("failed_to_publish_post"), tr("post_is_empty_or_too_big"));
        
        try {
            $post = new Post;
            $post->setOwner($this->user->id);
            $post->setWall($wall);
            $post->setCreated(time());
            $post->setContent($this->postParam("text"));
            $post->setAnonymous($anon);
            $post->setFlags($flags);
            $post->setNsfw($this->postParam("nsfw") === "on");
            $post->save();
        } catch (\LengthException $ex) {
            $this->flashFail("err", tr("failed_to_publish_post"), tr("post_is_too_big"));
        }
        
        if(!is_null($photo))
            $post->attach($photo);
        
        if(!is_null($video))
            $post->attach($video);
        
        if(!is_null($poll))
            $post->attach($poll);
        
        if($wall > 0 && $wall !== $this->user->identity->getId())
            (new WallPostNotification($wallOwner, $post, $this->user->identity))->emit();
        
        $this->redirect($wallOwner->getURL());
    }
    
    function renderPost(int $wall, int $post_id): void
    {
        $post = $this->posts->getPostById($wall, $post_id);
        if(!$post || $post->isDeleted())
            $this->notFound();
        
        $this->logPostView($post, $wall);
        
        $this->template->post     = $post;
        if ($post->getTargetWall() > 0) {
        	$this->template->wallOwner = (new Users)->get($post->getTargetWall());
			$this->template->isWallOfGroup = false;
            if($this->template->wallOwner->isBanned())
                $this->flashFail("err", tr("error"), tr("forbidden"));
		} else {
			$this->template->wallOwner = (new Clubs)->get(abs($post->getTargetWall()));
			$this->template->isWallOfGroup = true;
		}
        $this->template->cCount   = $post->getCommentsCount();
        $this->template->cPage    = (int) ($_GET["p"] ?? 1);
        $this->template->comments = iterator_to_array($post->getComments($this->template->cPage));
    }
    
    function renderLike(int $wall, int $post_id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        $this->assertNoCSRF();
        
        $post = $this->posts->getPostById($wall, $post_id);
        if(!$post || $post->isDeleted()) $this->notFound();
        
        if(!is_null($this->user)) {
            $post->toggleLike($this->user->identity);
        }
        
        $this->redirect("$_SERVER[HTTP_REFERER]#postGarter=" . $post->getId());
    }
    
    function renderShare(int $wall, int $post_id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        $this->assertNoCSRF();
        
        $post = $this->posts->getPostById($wall, $post_id);
        if(!$post || $post->isDeleted()) $this->notFound();
        
        if(!is_null($this->user)) {
            $nPost = new Post;
            $nPost->setOwner($this->user->id);
            $nPost->setWall($this->user->id);
            $nPost->setContent($this->postParam("text"));
            $nPost->save();
            $nPost->attach($post);
            
            if($post->getOwner(false)->getId() !== $this->user->identity->getId() && !($post->getOwner() instanceof Club))
                (new RepostNotification($post->getOwner(false), $post, $this->user->identity))->emit();
        };
        
        $this->returnJson(["wall_owner" => $this->user->identity->getId()]);
    }
    
    function renderDelete(int $wall, int $post_id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $post = $this->posts->getPostById($wall, $post_id);
        if(!$post)
            $this->notFound();
        $user = $this->user->id;

        $wallOwner = ($wall > 0 ? (new Users)->get($wall) : (new Clubs)->get($wall * -1))
                     ?? $this->flashFail("err", tr("failed_to_delete_post"), tr("error_4"));

        if($wall < 0) $canBeDeletedByOtherUser = $wallOwner->canBeModifiedBy($this->user->identity);
            else $canBeDeletedByOtherUser = false;

        if(!is_null($user)) {
            if($post->getOwnerPost() == $user || $post->getTargetWall() == $user || $canBeDeletedByOtherUser) {
                $post->unwire();
                $post->delete();
            }
        } else {
            $this->flashFail("err", tr("failed_to_delete_post"), tr("login_required_error_comment"));
        }
        
        $this->redirect($wall < 0 ? "/club" . ($wall*-1) : "/id" . $wall);
    }
    
    function renderPin(int $wall, int $post_id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $post = $this->posts->getPostById($wall, $post_id);
        if(!$post)
            $this->notFound();
        
        if(!$post->canBePinnedBy($this->user->identity))
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));
        
        if(($this->queryParam("act") ?? "pin") === "pin") {
            $post->pin();
        } else {
            $post->unpin();
        }
        
        # TODO localize message based on language and ?act=(un)pin
        $this->flashFail("succ", tr("information_-1"), tr("changes_saved_comment"));
    }

    function renderOpen(int $wall, int $post_id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $post = $this->posts->getPostById($wall, $post_id);
        if(!$post)
            $this->notFound();
        
        if(!$post->canBeClosedBy($this->user->identity))
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));
        
        if(($this->queryParam("act") ?? "open") === "open") {
            $post->open();
        } else {
            $post->close();
        }
        
        # TODO localize message based on language and ?act=(open|close)
        $this->flashFail("succ", tr("information_-1"), tr("changes_saved_comment"));
    }
}
