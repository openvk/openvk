<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities\Notifications;
use openvk\Web\Models\Entities\{User, Club};

final class NewSuggestedPostsNotification extends Notification
{
    protected $actionCode = 7;
    
    function __construct(User $owner, Club $group)
    {
        parent::__construct($owner, $owner, $group, time(), "");
    }
}
