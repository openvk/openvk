<?php declare(strict_types=1);

namespace openvk\Web\Presenters;

use openvk\Web\Models\Entities\Name;
use openvk\Web\Models\Repositories\Names;

final class NamesPresenter extends OpenVKPresenter
{
    private $names;

    public function __construct(Names $names)
    {
        $this->names = $names;
    }

    function renderList(): void
    {
        $this->assertUserLoggedIn();
        $this->assertPermission("openvk\Web\Models\Entities\TicketReply", "write", 0);

        $this->template->mode = $this->queryParam("act") ?? "new";
        $this->template->mode_status = 0;

        if ($this->template->mode == "accepted")
            $this->template->mode_status = 1;
        else if ($this->template->mode == "rejected")
            $this->template->mode_status = 2;

        $this->template->page = (int) $this->queryParam("p") ?: 1;

        $this->template->iterator = $this->names->getList($this->template->page, $this->template->mode_status);
        $this->template->names = iterator_to_array($this->template->iterator);

        $this->template->count = (clone $this->names)->getCount($this->template->mode_status);
    }

    function renderAction(int $id): void
    {
        $this->assertUserLoggedIn();
        $this->assertPermission("openvk\Web\Models\Entities\TicketReply", "write", 0);

        $act = $this->queryParam("act");

        if(!$act)
            $this->flashFail("err", tr("error"), tr("forbidden"));

        $name = $this->names->get($id);

        if(!$name)
            $this->flashFail("err", tr("error"), "Заявка #$id не найдена.");

        $user = $name->getUser();

        if($act == "accept") {
            $user->setFirst_name($name->getFirstName());
            $user->setLast_name($name->getLastName());
            $user->save();

            $name->setState(1);
            $name->save();

            $this->flashFail("success", "Успех", "Заявка #$id была принята.");
        } elseif($act == "reject") {
            $name->setState(2);
            $name->save();

            $this->flashFail("success", "Успех", "Заявка #$id была отклонена.");
        }

        $this->flashFail("err", tr("error"), tr("forbidden"));
    }
}
