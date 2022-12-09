<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Entities\{Comment, Photo, Video, User, Topic, Post, Wall};
use openvk\Web\Models\Entities\Notifications\CommentNotification;
use openvk\Web\Models\Repositories\{Comments, Clubs};

final class CommentPresenter extends OpenVKPresenter
{
    protected $presenterName = "comment";
    private $models = [
        "posts"   => "openvk\\Web\\Models\\Repositories\\Posts",
        "photos"  => "openvk\\Web\\Models\\Repositories\\Photos",
        "videos"  => "openvk\\Web\\Models\\Repositories\\Videos",
        "notes"   => "openvk\\Web\\Models\\Repositories\\Notes",
        "topics"  => "openvk\\Web\\Models\\Repositories\\Topics",
    ];
    
    function renderLike(int $id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $comment = (new Comments)->get($id);
        if(!$comment || $comment->isDeleted()) $this->notFound();
        
        if(!is_null($this->user)) $comment->toggleLike($this->user->identity);
        
        $this->redirect($_SERVER["HTTP_REFERER"]);
    }
    
    function renderMakeComment(string $repo, int $eId): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $repoClass = $this->models[$repo] ?? NULL;
        if(!$repoClass) chandler_http_panic(400, "Bad Request", "Unexpected $repo.");
        
        $repo   = new $repoClass;
        $entity = $repo->get($eId);
        if(!$entity) $this->notFound();

        if($entity instanceof Topic && $entity->isClosed())
            $this->notFound();

        if($entity instanceof Post && !$entity->getOwner()->getPrivacyPermission('comments.write', $this->user->identity))
            exit(header("HTTP/1.1 403 Forbidden"));

        if($entity instanceof Post && $entity->getTargetWall() < 0)
            $club = (new Clubs)->get(abs($entity->getTargetWall()));
        else if($entity instanceof Topic)
            $club = $entity->getClub();
        
        $flags = 0;
        if($this->postParam("as_group") === "on" && !is_null($club) && $club->canBeModifiedBy($this->user->identity))
            $flags |= 0b10000000;

        $photo = NULL;
        if($_FILES["_pic_attachment"]["error"] === UPLOAD_ERR_OK) {
            try {
                $photo = Photo::fastMake($this->user->id, $this->postParam("text"), $_FILES["_pic_attachment"]);
            } catch(ISE $ex) {
                $this->flashFail("err", "Не удалось опубликовать пост", "Файл изображения повреждён, слишком велик или одна сторона изображения в разы больше другой.");
            }
        }
        
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
                $video = Video::fastMake($this->user->id, $this->postParam("text"), $_FILES["_vid_attachment"]);
            }
        } catch(ISE $ex) {
            $this->flashFail("err", "Не удалось опубликовать комментарий", "Файл медиаконтента повреждён или слишком велик.");
        }
        
        if(empty($this->postParam("text")) && !$photo && !$video)
            $this->flashFail("err", "Не удалось опубликовать комментарий", "Комментарий пустой или слишком большой.");
        
        try {
            $comment = new Comment;
            $comment->setOwner($this->user->id);
            $comment->setModel(get_class($entity));
            $comment->setTarget($entity->getId());
            $comment->setContent($this->postParam("text"));
            $comment->setCreated(time());
            $comment->setFlags($flags);
            $comment->save();
        } catch (\LengthException $ex) {
            $this->flashFail("err", "Не удалось опубликовать комментарий", "Комментарий слишком большой.");
        }
        
        if(!is_null($photo))
            $comment->attach($photo);
        
        if(!is_null($video))
            $comment->attach($video);
        
        if($entity->getOwner()->getId() !== $this->user->identity->getId())
            if(($owner = $entity->getOwner()) instanceof User)
                (new CommentNotification($owner, $comment, $entity, $this->user->identity))->emit();
        
        $this->flashFail("succ", "Комментарий добавлен", "Ваш комментарий появится на странице.");
    }
    
    function renderDeleteComment(int $id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $comment = (new Comments)->get($id);
        if(!$comment) $this->notFound();
        if(!$comment->canBeDeletedBy($this->user->identity))
            $this->throwError(403, "Forbidden", "У вас недостаточно прав чтобы редактировать этот ресурс.");
        
        $comment->delete();
        $this->flashFail(
            "succ",
            "Успешно",
            "Этот комментарий больше не будет показыватся.<br/><a href='/al_comments/spam?$id'>Отметить как спам</a>?"
        );
    }
}
