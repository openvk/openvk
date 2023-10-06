<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Util\DateTime;
use Nette\Database\Table\ActiveRow;
use openvk\Web\Models\RowModel;
use openvk\Web\Models\Entities\Club;
use Chandler\Database\DatabaseConnection;
use openvk\Web\Models\Repositories\{Applications, Comments, Notes, Reports, Users, Posts, Photos, Videos, Clubs};
use Chandler\Database\DatabaseConnection as DB;
use Nette\InvalidStateException as ISE;
use Nette\Database\Table\Selection;

class Report extends RowModel
{
    protected $tableName = "reports";

    function getId(): int
    {
        return $this->getRecord()->id;
    }
    
    function getStatus(): int
    {
        return $this->getRecord()->status;
    }
    
    function getContentType(): string
    {
        return $this->getRecord()->type;
    }
    
    function getReason(): string
    {
        return $this->getRecord()->reason;
    }
    
    function getTime(): DateTime
    {
        return new DateTime($this->getRecord()->date);
    }
    
    function isDeleted(): bool
    {
        if ($this->getRecord()->deleted === 0) 
        {
            return false;
        } elseif ($this->getRecord()->deleted === 1) {
            return true;
        }
    }
    
    function authorId(): int
    {
        return $this->getRecord()->user_id;
    }
    
    function getUser(): User
    {
        return (new Users)->get((int) $this->getRecord()->user_id);
    }

    function getContentId(): int
    {
        return (int) $this->getRecord()->target_id;
    }

    function getContentObject()
    {
        if ($this->getContentType() == "post")         return (new Posts)->get($this->getContentId());
        else if ($this->getContentType() == "photo")   return (new Photos)->get($this->getContentId());
        else if ($this->getContentType() == "video")   return (new Videos)->get($this->getContentId());
        else if ($this->getContentType() == "group")   return (new Clubs)->get($this->getContentId());
        else if ($this->getContentType() == "comment") return (new Comments)->get($this->getContentId());
        else if ($this->getContentType() == "note")    return (new Notes)->get($this->getContentId());
        else if ($this->getContentType() == "app")     return (new Applications)->get($this->getContentId());
        else if ($this->getContentType() == "user")    return (new Users)->get($this->getContentId());
        else return null;
    }

    function getAuthor(): RowModel
    {
        return (new Posts)->get($this->getContentId())->getOwner();
    }

    function getReportAuthor(): User
    {
        return (new Users)->get($this->getRecord()->user_id);
    }

    function banUser($initiator)
    {
        $reason = $this->getContentType() !== "user" ? ("**content-" . $this->getContentType() . "-" . $this->getContentId() . "**") : ("Подозрительная активность");
        $this->getAuthor()->ban($reason, false, time() + $this->getAuthor()->getNewBanTime(), $initiator);
    }

    function deleteContent()
    {
        if ($this->getContentType() !== "user") {
            $pubTime = $this->getContentObject()->getPublicationTime();
            if (method_exists($this->getContentObject(), "getName")) {
                $name = $this->getContentObject()->getName();
                $placeholder = "$pubTime ($name)";
            } else {
                $placeholder = "$pubTime";
            }

            if ($this->getAuthor() instanceof Club) {
                $name = $this->getAuthor()->getName();
                $this->getAuthor()->getOwner()->adminNotify("Ваш контент, который опубликовали $placeholder в созданной вами группе \"$name\" был удалён модераторами инстанса. За повторные или серьёзные нарушения группу могут заблокировать.");
            } else {
                $this->getAuthor()->adminNotify("Ваш контент, который вы опубликовали $placeholder был удалён модераторами инстанса. За повторные или серьёзные нарушения вас могут заблокировать.");
            }
            $this->getContentObject()->delete($this->getContentType() !== "app");
        }

        $this->delete();
    }

    function getDuplicates(): \Traversable
    {
        return (new Reports)->getDuplicates($this->getContentType(), $this->getContentId(), $this->getId());
    }

    function getDuplicatesCount(): int
    {
        return count(iterator_to_array($this->getDuplicates()));
    }

    function hasDuplicates(): bool
    {
        return $this->getDuplicatesCount() > 0;
    }

    function getContentName(): string
    {
        if (method_exists($this->getContentObject(), "getCanonicalName"))
            return $this->getContentObject()->getCanonicalName();

        return $this->getContentType() . " #" . $this->getContentId();
    }

    public function delete(bool $softly = true): void
    {
        if ($this->hasDuplicates()) {
            foreach ($this->getDuplicates() as $duplicate) {
                $duplicate->setDeleted(1);
                $duplicate->save();
            }
        }

        $this->setDeleted(1);
        $this->save();
    }
}
