<?php declare(strict_types=1);
namespace openvk\ServiceAPI;
use openvk\Web\Models\Entities\User;
use openvk\Web\Models\Repositories\{Photos as PhotosRepo, Albums, Clubs};

class Photos implements Handler
{
    protected $user;
    protected $photos;
    
    function __construct(?User $user)
    {
        $this->user  = $user;
        $this->photos = new PhotosRepo;
    }

    function getPhotos(int $page = 1, int $album = 0, callable $resolve, callable $reject)
    {
        if($album == 0) {
            $photos = $this->photos->getEveryUserPhoto($this->user, $page, 24);
            $count  = $this->photos->getUserPhotosCount($this->user);
        } else {
            $album = (new Albums)->get($album);

            if(!$album || $album->isDeleted())
                $reject(55, "Invalid .");

            if($album->getOwner() instanceof User) {
                if($album->getOwner()->getId() != $this->user->getId())
                    $reject(555, "Access to album denied");
            } else {
                if(!$album->getOwner()->canBeModifiedBy($this->user))
                    $reject(555, "Access to album denied");
            }

            $photos = $album->getPhotos($page, 24);
            $count  = $album->size();
        }

        $arr = [
            "count"  => $count,
            "items"  => [],
        ];

        foreach($photos as $photo) {
            $res = json_decode(json_encode($photo->toVkApiStruct()), true);
            
            $arr["items"][] = $res;
        }

        $resolve($arr);
    }

    function getAlbums(int $club, callable $resolve, callable $reject)
    {
        $albumsRepo = (new Albums);
        
        $count  = $albumsRepo->getUserAlbumsCount($this->user);
        $albums = $albumsRepo->getUserAlbums($this->user, 1, $count);

        $arr = [
            "count"  => $count,
            "items"  => [],
        ];

        foreach($albums as $album) {
            $res = ["id" => $album->getId(), "name" => $album->getName()];
                
            $arr["items"][] = $res;
        }

        if($club > 0) {
            $cluber = (new Clubs)->get($club);

            if(!$cluber || !$cluber->canBeModifiedBy($this->user))
                $reject(1337, "Invalid (club), or you can't modify him");

            $clubCount  = (new Albums)->getClubAlbumsCount($cluber);
            $clubAlbums = (new Albums)->getClubAlbums($cluber, 1, $clubCount);

            foreach($clubAlbums as $albumr) {
                $res = ["id" => $albumr->getId(), "name" => $albumr->getName()];
                    
                $arr["items"][] = $res;
            }

            $arr["count"] = $arr["count"] + $clubCount;
        }

        $resolve($arr);
    }
}
