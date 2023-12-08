<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use openvk\Web\Models\Repositories\Users as UsersRepo;
use openvk\Web\Models\Repositories\Posts as PostsRepo;
use openvk\Web\Models\Repositories\Comments as CommentsRepo;
use openvk\Web\Models\Repositories\Videos as VideosRepo;
use openvk\Web\Models\Repositories\Photos as PhotosRepo;
use openvk\Web\Models\Repositories\Notes as NotesRepo;


final class Likes extends VKAPIRequestHandler
{
    function add(string $type, int $owner_id, int $item_id): object
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $postable = NULL;
        switch($type) {
            case "post":
                $post = (new PostsRepo)->getPostById($owner_id, $item_id);
                $postable = $post;
                break;
            case "comment":
                $comment = (new CommentsRepo)->get($item_id);
                $postable = $comment;
                break;
            case "video":
                $video = (new VideosRepo)->getByOwnerAndVID($owner_id, $item_id);
                $postable = $video;
                break;
            case "photo":
                $photo = (new PhotosRepo)->getByOwnerAndVID($owner_id, $item_id);
                $postable = $photo;
                break;
            case "note":
                $note = (new NotesRepo)->getNoteById($owner_id, $item_id);
                $postable = $note;
                break;
            default:
                $this->fail(100, "One of the parameters specified was missing or invalid: incorrect type");
        }

        if(is_null($postable) || $postable->isDeleted())
            $this->fail(100, "One of the parameters specified was missing or invalid: object not found");

        if(!$postable->canBeViewedBy($this->getUser() ?? NULL)) {
            $this->fail(2, "Access to postable denied");
        }

        $postable->setLike(true, $this->getUser());

        return (object) [
            "likes" => $postable->getLikesCount()
        ];
    }

    function delete(string $type, int $owner_id, int $item_id): object
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $postable = NULL;
        switch($type) {
            case "post":
                $post = (new PostsRepo)->getPostById($owner_id, $item_id);
                $postable = $post;
                break;
            case "comment":
                $comment = (new CommentsRepo)->get($item_id);
                $postable = $comment;
                break;
            case "video":
                $video = (new VideosRepo)->getByOwnerAndVID($owner_id, $item_id);
                $postable = $video;
                break;
            case "photo":
                $photo = (new PhotosRepo)->getByOwnerAndVID($owner_id, $item_id);
                $postable = $photo;
                break;
            case "note":
                $note = (new NotesRepo)->getNoteById($owner_id, $item_id);
                $postable = $note;
                break;
            default:
                $this->fail(100, "One of the parameters specified was missing or invalid: incorrect type");
        }

        if(is_null($postable) || $postable->isDeleted())
            $this->fail(100, "One of the parameters specified was missing or invalid: object not found");

        if(!$postable->canBeViewedBy($this->getUser() ?? NULL)) {
            $this->fail(2, "Access to postable denied");
        }

        if(!is_null($postable)) {
            $postable->setLike(false, $this->getUser());

            return (object) [
                "likes" => $postable->getLikesCount()
            ];
        }
    }
    
    function isLiked(int $user_id, string $type, int $owner_id, int $item_id): object
    {
        $this->requireUser();

        $user = (new UsersRepo)->get($user_id);

        if(is_null($user) || $user->isDeleted())
            $this->fail(100, "One of the parameters specified was missing or invalid: user not found");
        
        if(!$user->canBeViewedBy($this->getUser())) {
            $this->fail(1984, "Access denied: you can't see this user");
        }

        $postable = NULL;
        switch($type) {
            case "post":
                $post = (new PostsRepo)->getPostById($owner_id, $item_id);
                $postable = $post;
                break;
            case "comment":
                $comment = (new CommentsRepo)->get($item_id);
                $postable = $comment;
                break;
            case "video":
                $video = (new VideosRepo)->getByOwnerAndVID($owner_id, $item_id);
                $postable = $video;
                break;
            case "photo":
                $photo = (new PhotosRepo)->getByOwnerAndVID($owner_id, $item_id);
                $postable = $photo;
                break;
            case "note":
                $note = (new NotesRepo)->getNoteById($owner_id, $item_id);
                $postable = $note;
                break;
            default:
                $this->fail(100, "One of the parameters specified was missing or invalid: incorrect type");
        }

        if(is_null($postable) || $postable->isDeleted())
            $this->fail(100, "One of the parameters specified was missing or invalid: object not found");

        if(!$postable->canBeViewedBy($this->getUser())) {
            $this->fail(665, "Access to postable denied");
        }

        return (object) [
            "liked"  => (int) $postable->hasLikeFrom($user),
            "copied" => 0
        ];
	}

    function getList(string $type, int $owner_id, int $item_id, bool $extended = false, int $offset = 0, int $count = 10, bool $skip_own = false)
    {
        $this->requireUser();

        $object = NULL;

        switch($type) {
            case "post":
                $object = (new PostsRepo)->getPostById($owner_id, $item_id);
                break;
            case "comment":
                $object = (new CommentsRepo)->get($item_id);
                break;
            case "photo":
                $object = (new PhotosRepo)->getByOwnerAndVID($owner_id, $item_id);
                break;
            case "video":
                $object = (new VideosRepo)->getByOwnerAndVID($owner_id, $item_id);
                break;
            default:
                $this->fail(58, "Invalid type");
                break;
        }

        if(!$object || $object->isDeleted())
            $this->fail(56, "Invalid postable");

        if(!$object->canBeViewedBy($this->getUser()))
            $this->fail(665, "Access to postable denied");

        $res = (object)[
            "count" => $object->getLikesCount(),
            "items" => []
        ];

        $likers = array_slice(iterator_to_array($object->getLikers(1, $offset + $count)), $offset);
        
        foreach($likers as $liker) {
            if($skip_own && $liker->getId() == $this->getUser()->getId())
                continue;

            if(!$extended)
                $res->items[] = $liker->getId();
            else
                $res->items[] = $liker->toVkApiStruct();
        }

        return $res;
    }
}
