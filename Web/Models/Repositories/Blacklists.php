<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use openvk\Web\Models\Entities\{User, BlacklistItem};
use openvk\Web\Models\Repositories\{Clubs, Users};
use Nette\Database\Table\ActiveRow;
use Chandler\Database\DatabaseConnection as DB;

class Blacklists
{
    private $context;
    private $blacklists;

    function __construct()
    {
        $this->context = DB::i()->getContext();
        $this->blacklists = $this->context->table("blacklists");
    }

    function getList(User $user, $page = 1): \Traversable
    {
        foreach($this->blacklists->where("author", $user->getId())->order("created DESC")->page($page, 10) as $blacklistItem)
            yield new BlacklistItem($blacklistItem);
    }

    function getByAuthorAndTarget(int $author, int $target): ?BlacklistItem
    {
        return new BlacklistItem($this->blacklists->where(["author" => $author, "target" => $target])->fetch());
    }

    function getCount(User $user): int
    {
        return sizeof($this->blacklists->where("author", $user->getId())->fetch());
    }

    function isBanned(User $author, User $target): bool
    {
        if (!$author || !$target)
            return FALSE;

        return !is_null($this->getByAuthorAndTarget($author->getId(), $target->getId()));
    }
}
