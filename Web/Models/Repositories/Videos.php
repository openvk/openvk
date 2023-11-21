<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use openvk\Web\Models\Entities\{User, Club};
use openvk\Web\Models\Entities\Video;
use Chandler\Database\DatabaseConnection;

class Videos
{
    private $context;
    private $videos;
    private $rels;
    
    function __construct()
    {
        $this->context = DatabaseConnection::i()->getContext();
        $this->videos  = $this->context->table("videos");
        $this->rels    = $this->context->table("video_relations");
    }
    
    function get(int $id): ?Video
    {
        $videos = $this->videos->get($id);
        if(!$videos) return NULL;
        
        return new Video($videos);
    }
    
    function getByOwnerAndVID(int $owner, int $vId): ?Video
    {
        $videos = $this->videos->where([
            "owner"      => $owner,
            "virtual_id" => $vId,
        ])->fetch();
        if(!$videos) return NULL;
        
        return new Video($videos);
    }

    function getByEntityId(int $entity, int $offset = 0, ?int $limit = NULL): \Traversable
    {
        $limit ??= OPENVK_DEFAULT_PER_PAGE;
        $iter = $this->rels->where("entity", $entity)->limit($limit, $offset)->order("id DESC");
        foreach($iter as $rel) {
            $vid = $this->get($rel->video);
            if(!$vid || $vid->isDeleted()) {
                continue;
            }

            yield $vid;
        }
    }

    function getVideosCountByEntityId(int $id)
    {
        return sizeof($this->rels->where("entity", $id));
    }
    
    function getByUser(User $user, int $page = 1, ?int $perPage = NULL): \Traversable
    {
        return $this->getByEntityId($user->getId(), ($perPage * ($page - 1)), $perPage);
    }

    function getByClub(Club $club, int $page = 1, ?int $perPage = NULL): \Traversable
    {
        return $this->getByEntityId($club->getRealId(), ($perPage * ($page - 1)), $perPage);
    }
    
    function getUserVideosCount(User $user): int
    {
        return sizeof($this->rels->where("entity", $user->getId()));
    }

    function getRandomTwoVideosByEntityId(int $id): Array
    {
        $iter = $this->rels->where("entity", $id);
        $ids = [];

        foreach($iter as $it)
            $ids[] = $it->video;

        $shuffleSeed    = openssl_random_pseudo_bytes(6);
        $shuffleSeed    = hexdec(bin2hex($shuffleSeed));

        $ids = knuth_shuffle($ids, $shuffleSeed);
        $ids = array_slice($ids, 0, 3);
        $videos = [];

        foreach($ids as $id) {
            $video = $this->get((int)$id);

            if(!$video || $video->isDeleted())
                continue;

            $videos[] = $video;
        }

        return $videos;
    }

    function find(string $query = "", array $pars = [], string $sort = "id"): Util\EntityStream
    {
        $query  = "%$query%";

        $notNullParams = [];

        foreach($pars as $paramName => $paramValue)
            if($paramName != "before" && $paramName != "after")
                $paramValue != NULL ? $notNullParams+=["$paramName" => "%$paramValue%"]   : NULL;
            else
                $paramValue != NULL ? $notNullParams+=["$paramName" => "$paramValue"]     : NULL;
        
        $result = $this->videos->where("CONCAT_WS(' ', name, description) LIKE ?", $query)->where("deleted", 0);
        $nnparamsCount = sizeof($notNullParams);

        if($nnparamsCount > 0) {
            foreach($notNullParams as $paramName => $paramValue) {
                switch($paramName) {
                    case "before":
                        $result->where("created < ?", $paramValue);
                        break;
                    case "after":
                        $result->where("created > ?", $paramValue);
                        break;
                }
            }
        }


        return new Util\EntityStream("Video", $result->order("$sort"));
    }

    function getLastVideo(User $user)
    {
        $video = $this->videos->where("owner", $user->getId())->where(["deleted" => 0, "unlisted" => 0])->order("id DESC")->fetch();

        return new Video($video);
    }
}
