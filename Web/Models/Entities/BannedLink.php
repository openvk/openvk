<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Models\RowModel;
use openvk\Web\Models\Entities\{User};
use openvk\Web\Models\Repositories\{Users};
use Nette\Database\Table\ActiveRow;

class BannedLink extends RowModel
{
    protected $tableName = "links_banned";
    private $overrideContentColumn = "reason";

    function getId(): int
    {
        return $this->getRecord()->id;
    }

    function getDomain(): string
    {
        return $this->getRecord()->domain;
    }

    function getReason(): string
    {
        return $this->getRecord()->reason ?? tr("url_is_banned_default_reason");
    }

    function getInitiator(): ?User
    {
        return (new Users)->get($this->getRecord()->initiator);
    }

    function getComment(): string
    {
        return OPENVK_ROOT_CONF["openvk"]["preferences"]["susLinks"]["showReason"]
            ? tr("url_is_banned_comment_r", OPENVK_ROOT_CONF["openvk"]["appearance"]["name"], $this->getReason())
            : tr("url_is_banned_comment", OPENVK_ROOT_CONF["openvk"]["appearance"]["name"]);
    }

    function getRegexpRule(): string
    {
        return "/^" . $this->getDomain() . "\/" . $this->getRawRegexp() . "$/i";
    }

    function getRawRegexp(): string
    {
        return $this->getRecord()->regexp_rule;
    }
}
