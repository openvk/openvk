<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use Chandler\Database\DatabaseConnection;
use openvk\Web\Models\Entities\Audio;
use openvk\Web\Models\Entities\Club;
use openvk\Web\Models\Entities\Playlist;
use openvk\Web\Models\Entities\User;
use openvk\Web\Models\Repositories\Util\EntityStream;

class Audios
{
    private $context;
    private $audios;
    private $rels;
    private $playlists;
    private $playlistImports;
    private $playlistRels;

    const ORDER_NEW     = 0;
    const ORDER_POPULAR = 1;

    const VK_ORDER_NEW     = 0;
    const VK_ORDER_LENGTH  = 1;
    const VK_ORDER_POPULAR = 2;

    function __construct()
    {
        $this->context = DatabaseConnection::i()->getContext();
        $this->audios  = $this->context->table("audios");
        $this->rels    = $this->context->table("audio_relations");

        $this->playlists       = $this->context->table("playlists");
        $this->playlistImports = $this->context->table("playlist_imports");
        $this->playlistRels    = $this->context->table("playlist_relations");
    }

    function get(int $id): ?Audio
    {
        $audio = $this->audios->get($id);
        if(!$audio)
            return NULL;

        return new Audio($audio);
    }

    function getPlaylist(int $id): ?Playlist
    {
        $playlist = $this->playlists->get($id);
        if(!$playlist)
            return NULL;

        return new Playlist($playlist);
    }

    function getByOwnerAndVID(int $owner, int $vId): ?Audio
    {
        $audio = $this->audios->where([
            "owner"      => $owner,
            "virtual_id" => $vId,
        ])->fetch();
        if(!$audio) return NULL;

        return new Audio($audio);
    }

    function getPlaylistByOwnerAndVID(int $owner, int $vId): ?Playlist
    {
        $playlist = $this->playlists->where([
            "owner" => $owner,
            "id"    => $vId,
        ])->fetch();
        if(!$playlist) return NULL;

        return new Playlist($playlist);
    }

    function getByEntityID(int $entity, int $offset = 0, ?int $limit = NULL, ?int& $deleted = nullptr): \Traversable
    {
        $limit ??= OPENVK_DEFAULT_PER_PAGE;
        $iter = $this->rels->where("entity", $entity)->limit($limit, $offset)->order("index DESC");
        foreach($iter as $rel) {
            $audio = $this->get($rel->audio);
            if(!$audio || $audio->isDeleted()) {
                $deleted++;
                continue;
            }

            yield $audio;
        }
    }

    function getPlaylistsByEntityId(int $entity, int $offset = 0, ?int $limit = NULL, ?int& $deleted = nullptr): \Traversable
    {
        $limit ??= OPENVK_DEFAULT_PER_PAGE;
        $iter    = $this->playlistImports->where("entity", $entity)->limit($limit, $offset);
        foreach($iter as $rel) {
            $playlist = $this->getPlaylist($rel->playlist);
            if(!$playlist || $playlist->isDeleted()) {
                $deleted++;
                continue;
            }

            yield $playlist;
        }
    }

    function getByUser(User $user, int $page = 1, ?int $perPage = NULL, ?int& $deleted = nullptr): \Traversable
    {
        return $this->getByEntityID($user->getId(), ($perPage * ($page - 1)), $perPage, $deleted);
    }

    function getRandomThreeAudiosByEntityId(int $id): Array
    {
        $iter = $this->rels->where("entity", $id);
        $ids = [];

        foreach($iter as $it)
            $ids[] = $it->audio;

        $shuffleSeed    = openssl_random_pseudo_bytes(6);
        $shuffleSeed    = hexdec(bin2hex($shuffleSeed));

        $ids = knuth_shuffle($ids, $shuffleSeed);
        $ids = array_slice($ids, 0, 3);
        $audios = [];

        foreach($ids as $id) {
            $audio = $this->get((int)$id);

            if(!$audio || $audio->isDeleted())
                continue;

            $audios[] = $audio;
        }

        return $audios;
    }

    function getByClub(Club $club, int $page = 1, ?int $perPage = NULL, ?int& $deleted = nullptr): \Traversable
    {
        return $this->getByEntityID($club->getId() * -1, ($perPage * ($page - 1)), $perPage, $deleted);
    }

    function getPlaylistsByUser(User $user, int $page = 1, ?int $perPage = NULL, ?int& $deleted = nullptr): \Traversable
    {
        return $this->getPlaylistsByEntityId($user->getId(), ($perPage * ($page - 1)), $perPage, $deleted);
    }

    function getPlaylistsByClub(Club $club, int $page = 1, ?int $perPage = NULL, ?int& $deleted = nullptr): \Traversable
    {
        return $this->getPlaylistsByEntityId($club->getId() * -1, ($perPage * ($page - 1)), $perPage, $deleted);
    }
    
    function getCollectionSizeByEntityId(int $id): int
    {
        return sizeof($this->rels->where("entity", $id));
    }

    function getUserCollectionSize(User $user): int
    {
        return sizeof($this->rels->where("entity", $user->getId()));
    }

    function getClubCollectionSize(Club $club): int
    {
        return sizeof($this->rels->where("entity", $club->getId() * -1));
    }

    function getUserPlaylistsCount(User $user): int
    {
        return sizeof($this->playlistImports->where("entity", $user->getId()));
    }

    function getClubPlaylistsCount(Club $club): int
    {
        return sizeof($this->playlistImports->where("entity", $club->getId() * -1));
    }

    function getByUploader(User $user): EntityStream
    {
        $search = $this->audios->where([
            "owner"   => $user->getId(),
            "deleted" => 0,
        ]);

        return new EntityStream("Audio", $search);
    }

    function getGlobal(int $order, ?string $genreId = NULL): EntityStream
    {
        $search = $this->audios->where([
            "deleted"   => 0,
            "unlisted"  => 0,
            "withdrawn" => 0,
        ])->order($order == Audios::ORDER_NEW ? "created DESC" : "listens DESC");

        if(!is_null($genreId))
            $search = $search->where("genre", $genreId);

        return new EntityStream("Audio", $search);
    }

    function search(string $query, int $sortMode = 0, bool $performerOnly = false, bool $withLyrics = false): EntityStream
    {
        $columns = $performerOnly ? "performer" : "performer, name";
        $order   = (["created", "length", "listens"][$sortMode] ?? "") . " DESC";

        $search = $this->audios->where([
            "unlisted" => 0,
            "deleted"  => 0,
        ])->where("MATCH ($columns) AGAINST (? WITH QUERY EXPANSION)", $query)->order($order);

        if($withLyrics)
            $search = $search->where("lyrics IS NOT NULL");

        return new EntityStream("Audio", $search);
    }

    function searchPlaylists(string $query): EntityStream
    {
        $search = $this->playlists->where([
            "deleted" => 0,
        ])->where("MATCH (`name`, `description`) AGAINST (? IN BOOLEAN MODE)", $query);

        return new EntityStream("Playlist", $search);
    }

    function getNew(): EntityStream
    {
        return new EntityStream("Audio", $this->audios->where("created >= " . (time() - 259200))->where(["withdrawn" => 0, "deleted" => 0, "unlisted" => 0])->order("created DESC")->limit(25));
    }

    function getPopular(): EntityStream
    {
        return new EntityStream("Audio", $this->audios->where("listens > 0")->where(["withdrawn" => 0, "deleted" => 0, "unlisted" => 0])->order("listens DESC")->limit(25));
    }

    function isAdded(int $user_id, int $audio_id): bool
    {
        return !is_null($this->rels->where([
            "entity" => $user_id,
            "audio"  => $audio_id
        ])->fetch());
    }

    function find(string $query, array $pars = [], string $sort = "id DESC", int $page = 1, ?int $perPage = NULL): \Traversable
    {
        $query  = "%$query%";
        $result = $this->audios->where([
            "unlisted" => 0,
            "deleted"  => 0,
        ]);

        $notNullParams = [];

        foreach($pars as $paramName => $paramValue)
            if($paramName != "before" && $paramName != "after" && $paramName != "only_performers")
                $paramValue != NULL ? $notNullParams+=["$paramName" => "%$paramValue%"]   : NULL;
            else
                $paramValue != NULL ? $notNullParams+=["$paramName" => "$paramValue"]     : NULL;
        
        $nnparamsCount = sizeof($notNullParams);

        if($notNullParams["only_performers"] == "1") {
            $result->where("performer LIKE ?", $query);
        } else {
            $result->where("name LIKE ? OR performer LIKE ?", $query, $query);
        }

        if($nnparamsCount > 0) {
            foreach($notNullParams as $paramName => $paramValue) {
                switch($paramName) {
                    case "before":
                        $result->where("created < ?", $paramValue);
                        break;
                    case "after":
                        $result->where("created > ?", $paramValue);
                        break;
                    case "with_lyrics":
                        $result->where("lyrics IS NOT NULL");
                        break;
                }
            }
        }

        return new Util\EntityStream("Audio", $result->order($sort));
    }

    function findPlaylists(string $query, int $page = 1, ?int $perPage = NULL): \Traversable
    {
        $query  = "%$query%";
        $result = $this->playlists->where("name LIKE ?", $query);

        return new Util\EntityStream("Playlist", $result);
    }
}
