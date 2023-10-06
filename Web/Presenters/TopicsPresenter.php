<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Entities\{Topic, Club, Comment, Photo, Video};
use openvk\Web\Models\Repositories\{Topics, Clubs};

final class TopicsPresenter extends OpenVKPresenter
{
    private $topics;
    private $clubs;
    protected $presenterName = "topics";

    function __construct(Topics $topics, Clubs $clubs)
    {
        $this->topics = $topics;
        $this->clubs  = $clubs;
        
        parent::__construct();
    }

    function renderBoard(int $id): void
    {
        $this->assertUserLoggedIn();

        $club = $this->clubs->get($id);
        if(!$club)
            $this->notFound();

        $this->template->club = $club;
        $page = (int) ($this->queryParam("p") ?? 1);

        $query = $this->queryParam("query");
        if($query) {
            $results = $this->topics->find($club, $query);
            $this->template->topics = $results->page($page);
            $this->template->count  = $results->size();
        } else {
            $this->template->topics = $this->topics->getClubTopics($club, $page);
            $this->template->count  = $this->topics->getClubTopicsCount($club);
        }

        $this->template->paginatorConf = (object) [
            "count"   => $this->template->count,
            "page"    => $page,
            "amount"  => NULL,
            "perPage" => OPENVK_DEFAULT_PER_PAGE,
        ];
    }

    function renderTopic(int $clubId, int $topicId): void
    {
        $this->assertUserLoggedIn();

        $topic = $this->topics->getTopicById($clubId, $topicId);
        if(!$topic)
            $this->notFound();

        $this->template->topic    = $topic;
        $this->template->club     = $topic->getClub();
        $this->template->count    = $topic->getCommentsCount();
        $this->template->page     = (int) ($this->queryParam("p") ?? 1);
        $this->template->comments = iterator_to_array($topic->getComments($this->template->page));
    }

    function renderCreate(int $clubId): void
    {
        $this->assertUserLoggedIn();

        $club = $this->clubs->get($clubId);
        if(!$club)
            $this->notFound();

        if(!$club->isEveryoneCanCreateTopics() && !$club->canBeModifiedBy($this->user->identity))
            $this->notFound();

        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            $this->willExecuteWriteAction();
            $title = $this->postParam("title");

            if(!$title)
                $this->flashFail("err", tr("failed_to_create_topic"), tr("no_title_specified"));

            $flags = 0;
            if($this->postParam("as_group") === "on" && $club->canBeModifiedBy($this->user->identity))
                $flags |= 0b10000000;

            if($_FILES["_vid_attachment"] && OPENVK_ROOT_CONF['openvk']['preferences']['videos']['disableUploading'])
                $this->flashFail("err", tr("error"), "Video uploads are disabled by the system administrator.");

            $topic = new Topic;
            $topic->setGroup($club->getId());
            $topic->setOwner($this->user->id);
            $topic->setTitle(ovk_proc_strtr($title, 127));
            $topic->setCreated(time());
            $topic->setFlags($flags);
            $topic->save();
            
            # TODO move to trait
            try {
                $photo = NULL;
                $video = NULL;
                if($_FILES["_pic_attachment"]["error"] === UPLOAD_ERR_OK) {
                    $album = NULL;
                    if($wall > 0 && $wall === $this->user->id)
                        $album = (new Albums)->getUserWallAlbum($wallOwner);
                    
                    $photo = Photo::fastMake($this->user->id, $this->postParam("text"), $_FILES["_pic_attachment"], $album);
                }
                
                if($_FILES["_vid_attachment"]["error"] === UPLOAD_ERR_OK) {
                    $video = Video::fastMake($this->user->id, $_FILES["_vid_attachment"]["name"], $this->postParam("text"), $_FILES["_vid_attachment"]);
                }
            } catch(ISE $ex) {
                $this->flash("err", tr("error_when_publishing_comment"), tr("error_comment_file_too_big"));
                $this->redirect("/topic" . $topic->getPrettyId());
            }
            
            if(!empty($this->postParam("text")) || $photo || $video) {
                try {
                    $comment = new Comment;
                    $comment->setOwner($this->user->id);
                    $comment->setModel(get_class($topic));
                    $comment->setTarget($topic->getId());
                    $comment->setContent($this->postParam("text"));
                    $comment->setCreated(time());
                    $comment->setFlags($flags);
                    $comment->save();
                } catch (\LengthException $ex) {
                    $this->flash("err", tr("error_when_publishing_comment"), tr("error_comment_too_big"));
                    $this->redirect("/topic" . $topic->getPrettyId());
                }
                
                if(!is_null($photo))
                    $comment->attach($photo);
                
                if(!is_null($video))
                    $comment->attach($video);
            }

            $this->redirect("/topic" . $topic->getPrettyId());
        }

        $this->template->club = $club;
        $this->template->graffiti = (bool) ovkGetQuirk("comments.allow-graffiti");
    }

    function renderEdit(int $clubId, int $topicId): void
    {
        $this->assertUserLoggedIn();

        $topic = $this->topics->getTopicById($clubId, $topicId);
        if(!$topic)
            $this->notFound();

        if(!$topic->canBeModifiedBy($this->user->identity))
            $this->notFound();

        if($_SERVER["REQUEST_METHOD"] === "POST") {
            $this->willExecuteWriteAction();
            $title = $this->postParam("title");

            if(!$title)
                $this->flashFail("err", tr("failed_to_change_topic"), tr("no_title_specified"));

            $topic->setTitle(ovk_proc_strtr($title, 127));
            $topic->setClosed(empty($this->postParam("close")) ? 0 : 1);

            if($topic->getClub()->canBeModifiedBy($this->user->identity))
                $topic->setPinned(empty($this->postParam("pin")) ? 0 : 1);

            $topic->save();
            
            $this->flash("succ", tr("changes_saved"), tr("topic_changes_saved_comment"));
            $this->redirect("/topic" . $topic->getPrettyId());
        }

        $this->template->topic = $topic;
        $this->template->club  = $topic->getClub();
    }

    function renderDelete(int $clubId, int $topicId): void
    {
        $this->assertUserLoggedIn();
        $this->assertNoCSRF();

        $topic = $this->topics->getTopicById($clubId, $topicId);
        if(!$topic)
            $this->notFound();

        if(!$topic->canBeModifiedBy($this->user->identity))
            $this->notFound();

        $this->willExecuteWriteAction();
        $topic->deleteTopic();
        
        $this->redirect("/board" . $topic->getClub()->getId());
    }
}
