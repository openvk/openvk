<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Models\Repositories\Photos;

class Album extends MediaCollection
{
    const SPECIAL_AVATARS = 16;
    const SPECIAL_WALL    = 32;
    
    protected $tableName       = "albums";
    protected $relTableName    = "album_relations";
    protected $entityTableName = "photos";
    protected $entityClassName = 'openvk\Web\Models\Entities\Photo';
    
    protected $specialNames = [
        16 => "_avatar_album",
        32 => "_wall_album",
        64 => "_saved_photos_album",
    ];
    
    function getCoverURL(): ?string
    {
        $coverPhoto = $this->getCoverPhoto();
        if(!$coverPhoto)
            return "/assets/packages/static/openvk/img/camera_200.png";
        
        return $coverPhoto->getURL();
    }
    
    function getCoverPhoto(): ?Photo
    {
        $cover = $this->getRecord()->cover_photo;
        if(!$cover) {
            $photos = iterator_to_array($this->getPhotos(1, 1));
            $photo  = $photos[0] ?? NULL;
            if(!$photo || $photo->isDeleted())
                return NULL;
            else
                return $photo;
        }
        
        return (new Photos)->get($cover);
    }
    
    function getPhotos(int $page = 1, ?int $perPage = NULL): \Traversable
    {
        return $this->fetch($page, $perPage);
    }
    
    function getPhotosCount(): int
    {
        return $this->size();
    }
    
    function addPhoto(Photo $photo): void
    {
        $this->add($photo);
    }
    
    function removePhoto(Photo $photo): void
    {
        $this->remove($photo);
    }
    
    function hasPhoto(Photo $photo): bool
    {
        return $this->has($photo);
    }

    function canBeViewedBy(?User $user = NULL): bool
    {
        if($this->isDeleted()) {
            return false;
        }

        $owner = $this->getOwner();

        if(get_class($owner) == "openvk\\Web\\Models\\Entities\\User") {
            return $owner->canBeViewedBy($user) && $owner->getPrivacyPermission('photos.read', $user);
        } else {
            return $owner->canBeViewedBy($user);
        }
    }

    function toVkApiStruct(?User $user = NULL, bool $need_covers = false, bool $photo_sizes = false): object
    {
        $res = (object) [];

        $res->id              = $this->getPrettyId();
        $res->thumb_id        = !is_null($this->getCoverPhoto()) ? $this->getCoverPhoto()->getPrettyId() : 0;
        $res->owner_id        = $this->getOwner()->getId();
        $res->title           = $this->getName();
        $res->description     = $this->getDescription();
        $res->created         = $this->getCreationTime()->timestamp();
        $res->updated         = $this->getEditTime() ? $this->getEditTime()->timestamp() : NULL;
        $res->size            = $this->size();
        $res->privacy_comment = 1;
        $res->upload_by_admins_only = 1;
        $res->comments_disabled = 0;
        $res->can_upload      = $this->canBeModifiedBy($user); # thisUser недоступен в entities
        if($need_covers) {
            $res->thumb_src   = $this->getCoverURL();

            if($photo_sizes) {
                $res->sizes   = !is_null($this->getCoverPhoto()) ? $this->getCoverPhoto()->getVkApiSizes() : NULL;
            }
        }

        return $res;
    }
}
