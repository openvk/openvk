<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Models\RowModel;
use openvk\Web\Util\DateTime;
use openvk\Web\Models\Repositories\{Users};
use Nette\Database\Table\ActiveRow;

class Ban extends RowModel
{
    protected $tableName = "bans";

    function getId(): int
    {
        return $this->getRecord()->id;
    }

    function getReason(): ?string
    {
        return $this->getRecord()->reason;
    }

    function getUser(): ?User
    {
        return (new Users)->get($this->getRecord()->user);
    }

    function getInitiator(): ?User
    {
        return (new Users)->get($this->getRecord()->initiator);
    }

    function getStartTime(): int
    {
        return $this->getRecord()->iat;
    }

    function getEndTime(): int
    {
        return $this->getRecord()->exp;
    }

    function getTime(): int
    {
        return $this->getRecord()->time;
    }

    function isPermanent(): bool
    {
        return $this->getEndTime() === 0;
    }

    function isRemovedManually(): bool
    {
        return (bool) $this->getRecord()->removed_manually;
    }

    function isOver(): bool
    {
        return $this->isRemovedManually();
    }

    function whoRemoved(): ?User
    {
        return (new Users)->get($this->getRecord()->removed_by);
    }
}
