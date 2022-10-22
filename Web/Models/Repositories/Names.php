<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use Nette\Database\Table\ActiveRow;
use Chandler\Database\DatabaseConnection as DB;

use openvk\Web\Models\Entities\Name;
use openvk\Web\Models\Entities\User;

class Names
{
    private $context;
    private $names;

    public function __construct()
    {
        $this->context = DB::i()->getContext();
        $this->names   = $this->context->table("names");
        $this->users   = $this->context->table("profiles");
    }

    private function toName(?ActiveRow $ar): ?Name
    {
        return is_null($ar) ? NULL : new Name($ar);
    }

    function get(int $id): ?Name
    {
        return $this->toName($this->names->get($id));
    }

    function getCount(int $status = 0): int
    {
        return sizeof(DB::i()->getContext()->table("names")->where("state", $status));
    }

    function getList(int $page = 1, int $status = 0): \Traversable
    {
        foreach($this->names->where("state", $status)->order("created ASC")->page($page, 5) as $name)
            yield $this->toName($name);
    }

    function getByUser(int $uid, int $status = 0, ?bool $actual = true): \Traversable
    {
        $filter = ["author" => $uid, "state" => $status];
        $actual && $filter[] = "created >= " . (time() - 259200);

        foreach($this->names->where($filter) as $name)
            yield $this->toName($name);
    }
}
