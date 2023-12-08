<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities\Traits;
use openvk\Web\Models\Entities\User;

trait TOwnable
{
    function canBeViewedBy(?User $user = NULL): bool
    {
        # TODO: #950
        if($this->isDeleted())  {
            return false;
        }
        
        return true;
    }

    function canBeModifiedBy(User $user): bool
    {
        if(method_exists($this, "isCreatedBySystem"))
            if($this->isCreatedBySystem())
                return false;
        
        if($this->getRecord()->owner > 0)
            return $this->getRecord()->owner === $user->getId();
        else
            return $this->getOwner()->canBeModifiedBy($user);
    }
}
