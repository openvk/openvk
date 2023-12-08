<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use Chandler\Database\DatabaseConnection;
use openvk\Web\Models\Repositories\Clubs;
use openvk\Web\Models\Repositories\Users;
use openvk\Web\Models\Entities\Photo;
use openvk\Web\Models\RowModel;
use openvk\Web\Util\DateTime;

/**
 * Message entity.
 */
class Message extends RowModel
{
    protected $tableName = "messages";
    
    /**
     * Get origin of the message.
     * 
     * Returns either user or club.
     * 
     * @returns User|Club
     */
    function getSender(): ?RowModel
    {
        if($this->getRecord()->sender_type === 'openvk\Web\Models\Entities\User')
            return (new Users)->get($this->getRecord()->sender_id);
        else if($this->getRecord()->sender_type === 'openvk\Web\Models\Entities\Club')
            return (new Clubs)->get($this->getRecord()->sender_id);
    }
    
    /**
     * Get the destination of the message.
     * 
     * Returns either user or club.
     * 
     * @returns User|Club
     */
    function getRecipient(): ?RowModel
    {
        if($this->getRecord()->recipient_type === 'openvk\Web\Models\Entities\User')
            return (new Users)->get($this->getRecord()->recipient_id);
        else if($this->getRecord()->recipient_type === 'openvk\Web\Models\Entities\Club')
            return (new Clubs)->get($this->getRecord()->recipient_id);
    }
    
    function getUnreadState(): int
    {
        trigger_error("TODO: use isUnread", E_USER_DEPRECATED);
        
        return (int) $this->isUnread();
    }
    
    /**
     * Get date of initial publication.
     * 
     * @returns DateTime
     */
    function getSendTime(): DateTime
    {
        return new DateTime($this->getRecord()->created);
    }

    function getSendTimeHumanized(): string
    {
        $dateTime = new DateTime($this->getRecord()->created);

        if($dateTime->format("%d.%m.%y") == ovk_strftime_safe("%d.%m.%y", time())) {
            return $dateTime->format("%T %p");
        } else {
            return $dateTime->format("%d.%m.%y");
        }
    }
    
    /**
     * Get date of last edit, if any edits were made, otherwise null.
     * 
     * @returns DateTime|null
     */
    function getEditTime(): ?DateTime
    {
        $edited = $this->getRecord()->edited;
        if(is_null($edited)) return NULL;
        
        return new DateTime($edited);
    }
    
    /**
     * Is this message an ad?
     * 
     * Messages can never be ads.
     * 
     * @returns false
     */
    function isAd(): bool
    {
        return false;
    }
    
    function isUnread(): bool
    {
        return (bool) $this->getRecord()->unread;
    }
    
    /**
     * Simplify to array
     * 
     * @returns array
     */
    function simplify(): array
    {
        $author = $this->getSender();
        
        $attachments = [];
        foreach($this->getChildren() as $attachment) {
            if($attachment instanceof Photo) {
                $attachments[] = [
                    "type"  => "photo",
                    "link"  => "/photo" . $attachment->getPrettyId(),
                    "photo" => [
                        "url"     => $attachment->getURL(),
                        "caption" => $attachment->getDescription(),
                    ],
                ];
            } else {
                $attachments[] = [
                    "type"  => "unknown"
                ];
                
                # throw new \Exception("Unknown attachment type: " . get_class($attachment));
            }
        }
        
        return [
            "uuid"   => $this->getId(),
            "sender" => [
                "id"     => $author->getId(),
                "link"   => $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . $author->getURL(),
                "avatar" => $author->getAvatarUrl(),
                "name"   => $author->getFirstName().$unreadmsg,
            ],
            "timing" => [
                "sent"   => (string) $this->getSendTimeHumanized(),
                "edited" => is_null($this->getEditTime()) ? NULL : (string) $this->getEditTime(),
            ],
            "text"        => $this->getText(),
            "read"        => !$this->isUnread(),
            "attachments" => $attachments,
        ];
    }
    
    use Traits\TRichText;
    use Traits\TAttachmentHost;
}
