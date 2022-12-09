<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use openvk\Web\Models\Entities\User;
use openvk\Web\Models\Entities\Notifications\{WallPostNotification, RepostNotification, CommentNotification};
use openvk\Web\Models\Repositories\Users as UsersRepo;
use openvk\Web\Models\Entities\Club;
use openvk\Web\Models\Repositories\Clubs as ClubsRepo;
use openvk\Web\Models\Entities\Post;
use openvk\Web\Models\Repositories\Posts as PostsRepo;
use openvk\Web\Models\Entities\Comment;
use openvk\Web\Models\Repositories\Comments as CommentsRepo;

final class Wall extends VKAPIRequestHandler
{
    function get(int $owner_id, string $domain = "", int $offset = 0, int $count = 30, int $extended = 0): object
    {
        $posts    = new PostsRepo;

        $items    = [];
        $profiles = [];
        $groups   = [];
        $cnt      = $posts->getPostCountOnUserWall($owner_id);

        if ($owner_id > 0)
            $wallOnwer = (new UsersRepo)->get($owner_id);
        else
            $wallOnwer = (new ClubsRepo)->get($owner_id * -1);

        if ($owner_id > 0)
            if(!$wallOnwer || $wallOnwer->isDeleted())
                $this->fail(18, "User was deleted or banned");
        else
            if(!$wallOnwer)
                $this->fail(15, "Access denied: wall is disabled"); // Don't search for logic here pls

        foreach($posts->getPostsFromUsersWall($owner_id, 1, $count, $offset) as $post) {
            $from_id = get_class($post->getOwner()) == "openvk\Web\Models\Entities\Club" ? $post->getOwner()->getId() * (-1) : $post->getOwner()->getId();

            $attachments = [];
            $repost = [];
            foreach($post->getChildren() as $attachment) {
                if($attachment instanceof \openvk\Web\Models\Entities\Photo) {
                    if($attachment->isDeleted())
                        continue;
                    
                    $attachments[] = $this->getApiPhoto($attachment);
                } else if($attachment instanceof \openvk\Web\Models\Entities\Poll) {
                    $attachments[] = $this->getApiPoll($attachment, $this->getUser());
                } else if ($attachment instanceof \openvk\Web\Models\Entities\Post) {
                    $repostAttachments = [];

                    foreach($attachment->getChildren() as $repostAttachment) {
                        if($repostAttachment instanceof \openvk\Web\Models\Entities\Photo) {
                            if($repostAttachment->isDeleted())
                                continue;
                        
                            $repostAttachments[] = $this->getApiPhoto($repostAttachment);
                            /* Рекурсии, сука! Заказывали? */
                        }
                    }

                    if ($attachment->isPostedOnBehalfOfGroup())
                        $groups[] = $attachment->getOwner()->getId();
                    else
                        $profiles[] = $attachment->getOwner()->getId();

                    $repost[] = [
                        "id" => $attachment->getVirtualId(),
                        "owner_id" => $attachment->isPostedOnBehalfOfGroup() ? $attachment->getOwner()->getId() * -1 : $attachment->getOwner()->getId(),
                        "from_id" => $attachment->isPostedOnBehalfOfGroup() ? $attachment->getOwner()->getId() * -1 : $attachment->getOwner()->getId(),
                        "date" => $attachment->getPublicationTime()->timestamp(),
                        "post_type" => "post",
                        "text" => $attachment->getText(false),
                        "attachments" => $repostAttachments,
                        "post_source" => [
                            "type" => "vk"
                        ],
                    ];
                }
            }

            $items[] = (object)[
                "id"           => $post->getVirtualId(),
                "from_id"      => $from_id,
                "owner_id"     => $post->getTargetWall(),
                "date"         => $post->getPublicationTime()->timestamp(),
                "post_type"    => "post",
                "text"         => $post->getText(false),
                "copy_history" => $repost,
                "can_edit"     => 0, # TODO
                "can_delete"   => $post->canBeDeletedBy($this->getUser()),
                "can_pin"      => $post->canBePinnedBy($this->getUser()),
                "can_archive"  => false, # TODO MAYBE
                "is_archived"  => false,
                "is_pinned"    => $post->isPinned(),
                "attachments"  => $attachments,
                "post_source"  => (object)["type" => "vk"],
                "comments"     => (object)[
                    "count"    => $post->getCommentsCount(),
                    "can_post" => 1
                ],
                "likes" => (object)[
                    "count"       => $post->getLikesCount(),
                    "user_likes"  => (int) $post->hasLikeFrom($this->getUser()),
                    "can_like"    => 1,
                    "can_publish" => 1,
                ],
                "reposts" => (object)[
                    "count"         => $post->getRepostCount(),
                    "user_reposted" => 0
                ]
            ];
            
            if ($from_id > 0)
                $profiles[] = $from_id;
            else
                $groups[]   = $from_id * -1;

            $attachments = NULL; # free attachments so it will not clone everythingg
        }

        if($extended == 1) {
            $profiles = array_unique($profiles);
            $groups  = array_unique($groups);

            $profilesFormatted = [];
            $groupsFormatted   = [];

            foreach($profiles as $prof) {
                $user                = (new UsersRepo)->get($prof);
                $profilesFormatted[] = (object)[
                    "first_name"        => $user->getFirstName(),
                    "id"                => $user->getId(),
                    "last_name"         => $user->getLastName(),
                    "can_access_closed" => false,
                    "is_closed"         => false,
                    "sex"               => $user->isFemale() ? 1 : 2,
                    "screen_name"       => $user->getShortCode(),
                    "photo_50"          => $user->getAvatarUrl(),
                    "photo_100"         => $user->getAvatarUrl(),
                    "online"            => $user->isOnline()
                ];
            }

            foreach($groups as $g) {
                $group             = (new ClubsRepo)->get($g);
                $groupsFormatted[] = (object)[
                    "id"          => $group->getId(),
                    "name"        => $group->getName(),
                    "screen_name" => $group->getShortCode(),
                    "is_closed"   => 0,
                    "type"        => "group",
                    "photo_50"    => $group->getAvatarUrl(),
                    "photo_100"   => $group->getAvatarUrl(),
                    "photo_200"   => $group->getAvatarUrl(),
                ];
            }

            return (object) [
                "count"    => $cnt,
                "items"    => $items,
                "profiles" => $profilesFormatted,
                "groups"   => $groupsFormatted
            ];
        } else
            return (object) [
                "count" => $cnt,
                "items" => $items
            ];
    }

    function getById(string $posts, int $extended = 0, string $fields = "", User $user = NULL)
    {
        if($user == NULL) {
            $this->requireUser();
            $user = $this->getUser(); # костыли костыли крылышки
        }

        $items    = [];
        $profiles = [];
        $groups   = [];

        $psts     = explode(',', $posts);

        foreach($psts as $pst) {
            $id   = explode("_", $pst);
            $post = (new PostsRepo)->getPostById(intval($id[0]), intval($id[1]));
            if($post) {
                $from_id = get_class($post->getOwner()) == "openvk\Web\Models\Entities\Club" ? $post->getOwner()->getId() * (-1) : $post->getOwner()->getId();
                $attachments = [];
                $repost = []; // чел высрал семь сигарет 😳 помянем 🕯
                foreach($post->getChildren() as $attachment) {
                    if($attachment instanceof \openvk\Web\Models\Entities\Photo) {
                        $attachments[] = $this->getApiPhoto($attachment);
                    } else if($attachment instanceof \openvk\Web\Models\Entities\Poll) {
                        $attachments[] = $this->getApiPoll($attachment, $user);
                    } else if ($attachment instanceof \openvk\Web\Models\Entities\Post) {
                        $repostAttachments = [];

                        foreach($attachment->getChildren() as $repostAttachment) {
                            if($repostAttachment instanceof \openvk\Web\Models\Entities\Photo) {
                                if($attachment->isDeleted())
                                    continue;
                            
                                $repostAttachments[] = $this->getApiPhoto($repostAttachment);
                                /* Рекурсии, сука! Заказывали? */
                            }
                        } 
                        
                        if ($attachment->isPostedOnBehalfOfGroup())
                            $groups[] = $attachment->getOwner()->getId();
                        else
                            $profiles[] = $attachment->getOwner()->getId();

                        $repost[] = [
                            "id" => $attachment->getVirtualId(),
                            "owner_id" => $attachment->isPostedOnBehalfOfGroup() ? $attachment->getOwner()->getId() * -1 : $attachment->getOwner()->getId(),
                            "from_id" => $attachment->isPostedOnBehalfOfGroup() ? $attachment->getOwner()->getId() * -1 : $attachment->getOwner()->getId(),
                            "date" => $attachment->getPublicationTime()->timestamp(),
                            "post_type" => "post",
                            "text" => $attachment->getText(false),
                            "attachments" => $repostAttachments,
                            "post_source" => [
                                "type" => "vk"
                            ],
                        ];
                    }
                }

                $items[] = (object)[
                    "id"           => $post->getVirtualId(),
                    "from_id"      => $from_id,
                    "owner_id"     => $post->getTargetWall(),
                    "date"         => $post->getPublicationTime()->timestamp(),
                    "post_type"    => "post",
                    "text"         => $post->getText(false),
                    "copy_history" => $repost,
                    "can_edit"     => 0, # TODO
                    "can_delete"   => $post->canBeDeletedBy($user),
                    "can_pin"      => $post->canBePinnedBy($user),
                    "can_archive"  => false, # TODO MAYBE
                    "is_archived"  => false,
                    "is_pinned"    => $post->isPinned(),
                    "post_source"  => (object)["type" => "vk"],
                    "attachments"  => $attachments,
                    "comments"     => (object)[
                        "count"    => $post->getCommentsCount(),
                        "can_post" => 1
                    ],
                    "likes" => (object)[
                        "count"       => $post->getLikesCount(),
                        "user_likes"  => (int) $post->hasLikeFrom($user),
                        "can_like"    => 1,
                        "can_publish" => 1,
                    ],
                    "reposts" => (object)[
                        "count"         => $post->getRepostCount(),
                        "user_reposted" => 0
                    ]
                ];
                
                if ($from_id > 0)
                    $profiles[] = $from_id;
                else
                    $groups[]   = $from_id * -1;

                $attachments = NULL; # free attachments so it will not clone everything
                $repost = NULL;      # same
            }
        }

        if($extended == 1) {
            $profiles = array_unique($profiles);
            $groups   = array_unique($groups);

            $profilesFormatted = [];
            $groupsFormatted   = [];

            foreach($profiles as $prof) {
                $user                = (new UsersRepo)->get($prof);
                $profilesFormatted[] = (object)[
                    "first_name"        => $user->getFirstName(),
                    "id"                => $user->getId(),
                    "last_name"         => $user->getLastName(),
                    "can_access_closed" => false,
                    "is_closed"         => false,
                    "sex"               => $user->isFemale() ? 1 : 2,
                    "screen_name"       => $user->getShortCode(),
                    "photo_50"          => $user->getAvatarUrl(),
                    "photo_100"         => $user->getAvatarUrl(),
                    "online"            => $user->isOnline()
                ];
            }

            foreach($groups as $g) {
                $group             = (new ClubsRepo)->get($g);
                $groupsFormatted[] = (object)[
                    "id"           => $group->getId(),
                    "name"         => $group->getName(),
                    "screen_name"  => $group->getShortCode(),
                    "is_closed"    => 0,
                    "type"         => "group",
                    "photo_50"     => $group->getAvatarUrl(),
                    "photo_100"    => $group->getAvatarUrl(),
                    "photo_200"    => $group->getAvatarUrl(),
                ];
            }

            return (object) [
                "items"    => (array)$items,
                "profiles" => (array)$profilesFormatted,
                "groups"   => (array)$groupsFormatted
            ];
        } else
            return (object) [
                "items" => (array)$items
            ];
    }

    function post(string $owner_id, string $message = "", int $from_group = 0, int $signed = 0): object
    {
        $this->requireUser();

        $owner_id  = intval($owner_id);
        
        $wallOwner = ($owner_id > 0 ? (new UsersRepo)->get($owner_id) : (new ClubsRepo)->get($owner_id * -1))
                     ?? $this->fail(18, "User was deleted or banned");
        if($owner_id > 0)
            $canPost = $wallOwner->getPrivacyPermission("wall.write", $this->getUser());
        else if($owner_id < 0)
            if($wallOwner->canBeModifiedBy($this->getUser()))
                $canPost = true;
            else
                $canPost = $wallOwner->canPost();
        else
            $canPost = false; 

        if($canPost == false) $this->fail(15, "Access denied");

        $anon = OPENVK_ROOT_CONF["openvk"]["preferences"]["wall"]["anonymousPosting"]["enable"];
        if($wallOwner instanceof Club && $from_group == 1 && $signed != 1 && $anon) {
            $manager = $wallOwner->getManager($this->getUser());
            if($manager)
                $anon = $manager->isHidden();
            elseif($this->getUser()->getId() === $wallOwner->getOwner()->getId())
                $anon = $wallOwner->isOwnerHidden();
        } else {
            $anon = false;
        }

        $flags = 0;
        if($from_group == 1 && $wallOwner instanceof Club && $wallOwner->canBeModifiedBy($this->getUser()))
            $flags |= 0b10000000;
        if($signed == 1)
            $flags |= 0b01000000;

        # TODO: Compatible implementation of this
        try {
            $photo = NULL;
            $video = NULL;
            if($_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
                $album = NULL;
                if(!$anon && $owner_id > 0 && $owner_id === $this->getUser()->getId())
                    $album = (new AlbumsRepo)->getUserWallAlbum($wallOwner);

                $photo = Photo::fastMake($this->getUser()->getId(), $message, $_FILES["photo"], $album, $anon);
            }

            if($_FILES["video"]["error"] === UPLOAD_ERR_OK)
                $video = Video::fastMake($this->getUser()->getId(), $message, $_FILES["video"], $anon);
        } catch(\DomainException $ex) {
            $this->fail(-156, "The media file is corrupted");
        } catch(ISE $ex) {
            $this->fail(-156, "The media file is corrupted or too large ");
        }

        if(empty($message) && !$photo && !$video)
            $this->fail(100, "Required parameter 'message' missing.");

        try {
            $post = new Post;
            $post->setOwner($this->getUser()->getId());
            $post->setWall($owner_id);
            $post->setCreated(time());
            $post->setContent($message);
            $post->setFlags($flags);
            $post->save();
        } catch(\LogicException $ex) {
            $this->fail(100, "One of the parameters specified was missing or invalid");
        }

        if(!is_null($photo))
            $post->attach($photo);

        if(!is_null($video))
            $post->attach($video);

        if($wall > 0 && $wall !== $this->user->identity->getId())
            (new WallPostNotification($wallOwner, $post, $this->user->identity))->emit();

        return (object)["post_id" => $post->getVirtualId()];
    }

    function repost(string $object, string $message = "") {
        $this->requireUser();

        $postArray;
        if(preg_match('/wall((?:-?)[0-9]+)_([0-9]+)/', $object, $postArray) == 0)
            $this->fail(100, "One of the parameters specified was missing or invalid: object is incorrect");
        
        $post = (new PostsRepo)->getPostById((int) $postArray[1], (int) $postArray[2]);
        if(!$post || $post->isDeleted()) $this->fail(100, "One of the parameters specified was missing or invalid");
        
        $nPost = new Post;
        $nPost->setOwner($this->user->getId());
        $nPost->setWall($this->user->getId());
        $nPost->setContent($message);
        $nPost->save();
        $nPost->attach($post);
        
        if($post->getOwner(false)->getId() !== $this->user->getId() && !($post->getOwner() instanceof Club))
            (new RepostNotification($post->getOwner(false), $post, $this->user->identity))->emit();

        return (object) [
            "success" => 1, // 👍
            "post_id" => $nPost->getVirtualId(),
            "reposts_count" => $post->getRepostCount(),
            "likes_count" => $post->getLikesCount()
        ];
    }

    function getComments(int $owner_id, int $post_id, bool $need_likes = true, int $offset = 0, int $count = 10, string $fields = "sex,screen_name,photo_50,photo_100,online_info,online", string $sort = "asc", bool $extended = false) {
        $this->requireUser();

        $post = (new PostsRepo)->getPostById($owner_id, $post_id);
        if(!$post || $post->isDeleted()) $this->fail(100, "One of the parameters specified was missing or invalid");

        $comments = (new CommentsRepo)->getCommentsByTarget($post, $offset+1, $count, $sort == "desc" ? "DESC" : "ASC");

        if(!$post->getPrivacyPermission('comments.read', $this->user->identity))
            $this->fail(15, "Access denied");
        
        $items = [];
        $profiles = [];

        foreach($comments as $comment) {
            $owner = $comment->getOwner();
            $oid   = $owner->getId();
            if($owner instanceof Club)
                $oid *= -1;
            
            $item = [
                "id"            => $comment->getId(),
                "from_id"       => $oid,
                "date"          => $comment->getPublicationTime()->timestamp(),
                "text"          => $comment->getText(false),
                "post_id"       => $post->getVirtualId(),
                "owner_id"      => $post->isPostedOnBehalfOfGroup() ? $post->getOwner()->getId() * -1 : $post->getOwner()->getId(),
                "parents_stack" => [],
                "thread"        => [
                    "count"             => 0,
                    "items"             => [],
                    "can_post"          => false,
                    "show_reply_button" => true,
                    "groups_can_post"   => false,
                ]
            ];

            if($need_likes == true)
                $item['likes'] = [
                    "can_like"    => 1,
                    "count"       => $comment->getLikesCount(),
                    "user_likes"  => (int) $comment->hasLikeFrom($this->getUser()),
                    "can_publish" => 1
                ];
            
            $items[] = $item;
            if($extended == true)
                $profiles[] = $comment->getOwner()->getId();
        }

        $response = [
            "count"               => (new CommentsRepo)->getCommentsCountByTarget($post),
            "items"               => $items,
            "current_level_count" => (new CommentsRepo)->getCommentsCountByTarget($post),
            "can_post"            => true,
            "show_reply_button"   => true,
            "groups_can_post"     => false
        ];

        if($extended == true) {
            $profiles = array_unique($profiles);
            $response['profiles'] = (!empty($profiles) ? (new Users)->get(implode(',', $profiles), $fields) : []);
        }

        return (object) $response;
    }

    function getComment(int $owner_id, int $comment_id, bool $extended = false, string $fields = "sex,screen_name,photo_50,photo_100,online_info,online") {
        $this->requireUser();

        $comment = (new CommentsRepo)->get($comment_id); // один хуй айди всех комментов общий

        $profiles = [];

        $item = [
            "id"            => $comment->getId(),
            "from_id"       => $comment->getOwner()->getId(),
            "date"          => $comment->getPublicationTime()->timestamp(),
            "text"          => $comment->getText(false),
            "post_id"       => $comment->getTarget()->getVirtualId(),
            "owner_id"      => $comment->getTarget()->isPostedOnBehalfOfGroup() ? $comment->getTarget()->getOwner()->getId() * -1 : $comment->getTarget()->getOwner()->getId(),
            "parents_stack" => [],
            "likes"         => [
                "can_like"    => 1,
                "count"       => $comment->getLikesCount(),
                "user_likes"  => (int) $comment->hasLikeFrom($this->getUser()),
                "can_publish" => 1
            ],
            "thread"        => [
                "count"             => 0,
                "items"             => [],
                "can_post"          => false,
                "show_reply_button" => true,
                "groups_can_post"   => false,
            ]
        ];
        
        if($extended == true)
            $profiles[] = $comment->getOwner()->getId();

        $response = [
            "items"               => [$item],
            "can_post"            => true,
            "show_reply_button"   => true,
            "groups_can_post"     => false
        ];

        if($extended == true) {
            $profiles = array_unique($profiles);
            $response['profiles'] = (!empty($profiles) ? (new Users)->get(implode(',', $profiles), $fields) : []);
        }

        return $response;
    }

    function createComment(int $owner_id, int $post_id, string $message, int $from_group = 0) {
        $post = (new PostsRepo)->getPostById($owner_id, $post_id);
        if(!$post || $post->isDeleted()) $this->fail(100, "One of the parameters specified was missing or invalid");

        if($post->isClosed() || !$post->getPrivacyPermission('comments.write', $this->user->identity))
            $this->fail(15, "Access denied");

        if($post->getTargetWall() < 0)
            $club = (new ClubsRepo)->get(abs($post->getTargetWall()));        
        
        $flags = 0;
        if($from_group != 0 && !is_null($club) && $club->canBeModifiedBy($this->user))
            $flags |= 0b10000000;
        
        try {
            $comment = new Comment;
            $comment->setOwner($this->user->getId());
            $comment->setModel(get_class($post));
            $comment->setTarget($post->getId());
            $comment->setContent($message);
            $comment->setCreated(time());
            $comment->setFlags($flags);
            $comment->save();
        } catch (\LengthException $ex) {
            $this->fail(1, "ошибка про то что коммент большой слишком");
        }
        
        if($post->getOwner()->getId() !== $this->user->getId())
            if(($owner = $post->getOwner()) instanceof User)
                (new CommentNotification($owner, $comment, $post, $this->user))->emit();
        
        return (object) [
            "comment_id" => $comment->getId(),
            "parents_stack" => []
        ];
    }

    function deleteComment(int $comment_id) {
        $this->requireUser();

        $comment = (new CommentsRepo)->get($comment_id);
        if(!$comment) $this->fail(100, "One of the parameters specified was missing or invalid");;
        if(!$comment->canBeDeletedBy($this->user))
            $this->fail(15, "Access denied");
        
        $comment->delete();
        
        return 1;
    }
    
    private function getApiPhoto($attachment) {
        return [
            "type"  => "photo",
            "photo" => [
                "album_id" => $attachment->getAlbum() ? $attachment->getAlbum()->getId() : NULL,
                "date"     => $attachment->getPublicationTime()->timestamp(),
                "id"       => $attachment->getVirtualId(),
                "owner_id" => $attachment->getOwner()->getId(),
                "sizes"    => array_values($attachment->getVkApiSizes()),
                "text"     => "",
                "has_tags" => false
            ]
        ];
    }

    private function getApiPoll($attachment, $user) {
        $answers = array();
        foreach($attachment->getResults()->options as $answer) {
            $answers[] = (object)[
                "id"    => $answer->id,
                "rate"  => $answer->pct,
                "text"  => $answer->name,
                "votes" => $answer->votes
            ];
        }
        
        $userVote = array();
        foreach($attachment->getUserVote($user) as $vote)
            $userVote[] = $vote[0];

        return [
            "type"  => "poll",
            "poll" => [
                "multiple"       => $attachment->isMultipleChoice(),
                "end_date"       => $attachment->endsAt() == NULL ? 0 : $attachment->endsAt()->timestamp(),
                "closed"         => $attachment->hasEnded(),
                "is_board"       => false,
                "can_edit"       => false,
                "can_vote"       => $attachment->canVote($user),
                "can_report"     => false,
                "can_share"      => true,
                "created"        => 0,
                "id"             => $attachment->getId(),
                "owner_id"       => $attachment->getOwner()->getId(),
                "question"       => $attachment->getTitle(),
                "votes"          => $attachment->getVoterCount(),
                "disable_unvote" => $attachment->isRevotable(),
                "anonymous"      => $attachment->isAnonymous(),
                "answer_ids"     => $userVote,
                "answers"        => $answers,
                "author_id"      => $attachment->getOwner()->getId(),
            ]
        ];
    }
}
