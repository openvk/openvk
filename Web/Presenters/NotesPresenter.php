<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Repositories\{Users, Notes};
use openvk\Web\Models\Entities\Note;

final class NotesPresenter extends OpenVKPresenter
{
    private $notes;
    protected $presenterName = "notes";

    function __construct(Notes $notes)
    {
        $this->notes = $notes;
        
        parent::__construct();
    }
    
    function renderList(int $owner): void
    {
        $user = (new Users)->get($owner);
        if(!$user) $this->notFound();
        if(!$user->getPrivacyPermission('notes.read', $this->user->identity ?? NULL))
            $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));
        
        $this->template->notes = $this->notes->getUserNotes($user, (int)($this->queryParam("p") ?? 1));
        $this->template->count = $this->notes->getUserNotesCount($user);
        $this->template->owner = $user;
        $this->template->paginatorConf = (object) [
            "count"   => $this->template->count,
            "page"    => $this->queryParam("p") ?? 1,
            "amount"  => NULL,
            "perPage" => OPENVK_DEFAULT_PER_PAGE,
        ];
    }
    
    function renderView(int $owner, int $note_id): void
    {
        $note = $this->notes->getNoteById($owner, $note_id);
        if(!$note || $note->getOwner()->getId() !== $owner || $note->isDeleted())
            $this->notFound();
        if(!$note->getOwner()->getPrivacyPermission('notes.read', $this->user->identity ?? NULL))
            $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));
        if(!$note->canBeViewedBy($this->user->identity))
            $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));
        
        $this->template->cCount   = $note->getCommentsCount();
        $this->template->cPage    = (int) ($this->queryParam("p") ?? 1);
        $this->template->comments = iterator_to_array($note->getComments($this->template->cPage));
        $this->template->note     = $note;
    }
    
    function renderPreView(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
    
        if($_SERVER["REQUEST_METHOD"] !== "POST") {
            header("HTTP/1.1 400 Bad Request");
            exit;
        }
        
        if(empty($this->postParam("html")) || empty($this->postParam("title"))) {
            header("HTTP/1.1 400 Bad Request");
            exit(tr("note_preview_empty_err"));
        }
    
        $note = new Note;
        $note->setSource($this->postParam("html"));
        
        $this->flash("info", tr("note_preview_warn"), tr("note_preview_warn_details"));
        $this->template->title = $this->postParam("title");
        $this->template->html  = $note->getText();
    }
    
    function renderCreate(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $id = $this->user->id; #TODO: when ACL'll be done, allow admins to edit users via ?GUID=(chandler guid)
        
        if(!$id)
            $this->notFound();
            
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            if(empty($this->postParam("name"))) {
                $this->flashFail("err", tr("error"), tr("error_segmentation")); 
            }

            $note = new Note;
            $note->setOwner($this->user->id);
            $note->setCreated(time());
            $note->setName($this->postParam("name"));
            $note->setSource($this->postParam("html"));
            $note->setEdited(time());
            $note->save();
            
            $this->redirect("/note" . $this->user->id . "_" . $note->getVirtualId());
        }
    }

    function renderEdit(int $owner, int $note_id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $note = $this->notes->getNoteById($owner, $note_id);

        if(!$note || $note->getOwner()->getId() !== $owner || $note->isDeleted())
            $this->notFound();
        if(is_null($this->user) || !$note->canBeModifiedBy($this->user->identity))
            $this->flashFail("err", tr("error_access_denied_short"), tr("error_access_denied"));
        $this->template->note = $note;
            
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            if(empty($this->postParam("name"))) {
                $this->flashFail("err", tr("error"), tr("error_segmentation")); 
            }

            $note->setName($this->postParam("name"));
            $note->setSource($this->postParam("html"));
            $note->setCached_Content(NULL);
            $note->setEdited(time());
            $note->save();
            
            $this->redirect("/note" . $this->user->id . "_" . $note->getVirtualId());
        }
    }
    
    function renderDelete(int $owner, int $id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        $this->assertNoCSRF();
        
        $note = $this->notes->get($id);
        if(!$note) $this->notFound();
        if($note->getOwner()->getId() . "_" . $note->getId() !== $owner . "_" . $id || $note->isDeleted()) $this->notFound();
        if(is_null($this->user) || !$note->canBeModifiedBy($this->user->identity))
            $this->flashFail("err", tr("error_access_denied_short"), tr("error_access_denied"));
        
        $name = $note->getName();
        $note->delete();
        $this->flash("succ", tr("note_is_deleted"), tr("note_x_is_now_deleted", $name));
        $this->redirect("/notes" . $this->user->id);
    }
}
