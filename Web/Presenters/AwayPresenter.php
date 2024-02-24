<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Repositories\BannedLinks;
use openvk\Web\Models\Entities\BannedLink;

final class AwayPresenter extends OpenVKPresenter
{
    function renderAway(): void
    {
        $checkBanEntries = (new BannedLinks)->check($this->queryParam("to"));
        if (OPENVK_ROOT_CONF["openvk"]["preferences"]["susLinks"]["warnings"])
            if (sizeof($checkBanEntries) > 0)
                $this->pass("openvk!Away->view", $checkBanEntries[0]);

        header("HTTP/1.0 302 Found");
        header("X-Robots-Tag: noindex, nofollow, noarchive");
        header("Location: " . $this->queryParam("to"));
        exit;
    }

    function renderView(int $lid) {
        $this->template->link = (new BannedLinks)->get($lid);

        if (!$this->template->link)
            $this->notFound();

        $this->template->to   = $this->queryParam("to");
    }
}
