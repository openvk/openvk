<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use openvk\Web\Models\Entities\Report;
use openvk\Web\Models\Repositories\Reports as ReportsRepo;

final class Reports extends VKAPIRequestHandler
{
    function add(string $type, int $id, string $reason): object
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        if ($id <= 0) {
            $this->fail(100, "ID must be a positive number");
        }

        if(mb_strlen(trim($reason)) === 0) {
            $this->fail(100, "Reason can't be empty");
        }

        if ($type === "user" && $id === $this->getUser()->getId()) {
            $this->fail(100, "You can't report yourself");
        }

        if(in_array($type, ["post", "photo", "video", "group", "comment", "note", "app", "user"])) {
            if (count(iterator_to_array((new ReportsRepo)->getDuplicates($type, $id, NULL, $this->getUser()->getId()))) <= 0) {
                $report = new Report;
                $report->setUser_id($this->getUser()->getId());
                $report->setTarget_id($id);
                $report->setType($type);
                $report->setReason($reason);
                $report->setCreated(time());
                $report->save();
            }

            return (object) [ "reason" => $reason ];
        } else {
            $this->fail(3, "Unable to submit a report on this content type");
        }
    }
}
