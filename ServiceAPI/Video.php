<?php declare(strict_types=1);
namespace openvk\ServiceAPI;

use openvk\Web\Models\Entities\{User, Post};
use openvk\Web\Models\Repositories\{Videos, Comments, Clubs};
use Chandler\MVC\Routing\Router;

class Video implements Handler
{
    protected $user;
    protected $videos;
    protected $comments;
    protected $groups;

    function __construct(?User $user)
    {
        $this->user  = $user;
        $this->videos = new Videos;
        $this->comments = new Comments;
        $this->groups = new Clubs;
    }

    function getVideo(int $id, callable $resolve, callable $reject)
    {
        $video = $this->videos->get($id);

        if(!$video || $video->isDeleted()) {
            $reject(2, "Video does not exists");
        }

        if(method_exists($video, "canBeViewedBy") && !$video->canBeViewedBy($this->user)) {
            $reject(4, "Access to video denied");
        }

        if(!$video->getOwner()->getPrivacyPermission('videos.read', $this->user)) {
            $reject(8, "Access to video denied: this user chose to hide his videos");
        }

        $prevVideo = NULL;
        $nextVideo = NULL;
        $lastVideo = $this->videos->getLastVideo($video->getOwner());

        if($video->getVirtualId() - 1 != 0) {
            for($i = $video->getVirtualId(); $i != 0; $i--) {
                $maybeVideo = (new Videos)->getByOwnerAndVID($video->getOwner()->getId(), $i);

                if(!is_null($maybeVideo) && !$maybeVideo->isDeleted() && $maybeVideo->getId() != $video->getId()) {
                    if(method_exists($maybeVideo, "canBeViewedBy") && !$maybeVideo->canBeViewedBy($this->user)) {
                        continue;
                    }

                    $prevVideo = $maybeVideo;
                    break;
                }
            }
        }

        if(is_null($lastVideo) || $lastVideo->getId() == $video->getId()) {
            $nextVideo = NULL;
        } else {
            for($i = $video->getVirtualId(); $i <= $lastVideo->getVirtualId(); $i++) {
                $maybeVideo = (new Videos)->getByOwnerAndVID($video->getOwner()->getId(), $i);

                if(!is_null($maybeVideo) && !$maybeVideo->isDeleted() && $maybeVideo->getId() != $video->getId()) {
                    if(method_exists($maybeVideo, "canBeViewedBy") && !$maybeVideo->canBeViewedBy($this->user)) {
                        continue;
                    }

                    $nextVideo = $maybeVideo;
                    break;
                }
            }
        }

        $res = [
            "id"           => $video->getId(),
            "title"        => $video->getName(),
            "owner"        => $video->getOwner()->getId(),
            "commentsCount" => $video->getCommentsCount(),
            "description"  => $video->getDescription(),
            "type"         => $video->getType(),
            "name"         => $video->getOwner()->getCanonicalName(),
            "pretty_id"    => $video->getPrettyId(),
            "virtual_id"   => $video->getVirtualId(),
            "published"    => (string)$video->getPublicationTime(),
            "likes"        => $video->getLikesCount(),
            "has_like"     => $video->hasLikeFrom($this->user),
            "author"       => $video->getOwner()->getCanonicalName(),
            "canBeEdited"  => $video->getOwner()->getId() == $this->user->getId(),
            "isProcessing" => $video->getType() == 0 && $video->getURL() == "/assets/packages/static/openvk/video/rendering.mp4",
            "prevVideo"    => !is_null($prevVideo) ? $prevVideo->getId() : null,
            "nextVideo"    => !is_null($nextVideo) ? $nextVideo->getId() : null,
        ];

        if($video->getType() == 1) {
            $res["embed"] = $video->getVideoDriver()->getEmbed();
        } else {
            $res["url"]   = $video->getURL();
        }

        $resolve($res);
    }

    function shareVideo(int $owner, int $vid, int $type, string $message, int $club, bool $signed, bool $asGroup, callable $resolve, callable $reject)
    {
        $video = $this->videos->getByOwnerAndVID($owner, $vid);

        if(!$video || $video->isDeleted()) {
            $reject(16, "Video does not exists");
        }

        if(method_exists($video, "canBeViewedBy") && !$video->canBeViewedBy($this->user)) {
            $reject(32, "Access to video denied");
        }

        if(!$video->getOwner()->getPrivacyPermission('videos.read', $this->user)) {
            $reject(8, "Access to video denied: this user chose to hide his videos");
        }

        $flags = 0;

        $nPost = new Post;
        $nPost->setOwner($this->user->getId());

        if($type == 0) {
            $nPost->setWall($this->user->getId());
        } else {
            $club = $this->groups->get($club);

            if(!$club || $club->isDeleted() || !$club->canBeModifiedBy($this->user)) {
                $reject(64, "Can't do repost to this club");
            }

            if($asGroup) 
                $flags |= 0b10000000;

            if($signed)
                $flags |= 0b01000000;

            $nPost->setWall($club->getId() * -1);
        }

        $nPost->setContent($message);
        $nPost->setFlags($flags);
        $nPost->save();

        $nPost->attach($video);

        $res = [
            "id"        => $nPost->getId(),
            "pretty_id" => $nPost->getPrettyId(),
        ];

        $resolve($res);
    }
}
