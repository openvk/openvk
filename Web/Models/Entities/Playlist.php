<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use Chandler\Database\DatabaseConnection;
use Nette\Database\Table\ActiveRow;
use openvk\Web\Models\Repositories\Audios;
use openvk\Web\Models\Repositories\Photos;
use openvk\Web\Models\RowModel;
use openvk\Web\Models\Entities\Photo;

/**
 * @method setName(string $name)
 * @method setDescription(?string $desc)
 */
class Playlist extends MediaCollection
{
    protected $tableName       = "playlists";
    protected $relTableName    = "playlist_relations";
    protected $entityTableName = "audios";
    protected $entityClassName = 'openvk\Web\Models\Entities\Audio';
    protected $allowDuplicates = false;

    private $importTable;

    const MAX_COUNT = 1000;
    const MAX_ITEMS = 10000;

    function __construct(?ActiveRow $ar = NULL)
    {
        parent::__construct($ar);

        $this->importTable = DatabaseConnection::i()->getContext()->table("playlist_imports");
    }

    function getCoverURL(string $size = "normal"): ?string
    {
        $photo = (new Photos)->get((int) $this->getRecord()->cover_photo_id);
        return is_null($photo) ? "/assets/packages/static/openvk/img/song.jpg" : $photo->getURLBySizeId($size);
    }

    function getLength(): int
    {
        return $this->getRecord()->length;
    }

    function getAudios(int $offset = 0, ?int $limit = NULL, ?int $shuffleSeed = NULL): \Traversable
    {
        if(!$shuffleSeed) {
            foreach ($this->fetchClassic($offset, $limit) as $e)
                yield $e; # No, I can't return, it will break with []

            return;
        }

        $ids = [];
        foreach($this->relations->select("media AS i")->where("collection", $this->getId()) as $rel)
            $ids[] = $rel->i;

        $ids = knuth_shuffle($ids, $shuffleSeed);
        $ids = array_slice($ids, $offset, $limit ?? OPENVK_DEFAULT_PER_PAGE);
        foreach($ids as $id)
            yield (new Audios)->get($id);
    }

    function add(RowModel $audio): bool
    {
        if($res = parent::add($audio)) {
            $this->stateChanges("length", $this->getRecord()->length + $audio->getLength());
            $this->save();
        }

        return $res;
    }

    function remove(RowModel $audio): bool
    {
        if($res = parent::remove($audio)) {
            $this->stateChanges("length", $this->getRecord()->length - $audio->getLength());
            $this->save();
        }

        return $res;
    }

    function isBookmarkedBy(RowModel $entity): bool
    {
        $id = $entity->getId();
        if($entity instanceof Club)
            $id *= -1;

        return !is_null($this->importTable->where([
            "entity"   => $id,
            "playlist" => $this->getId(),
        ])->fetch());
    }

    function bookmark(RowModel $entity): bool
    {
        if($this->isBookmarkedBy($entity))
            return false;

        $id = $entity->getId();
        if($entity instanceof Club)
            $id *= -1;

        if($this->importTable->where("entity", $id)->count() > self::MAX_COUNT)
            throw new \OutOfBoundsException("Maximum amount of playlists");

        $this->importTable->insert([
            "entity"   => $id,
            "playlist" => $this->getId(),
        ]);

        return true;
    }

    function unbookmark(RowModel $entity): bool
    {
        $id = $entity->getId();
        if($entity instanceof Club)
            $id *= -1;

        $count = $this->importTable->where([
            "entity"   => $id,
            "playlist" => $this->getId(),
        ])->delete();

        return $count > 0;
    }
    
    function getDescription(): ?string
    {
        return $this->getRecord()->description;
    }

    function getDescriptionHTML(): ?string
    {
        return htmlspecialchars($this->getRecord()->description, ENT_DISALLOWED | ENT_XHTML);
    }

    function getListens()
    {
        return $this->getRecord()->listens;
    }

    function toVkApiStruct(?User $user = NULL): object
    {
        $oid = $this->getOwner()->getId();
        if($this->getOwner() instanceof Club)
            $oid *= -1;

        return (object) [
            "id"          => $this->getId(),
            "owner_id"    => $oid,
            "title"       => $this->getName(),
            "description" => $this->getDescription(),
            "size"        => $this->size(),
            "length"      => $this->getLength(),
            "created"     => $this->getCreationTime()->timestamp(),
            "modified"    => $this->getEditTime() ? $this->getEditTime()->timestamp() : NULL,
            "accessible"  => $this->canBeViewedBy($user),
            "editable"    => $this->canBeModifiedBy($user),
            "bookmarked"  => $this->isBookmarkedBy($user),
            "listens"     => $this->getListens(),
            "cover_url"   => $this->getCoverURL(),
        ];
    }

    function setLength(): void
    {
        throw new \LogicException("Can't set length of playlist manually");
    }

    function resetLength(): bool
    {
        $this->stateChanges("length", 0);

        return true;
    }

    function delete(bool $softly = true): void
    {
        $ctx = DatabaseConnection::i()->getContext();
        $ctx->table("playlist_imports")->where("playlist", $this->getId())
            ->delete();

        parent::delete($softly);
    }

    function hasAudio(Audio $audio): bool
    {
        $ctx = DatabaseConnection::i()->getContext();
        return !is_null($ctx->table("playlist_relations")->where([
            "collection" => $this->getId(),
            "media"      => $audio->getId()
        ])->fetch());
    }

    function getCoverPhotoId(): ?int
    {
        return $this->getRecord()->cover_photo_id;
    }

    function canBeModifiedBy(User $user): bool
    {
        if(!$user)
            return false;

        if($this->getOwner() instanceof User)
            return $user->getId() == $this->getOwner()->getId();
        else
            return $this->getOwner()->canBeModifiedBy($user);
    }

    function getLengthInMinutes(): int
    {
        return (int)round($this->getLength() / 60, PHP_ROUND_HALF_DOWN);
    }

    function fastMakeCover(int $owner, array $file)
    {
        $cover = new Photo;
        $cover->setOwner($owner);
        $cover->setDescription("Playlist cover image");
        $cover->setFile($file);
        $cover->setCreated(time());
        $cover->save();

        $this->setCover_photo_id($cover->getId());

        return $cover;
    }

    function getURL(): string
    {
        return "/playlist" . $this->getOwner()->getRealId() . "_" . $this->getId();
    }

    function incrementListens()
    {
        $this->stateChanges("listens", ($this->getListens() + 1));
    }

    function getMetaDescription(): string
    {
        $length = $this->getLengthInMinutes();

        $props = [];
        $props[] = tr("audios_count", $this->size());
        $props[] = "<span id='listensCount'>" . tr("listens_count", $this->getListens()) . "</span>";
        if($length > 0) $props[] = tr("minutes_count", $length);
        $props[] = tr("created_playlist") . " " . $this->getPublicationTime();
        # if($this->getEditTime()) $props[] = tr("updated_playlist") . " " . $this->getEditTime();
        
        return implode(" • ", $props);
    }
}
