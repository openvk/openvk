<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use openvk\Web\Models\Entities\Club;
use openvk\Web\Models\Repositories\{Notifications as Notifs, Clubs, Users};

final class Notifications extends VKAPIRequestHandler
{
    function get(int $count = 10, 
                string $from = "", 
                int $offset = 0, 
                string $start_from = "",
                string $filters = "", 
                int $start_time = 0, 
                int $end_time = 0,
                int $archived = 0)
    {
        $this->requireUser();

        $res = (object)[
            "items"       => [],
            "profiles"    => [],
            "groups"      => [],
            "last_viewed" => $this->getUser()->getNotificationOffset()
        ];

        if($count > 100)
            $this->fail(125, "Count is too big");

        if(!eventdb())
            $this->fail(1289, "EventDB is disabled on this instance");

        $notifs = array_slice(iterator_to_array((new Notifs)->getNotificationsByUser($this->getUser(), $this->getUser()->getNotificationOffset(), (bool)$archived, 1, $offset + $count)), $offset);
        $tmpProfiles = [];
        foreach($notifs as $notif) {
            $sxModel = $notif->getModel(1);

            if(!method_exists($sxModel, "getAvatarUrl"))
                $sxModel = $notif->getModel(0);

            
            $tmpProfiles[] = $sxModel instanceof Club ? $sxModel->getId() * -1 : $sxModel->getId();
            $res->items[] = $notif->toVkApiStruct();
        }

        foreach(array_unique($tmpProfiles) as $id) {
            if($id > 0) {
                $sxModel = (new Users)->get($id);
                $result  = (object)[
                    "uid"        => $sxModel->getId(),
                    "first_name" => $sxModel->getFirstName(),
                    "last_name"  => $sxModel->getLastName(),
                    "photo"      => $sxModel->getAvatarUrl(),
                    "photo_medium_rec" => $sxModel->getAvatarUrl("tiny"),
                    "screen_name"      => $sxModel->getShortCode()
                ];

                $res->profiles[] = $result;
            } else {
                $sxModel = (new Clubs)->get(abs($id));
                $result  = $sxModel->toVkApiStruct($this->getUser());

                $res->groups[] = $result;
            }
        }

        return $res;
    }

    function markAsViewed()
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        try {
            $this->getUser()->updateNotificationOffset();
            $this->getUser()->save();
        } catch(\Throwable $e) {
            return 0;
        }

        return 1;
    }
}
