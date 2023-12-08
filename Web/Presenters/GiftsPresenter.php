<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Repositories\{Gifts, Users};
use openvk\Web\Models\Entities\Notifications\GiftNotification;

final class GiftsPresenter extends OpenVKPresenter
{
    private $gifts;
    private $users;
    protected $presenterName = "gifts";
    
    function __construct(Gifts $gifts, Users $users)
    {
        $this->gifts = $gifts;
        $this->users = $users;
    }
    
    function renderUserGifts(int $user): void
    {
        $this->assertUserLoggedIn();
        
        $user = $this->users->get($user);
        if(!$user || $user->isDeleted())
            $this->notFound();
        
        if(!$user->canBeViewedBy($this->user->identity ?? NULL))
            $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));

        $this->template->user     = $user;
        $this->template->page     = $page = (int) ($this->queryParam("p") ?? 1);
        $this->template->count    = $user->getGiftCount();
        $this->template->iterator = $user->getGifts($page);
        $this->template->hideInfo = $this->user->id !== $user->getId();
    }
    
    function renderGiftMenu(): void
    {
        $user = $this->users->get((int) ($this->queryParam("user") ?? 0));
        if(!$user)
            $this->notFound();
        
        $this->template->page = $page = (int) ($this->queryParam("p") ?? 1);
        $cats = $this->gifts->getCategories($page, NULL, $this->template->count);
        
        $this->template->user      = $user;
        $this->template->iterator  = $cats;
        $this->template->count     = $this->gifts->getCategoriesCount();
        $this->template->_template = "Gifts/Menu.xml";
    }
    
    function renderGiftList(): void
    {
        $user = $this->users->get((int) ($this->queryParam("user") ?? 0));
        $cat  = $this->gifts->getCat((int) ($this->queryParam("pack") ?? 0));
        if(!$user || !$cat)
            $this->flashFail("err", tr("error_when_gifting"), tr("error_user_not_exists"));
        
        if(!$user->canBeViewedBy($this->user->identity))
            $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));
        
        $this->template->page = $page = (int) ($this->queryParam("p") ?? 1);
        $gifts = $cat->getGifts($page, null, $this->template->count);
        
        $this->template->user      = $user;
        $this->template->cat       = $cat;
        $this->template->gifts     = iterator_to_array($gifts);
        $this->template->_template = "Gifts/Pick.xml";
    }
    
    function renderConfirmGift(): void
    {
        $user = $this->users->get((int) ($this->queryParam("user") ?? 0));
        $gift = $this->gifts->get((int) ($this->queryParam("elid") ?? 0));
        $cat  = $this->gifts->getCat((int) ($this->queryParam("pack") ?? 0));
        if(!$user || !$cat || !$gift || !$cat->hasGift($gift))
            $this->flashFail("err", tr("error_when_gifting"), tr("error_no_rights_gifts"));
        
        if(!$gift->canUse($this->user->identity))
            $this->flashFail("err", tr("error_when_gifting"), tr("error_no_more_gifts"));
        
        if(!$user->canBeViewedBy($this->user->identity ?? NULL))
            $this->flashFail("err", tr("forbidden"), tr("forbidden_comment"));

        $coinsLeft = $this->user->identity->getCoins() - $gift->getPrice();
        if($coinsLeft < 0)
            $this->flashFail("err", tr("error_when_gifting"), tr("error_no_money"));
        
        $this->template->_template = "Gifts/Confirm.xml";
        if($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->template->user = $user;
            $this->template->cat  = $cat;
            $this->template->gift = $gift;
            return;
        }
        
        $comment      = empty($c = $this->postParam("comment")) ? NULL : $c;
        $notification = new GiftNotification($user, $this->user->identity, $gift, $comment);
        $notification->emit();
        $this->user->identity->setCoins($coinsLeft);
        $this->user->identity->save();
        $user->gift($this->user->identity, $gift, $comment, !is_null($this->postParam("anonymous")));
        $gift->used();
        
        $this->flash("succ", tr("gift_sent"), tr("gift_sent_desc", $user->getFirstName(), $gift->getPrice()));
        $this->redirect($user->getURL());
    }
    
    function renderStub(): void
    {
        $this->assertUserLoggedIn();
        
        $act = $this->queryParam("act");
        switch($act) {
            case "pick":
                $this->renderGiftMenu();
                break;
            
            case "menu":
                $this->renderGiftList();
                break;
            
            case "confirm":
                $this->renderConfirmGift();
                break;
            
            default:
                $this->notFound();
        }
    }
    
    function renderGiftImage(int $id, int $timestamp): void
    {
        $gift = $this->gifts->get($id);
        if(!$gift)
            $this->notFound();
        
        $image = $gift->getImage();
        header("Cache-Control: no-transform, immutable");
        header("Content-Length: " . strlen($image));
        header("Content-Type: image/png");
        exit($image);
    }
    
    function onStartup(): void
    {
        if(!OPENVK_ROOT_CONF["openvk"]["preferences"]["commerce"])
            $this->flashFail("err", tr("error"), tr("feature_disabled"));
        
        parent::onStartup();
    }
}
