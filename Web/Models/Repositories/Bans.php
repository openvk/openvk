<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use Chandler\Database\DatabaseConnection as DB;
use Nette\Database\Table\{ActiveRow, Selection};
use openvk\Web\Models\Entities\Ban;

class Bans
{
    private $context;
    private $bans;

    function __construct()
    {
        $this->context = DB::i()->getContext();
        $this->bans = $this->context->table("bans");
    }

    function toBan(?ActiveRow $ar): ?Ban
    {
        return is_null($ar) ? NULL : new Ban($ar);
    }

    function get(int $id): ?Ban
    {
        return $this->toBan($this->bans->get($id));
    }

    function getByUser(int $user_id): \Traversable
    {
        foreach ($this->bans->where("user", $user_id) as $ban)
            yield new Ban($ban);
    }
}