<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Util\DateTime;
use Chandler\Database\DatabaseConnection as DB;
use openvk\Web\Models\{RowModel};
use openvk\Web\Models\Entities\{User, BugtrackerProduct};
use openvk\Web\Models\Repositories\{Users, BugtrackerProducts};

class BugtrackerPrivateProduct extends BugtrackerProduct
{
    protected $tableName = "bt_products_access";

    function toProduct(): ?BugtrackerProduct
    {
        return (new BugtrackerProducts)->get($this->getId());
    }

    function getName(): string
    {
        return $this->toProduct()->getName();
    }

    function isClosed(): ?bool
    {
        return $this->toProduct()->isClosed();
    }

    function getCreator(): ?User
    {
        return $this->toProduct()->getCreator();
    }

    function isPrivate(): ?bool
    {
        return true;
    }

    function getModerator(): ?User
    {
        return (new Users)->get($this->getRecord("moderator"));
    }
}