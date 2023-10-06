<?php declare(strict_types=1);
namespace Chandler\Database;
use Chandler\Database\DatabaseConnection;
use Nette\Database\Table\Selection;
use Nette\Database\Table\ActiveRow;
use Nette\InvalidStateException as ISE;
use openvk\Web\Models\Repositories\CurrentUser;
use openvk\Web\Models\Repositories\Logs;


abstract class DBEntity
{
    protected $record;
    protected $changes;
    protected $deleted;
    protected $user;

    protected $tableName;

    function __construct(?ActiveRow $row = NULL)
    {
        if(is_null($row)) return;

        $_table = $row->getTable()->getName();
        if($_table !== $this->tableName)
            throw new ISE("Invalid data supplied for model: table $_table is not compatible with table" . $this->tableName);

        $this->record = $row;
    }

    function __call(string $fName, array $args)
    {
        if(substr($fName, 0, 3) === "set") {
            $field = mb_strtolower(substr($fName, 3));
            $this->stateChanges($field, $args[0]);
        } else {
            throw new \Error("Call to undefined method " . get_class($this) . "::$fName");
        }
    }

    private function getTable(): Selection
    {
        return DatabaseConnection::i()->getContext()->table($this->tableName);
    }

    protected function getRecord(): ?ActiveRow
    {
        return $this->record;
    }

    protected function stateChanges(string $column, $value): void
    {
        if(!is_null($this->record))
            $t = $this->record->{$column}; #Test if column exists

        $this->changes[$column] = $value;
    }

    function getId()
    {
        return $this->getRecord()->id;
    }

    function isDeleted(): bool
    {
        return (bool) $this->getRecord()->deleted;
    }

    function unwrap(): object
    {
        return (object) $this->getRecord()->toArray();
    }

    function delete(bool $softly = true): void
    {
        $user = CurrentUser::i()->getUser();
        $user_id = is_null($user) ? (int) OPENVK_ROOT_CONF["openvk"]["preferences"]["support"]["adminAccount"] : $user->getId();

        if(is_null($this->record))
            throw new ISE("Can't delete a model, that hasn't been flushed to DB. Have you forgotten to call save() first?");

        (new Logs)->create($user_id, $this->getTable()->getName(), get_class($this), 2, $this->record->toArray(), $this->changes);

        if($softly) {
            $this->record = $this->getTable()->where("id", $this->record->id)->update(["deleted" => true]);
        } else {
            $this->record->delete();
            $this->deleted = true;
        }
    }

    function undelete(): void
    {
        if(is_null($this->record))
            throw new ISE("Can't undelete a model, that hasn't been flushed to DB. Have you forgotten to call save() first?");

        $user = CurrentUser::i()->getUser();
        $user_id = is_null($user) ? (int) OPENVK_ROOT_CONF["openvk"]["preferences"]["support"]["adminAccount"] : $user->getId();

        (new Logs)->create($user_id, $this->getTable()->getName(), get_class($this), 3, $this->record->toArray(), ["deleted" => false]);

        $this->getTable()->where("id", $this->record->id)->update(["deleted" => false]);
    }

    function save(?bool $log = true): void
    {
        if ($log) {
            $user = CurrentUser::i();
            $user_id = is_null($user) ? (int)OPENVK_ROOT_CONF["openvk"]["preferences"]["support"]["adminAccount"] : $user->getUser()->getId();
        }

        if(is_null($this->record)) {
            $this->record = $this->getTable()->insert($this->changes);

            if ($log && $this->getTable()->getName() !== "logs") {
                (new Logs)->create($user_id, $this->getTable()->getName(), get_class($this), 0, $this->record->toArray(), $this->changes);
            }
        } else {
            if ($log && $this->getTable()->getName() !== "logs") {
                (new Logs)->create($user_id, $this->getTable()->getName(), get_class($this), 1, $this->record->toArray(), $this->changes);
            }

            if ($this->deleted) {
                $this->record = $this->getTable()->insert((array)$this->record);
            } else {
                $this->getTable()->get($this->record->id)->update($this->changes);
                $this->record = $this->getTable()->get($this->record->id);
            }
        }

        $this->changes = [];
    }

    function getTableName(): string
    {
        return $this->getTable()->getName();
    }

    use \Nette\SmartObject;
}
