<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use Nette\Database\Table\ActiveRow;
use Chandler\Database\DatabaseConnection as DB;
use openvk\Web\Models\Entities\User;
use Chandler\Security\User as ChandlerUser;

class ChandlerGroups
{
    private $context;
    private $groups;

    public function __construct()
    {
        $this->context = DB::i()->getContext();
        $this->groups  = $this->context->table("ChandlerGroups");
        $this->members = $this->context->table("ChandlerACLRelations");
        $this->perms   = $this->context->table("ChandlerACLGroupsPermissions");
    }

    function get(string $UUID): ?ActiveRow
    {
        return $this->groups->where("id", $UUID)->fetch();
    }

    function getList(): \Traversable
    {
        foreach($this->groups as $group) yield $group;
    }

    function getMembersById(string $UUID): \Traversable
    {
        foreach($this->members->where("group", $UUID) as $member)
            yield (new Users)->getByChandlerUser(
                new ChandlerUser($this->context->table("ChandlerUsers")->where("id", $member->user)->fetch())
            );
    }

    function getUsersMemberships(string $UUID): \Traversable
    {
        foreach($this->members->where("user", $UUID) as $member) yield $member;
    }

    function getPermissionsById(string $UUID): \Traversable
    {
        foreach($this->perms->where("group", $UUID) as $perm) yield $perm;
    }

    function isUserAMember(string $GID, string $UID): bool
    {
        return ($this->context->query("SELECT * FROM `ChandlerACLRelations` WHERE `group` = ? AND `user` = ?", $GID, $UID)) !== NULL;
    }
}
