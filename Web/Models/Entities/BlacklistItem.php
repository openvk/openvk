<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Models\RowModel;
use openvk\Web\Util\DateTime;
use openvk\Web\Models\Entities\{User, Manager};
use openvk\Web\Models\Repositories\{Users, Clubs};

class BlacklistItem extends RowModel
{
    protected $tableName = "blacklists";

    function getId(): int
    {
        return $this->getRecord()->index;
    }

    function getAuthor(): ?User
    {
        return (new Users)->get($this->getRecord()->author);
    }

    function getTarget(): ?User
    {
        return (new Users)->get($this->getRecord()->target);
    }

    function getCreationDate(): DateTime
    {
        return new DateTime($this->getRecord()->created);
    }
}