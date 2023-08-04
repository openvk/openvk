<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Entities\{BlacklistItem};
use openvk\Web\Models\Repositories\{Blacklists, Users};
use Chandler\Database\DatabaseConnection as DB;

final class BlacklistPresenter extends OpenVKPresenter
{
    private $blacklists;

    function __construct(Blacklists $blacklists)
    {
        $this->blacklists = $blacklists;
    }

    function renderAddToBlacklist(): void
    {
        $this->willExecuteWriteAction();
        $this->assertUserLoggedIn();

        $record = new BlacklistItem;
        $target = (new Users)->get((int) $this->postParam("id"));

        $record->setAuthor($this->user->identity->getId());
        $record->setTarget($this->postParam("id"));
        $record->setCreated(time());
        $record->save();

        $this->flashFail("succ", tr("success"), tr("user_blacklisted", $target->getCanonicalName()));
    }

    function renderRemoveFromBlacklist(): void
    {
        $this->willExecuteWriteAction();
        $this->assertUserLoggedIn();

        $record = $this->blacklists->getByAuthorAndTarget($this->user->identity->getId(), $this->postParam("id"));
        $name = $record->getTarget()->getCanonicalName();
        $record->delete(false);

        $this->flashFail("succ", tr("success"),  tr("user_removed_from_the_blacklist", $name));
    }
}
