<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use openvk\Web\Models\Entities\User;
use openvk\Web\Models\Repositories\Users as UsersRepo;

final class Status extends VKAPIRequestHandler
{
    function get(int $user_id = 0, int $group_id = 0)
    {
        $this->requireUser();
        
        if($user_id == 0 && $group_id == 0)
            $user_id = $this->getUser()->getId();

        if($group_id > 0)
            $this->fail(501, "Group statuses are not implemented");
        else {
            $user = (new UsersRepo)->get($user_id);

            if(!$user || $user->isDeleted() || !$user->canBeViewedBy($this->getUser()))
                $this->fail(15, "Invalid user");

            $audioStatus = $user->getCurrentAudioStatus();
            if($audioStatus) {
                return [
                    "status" => $user->getStatus(),
                    "audio" => $audioStatus->toVkApiStruct(),
                ];
            }

            return $user->getStatus();
        }
    }

    function set(string $text, int $group_id = 0)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        if($group_id > 0) {
            $this->fail(501, "Group statuses are not implemented");
        } else {
            $this->getUser()->setStatus($text);
            $this->getUser()->save();
            
            return 1;
        }
    }
}
