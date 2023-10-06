<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Models\Repositories\Clubs;
use openvk\Web\Models\RowModel;
use openvk\Web\Models\Entities\{Note};

class Comment extends Post
{
    protected $tableName = "comments";
    protected $upperNodeReferenceColumnName = "owner";
    
    function getPrettyId(): string
    {
        return (string)$this->getRecord()->id;
    }
    
    function getVirtualId(): int
    {
        return 0;
    }
    
    function getTarget(): ?Postable
    {
        $entityClassName = $this->getRecord()->model;
        $repoClassName   = str_replace("Entities", "Repositories", $entityClassName) . "s";
        $entity          = (new $repoClassName)->get($this->getRecord()->target);
        
        return $entity;
    }

    /**
     * May return fake owner (group), if flags are [1, (*)]
     * 
     * @param bool $honourFlags - check flags
     */
    function getOwner(bool $honourFlags = true, bool $real = false): RowModel
    {
        if($honourFlags && $this->isPostedOnBehalfOfGroup()) {
            if($this->getTarget() instanceof Post)
                return (new Clubs)->get(abs($this->getTarget()->getTargetWall()));

            if($this->getTarget() instanceof Topic)
                return $this->getTarget()->getClub();
        }

        return parent::getOwner($honourFlags, $real);
    }

    function canBeDeletedBy(User $user): bool
    {
        return $this->getOwner()->getId() == $user->getId() ||
               $this->getTarget()->getOwner()->getId() == $user->getId() ||
               $this->getTarget() instanceof Post && $this->getTarget()->getTargetWall() < 0 && (new Clubs)->get(abs($this->getTarget()->getTargetWall()))->canBeModifiedBy($user) ||
               $this->getTarget() instanceof Topic && $this->getTarget()->canBeModifiedBy($user);
    }

    function toVkApiStruct(?User $user = NULL, bool $need_likes = false, bool $extended = false, ?Note $note = NULL): object
    {
        $res = (object) [];

        $res->id            = $this->getId();
        $res->from_id       = $this->getOwner()->getId();
        $res->date          = $this->getPublicationTime()->timestamp();
        $res->text          = $this->getText(false);
        $res->attachments   = [];
        $res->parents_stack = [];
        
        if(!is_null($note)) {
            $res->uid       = $this->getOwner()->getId();
            $res->nid       = $note->getId();
            $res->oid       = $note->getOwner()->getId();
        }

        foreach($this->getChildren() as $attachment) {
            if($attachment->isDeleted())
                continue;
                
            $res->attachments[] = $attachment->toVkApiStruct();
        }

        if($need_likes) {
            $res->count      = $this->getLikesCount();
            $res->user_likes = (int)$this->hasLikeFrom($user);
            $res->can_like   = 1;
        }
        return $res;
    }

    function getURL(): string
    {
        return "/wall" . $this->getTarget()->getPrettyId() . "#_comment" . $this->getId();
    }

    function canBeEditedBy(?User $user = NULL): bool
    {
        if(!$user)
            return false;
        
        return $user->getId() == $this->getOwner(false)->getId();
    }
}
