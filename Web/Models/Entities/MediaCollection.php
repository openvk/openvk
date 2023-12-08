<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use Nette\Database\Table\ActiveRow;
use openvk\Web\Util\DateTime;
use openvk\Web\Models\RowModel;
use openvk\Web\Models\Repositories\Clubs;
use openvk\Web\Models\Repositories\Users;
use openvk\Web\Models\Repositories\Util\EntityStream;
use Chandler\Database\DatabaseConnection;

abstract class MediaCollection extends RowModel
{
    protected $relTableName;
    protected $entityTableName;
    protected $entityClassName;
    protected $allowDuplicates = true;
    
    protected $specialNames = [];
    
    protected $relations;

    /**
     * Maximum amount of items Collection can have
     */
    const MAX_ITEMS = INF;

    /**
     * Maximum amount of Collections with same "owner" allowed
     */
    const MAX_COUNT = INF;
    
    function __construct(?ActiveRow $ar = NULL)
    {
        parent::__construct($ar);
        
        $this->relations = DatabaseConnection::i()->getContext()->table($this->relTableName);
    }
    
    private function entitySuitable(RowModel $entity): bool
    {
        if(($class = get_class($entity)) !== $this->entityClassName)
            throw new \UnexpectedValueException("This MediaCollection can only store '$this->entityClassName' (not '$class').");
        
        return true;
    }
    
    function getOwner(): RowModel
    {
        $oid = $this->getRecord()->owner;
        if($oid > 0)
            return (new Users)->get($oid);
        else
            return (new Clubs)->get($oid * -1);
    }
    
    function getPrettyId(): string
    {
        return $this->getRecord()->owner . "_" . $this->getRecord()->id;
    }
    
    function getName(): string
    {
        $special = $this->getRecord()->special_type;
        if($special === 0)
            return $this->getRecord()->name;
        
        $sName = $this->specialNames[$special];
        if(!$sName)
            return $this->getRecord()->name;
            
        if($sName[0] === "_")
            $sName = tr(substr($sName, 1));
        
        return $sName;
    }
    
    function getDescription(): ?string
    {
        return $this->getRecord()->description;
    }
    
    abstract function getCoverURL(): ?string;

    function fetchClassic(int $offset = 0, ?int $limit = NULL): \Traversable
    {
        $related = $this->getRecord()->related("$this->relTableName.collection")
            ->limit($limit ?? OPENVK_DEFAULT_PER_PAGE, $offset)
            ->order("media ASC");

        foreach($related as $rel) {
            $media = $rel->ref($this->entityTableName, "media");
            if(!$media)
                continue;

            yield new $this->entityClassName($media);
        }
    }

    function fetch(int $page = 1, ?int $perPage = NULL): \Traversable
    {
        $page      = max(1, $page);
        $perPage ??= OPENVK_DEFAULT_PER_PAGE;

        return $this->fetchClassic($perPage * ($page - 1), $perPage);
    }
    
    function size(): int
    {
        return sizeof($this->getRecord()->related("$this->relTableName.collection"));
    }
    
    function getCreationTime(): DateTime
    {
        return new DateTime($this->getRecord()->created);
    }
    
    function getPublicationTime(): DateTime
    {
        return $this->getCreationTime();
    }
    
    function getEditTime(): ?DateTime
    {
        $edited = $this->getRecord()->edited;
        if(is_null($edited)) return NULL;
        
        return new DateTime($edited);
    }
    
    function isCreatedBySystem(): bool
    {
        return $this->getRecord()->special_type !== 0;
    }

    function add(RowModel $entity): bool
    {
        $this->entitySuitable($entity);
        
        if(!$this->allowDuplicates)
            if($this->has($entity))
                return false;

        if(self::MAX_ITEMS != INF)
            if(sizeof($this->relations->where("collection", $this->getId())) > self::MAX_ITEMS)
                throw new \OutOfBoundsException("Collection is full");
        
        $this->relations->insert([
            "collection" => $this->getId(),
            "media"      => $entity->getId(),
        ]);
        
        return true;
    }
    
    function remove(RowModel $entity): bool
    {
        $this->entitySuitable($entity);
        
        return $this->relations->where([
            "collection" => $this->getId(),
            "media"      => $entity->getId(),
        ])->delete() > 0;
    }
    
    function has(RowModel $entity): bool
    {
        $this->entitySuitable($entity);
        
        $rel = $this->relations->where([
            "collection" => $this->getId(),
            "media"      => $entity->getId(),
        ])->fetch();
        
        return !is_null($rel);
    }

    function save(?bool $log = false): void
    {
        $thisTable = DatabaseConnection::i()->getContext()->table($this->tableName);
        if(self::MAX_COUNT != INF)
            if(isset($this->changes["owner"]))
                if(sizeof($thisTable->where("owner", $this->changes["owner"])) > self::MAX_COUNT)
                    throw new \OutOfBoundsException("Maximum amount of collections");

        if(is_null($this->getRecord()))
            if(!isset($this->changes["created"]))
                $this->stateChanges("created", time());
        else
            $this->stateChanges("edited", time());

        parent::save($log);
    }

    function delete(bool $softly = true): void
    {
        if(!$softly) {
            $this->relations->where("collection", $this->getId())
                ->delete();
        }

        parent::delete($softly);
    }

    use Traits\TOwnable;
}
