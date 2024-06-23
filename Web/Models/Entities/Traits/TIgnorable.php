<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities\Traits;
use Chandler\Database\DatabaseConnection;
use openvk\Web\Models\Entities\User;

trait TIgnorable 
{
    function isIgnoredBy(User $user): bool
    {
        $ctx  = DatabaseConnection::i()->getContext();
        $data = [
            "owner"            => $user->getId(),
            "ignored_source"   => $this->getRealId(),
        ];

        $sub = $ctx->table("ignored_sources")->where($data);

        if(!$sub->fetch()) {
            return false;
        }

        return true;
    }

    function getIgnoresCount()
    {
        return sizeof(DatabaseConnection::i()->getContext()->table("ignored_sources")->where("ignored_source", $this->getRealId()));
    }

    function toggleIgnore(User $user): bool
    {
        if($this->isIgnoredBy($user)) {
            DatabaseConnection::i()->getContext()->table("ignored_sources")->where([
                "owner"          => $user->getId(),
                "ignored_source" => $this->getRealId(),
            ])->delete();

            return false;
        } else {
            DatabaseConnection::i()->getContext()->table("ignored_sources")->insert([
                "owner"          => $user->getId(),
                "ignored_source" => $this->getRealId(),
            ]);

            return true;
        }
    }
}
