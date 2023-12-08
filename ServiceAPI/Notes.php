<?php

namespace openvk\ServiceAPI;
use openvk\Web\Models\Entities\User;
use openvk\Web\Models\Repositories\Notes as NoteRepo;

class Notes implements Handler
{
    protected $user;
    protected $notes;
    
    function __construct(?User $user)
    {
        $this->user  = $user;
        $this->notes = new NoteRepo;
    }
    
    function getNote(int $noteId, callable $resolve, callable $reject): void
    {
        $note = $this->notes->get($noteId);
        if(!$note || $note->isDeleted())
            $reject(83, "Note is gone");
        
        $noteOwner = $note->getOwner();
        assert($noteOwner instanceof User);
        if(!$noteOwner->getPrivacyPermission("notes.read", $this->user))
            $reject(160, "You don't have permission to access this note");

        if(!$note->canBeViewedBy($this->user))
            $reject(15, "Access to note denied");
        
        $resolve([
            "title"   => $note->getName(),
            "link"    => "/note" . $note->getPrettyId(),
            "html"    => $note->getText(),
            "created" => (string) $note->getPublicationTime(),
            "author"  => [
                "name" => $noteOwner->getCanonicalName(),
                "ava"  => $noteOwner->getAvatarUrl(),
                "link" => $noteOwner->getURL(),
            ],
        ]);
    }
}
