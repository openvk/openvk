<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use openvk\Web\Models\Exceptions\InvalidUserNameException;

final class Account extends VKAPIRequestHandler
{
    function getProfileInfo(): object
    {
        $this->requireUser();

        return (object) [
            "first_name"       => $this->getUser()->getFirstName(),
            "id"               => $this->getUser()->getId(),
            "last_name"        => $this->getUser()->getLastName(),
            "home_town"        => $this->getUser()->getHometown(),
            "status"           => $this->getUser()->getStatus(),
            "audio_status"     => is_null($this->getUser()->getCurrentAudioStatus()) ? NULL : $this->getUser()->getCurrentAudioStatus()->toVkApiStruct($this->getUser()),
            "bdate"            => is_null($this->getUser()->getBirthday()) ? '01.01.1970' : $this->getUser()->getBirthday()->format('%e.%m.%Y'),
            "bdate_visibility" => $this->getUser()->getBirthdayPrivacy(),
            "phone"            => "+420 ** *** 228",                       # TODO
            "relation"         => $this->getUser()->getMaritalStatus(),
            "sex"              => $this->getUser()->isFemale() ? 1 : 2
        ];
    }

    function getInfo(): object
    {
        $this->requireUser();

        return (object) [
            "2fa_required"                  => $this->getUser()->is2faEnabled() ? 1 : 0,
            "country"                       => "CZ",                                  # TODO
            "eu_user"                       => false,                                 # TODO
            "https_required"                => 1,
            "intro"                         => 0,
            "community_comments"            => false,
            "is_live_streaming_enabled"     => false,
            "is_new_live_streaming_enabled" => false,
            "lang"                          => 1,
            "no_wall_replies"               => 0,
            "own_posts_default"             => 0
        ];
    }

    function setOnline(): int
    {
        $this->requireUser();

        $this->getUser()->updOnline($this->getPlatform());
        
        return 1;
    }

    function setOffline(): int
    {
        $this->requireUser();

        # Цiй метод є заглушка
 
        return 1;
    }

    function getAppPermissions(): int
    {
        return 9355263;
    }

    function getCounters(string $filter = ""): object
    {
        $this->requireUser();
        
        return (object) [
            "friends"       => $this->getUser()->getFollowersCount(),
            "notifications" => $this->getUser()->getNotificationsCount(),
            "messages"      => $this->getUser()->getUnreadMessagesCount()
        ];

        # TODO: Filter
    }

    function saveProfileInfo(string $first_name = "", string $last_name = "", string $screen_name = "", int $sex = -1, int $relation = -1, string $bdate = "", int $bdate_visibility = -1, string $home_town = "", string $status = ""): object 
    {
        $this->requireUser();
        $this->willExecuteWriteAction();
        
        $user = $this->getUser();

        $output = [
            "changed" => 0,
        ];

        if(!empty($first_name) || !empty($last_name)) {
            $output["name_request"] = [
                "id" => random_int(1, 2048), # For compatibility with original VK API
                "status" => "success",
                "first_name" => !empty($first_name) ? $first_name : $user->getFirstName(),
                "last_name" => !empty($last_name) ? $last_name : $user->getLastName(),
            ];

            try {
                if(!empty($first_name))
                    $user->setFirst_name($first_name);
                if(!empty($last_name))
                    $user->setLast_Name($last_name);
            } catch (InvalidUserNameException $e) {
                $output["name_request"]["status"] = "declined";
                return (object) $output;
            }
        }

        if(!empty($screen_name))
            if (!$user->setShortCode($screen_name))
                $this->fail(1260, "Invalid screen name");

        # For compatibility with original VK API
        if($sex > 0)
            $user->setSex($sex == 1 ? 1 : 0);
            
        if($relation > -1)
            $user->setMarital_Status($relation);

        if(!empty($bdate)) {
            $birthday = strtotime($bdate);
            if (!is_int($birthday))
                $this->fail(100, "invalid value of bdate.");

            $user->setBirthday($birthday);
        }

        # For compatibility with original VK API
        switch($bdate_visibility) {
            case 0:
                $this->fail(946, "Hiding date of birth is not implemented.");
                break;
            case 1:
                $user->setBirthday_privacy(0);
                break;
            case 2:
                $user->setBirthday_privacy(1);
        }
        
        if(!empty($home_town))
            $user->setHometown($home_town);

        if(!empty($status)) 
            $user->setStatus($status);
        
        if($sex > 0 || $relation > -1 || $bdate_visibility > 1 || !empty("$first_name$last_name$screen_name$bdate$home_town$status")) {
            $output["changed"] = 1;
            $user->save();
        }

        return (object) $output;
    }
}
