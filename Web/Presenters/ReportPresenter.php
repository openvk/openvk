<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Repositories\Users;
use openvk\Web\Models\Repositories\Reports;
use openvk\Web\Models\Repositories\Posts;
use openvk\Web\Models\Entities\Report;

final class ReportPresenter extends OpenVKPresenter
{
    private $reports;
    
    function __construct(Reports $reports)
    {
        $this->reports = $reports;
        
        parent::__construct();
    }
    
    function renderList(): void
    {
        $this->assertUserLoggedIn();
        $this->assertPermission('openvk\Web\Models\Entities\TicketReply', 'write', 0);
        if ($_SERVER["REQUEST_METHOD"] === "POST")
            $this->assertNoCSRF();

        $act = in_array($this->queryParam("act"), ["post", "photo", "video", "group", "comment", "note", "app", "user", "audio"]) ? $this->queryParam("act") : NULL;

        if (!$this->queryParam("orig")) {
            $this->template->reports = $this->reports->getReports(0, (int)($this->queryParam("p") ?? 1), $act, $_SERVER["REQUEST_METHOD"] !== "POST");
            $this->template->count = $this->reports->getReportsCount();
        } else {
            $orig = $this->reports->get((int) $this->queryParam("orig"));
            if (!$orig) $this->redirect("/scumfeed");

            $this->template->reports = $orig->getDuplicates();
            $this->template->count = $orig->getDuplicatesCount();
            $this->template->orig = $orig->getId();
        }
        $this->template->paginatorConf = (object) [
            "count"   => $this->template->count,
            "page"    => $this->queryParam("p") ?? 1,
            "amount"  => NULL,
            "perPage" => 15,
        ];
        $this->template->mode = $act ?? "all";

        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $reports = [];
            foreach ($this->reports->getReports(0, 0, $act, false) as $report) {
                $reports[] = [
                    "id" => $report->getId(),
                    "author" => [
                        "id" => $report->getReportAuthor()->getId(),
                        "url" => $report->getReportAuthor()->getURL(),
                        "name" => $report->getReportAuthor()->getCanonicalName(),
                        "is_female" => $report->getReportAuthor()->isFemale()
                    ],
                    "content" => [
                        "name" => $report->getContentName(),
                        "type" => $report->getContentType(),
                        "id" => $report->getContentId(),
                        "url" => $report->getContentType() === "user" ? (new Users)->get((int) $report->getContentId())->getURL() : NULL
                    ],
                    "duplicates" => $report->getDuplicatesCount(),
                ];
            }
            $this->returnJson(["reports" => $reports]);
        }
    }
    
    function renderView(int $id): void
    {
        $this->assertUserLoggedIn();
        $this->assertPermission('openvk\Web\Models\Entities\TicketReply', 'write', 0);

        $report = $this->reports->get($id);
        if(!$report || $report->isDeleted())
            $this->notFound();
        
        $this->template->report = $report;
    }
    
    function renderCreate(int $id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();

        if(!$id)
            exit(json_encode([ "error" => tr("error_segmentation") ]));

        if(in_array($this->queryParam("type"), ["post", "photo", "video", "group", "comment", "note", "app", "user", "audio"])) {
            if (count(iterator_to_array($this->reports->getDuplicates($this->queryParam("type"), $id, NULL, $this->user->id))) <= 0) {
                $report = new Report;
                $report->setUser_id($this->user->id);
                $report->setTarget_id($id);
                $report->setType($this->queryParam("type"));
                $report->setReason($this->queryParam("reason"));
                $report->setCreated(time());
                $report->save();
            }
            
            exit(json_encode([ "reason" => $this->queryParam("reason") ]));
        } else {
            exit(json_encode([ "error" => "Unable to submit a report on this content type" ]));
        }
    }
    
    function renderAction(int $id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        $this->assertPermission('openvk\Web\Models\Entities\TicketReply', 'write', 0);

        $report = $this->reports->get($id);
        if(!$report || $report->isDeleted()) $this->notFound();

        if ($this->postParam("ban")) {
            $report->deleteContent();
            $report->banUser($this->user->identity->getId());

            $this->flash("suc", tr("death"), tr("user_successfully_banned"));
        } else if ($this->postParam("delete")) {
            $report->deleteContent();

            $this->flash("suc", tr("nehay"), tr("content_is_deleted"));
        } else if ($this->postParam("ignore")) {
            $report->delete();

            $this->flash("suc", tr("nehay"), tr("report_is_ignored"));
        } else if ($this->postParam("banClubOwner") || $this->postParam("banClub")) {
            if ($report->getContentType() !== "group")
                $this->flashFail("err", tr("error_access_denied_short"), tr("error_access_denied"));

            $club = $report->getContentObject();
            if (!$club || $club->isBanned())
                $this->flashFail("err", tr("error_access_denied_short"), tr("error_access_denied"));

            if ($this->postParam("banClubOwner")) {
                $club->getOwner()->ban("**content-" . $report->getContentType() . "-" . $report->getContentId() . "**", false, $club->getOwner()->getNewBanTime(), $this->user->identity->getId());
            } else {
                $club->ban("**content-" . $report->getContentType() . "-" . $report->getContentId() . "**");
            }

            $report->delete();

            $this->flash("suc", tr("death"), ($this->postParam("banClubOwner") ? tr("group_owner_is_banned") : tr("group_is_banned")));
        }

        $this->redirect("/scumfeed");
    }
}
