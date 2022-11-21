<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Entities\{Club, Photo};
use openvk\Web\Models\Entities\Notifications\ClubModeratorNotification;
use openvk\Web\Models\Repositories\{Clubs, Users, Albums, Managers, Topics, Links};
use Chandler\Security\Authenticator;

final class GroupPresenter extends OpenVKPresenter
{
    private $clubs;
    protected $presenterName = "group";

    function __construct(Clubs $clubs)
    {
        $this->clubs = $clubs;
        
        parent::__construct();
    }
    
    function renderView(int $id): void
    {
        $club = $this->clubs->get($id);
        if(!$club) {
            $this->notFound();
        } else {
            $this->template->albums      = (new Albums)->getClubAlbums($club, 1, 3);
            $this->template->albumsCount = (new Albums)->getClubAlbumsCount($club);
            $this->template->topics      = (new Topics)->getLastTopics($club, 3);
            $this->template->topicsCount = (new Topics)->getClubTopicsCount($club);
            $this->template->links       = (new Links)->getByOwnerId($club->getId() * -1, 1, 5);
            $this->template->linksCount  = (new Links)->getCountByOwnerId($club->getId() * -1);

            $this->template->club = $club;
        }
    }
    
    function renderCreate(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        if($_SERVER["REQUEST_METHOD"] === "POST") {
            if(!empty($this->postParam("name")))
            {
                $club = new Club;
                $club->setName($this->postParam("name"));
                $club->setAbout(empty($this->postParam("about")) ? NULL : $this->postParam("about"));
                $club->setOwner($this->user->id);
                
                try {
                    $club->save();
                } catch(\PDOException $ex) {
                    if($ex->getCode() == 23000)
                        $this->flashFail("err", "Ошибка", "Произошла ошибка на стороне сервера. Обратитесь к системному администратору.");
                    else
                        throw $ex;
                }
                
                $club->toggleSubscription($this->user->identity);
                $this->redirect("/club" . $club->getId());
            }else{
                $this->flashFail("err", "Ошибка", "Вы не ввели название группы.");
            }
        }
    }
    
    function renderSub(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        if($_SERVER["REQUEST_METHOD"] !== "POST") exit("Invalid state");
        
        $club = $this->clubs->get((int) $this->postParam("id"));
        if(!$club) exit("Invalid state");
        
        $club->toggleSubscription($this->user->identity);
        
        $this->redirect($club->getURL());
    }
    
    function renderFollowers(int $id): void
    {
        $this->assertUserLoggedIn();

        $this->template->club              = $this->clubs->get($id);
        $this->template->onlyShowManagers  = $this->queryParam("onlyAdmins") == "1";
        if($this->template->onlyShowManagers) {
            $this->template->followers     = NULL;

            $this->template->managers     = $this->template->club->getManagers((int) ($this->queryParam("p") ?? 1), !$this->template->club->canBeModifiedBy($this->user->identity));
            if($this->template->club->canBeModifiedBy($this->user->identity) || !$this->template->club->isOwnerHidden()) {
                $this->template->managers  = array_merge([$this->template->club->getOwner()], iterator_to_array($this->template->managers));
            }

            $this->template->count         = $this->template->club->getManagersCount();
        } else {
            $this->template->followers     = $this->template->club->getFollowers((int) ($this->queryParam("p") ?? 1));
            $this->template->managers      = NULL;
            $this->template->count         = $this->template->club->getFollowersCount();
        }

        $this->template->paginatorConf = (object) [
            "count"   => $this->template->count,
            "page"    => $this->queryParam("p") ?? 1,
            "amount"  => NULL,
            "perPage" => OPENVK_DEFAULT_PER_PAGE,
        ];
    }
    
    function renderModifyAdmin(int $id): void
    {
        $user = is_null($this->queryParam("user")) ? $this->postParam("user") : $this->queryParam("user");
        $comment = $this->postParam("comment");
        $removeComment = $this->postParam("removeComment") === "1";
        $hidden = ["0" => false, "1" => true][$this->queryParam("hidden")] ?? NULL;
        //$index = $this->queryParam("index");
        if(!$user)
            $this->badRequest();
        
        $club = $this->clubs->get($id);
        $user = (new Users)->get((int) $user);
        if(!$user || !$club)
            $this->notFound();
        
        if(!$club->canBeModifiedBy($this->user->identity ?? NULL))
            $this->flashFail("err", "Ошибка доступа", "У вас недостаточно прав, чтобы изменять этот ресурс.");

        if(!is_null($hidden)) {
            if($club->getOwner()->getId() == $user->getId()) {
                $club->setOwner_Hidden($hidden);
                $club->save();
            } else {
                $manager = (new Managers)->getByUserAndClub($user->getId(), $club->getId());
                $manager->setHidden($hidden);
                $manager->save();
            }

            if($club->getManagersCount(true) == 0) {
                $club->setAdministrators_List_Display(2);
                $club->save();
            }

            if($hidden) {
                $this->flashFail("succ", "Операция успешна", "Теперь " . $user->getCanonicalName() . " будет показываться как обычный подписчик всем кроме других администраторов");
            } else {
                $this->flashFail("succ", "Операция успешна", "Теперь все будут знать про то что " . $user->getCanonicalName() . " - администратор");
            }
        } elseif($removeComment) {
            if($club->getOwner()->getId() == $user->getId()) {
                $club->setOwner_Comment(null);
                $club->save();
            } else {
                $manager = (new Managers)->getByUserAndClub($user->getId(), $club->getId());
                $manager->setComment(null);
                $manager->save();
            }

            $this->flashFail("succ", "Операция успешна", "Комментарий к администратору удален");
        } elseif($comment) {
            if(mb_strlen($comment) > 36) {
                $commentLength = (string) mb_strlen($comment);
                $this->flashFail("err", "Ошибка", "Комментарий слишком длинный ($commentLength символов вместо 36 символов)");
            }

            if($club->getOwner()->getId() == $user->getId()) {
                $club->setOwner_Comment($comment);
                $club->save();
            } else {
                $manager = (new Managers)->getByUserAndClub($user->getId(), $club->getId());
                $manager->setComment($comment);
                $manager->save();
            }

            $this->flashFail("succ", "Операция успешна", "Комментарий к администратору изменён");
        }else{
            if($club->canBeModifiedBy($user)) {
                $club->removeManager($user);
                $this->flashFail("succ", "Операция успешна", $user->getCanonicalName() . " более не администратор.");
            } else {
                $club->addManager($user);
                
                (new ClubModeratorNotification($user, $club, $this->user->identity))->emit();
                $this->flashFail("succ", "Операция успешна", $user->getCanonicalName() . " назначен(а) администратором.");
            }
        }
        
    }
    
    function renderEdit(int $id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        $club = $this->clubs->get($id);
        if(!$club->canBeModifiedBy($this->user->identity))
            $this->notFound();
        else
            $this->template->club = $club;
            
        if($_SERVER["REQUEST_METHOD"] === "POST") {
	    if(!$club->setShortcode( empty($this->postParam("shortcode")) ? NULL : $this->postParam("shortcode") ))
                $this->flashFail("err", tr("error"), tr("error_shorturl_incorrect"));
            
            $club->setName(empty($this->postParam("name")) ? $club->getName() : $this->postParam("name"));
            $club->setAbout(empty($this->postParam("about")) ? NULL : $this->postParam("about"));
	    $club->setWall(empty($this->postParam("wall")) ? 0 : 1);
            $club->setAdministrators_List_Display(empty($this->postParam("administrators_list_display")) ? 0 : $this->postParam("administrators_list_display"));
	    $club->setEveryone_Can_Create_Topics(empty($this->postParam("everyone_can_create_topics")) ? 0 : 1);
            $club->setDisplay_Topics_Above_Wall(empty($this->postParam("display_topics_above_wall")) ? 0 : 1);
            $club->setHide_From_Global_Feed(empty($this->postParam("hide_from_global_feed")) ? 0 : 1);
            
            $website = $this->postParam("website") ?? "";
            if(empty($website))
                $club->setWebsite(NULL);
            else
                $club->setWebsite((!parse_url($website, PHP_URL_SCHEME) ? "https://" : "") . $website);
            
            if($_FILES["ava"]["error"] === UPLOAD_ERR_OK) {
                $photo = new Photo;
                try {
                    $anon = OPENVK_ROOT_CONF["openvk"]["preferences"]["wall"]["anonymousPosting"]["enable"];
                    if($anon && $this->user->id === $club->getOwner()->getId())
                        $anon = $club->isOwnerHidden();  
                    else if($anon)
                        $anon = $club->getManager($this->user->identity)->isHidden();

                    $photo->setOwner($this->user->id);
                    $photo->setDescription("Profile image");
                    $photo->setFile($_FILES["ava"]);
                    $photo->setCreated(time());
                    $photo->setAnonymous($anon);
                    $photo->save();
                    
                    (new Albums)->getClubAvatarAlbum($club)->addPhoto($photo);
                } catch(ISE $ex) {
                    $name = $album->getName();
                    $this->flashFail("err", "Неизвестная ошибка", "Не удалось сохранить фотографию.");
                }
            }
            
            try {
                $club->save();
            } catch(\PDOException $ex) {
                if($ex->getCode() == 23000)
                    $this->flashFail("err", "Ошибка", "Произошла ошибка на стороне сервера. Обратитесь к системному администратору.");
                else
                    throw $ex;
            }
            
            $this->flash("succ", "Изменения сохранены", "Новые данные появятся в вашей группе.");
        }
    }
    
    function renderStatistics(int $id): void
    {
        $this->assertUserLoggedIn();
        
        if(!eventdb())
            $this->flashFail("err", "Ошибка подключения", "Не удалось подключится к службе телеметрии.");
        
        $club = $this->clubs->get($id);
        if(!$club->canBeModifiedBy($this->user->identity))
            $this->notFound();
        else
            $this->template->club = $club;
        
        $this->template->reach = $club->getPostViewStats(true);
        $this->template->views = $club->getPostViewStats(false);
    }

    function renderAdmin(int $clb, int $id): void
    {
        $this->assertUserLoggedIn();

        $manager = (new Managers)->get($id);
        if($manager->getClub()->canBeModifiedBy($this->user->identity)){
            $this->template->manager = $manager;
            $this->template->club = $manager->getClub();
        }else{
            $this->notFound();
        }
    }

    function renderChangeOwner(int $id, int $newOwnerId): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();

        if($_SERVER['REQUEST_METHOD'] !== "POST")
            $this->redirect("/groups" . $this->user->id);

        if(!Authenticator::verifyHash($this->postParam("password"), $this->user->identity->getChandlerUser()->getRaw()->passwordHash))
            $this->flashFail("err", tr("error"), tr("incorrect_password"));

        $club = $this->clubs->get($id);
        $newOwner = (new Users)->get($newOwnerId);
        if($this->user->id !== $club->getOwner()->getId())
            $this->flashFail("err", tr("error"), tr("forbidden"));

        $club->setOwner($newOwnerId);

        $club->addManager($this->user->identity);
        $oldOwnerManager = $club->getManager($this->user->identity);
        $oldOwnerManager->setHidden($club->isOwnerHidden());
        $oldOwnerManager->setComment($club->getOwnerComment());
        $oldOwnerManager->save();

        $newOwnerManager = $club->getManager($newOwner);
        $club->setOwner_Hidden($newOwnerManager->isHidden());
        $club->setOwner_Comment($newOwnerManager->getComment());
        $club->removeManager($newOwner);

        $club->save();

        $this->flashFail("succ", tr("information_-1"), tr("group_owner_setted", $newOwner->getCanonicalName(), $club->getName()));
    }
}
