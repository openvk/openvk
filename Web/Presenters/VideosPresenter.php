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
        if(!$user) $this->notFound();
        if(!$user->getPrivacyPermission('videos.read', $this->user->identity ?? NULL))
            $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));

        if($this->videos->getByOwnerAndVID($owner, $vId)->isDeleted()) $this->notFound();
        
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
                        $this->flashFail("err", tr("no_video"), tr("no_video_desc"));
                } catch(\DomainException $ex) {
                    $this->flashFail("err", tr("error_occured"), tr("error_video_damaged_file"));
                } catch(ISE $ex) {
                    $this->flashFail("err", tr("error_occured"), tr("error_video_incorrect_link"));
                }
                
                $video->save();
                
                $this->redirect("/video" . $video->getPrettyId());
            } else {
                $this->flashFail("err", tr("error_occured"), tr("error_video_no_title"));
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
            $this->flashFail("err", tr("error_access_denied_short"), tr("error_access_denied"));
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            $video->setName(empty($this->postParam("name")) ? NULL : $this->postParam("name"));
            $video->setDescription(empty($this->postParam("desc")) ? NULL : $this->postParam("desc"));
            $video->save();
            
            $this->flash("succ", tr("changes_saved"), tr("new_data_video"));
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
            $this->flashFail("err", tr("error_deleting_video"), tr("login_please"));
        }
        
        $this->redirect("/videos" . $owner);
    }
}
