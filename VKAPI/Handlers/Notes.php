<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use openvk\Web\Models\Repositories\Notes as NotesRepo;
use openvk\Web\Models\Repositories\Users as UsersRepo;
use openvk\Web\Models\Repositories\Comments as CommentsRepo;
use openvk\Web\Models\Repositories\Photos as PhotosRepo;
use openvk\Web\Models\Repositories\Videos as VideosRepo;
use openvk\Web\Models\Entities\{Note, Comment};

final class Notes extends VKAPIRequestHandler
{
    function add(string $title, string $text, int $privacy = 0, int $comment_privacy = 0, string $privacy_view  = "", string $privacy_comment  = "")
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $note = new Note;
        $note->setOwner($this->getUser()->getId());
        $note->setCreated(time());
        $note->setName($title);
        $note->setSource($text);
        $note->setEdited(time());
        $note->save();

        return $note->getVirtualId();
    }

    function createComment(string $note_id, int $owner_id, string $message, int $reply_to = 0, string $attachments = "")
    {
        $this->requireUser();
        $this->willExecuteWriteAction();
        $note = (new NotesRepo)->getNoteById((int)$owner_id, (int)$note_id);

        if(!$note)
            $this->fail(180, "Note not found");
        
        if($note->isDeleted())
            $this->fail(189, "Note is deleted");
        
        if($note->getOwner()->isDeleted())
            $this->fail(403, "Owner is deleted");

        if(!$note->canBeViewedBy($this->getUser()))
            $this->fail(15, "Access denied");
        
        if(!$note->getOwner()->getPrivacyPermission('notes.read', $this->getUser()))
            $this->fail(43, "No access");

        if(empty($message) && empty($attachments))
            $this->fail(100, "Required parameter 'message' missing.");

        $comment = new Comment;
        $comment->setOwner($this->getUser()->getId());
        $comment->setModel(get_class($note));
        $comment->setTarget($note->getId());
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

    function delete(string $note_id)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $note = (new NotesRepo)->get((int)$note_id);

        if(!$note)
            $this->fail(180, "Note not found");
        
        if(!$note->canBeModifiedBy($this->getUser()))
            $this->fail(15, "Access to note denied");
        
        $note->delete();

        return 1;
    }

    function edit(string $note_id, string $title = "", string $text = "", int $privacy = 0, int $comment_privacy = 0, string $privacy_view  = "", string $privacy_comment  = "")
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $note = (new NotesRepo)->getNoteById($this->getUser()->getId(), (int)$note_id);

        if(!$note)
            $this->fail(180, "Note not found");
        
        if($note->isDeleted())
            $this->fail(189, "Note is deleted");

        if(!$note->canBeModifiedBy($this->getUser()))
            $this->fail(403, "No access");

        !empty($title) ? $note->setName($title) : NULL;
        !empty($text)  ? $note->setSource($text) : NULL;

        $note->setCached_Content(NULL);
        $note->setEdited(time());
        $note->save();

        return 1;
    }

    function get(int $user_id, string $note_ids = "", int $offset = 0, int $count = 10, int $sort = 0)
    {
        $this->requireUser();
        $user = (new UsersRepo)->get($user_id);

        if(!$user || $user->isDeleted())
            $this->fail(15, "Invalid user");
        
        if(!$user->getPrivacyPermission('notes.read', $this->getUser()))
            $this->fail(15, "Access denied: this user chose to hide his notes");

        if(!$user->canBeViewedBy($this->getUser()))
            $this->fail(15, "Access denied");
        
        if(empty($note_ids)) {
            $notes = array_slice(iterator_to_array((new NotesRepo)->getUserNotes($user, 1, $count + $offset, $sort == 0 ? "ASC" : "DESC")), $offset);
            $nodez = (object) [
                "count" => (new NotesRepo)->getUserNotesCount((new UsersRepo)->get($user_id)), 
                "notes" => []
            ];
    
            foreach($notes as $note) {
                if($note->isDeleted()) continue;
                
                $nodez->notes[] = $note->toVkApiStruct();
            }
        } else {
            $notes = explode(',', $note_ids);

            foreach($notes as $note)
            {
                $id    = explode("_", $note);
    
                $items = [];
    
                $note = (new NotesRepo)->getNoteById((int)$id[0], (int)$id[1]);
                if($note && !$note->isDeleted()) {
                    $nodez->notes[] = $note->toVkApiStruct();
                }
            }
        }

        return $nodez;
    }

    function getById(int $note_id, int $owner_id, bool $need_wiki = false)
    {
        $this->requireUser();

        $note = (new NotesRepo)->getNoteById($owner_id, $note_id);

        if(!$note)
            $this->fail(180, "Note not found");
        
        if($note->isDeleted())
            $this->fail(189, "Note is deleted");

        if(!$note->getOwner() || $note->getOwner()->isDeleted())
            $this->fail(177, "Owner does not exists");

        if(!$note->getOwner()->getPrivacyPermission('notes.read', $this->getUser()))
            $this->fail(40, "Access denied: this user chose to hide his notes");

        if(!$note->canBeViewedBy($this->getUser()))
            $this->fail(15, "Access to note denied");

        return $note->toVkApiStruct();
    }

    function getComments(int $note_id, int $owner_id, int $sort = 1, int $offset = 0, int $count = 100)
    {
        $this->requireUser();

        $note = (new NotesRepo)->getNoteById($owner_id, $note_id);

        if(!$note)
            $this->fail(180, "Note not found");
        
        if($note->isDeleted())
            $this->fail(189, "Note is deleted");
        
        if(!$note->getOwner())
            $this->fail(177, "Owner does not exists");

        if(!$note->getOwner()->getPrivacyPermission('notes.read', $this->getUser()))
            $this->fail(14, "No access");

        if(!$note->canBeViewedBy($this->getUser()))
            $this->fail(15, "Access to note denied");
        
        $arr = (object) [
            "count" => $note->getCommentsCount(), 
            "comments" => []];
        $comments = array_slice(iterator_to_array($note->getComments(1, $count + $offset)), $offset);
        
        foreach($comments as $comment) {
            $arr->comments[] = $comment->toVkApiStruct($this->getUser(), false, false, $note);
        }

        return $arr;
    }

    function getFriendsNotes(int $offset = 0, int $count = 0)
    {
        $this->fail(501, "Not implemented");
    }

    function restoreComment(int $comment_id = 0, int $owner_id = 0)
    {
        $this->fail(501, "Not implemented");
    }
}
