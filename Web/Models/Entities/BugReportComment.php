<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Util\DateTime;
use openvk\Web\Models\{RowModel};
use openvk\Web\Models\Entities\{User, BugtrackerProduct};
use openvk\Web\Models\Repositories\{Users, BugtrackerProducts};
use Chandler\Database\DatabaseConnection as DB;

class BugReportComment extends RowModel
{
    protected $tableName = "bt_comments";

    function getId(): int
    {
        return $this->getRecord()->id;
    }

    function getAuthor(): ?User
    {
        return (new Users)->get($this->getRecord()->author);
    }
    
    function isModer(): bool
    {
        return (bool) $this->getRecord()->is_moder;
    }

    function isHidden(): bool
    {
        return (bool) $this->getRecord()->is_hidden;
    }

    function getText(): string
    {
        return $this->getRecord()->text;
    }

    function getLabel(): string
    {
        return $this->getRecord()->label;
    }

    function getBalanceChanges(): ?int
    {
        return $this->getRecord()->point_actions;
    }

    function getCreationTime(): DateTime
    {
        return new DateTime($this->getRecord()->created);
    }
}