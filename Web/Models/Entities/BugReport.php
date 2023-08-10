<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Models\{RowModel};
use openvk\Web\Models\Entities\{User, BugtrackerProduct};
use openvk\Web\Models\Repositories\{Users, BugtrackerProducts};
use Chandler\Database\DatabaseConnection as DB;
use openvk\Web\Util\DateTime;

class BugReport extends RowModel
{
    protected $tableName = "bugs";

    function getId(): int
    {
        return $this->getRecord()->id;
    }

    function getReporter(): ?User
    {
        return (new Users)->get($this->getRecord()->reporter);
    }

    function getName(): string
    {
        return $this->getRecord()->title;
    }

    function getCanonicalName(): string
    {
        return $this->getName();
    }

    function getText(): string
    {
        return $this->getRecord()->text;
    }

    function getProduct(): ?BugtrackerProduct
    {
        return (new BugtrackerProducts)->get($this->getRecord()->product_id);
    }

    function getStatus(): string
    {
        $list = [
            tr("bug_tracker_status_open"),
            tr("bug_tracker_status_under_review"),
            tr("bug_tracker_status_in_progress"),
            tr("bug_tracker_status_fixed"),
            tr("bug_tracker_status_closed"),
            tr("bug_tracker_status_requires_adjustment"),
            tr("bug_tracker_status_locked"),
            tr("bug_tracker_status_rejected"),
            "Переоткрыт"
        ];
        $status_id = $this->getRecord()->status;

        return $list[$status_id];
    }

    function getRawStatus(): ?int
    {
        return $this->getRecord()->status;
    }

    function getPriority(): string
    {
        $list = [
            tr("bug_tracker_priority_feature"),
            tr("bug_tracker_priority_low"),
            tr("bug_tracker_priority_medium"),
            tr("bug_tracker_priority_high"),
            tr("bug_tracker_priority_critical"),
            tr("bug_tracker_priority_vulnerability")
        ];
        $priority_id = $this->getRecord()->priority;
        
        return $list[$priority_id];
    }

    function getRawPriority(): ?int
    {
        return $this->getRecord()->priority;
    }

    function getDevice(): string
    {
        return $this->getRecord()->device;
    }

    function getReproducedCount(): ?int
    {
        return $this->getRecord()->reproduced;
    }

    function getCreationTime(): DateTime
    {
        return new DateTime($this->getRecord()->created);
    }

    function isDeleted(): bool
    {
        return (bool) $this->getRecord()->deleted;
    }
}