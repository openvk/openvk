<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Models\RowModel;
use openvk\Web\Util\DateTime;
use openvk\Web\Models\Repositories\{Users};
use Nette\Database\Table\ActiveRow;

class NoSpamLog extends RowModel
{
    protected $tableName = "noSpam_templates";

    function getId(): int
    {
        return $this->getRecord()->id;
    }

    function getUser(): ?User
    {
        return (new Users)->get($this->getRecord()->user);
    }

    function getModel(): string
    {
        return $this->getRecord()->model;
    }

    function getRegex(): ?string
    {
        return $this->getRecord()->regex;
    }

    function getRequest(): ?string
    {
        return $this->getRecord()->request;
    }

    function getCount(): int
    {
        return $this->getRecord()->count;
    }

    function getTime(): DateTime
    {
        return new DateTime($this->getRecord()->time);
    }

    function getItems(): ?array
    {
        return explode(",", $this->getRecord()->items);
    }

    function getTypeRaw(): int
    {
        return $this->getRecord()->ban_type;
    }

    function getType(): string
    {
        switch ($this->getTypeRaw()) {
            case 1: return "О";
            case 2: return "Б";
            case 3: return "ОБ";
            default: return (string) $this->getTypeRaw();
        }
    }

    function isRollbacked(): bool
    {
        return !is_null($this->getRecord()->rollback);
    }
}
