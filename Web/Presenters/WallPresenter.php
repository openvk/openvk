<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Exceptions\TooMuchOptionsException;
use openvk\Web\Models\Entities\{Poll, Post, Photo, Video, Club, User};
use openvk\Web\Models\Entities\Notifications\{MentionNotification, RepostNotification, WallPostNotification};
use openvk\Web\Models\Repositories\{Posts, Users, Clubs, Albums, Notes, Videos, Comments, Photos};
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
        if ($owner->isBanned())
            $this->flashFail("err", tr("error"), tr("forbidden"));

        if(is_null($this->user)) {
            $canPost = false;
        } else if($user > 0) {
            $canPost = $owner->getPrivacyPermission("wall.write", $this->user->identity);
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
            else if ($owner->isBanned())
                $this->flashFail("err", tr("error"), tr("forbidden"));
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
            "amount"  => $posts->page((int) ($_GET["p"] ?? 1), $perPage)->count(),
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
            "amount"  => $posts->getRowCount(),
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

        if ($wallOwner->isBanned())
            $this->flashFail("err", tr("error"), tr("forbidden"));

        if($wall > 0) {
            $canPost = $wallOwner->getPrivacyPermission("wall.write", $this->user->identity);
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
        
        $photos = [];

        if(!empty($this->postParam("photos"))) {
            $un  = rtrim($this->postParam("photos"), ",");
            $arr = explode(",", $un);

            if(sizeof($arr) < 11) {
                foreach($arr as $dat) {
                    $ids = explode("_", $dat);
                    $photo = (new Photos)->getByOwnerAndVID((int)$ids[0], (int)$ids[1]);
    
                    if(!$photo || $photo->isDeleted())
                        continue;
    
                    $photos[] = $photo;
                }
            }
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

        $note = NULL;

        if(!is_null($this->postParam("note")) && $this->postParam("note") != "none") {
            $note = (new Notes)->get((int)$this->postParam("note"));

            if(!$note || $note->isDeleted() || $note->getOwner()->getId() != $this->user->id) {
                $this->flashFail("err", tr("error"), tr("error_attaching_note"));
            }
            
            if($note->getOwner()->getPrivacySetting("notes.read") < 1) {
                $this->flashFail("err", " ");
            }
        }

        $videos = [];

        if(!empty($this->postParam("videos"))) {
            $un  = rtrim($this->postParam("videos"), ",");
            $arr = explode(",", $un);

            if(sizeof($arr) < 11) {
                foreach($arr as $dat) {
                    $ids = explode("_", $dat);
                    $video = (new Videos)->getByOwnerAndVID((int)$ids[0], (int)$ids[1]);
    
                    if(!$video || $video->isDeleted())
                        continue;
    
                    $videos[] = $video;
                }
            }
        }
        
        if(empty($this->postParam("text")) && sizeof($photos) < 1 && sizeof($videos) < 1 && !$poll && !$note)
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

            if($this->postParam("set_source") === "on" && !is_null($this->postParam("source")) && !empty($this->postParam("source")) && preg_match("/^(http:\/\/|https:\/\/)*[а-яА-ЯёЁa-z0-9\-_]+(\.[а-яА-ЯёЁa-z0-9\-_]+)+(\/\S*)*$/iu", $this->postParam("source")) && iconv_strlen($this->postParam("set_source")) < 50) {
                $post->setSource($this->postParam("source"));
            }

            $post->save();
        } catch (\LengthException $ex) {
            $this->flashFail("err", tr("failed_to_publish_post"), tr("post_is_too_big"));
        }
        
        foreach($photos as $photo)
        	$post->attach($photo);
        
        if(sizeof($videos) > 0)
            foreach($videos as $vid)
                $post->attach($vid);
        
        if(!is_null($poll))
            $post->attach($poll);

        if(!is_null($note))
            $post->attach($note);
        
        if($wall > 0 && $wall !== $this->user->identity->getId())
            (new WallPostNotification($wallOwner, $post, $this->user->identity))->emit();
        
        $excludeMentions = [$this->user->identity->getId()];
        if($wall > 0)
            $excludeMentions[] = $wall;

        $mentions = iterator_to_array($post->resolveMentions($excludeMentions));
        foreach($mentions as $mentionee)
            if($mentionee instanceof User)
                (new MentionNotification($mentionee, $post, $post->getOwner(), strip_tags($post->getText())))->emit();
        
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

            if ($this->template->wallOwner->isBanned())
                $this->flashFail("err", tr("error"), tr("forbidden"));
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

        if ($post->getWallOwner()->isBanned())
            $this->flashFail("err", tr("error"), tr("forbidden"));

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

        if(!$post || $post->isDeleted()) 
            $this->notFound();

        if ($post->getWallOwner()->isBanned())
            $this->flashFail("err", tr("error"), tr("forbidden"));
        
        $where = $this->postParam("type") ?? "wall";
        $groupId = NULL;
        $flags = 0;

        if($where == "group")
            $groupId = $this->postParam("groupId");

        if(!is_null($this->user)) {
            $nPost = new Post;

            if($where == "wall") {
                $nPost->setOwner($this->user->id);
                $nPost->setWall($this->user->id);
            } elseif($where == "group") {
                $nPost->setOwner($this->user->id);
                $club = (new Clubs)->get((int)$groupId);

                if(!$club || !$club->canBeModifiedBy($this->user->identity))
                    $this->notFound();
                
                if($this->postParam("asGroup") == 1) 
                    $flags |= 0b10000000;

                if($this->postParam("signed") == 1)
                    $flags |= 0b01000000;
                
                $nPost->setWall($groupId * -1);
            }

            $nPost->setContent($this->postParam("text"));
            $nPost->setFlags($flags);
            $nPost->save();

            $nPost->attach($post);
            
            if($post->getOwner(false)->getId() !== $this->user->identity->getId() && !($post->getOwner() instanceof Club))
                (new RepostNotification($post->getOwner(false), $post, $this->user->identity))->emit();
        };
		
        $this->returnJson([
            "wall_owner" => $where == "wall" ? $this->user->identity->getId() : $groupId * -1
        ]);
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

        if ($wallOwner->isBanned())
            $this->flashFail("err", tr("error"), tr("forbidden"));

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

        if ($post->getWallOwner()->isBanned())
            $this->flashFail("err", tr("error"), tr("forbidden"));
        
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

    function renderEdit()
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();

        if($_SERVER["REQUEST_METHOD"] !== "POST")
            $this->redirect("/id0");

        if($this->postParam("type") == "post")
            $post = $this->posts->get((int)$this->postParam("postid"));
        else
            $post = (new Comments)->get((int)$this->postParam("postid"));

        if(!$post || $post->isDeleted())
            $this->returnJson(["error" => "Invalid post"]);

        if(!$post->canBeEditedBy($this->user->identity))
            $this->returnJson(["error" => "Access denied"]);

        $attachmentsCount = sizeof(iterator_to_array($post->getChildren()));

        if(empty($this->postParam("newContent")) && $attachmentsCount < 1)
            $this->returnJson(["error" => "Empty post"]);

        $post->setEdited(time());

        try {
            $post->setContent($this->postParam("newContent"));
        } catch(\LengthException $e) {
            $this->returnJson(["error" => $e->getMessage()]);
        }

        if($this->postParam("type") === "post") {
            $post->setNsfw($this->postParam("nsfw") == "true");
            $flags = 0;

            if($post->getTargetWall() < 0 && $post->getWallOwner()->canBeModifiedBy($this->user->identity)) {
                if($this->postParam("fromgroup") == "true") {
                    $flags |= 0b10000000;
                    $post->setFlags($flags);
                } else
                    $post->setFlags($flags);
            }
        }

        $post->save(true);

        $this->returnJson(["error"    => "no", 
                        "new_content" => $post->getText(), 
                        "new_edited"  => (string)$post->getEditTime(),
                        "nsfw"        => $this->postParam("type") === "post" ? (int)$post->isExplicit() : 0,
                        "from_group"  => $this->postParam("type") === "post" && $post->getTargetWall() < 0 ?
                        ((int)$post->isPostedOnBehalfOfGroup()) : "false",
                        "new_text"    => $post->getText(false),
                        "author"      => [
                            "name"    => $post->getOwner()->getCanonicalName(),
                            "avatar"  => $post->getOwner()->getAvatarUrl()
                        ]]);
    }
}
