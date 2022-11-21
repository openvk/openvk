<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Entities\Link;
use openvk\Web\Models\Repositories\{Links, Clubs, Users};

final class LinksPresenter extends OpenVKPresenter
{
    private $links;
    
    function __construct(Links $links)
    {
        $this->links  = $links;
        
        parent::__construct();
    }

    function renderList(int $ownerId): void
    {
        $owner = ($ownerId < 0 ? (new Clubs) : (new Users))->get(abs($ownerId));
        if(!$owner)
            $this->notFound();

        $this->template->owner   = $owner;
        $this->template->ownerId = $ownerId;
        $page = (int) ($this->queryParam("p") ?? 1);

        $this->template->links = $this->links->getByOwnerId($ownerId, $page);
        $this->template->count = $this->links->getCountByOwnerId($ownerId);

        $this->template->paginatorConf = (object) [
            "count"   => $this->template->count,
            "page"    => $page,
            "amount"  => NULL,
            "perPage" => OPENVK_DEFAULT_PER_PAGE,
        ];
    }

    function renderCreate(int $ownerId): void
    {
        $this->assertUserLoggedIn();

        $owner = ($ownerId < 0 ? (new Clubs) : (new Users))->get(abs($ownerId));
        if(!$owner)
            $this->notFound();

        if($ownerId < 0 ? !$owner->canBeModifiedBy($this->user->identity) : $owner->getId() !== $this->user->id)
            $this->notFound();

        $this->template->_template = "Links/Edit.xml";
        $this->template->create    = true;
        $this->template->owner     = $owner;
        $this->template->ownerId   = $ownerId;
    }

    function renderEdit(int $ownerId, int $id): void
    {
        $this->assertUserLoggedIn();

        $owner = ($ownerId < 0 ? (new Clubs) : (new Users))->get(abs($ownerId));
        if(!$owner)
            $this->notFound();

        $link = $this->links->get($id);
        if(!$link && $id !== 0) // If the link ID is 0, consider the request as link creation
            $this->notFound();

        if($ownerId < 0 ? !$owner->canBeModifiedBy($this->user->identity) : $owner->getId() !== $this->user->id)
            $this->notFound();

        if($_SERVER["REQUEST_METHOD"] === "POST") {
            $this->willExecuteWriteAction();

            $create        = $id === 0;
            $title         = $this->postParam("title");
            $description   = $this->postParam("description");
            $url           = $this->postParam("url");
            $url           = (!parse_url($url, PHP_URL_SCHEME) ? "https://" : "") . $url;

            if(!$title || !$url)
                $this->flashFail("err", tr($create ? "failed_to_create_link" : "failed_to_change_link"), tr("not_all_data_entered"));

            if(!filter_var($url, FILTER_VALIDATE_URL))
                $this->flashFail("err", tr($create ? "failed_to_create_link" : "failed_to_change_link"), tr("wrong_address"));

            if($create)
                $link = new Link;

            $link->setOwner($ownerId);
            $link->setTitle(ovk_proc_strtr($title, 127));
            $link->setDescription($description === "" ? NULL : ovk_proc_strtr($description, 127));
            $link->setUrl($url);

            if(isset($_FILES["icon"]) && $_FILES["icon"]["size"] > 0) {
                if(($res = $link->setIcon($_FILES["icon"])) !== 0)
                    $this->flashFail("err", tr("unable_to_upload_icon"), tr("unable_to_upload_icon_desc", $res));
            }

            $link->save();

            $this->flash("succ", tr("information_-1"), tr($create ? "link_created" : "link_changed"));
            $this->redirect("/links" . $ownerId);
        }

        if($id === 0) // But there is a separate handler for displaying page with the fields to create, so here we do not skip
            $this->notFound();

        $this->template->linkId      = $link->getId();
        $this->template->title       = $link->getTitle();
        $this->template->description = $link->getDescription();
        $this->template->url         = $link->getUrl();
        $this->template->create      = false;
        $this->template->owner       = $owner;
        $this->template->ownerId     = $ownerId;
        $this->template->link        = $link;
    }

    function renderDelete(int $ownerId, int $id): void
    {
        $this->assertUserLoggedIn();

        $owner = ($ownerId < 0 ? (new Clubs) : (new Users))->get(abs($ownerId));
        if(!$owner)
            $this->notFound();

        $link = $this->links->get($id);
        if(!$link)
            $this->notFound();

        if(!$link->canBeModifiedBy($this->user->identity))
            $this->notFound();
        
        $this->willExecuteWriteAction();
        $link->delete(false);

        $this->flashFail("succ", tr("information_-1"), tr("link_deleted"));
    }
}
