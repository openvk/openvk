<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use Nette\Database\Table\ActiveRow;
use Chandler\Database\DatabaseConnection as DB;
use openvk\Web\Models\Entities\User;
use Chandler\Security\User as ChandlerUser;

class ChandlerUsers
{
    private $context;
    private $users;

    public function __construct()
    {
        $this->context = DB::i()->getContext();
        $this->users   = $this->context->table("ChandlerUsers");
    }

    private function toUser(?ActiveRow $ar): ?ChandlerUser
    {
        return is_null($ar) ? NULL : (new User($ar))->getChandlerUser();
    }

    function get(int $id): ?ChandlerUser
    {
        return (new Users)->get($id)->getChandlerUser();
    }

    function getById(string $UUID): ?ChandlerUser
    {
        $user = $this->users->where("id", $UUID)->fetch();
        return $user ? new ChandlerUser($user) : NULL;
    }

    function getList(int $page = 1): \Traversable
    {
        foreach($this->users as $user)
            yield new ChandlerUser($user);
    }
}
