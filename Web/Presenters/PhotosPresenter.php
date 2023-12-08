<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Entities\{Club, Photo, Album, User};
use openvk\Web\Models\Repositories\{Photos, Albums, Users, Clubs};
use Nette\InvalidStateException as ISE;

final class PhotosPresenter extends OpenVKPresenter
{
    private $users;
    private $photos;
    private $albums;
    protected $presenterName = "photos";

    function __construct(Photos $photos, Albums $albums, Users $users)
    {
        $this->users  = $users;
        $this->photos = $photos;
        $this->albums = $albums;
        
        parent::__construct();
    }
    
    function renderAlbumList(int $owner): void
    {
        if($owner > 0) {
            $user = $this->users->get($owner);
            if(!$user) $this->notFound();
            if (!$user->getPrivacyPermission('photos.read', $this->user->identity ?? NULL))
                $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));
            $this->template->albums  = $this->albums->getUserAlbums($user, (int)($this->queryParam("p") ?? 1));
            $this->template->count   = $this->albums->getUserAlbumsCount($user);
            $this->template->owner   = $user;
            $this->template->canEdit = false;
            if(!is_null($this->user))
                $this->template->canEdit = $this->user->id === $user->getId();
        } else {
            $club = (new Clubs)->get(abs($owner));
            if(!$club) $this->notFound();
            $this->template->albums  = $this->albums->getClubAlbums($club, (int)($this->queryParam("p") ?? 1));
            $this->template->count   = $this->albums->getClubAlbumsCount($club);
            $this->template->owner   = $club;
            $this->template->canEdit = false;
            if(!is_null($this->user))
                $this->template->canEdit = $club->canBeModifiedBy($this->user->identity);
        }
        
        $this->template->paginatorConf = (object) [
            "count"   => $this->template->count,
            "page"    => (int)($this->queryParam("p") ?? 1),
            "amount"  => NULL,
            "perPage" => OPENVK_DEFAULT_PER_PAGE,
        ];
    }
    
    function renderCreateAlbum(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        if(!is_null($gpid = $this->queryParam("gpid"))) {
            $club = (new Clubs)->get((int) $gpid);
            if(!$club->canBeModifiedBy($this->user->identity))
                $this->notFound();
            
            $this->template->club = $club;
        }
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            if(empty($this->postParam("name")) || mb_strlen(trim($this->postParam("name"))) === 0)
                $this->flashFail("err", tr("error"), tr("error_segmentation")); 
            else if(strlen($this->postParam("name")) > 36)
                $this->flashFail("err", tr("error"), tr("error_data_too_big", "name", 36, "bytes")); 

            $album = new Album;
            $album->setOwner(isset($club) ? $club->getId() * -1 : $this->user->id);
            $album->setName($this->postParam("name"));
            $album->setDescription($this->postParam("desc"));
            $album->setCreated(time());
            $album->save();
            
            if(isset($club))
                $this->redirect("/album-" . $album->getOwner()->getId() . "_" . $album->getId());
            else
                $this->redirect("/album" . $album->getOwner()->getId() . "_" . $album->getId());
        }
    }
    
    function renderEditAlbum(int $owner, int $id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $album = $this->albums->get($id);
        if(!$album) $this->notFound();
        if($album->getPrettyId() !== $owner . "_" . $id || $album->isDeleted()) $this->notFound();
        if(is_null($this->user) || !$album->canBeModifiedBy($this->user->identity) || $album->isDeleted())
            $this->flashFail("err", tr("error_access_denied_short"), tr("error_access_denied"));
        $this->template->album = $album;
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            if(strlen($this->postParam("name")) > 36)
                $this->flashFail("err", tr("error"), tr("error_data_too_big", "name", 36, "bytes"));
            
            $album->setName((empty($this->postParam("name")) || mb_strlen(trim($this->postParam("name"))) === 0) ? $album->getName() : $this->postParam("name"));
            $album->setDescription(empty($this->postParam("desc")) ? NULL : $this->postParam("desc"));
            $album->setEdited(time());
            $album->save();
            
            $this->flash("succ", tr("changes_saved"), tr("new_data_accepted"));
        }
    }
    
    function renderDeleteAlbum(int $owner, int $id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        $this->assertNoCSRF();
        
        $album = $this->albums->get($id);
        if(!$album) $this->notFound();
        if($album->getPrettyId() !== $owner . "_" . $id || $album->isDeleted()) $this->notFound();
        if(is_null($this->user) || !$album->canBeModifiedBy($this->user->identity))
            $this->flashFail("err", tr("error_access_denied_short"), tr("error_access_denied"));
        
        $name  = $album->getName();
        $owner = $album->getOwner();
        $album->delete();

        $this->flash("succ", tr("album_is_deleted"), tr("album_x_is_deleted", $name));
        $this->redirect("/albums" . ($owner instanceof Club ? "-" : "") . $owner->getId());
    }
    
    function renderAlbum(int $owner, int $id): void
    {
        $album = $this->albums->get($id);
        if(!$album) $this->notFound();
        if($album->getPrettyId() !== $owner . "_" . $id || $album->isDeleted())
            $this->notFound();

        if(!$album->canBeViewedBy($this->user->identity))
            $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));
        
        if($owner > 0 /* bc we currently don't have perms for clubs */) {
            $ownerObject = (new Users)->get($owner);
            if(!$ownerObject->getPrivacyPermission('photos.read', $this->user->identity ?? NULL))
                $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));
        }
        
        $this->template->album  = $album;
        $this->template->photos = iterator_to_array( $album->getPhotos( (int) ($this->queryParam("p") ?? 1), 20) );
        $this->template->paginatorConf = (object) [
            "count"   => $album->getPhotosCount(),
            "page"    => (int)($this->queryParam("p") ?? 1),
            "amount"  => sizeof($this->template->photos),
            "perPage" => 20,
            "atBottom" => true
        ];
    }
    
    function renderPhoto(int $ownerId, int $photoId): void
    {
        $photo = $this->photos->getByOwnerAndVID($ownerId, $photoId);
        if(!$photo || $photo->isDeleted()) $this->notFound();
        if(!$photo->canBeViewedBy($this->user->identity))
            $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));
        if(!is_null($this->queryParam("from"))) {
            if(preg_match("%^album([0-9]++)$%", $this->queryParam("from"), $matches) === 1) {
                $album = $this->albums->get((int) $matches[1]);
                if($album)
                    if($album->hasPhoto($photo) && !$album->isDeleted())
                        $this->template->album = $album;
            }
        }
        
        $this->template->photo    = $photo;
        $this->template->cCount   = $photo->getCommentsCount();
        $this->template->cPage    = (int) ($this->queryParam("p") ?? 1);
        $this->template->comments = iterator_to_array($photo->getComments($this->template->cPage));
    }
    
    function renderAbsolutePhoto($id): void
    {
        $id    = (int) base_convert((string) $id, 32, 10);
        $photo = $this->photos->get($id);
        if(!$photo || $photo->isDeleted())
            $this->notFound();
        
        $this->template->_template = "Photos/Photo.xml";
        $this->renderPhoto($photo->getOwner(true)->getId(), $photo->getVirtualId());
    }
    
    function renderThumbnail($id, $size): void
    {
        $photo = $this->photos->get($id);
        if(!$photo || $photo->isDeleted())
            $this->notFound();
        
        if(!$photo->forceSize($size))
            chandler_http_panic(588, "Gone", "This thumbnail cannot be generated due to server misconfiguration");
        
        $this->redirect($photo->getURLBySizeId($size), 8);
    }
    
    function renderEditPhoto(int $ownerId, int $photoId): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $photo = $this->photos->getByOwnerAndVID($ownerId, $photoId);
        if(!$photo) $this->notFound();
        if(is_null($this->user) || $this->user->id != $ownerId)
            $this->flashFail("err", tr("error_access_denied_short"), tr("error_access_denied"));
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            $photo->setDescription(empty($this->postParam("desc")) ? NULL : $this->postParam("desc"));
            $photo->save();
            
            $this->flash("succ", tr("changes_saved"), tr("new_description_will_appear"));
            $this->redirect("/photo" . $photo->getPrettyId());
        } 
        
        $this->template->photo = $photo;
    }
    
    function renderUploadPhoto(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction(true);

        if(is_null($this->queryParam("album"))) {
            $album = $this->albums->getUserWallAlbum($this->user->identity);
        } else {
            [$owner, $id] = explode("_", $this->queryParam("album"));
            $album = $this->albums->get((int) $id);
        }

        if(!$album)
            $this->flashFail("err", tr("error"), tr("error_adding_to_deleted"), 500, true);

        # Для быстрой загрузки фоток из пикера фотографий нужен альбом, но юзер не может загружать фото
        # в системные альбомы, так что так.
        if(is_null($this->user) || !is_null($this->queryParam("album")) && !$album->canBeModifiedBy($this->user->identity))
            $this->flashFail("err", tr("error_access_denied_short"), tr("error_access_denied"), 500, true);
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            if($this->queryParam("act") == "finish") {
                $result = json_decode($this->postParam("photos"), true);
                
                foreach($result as $photoId => $description) {
                    $phot = $this->photos->get($photoId);

                    if(!$phot || $phot->isDeleted() || $phot->getOwner()->getId() != $this->user->id)
                        continue;
                    
                    if(iconv_strlen($description) > 255)
                        $this->flashFail("err", tr("error"), tr("description_too_long"), 500, true);

                    $phot->setDescription($description);
                    $phot->save();

                    $album = $phot->getAlbum();
                }

                $this->returnJson(["success" => true,
                                    "album"  => $album->getId(),
                                    "owner"  => $album->getOwner() instanceof User ? $album->getOwner()->getId() : $album->getOwner()->getId() * -1]);
            }

            if(!isset($_FILES))
                $this->flashFail("err", tr("no_photo"), tr("select_file"), 500, true);
            
            $photos = [];
            if((int)$this->postParam("count") > 10)
                $this->flashFail("err", tr("no_photo"), "ты еблан", 500, true);

            for($i = 0; $i < $this->postParam("count"); $i++) {
                try {
                    $photo = new Photo;
                    $photo->setOwner($this->user->id);
                    $photo->setDescription("");
                    $photo->setFile($_FILES["photo_".$i]);
                    $photo->setCreated(time());
                    $photo->save();

                    $photos[] = [
                        "url"   => $photo->getURLBySizeId("tiny"),
                        "id"    => $photo->getId(),
                        "vid"   => $photo->getVirtualId(),
                        "owner" => $photo->getOwner()->getId(),
                        "link"  => $photo->getURL()
                    ];
                } catch(ISE $ex) {
                    $name = $album->getName();
                    $this->flashFail("err", "Неизвестная ошибка", "Не удалось сохранить фотографию в $name.", 500, true);
                }

                $album->addPhoto($photo);
                $album->setEdited(time());
                $album->save();
            }

            $this->returnJson(["success" => true,
                "photos" => $photos]);
        } else {
            $this->template->album = $album;
        }
    }
    
    function renderUnlinkPhoto(int $owner, int $albumId, int $photoId): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $album = $this->albums->get($albumId);
        $photo = $this->photos->get($photoId);
        if(!$album || !$photo) $this->notFound();
        if(!$album->hasPhoto($photo)) $this->notFound();
        if(is_null($this->user) || !$album->canBeModifiedBy($this->user->identity))
            $this->flashFail("err", tr("error_access_denied_short"), tr("error_access_denied"));
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            $this->assertNoCSRF();
            $album->removePhoto($photo);
            $album->setEdited(time());
            $album->save();
            
            $this->flash("succ", tr("photo_is_deleted"), tr("photo_is_deleted_desc"));
            $this->redirect("/album" . $album->getPrettyId());
        }
    }
    
    function renderDeletePhoto(int $ownerId, int $photoId): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction($_SERVER["REQUEST_METHOD"] === "POST");
        $this->assertNoCSRF();
        
        $photo = $this->photos->getByOwnerAndVID($ownerId, $photoId);
        if(!$photo) $this->notFound();
        if(is_null($this->user) || $this->user->id != $ownerId)
            $this->flashFail("err", tr("error_access_denied_short"), tr("error_access_denied"));

        if(!is_null($album = $photo->getAlbum()))
            $redirect = $album->getOwner() instanceof User ? "/id0" : "/club" . $ownerId;
        else
            $redirect = "/id0";

        $photo->isolate();
        $photo->delete();
        
        if($_SERVER["REQUEST_METHOD"] === "POST")
            $this->returnJson(["success" => true]);

        $this->flash("succ", tr("photo_is_deleted"), tr("photo_is_deleted_desc"));
        $this->redirect($redirect);
    }
}
