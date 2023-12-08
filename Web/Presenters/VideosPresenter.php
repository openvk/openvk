<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Entities\Video;
use openvk\Web\Models\Repositories\{Users, Videos};
use Nette\InvalidStateException as ISE;

final class VideosPresenter extends OpenVKPresenter
{
    private $videos;
    private $users;
    protected $presenterName = "videos";

    function __construct(Videos $videos, Users $users)
    {
        $this->videos = $videos;
        $this->users  = $users;
        
        parent::__construct();
    }
    
    function renderList(int $id): void
    {
        $user = $this->users->get($id);
        if(!$user) $this->notFound();
        if(!$user->getPrivacyPermission('videos.read', $this->user->identity ?? NULL))
            $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));
        
        $this->template->user   = $user;
        $this->template->videos = $this->videos->getByUser($user, (int) ($this->queryParam("p") ?? 1));
        $this->template->count  = $this->videos->getUserVideosCount($user);
        $this->template->paginatorConf = (object) [
            "count"   => $this->template->count,
            "page"    => (int) ($this->queryParam("p") ?? 1),
            "amount"  => NULL,
            "perPage" => 7,
        ];
    }
    
    function renderView(int $owner, int $vId): void
    {
        $user = $this->users->get($owner);
        $video = $this->videos->getByOwnerAndVID($owner, $vId);

        if(!$user) $this->notFound();
        if(!$video || $video->isDeleted()) $this->notFound();
        if(!$user->getPrivacyPermission('videos.read', $this->user->identity ?? NULL))
            $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));
        
        $this->template->user     = $user;
        $this->template->video    = $this->videos->getByOwnerAndVID($owner, $vId);
        $this->template->cCount   = $this->template->video->getCommentsCount();
        $this->template->cPage    = (int) ($this->queryParam("p") ?? 1);
        $this->template->comments = iterator_to_array($this->template->video->getComments($this->template->cPage));
    }
    
    function renderUpload(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();

        if(OPENVK_ROOT_CONF['openvk']['preferences']['videos']['disableUploading'])
            $this->flashFail("err", tr("error"), tr("video_uploads_disabled"));
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            if(!empty($this->postParam("name"))) {
                $video = new Video;
                $video->setOwner($this->user->id);
                $video->setName(ovk_proc_strtr($this->postParam("name"), 61));
                $video->setDescription(ovk_proc_strtr($this->postParam("desc"), 300));
                $video->setCreated(time());
                
                try {
                    if(isset($_FILES["blob"]) && file_exists($_FILES["blob"]["tmp_name"]))
                        $video->setFile($_FILES["blob"]);
                    else if(!empty($this->postParam("link")))
                        $video->setLink($this->postParam("link"));
                    else
                        $this->flashFail("err", tr("no_video_error"), tr("no_video_description"));
                } catch(\DomainException $ex) {
                    $this->flashFail("err", tr("error_video"), tr("file_corrupted"));
                } catch(ISE $ex) {
                    $this->flashFail("err", tr("error_video"), tr("link_incorrect"));
                }
                
                $video->save();
                
                $this->redirect("/video" . $video->getPrettyId());
            } else {
                $this->flashFail("err", tr("error_video"), tr("no_name_error"));
            }
        }
    }
    
    function renderEdit(int $owner, int $vId): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $video = $this->videos->getByOwnerAndVID($owner, $vId);
        if(!$video)
            $this->notFound();
        if(is_null($this->user) || $this->user->id !== $owner)
            $this->flashFail("err", tr("access_denied_error"), tr("access_denied_error_description"));
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            $video->setName(empty($this->postParam("name")) ? NULL : $this->postParam("name"));
            $video->setDescription(empty($this->postParam("desc")) ? NULL : $this->postParam("desc"));
            $video->save();
            
            $this->flash("succ", tr("changes_saved"), tr("changes_saved_video_comment"));
            $this->redirect("/video" . $video->getPrettyId());
        } 
        
        $this->template->video = $video;
    }

    function renderRemove(int $owner, int $vid): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $video = $this->videos->getByOwnerAndVID($owner, $vid);
        if(!$video)
            $this->notFound();
        $user = $this->user->id;
        
        if(!is_null($user)) {
            if($video->getOwnerVideo() == $user) {
                $video->deleteVideo($owner, $vid);
            }
        } else {
            $this->flashFail("err", tr("cant_delete_video"), tr("cant_delete_video_comment"));
        }
        
        $this->redirect("/videos" . $owner);
    }

    function renderLike(int $owner, int $video_id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        $this->assertNoCSRF();

        $video = $this->videos->getByOwnerAndVID($owner, $video_id);
        if(!$video || $video->isDeleted() || $video->getOwner()->isDeleted()) $this->notFound();

        if(method_exists($video, "canBeViewedBy") && !$video->canBeViewedBy($this->user->identity)) {
            $this->flashFail("err", tr("error"), tr("forbidden"));
        }

        if(!is_null($this->user)) {
            $video->toggleLike($this->user->identity);
        }

        $this->returnJson(["success" => true]);
    }
}
