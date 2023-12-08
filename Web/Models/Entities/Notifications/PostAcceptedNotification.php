<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities\Notifications;
use openvk\Web\Models\Entities\{User, Club, Post};

final class PostAcceptedNotification extends Notification
{
    protected $actionCode = 6;
    
    function __construct(User $author, Post $post, Club $group)
    {
        parent::__construct($author, $post, $group, time(), "");
    }
}
