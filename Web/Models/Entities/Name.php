<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Util\DateTime;
use openvk\Web\Models\RowModel;
use Nette\Database\Table\ActiveRow;
use Chandler\Database\DatabaseConnection as DB;
use openvk\Web\Models\Repositories\{Users};
use openvk\Web\Models\Entities\User;

class Name extends RowModel
{
    protected $tableName = "names";

    function getId(): int
    {
        return $this->getRecord()->id;
    }

    function getCreationDate(): DateTime
    {
        return new DateTime($this->getRecord()->created);
    }

    function getFirstName(): ?string
    {
        return $this->getRecord()->new_fn;
    }

    function getLastName(): ?string
    {
        return $this->getRecord()->new_ln;
    }

    function getUser(): ?User
    {
        return (new Users)->get($this->getRecord()->author);
    }

    function getStatus(): int
    {
        return $this->getRecord()->state;
    }
}
