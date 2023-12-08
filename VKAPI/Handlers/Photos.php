<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;

use Nette\InvalidStateException;
use Nette\Utils\ImageException;
use openvk\Web\Models\Entities\{Photo, Album, Comment};
use openvk\Web\Models\Repositories\Albums;
use openvk\Web\Models\Repositories\Photos as PhotosRepo;
use openvk\Web\Models\Repositories\Clubs;
use openvk\Web\Models\Repositories\Users as UsersRepo;
use openvk\Web\Models\Repositories\Comments as CommentsRepo;

final class Photos extends VKAPIRequestHandler
{
    private function getPhotoUploadUrl(string $field, int $group = 0, bool $multifile = false): string
    {
        $secret     = CHANDLER_ROOT_CONF["security"]["secret"];
        $uploadInfo = [
            1,
            $field,
            (int) $multifile,
            0,
            time(),
            $this->getUser()->getId(),
            $group,
            0, # this is unused but stays here base64 reasons (X2 doesn't work, so there's dummy value for short)
        ];
        $uploadInfo = pack("vZ10v2P3S", ...$uploadInfo);
        $uploadInfo = base64_encode($uploadInfo);
        $uploadHash = hash_hmac("sha3-224", $uploadInfo, $secret);
        $uploadInfo = rawurlencode($uploadInfo);

        return ovk_scheme(true) . $_SERVER["HTTP_HOST"] . "/upload/photo/$uploadHash?$uploadInfo";
    }

    private function getImagePath(string $photo, string $hash, ?string& $up = NULL, ?string& $group = NULL): string
    {
        $secret = CHANDLER_ROOT_CONF["security"]["secret"];
        if(!hash_equals(hash_hmac("sha3-224", $photo, $secret), $hash))
            $this->fail(121, "Incorrect hash");

        [$up, $image, $group] = explode("|", $photo);

        $imagePath = __DIR__ . "/../../tmp/api-storage/photos/$up" . "_$image.oct";
        if(!file_exists($imagePath))
            $this->fail(10, "Invalid image");

        return $imagePath;
    }

    function getOwnerPhotoUploadServer(int $owner_id = 0): object
    {
        $this->requireUser();

        if($owner_id < 0) {
            $club = (new Clubs)->get(abs($owner_id));
            if(!$club)
                $this->fail(0404, "Club not found");
            else if(!$club->canBeModifiedBy($this->getUser()))
                $this->fail(200, "Access: Club can't be 'written' by user");
        }

        return (object) [
            "upload_url" => $this->getPhotoUploadUrl("photo", !isset($club) ? 0 : $club->getId()),
        ];
    }

    function saveOwnerPhoto(string $photo, string $hash): object
    {
        $imagePath = $this->getImagePath($photo, $hash, $uploader, $group);
        if($group == 0) {
            $user  = (new \openvk\Web\Models\Repositories\Users)->get((int) $uploader);
            $album = (new Albums)->getUserAvatarAlbum($user);
        } else {
            $club  = (new Clubs)->get((int) $group);
            $album = (new Albums)->getClubAvatarAlbum($club);
        }

        try {
            $avatar = new Photo;
            $avatar->setOwner((int) $uploader);
            $avatar->setDescription("Profile photo");
            $avatar->setCreated(time());
            $avatar->setFile([
                "tmp_name" => $imagePath,
                "error" => 0,
            ]);
            $avatar->save();
            $album->addPhoto($avatar);
            unlink($imagePath);
        } catch(ImageException | InvalidStateException $e) {
            unlink($imagePath);
            $this->fail(129, "Invalid image file");
        }

        return (object) [
            "photo_hash" => NULL,
            "photo_src"  => $avatar->getURL(),
        ];
    }

    function getWallUploadServer(?int $group_id = NULL): object
    {
        $this->requireUser();

        $album = NULL;
        if(!is_null($group_id)) {
            $club = (new Clubs)->get(abs($group_id));
            if(!$club)
                $this->fail(0404, "Club not found");
            else if(!$club->canBeModifiedBy($this->getUser()))
                $this->fail(200, "Access: Club can't be 'written' by user");
        } else {
            $album = (new Albums)->getUserWallAlbum($this->getUser());
        }

        return (object) [
            "upload_url" => $this->getPhotoUploadUrl("photo", $group_id ?? 0),
            "album_id"   => $album,
            "user_id"    => $this->getUser()->getId(),
        ];
    }

    function saveWallPhoto(string $photo, string $hash, int $group_id = 0, ?string $caption = NULL): array
    {
        $imagePath = $this->getImagePath($photo, $hash, $uploader, $group);
        if($group_id != $group)
            $this->fail(8, "group_id doesn't match");

        $album = NULL;
        if($group_id != 0) {
            $uploader = (new \openvk\Web\Models\Repositories\Users)->get((int) $uploader);
            $album    = (new Albums)->getUserWallAlbum($uploader);
        }

        try {
            $photo = new Photo;
            $photo->setOwner((int) $uploader);
            $photo->setCreated(time());
            $photo->setFile([
                "tmp_name" => $imagePath,
                "error" => 0,
            ]);

            if (!is_null($caption))
                $photo->setDescription($caption);

            $photo->save();
            unlink($imagePath);
        } catch(ImageException | InvalidStateException $e) {
            unlink($imagePath);
            $this->fail(129, "Invalid image file");
        }

        if(!is_null($album))
            $album->addPhoto($photo);

        return [
            $photo->toVkApiStruct(),
        ];
    }

    function getUploadServer(?int $album_id = NULL): object
    {
        $this->requireUser();

        # Not checking rights to album because save() method will do so anyways
        return (object) [
            "upload_url" => $this->getPhotoUploadUrl("photo", 0, true),
            "album_id"   => $album_id,
            "user_id"    => $this->getUser()->getId(),
        ];
    }

    function save(string $photos_list, string $hash, int $album_id = 0, ?string $caption = NULL): object
    {
        $this->requireUser();

        $secret = CHANDLER_ROOT_CONF["security"]["secret"];
        if(!hash_equals(hash_hmac("sha3-224", $photos_list, $secret), $hash))
            $this->fail(121, "Incorrect hash");

        $album = NULL;
        if($album_id != 0) {
            $album_ = (new Albums)->get($album_id);
            if(!$album_)
                $this->fail(0404, "Invalid album");
            else if(!$album_->canBeModifiedBy($this->getUser()))
                $this->fail(15, "Access: Album can't be 'written' by user");

            $album = $album_;
        }

        $pList      = json_decode($photos_list);
        $imagePaths = [];
        foreach($pList as $pDesc)
            $imagePaths[] = __DIR__ . "/../../tmp/api-storage/photos/$pDesc->keyholder" . "_$pDesc->resource.oct";

        $images = [];
        try {
            foreach($imagePaths as $imagePath) {
                $photo = new Photo;
                $photo->setOwner($this->getUser()->getId());
                $photo->setCreated(time());
                $photo->setFile([
                    "tmp_name" => $imagePath,
                    "error" => 0,
                ]);

                if (!is_null($caption))
                    $photo->setDescription($caption);

                $photo->save();
                unlink($imagePath);

                if(!is_null($album))
                    $album->addPhoto($photo);

                $images[] = $photo->toVkApiStruct();
            }
        } catch(ImageException | InvalidStateException $e) {
            foreach($imagePaths as $imagePath)
                unlink($imagePath);

            $this->fail(129, "Invalid image file");
        }

        return (object) [
            "count" => sizeof($images),
            "items" => $images,
        ];
    }

    function createAlbum(string $title, int $group_id = 0, string $description = "", int $privacy = 0)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        if($group_id != 0) {
            $club = (new Clubs)->get((int) $group_id);

            if(!$club || !$club->canBeModifiedBy($this->getUser())) {
                $this->fail(20, "Invalid club");
            }
        }

        $album = new Album;
        $album->setOwner(isset($club) ? $club->getId() * -1 : $this->getUser()->getId());
        $album->setName($title);
        $album->setDescription($description);
        $album->setCreated(time());
        $album->save();

        return $album->toVkApiStruct($this->getUser());
    }

    function editAlbum(int $album_id, int $owner_id, string $title, string $description = "", int $privacy = 0)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $album = (new Albums)->getAlbumByOwnerAndId($owner_id, $album_id);

        if(!$album || $album->isDeleted()) {
            $this->fail(2, "Invalid album");
        }

        if(empty($title)) {
            $this->fail(25, "Title is empty");
        }

        if($album->isCreatedBySystem()) {
            $this->fail(40, "You can't change system album");
        }

        if(!$album->canBeModifiedBy($this->getUser())) {
            $this->fail(2, "Access to album denied");
        }

        $album->setName($title);
        $album->setDescription($description);

        $album->save();

        return $album->toVkApiStruct($this->getUser());
    }

    function getAlbums(int $owner_id, string $album_ids = "", int $offset = 0, int $count = 100, bool $need_system = true, bool $need_covers = true, bool $photo_sizes = false)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $res = [];

        if(empty($album_ids)) {
            if($owner_id > 0) {
                $user   = (new UsersRepo)->get($owner_id);

                $res = [
                    "count" => (new Albums)->getUserAlbumsCount($user),
                    "items" => []
                ];

                if(!$user || $user->isDeleted())
                    $this->fail(2, "Invalid user");
                
                if(!$user->getPrivacyPermission('photos.read', $this->getUser()))
                    $this->fail(21, "This user chose to hide his albums.");

                $albums = array_slice(iterator_to_array((new Albums)->getUserAlbums($user, 1, $count + $offset)), $offset);

                foreach($albums as $album) {
                    if(!$need_system && $album->isCreatedBySystem()) continue;
                    $res["items"][] = $album->toVkApiStruct($this->getUser(), $need_covers, $photo_sizes);
                }
            }

            else {
                $club   = (new Clubs)->get($owner_id * -1);

                $res = [
                    "count" => (new Albums)->getClubAlbumsCount($club),
                    "items" => []
                ];

                if(!$club)
                    $this->fail(2, "Invalid club");
                
                $albums = array_slice(iterator_to_array((new Albums)->getClubAlbums($club, 1, $count + $offset)), $offset);

                foreach($albums as $album) {
                    if(!$need_system && $album->isCreatedBySystem()) continue;
                    $res["items"][] = $album->toVkApiStruct($this->getUser(), $need_covers, $photo_sizes);
                }
            }

        } else {
            $albums = explode(',', $album_ids);

            $res = [
                "count" => sizeof($albums),
                "items" => []
            ];

            foreach($albums as $album)
            {
                $id = explode("_", $album);
    
                $album = (new Albums)->getAlbumByOwnerAndId((int)$id[0], (int)$id[1]);
                if($album && !$album->isDeleted()) {
                    if(!$need_system && $album->isCreatedBySystem()) continue;
                    $res["items"][] = $album->toVkApiStruct($this->getUser(), $need_covers, $photo_sizes);
                }
            }
        }

        return $res;
    }

    function getAlbumsCount(int $user_id = 0, int $group_id = 0)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        if($user_id == 0 && $group_id == 0 || $user_id > 0 && $group_id > 0)
            $this->fail(21, "Select user_id or group_id");

        if($user_id > 0) {
            $us = (new UsersRepo)->get($user_id);
            if(!$us || $us->isDeleted())
                $this->fail(21, "Invalid user");
            
            if(!$us->getPrivacyPermission('photos.read', $this->getUser()))
                $this->fail(21, "This user chose to hide his albums.");

            return (new Albums)->getUserAlbumsCount($us);
        }

        if($group_id > 0) {
            $cl = (new Clubs)->get($group_id);
            if(!$cl) {
                $this->fail(21, "Invalid club");
            }

            return (new Albums)->getClubAlbumsCount($cl);
        }
    }

    function getById(string $photos, bool $extended = false, bool $photo_sizes = false)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $phts = explode(",", $photos);
        $res = [];

        foreach($phts as $phota) {
            $ph    = explode("_", $phota);
            $photo = (new PhotosRepo)->getByOwnerAndVID((int)$ph[0], (int)$ph[1]);
            
            if(!$photo || $photo->isDeleted())
                $this->fail(21, "Invalid photo");

            if(!$photo->canBeViewedBy($this->getUser()))
                $this->fail(15, "Access denied");

            $res[] = $photo->toVkApiStruct($photo_sizes, $extended);
        }

        return $res;
    }

    function get(int $owner_id, int $album_id, string $photo_ids = "", bool $extended = false, bool $photo_sizes = false, int $offset = 0, int $count = 10)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $res = [];

        if(empty($photo_ids)) {
            $album = (new Albums)->getAlbumByOwnerAndId($owner_id, $album_id);

            if(!$album || $album->isDeleted())
                $this->fail(21, "Invalid album");
            
            if(!$album->canBeViewedBy($this->getUser())) 
                $this->fail(15, "Access denied");
            
            $photos = array_slice(iterator_to_array($album->getPhotos(1, $count + $offset)), $offset);
            $res["count"] = sizeof($photos);

            foreach($photos as $photo) {
                if(!$photo || $photo->isDeleted()) continue;
                $res["items"][] = $photo->toVkApiStruct($photo_sizes, $extended);
            }

        } else {
            $photos = explode(',', $photo_ids);

            $res = [
                "count" => sizeof($photos),
                "items" => []
            ];

            foreach($photos as $photo) {
                $id = explode("_", $photo);
    
                $phot = (new PhotosRepo)->getByOwnerAndVID((int)$id[0], (int)$id[1]);
                if($phot && !$phot->isDeleted() && $phot->canBeViewedBy($this->getUser())) {
                    $res["items"][] = $phot->toVkApiStruct($photo_sizes, $extended);
                }
            }
        }

        return $res;
    }

    function deleteAlbum(int $album_id, int $group_id = 0)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $album = (new Albums)->get($album_id);

        if(!$album || $album->canBeModifiedBy($this->getUser()))
            $this->fail(21, "Invalid album");

        if($album->isDeleted())
            $this->fail(22, "Album already deleted");

        $album->delete();

        return 1;
    }

    function edit(int $owner_id, int $photo_id, string $caption = "")
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $photo = (new PhotosRepo)->getByOwnerAndVID($owner_id, $photo_id);

        if(!$photo)
            $this->fail(21, "Invalid photo");

        if($photo->isDeleted())
            $this->fail(21, "Photo is deleted");

        if(!empty($caption)) {
            $photo->setDescription($caption);
            $photo->save();
        }

        return 1;
    }

    function delete(int $owner_id, int $photo_id, string $photos = "")
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        if(empty($photos)) {
            $photo = (new PhotosRepo)->getByOwnerAndVID($owner_id, $photo_id);

            if($this->getUser()->getId() !== $photo->getOwner()->getId())
                $this->fail(21, "You can't delete another's photo");

            if(!$photo)
                $this->fail(21, "Invalid photo");

            if($photo->isDeleted())
                $this->fail(21, "Photo is already deleted");

            $photo->delete();
        } else {
            $photozs = explode(',', $photos);

            foreach($photozs as $photo)
            {
                $id = explode("_", $photo);
    
                $phot = (new PhotosRepo)->getByOwnerAndVID((int)$id[0], (int)$id[1]);

                if($this->getUser()->getId() !== $phot->getOwner()->getId())
                    $this->fail(21, "You can't delete another's photo");

                if(!$phot)
                    $this->fail(21, "Invalid photo");
    
                if($phot->isDeleted())
                    $this->fail(21, "Photo already deleted");

                $phot->delete();
            }
        }

        return 1;
    }

    function getAllComments(int $owner_id, int $album_id, bool $need_likes = false, int $offset = 0, int $count = 100)
    {
        $this->fail(501, "Not implemented");
    }

    function deleteComment(int $comment_id, int $owner_id = 0)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $comment = (new CommentsRepo)->get($comment_id);
        if(!$comment)
            $this->fail(21, "Invalid comment");

        if(!$comment->canBeModifiedBy($this->getUser()))
            $this->fail(21, "Access denied");

        $comment->delete();

        return 1;
    }

    function createComment(int $owner_id, int $photo_id, string $message = "", string $attachments = "", bool $from_group = false)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        if(empty($message) && empty($attachments))
            $this->fail(100, "Required parameter 'message' missing.");

        $photo = (new PhotosRepo)->getByOwnerAndVID($owner_id, $photo_id);

        if(!$photo || $photo->isDeleted())
            $this->fail(180, "Invalid photo");

        if(!$photo->canBeViewedBy($this->getUser()))
            $this->fail(15, "Access to photo denied");

        $comment = new Comment;
        $comment->setOwner($this->getUser()->getId());
        $comment->setModel(get_class($photo));
        $comment->setTarget($photo->getId());
        $comment->setContent($message);
        $comment->setCreated(time());
        $comment->save();

        if(!empty($attachments)) {
            $attachmentsArr = explode(",", $attachments);

            if(sizeof($attachmentsArr) > 10)
                $this->fail(50, "Error: too many attachments");
            
            foreach($attachmentsArr as $attac) {
                $attachmentType = NULL;

                if(str_contains($attac, "photo"))
                    $attachmentType = "photo";
                elseif(str_contains($attac, "video"))
                    $attachmentType = "video";
                else
                    $this->fail(205, "Unknown attachment type");

                $attachment = str_replace($attachmentType, "", $attac);

                $attachmentOwner = (int)explode("_", $attachment)[0];
                $attachmentId    = (int)end(explode("_", $attachment));

                $attacc = NULL;

                if($attachmentType == "photo") {
                    $attacc = (new PhotosRepo)->getByOwnerAndVID($attachmentOwner, $attachmentId);
                    if(!$attacc || $attacc->isDeleted())
                        $this->fail(100, "Photo does not exists");
                    if($attacc->getOwner()->getId() != $this->getUser()->getId())
                        $this->fail(43, "You do not have access to this photo");
                    
                    $comment->attach($attacc);
                } elseif($attachmentType == "video") {
                    $attacc = (new VideosRepo)->getByOwnerAndVID($attachmentOwner, $attachmentId);
                    if(!$attacc || $attacc->isDeleted())
                        $this->fail(100, "Video does not exists");
                    if($attacc->getOwner()->getId() != $this->getUser()->getId())
                        $this->fail(43, "You do not have access to this video");

                    $comment->attach($attacc);
                }
            }
        }

        return $comment->getId();
    }

    function getAll(int $owner_id, bool $extended = false, int $offset = 0, int $count = 100, bool $photo_sizes = false)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        if($owner_id < 0)
            $this->fail(4, "This method doesn't works with clubs");

        $user = (new UsersRepo)->get($owner_id);

        if(!$user)
            $this->fail(4, "Invalid user");
        
        if(!$user->getPrivacyPermission('photos.read', $this->getUser()))
            $this->fail(21, "This user chose to hide his albums.");

        $photos = array_slice(iterator_to_array((new PhotosRepo)->getEveryUserPhoto($user, 1, $count + $offset)), $offset);
        $res = [
            "items" => [],
        ];

        foreach($photos as $photo) {
            if(!$photo || $photo->isDeleted()) continue;
            $res["items"][] = $photo->toVkApiStruct($photo_sizes, $extended);
        }

        return $res;
    }

    function getComments(int $owner_id, int $photo_id, bool $need_likes = false, int $offset = 0, int $count = 100, bool $extended = false, string $fields = "")
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $photo = (new PhotosRepo)->getByOwnerAndVID($owner_id, $photo_id);
        $comms = array_slice(iterator_to_array($photo->getComments(1, $offset + $count)), $offset);

        if(!$photo || $photo->isDeleted())
            $this->fail(4, "Invalid photo");

        if(!$photo->canBeViewedBy($this->getUser()))
            $this->fail(21, "Access denied");

        $res = [
            "count" => sizeof($comms),
            "items" => []
        ];

        foreach($comms as $comment) {
            $res["items"][] = $comment->toVkApiStruct($this->getUser(), $need_likes, $extended);
            if($extended) {
                if($comment->getOwner() instanceof \openvk\Web\Models\Entities\User) {
                    $res["profiles"][] = $comment->getOwner()->toVkApiStruct();
                }
            }
        }

        return $res;
    }
}
