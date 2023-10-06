<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use Chandler\Database\DatabaseConnection;
use openvk\Web\Models\Entities\NoSpamLog;
use openvk\Web\Models\Entities\User;
use Nette\Database\Table\ActiveRow;

class NoSpamLogs
{
    private $context;
    private $noSpamLogs;
    
    function __construct()
    {
        $this->context = DatabaseConnection::i()->getContext();
        $this->noSpamLogs   = $this->context->table("noSpam_templates");
    }
    
    private function toNoSpamLog(?ActiveRow $ar): ?NoSpamLog
    {
        return is_null($ar) ? NULL : new NoSpamLog($ar);
    }
    
    function get(int $id): ?NoSpamLog
    {
        return $this->toNoSpamLog($this->noSpamLogs->get($id));
    }
    
    function getList(array $filter = []): \Traversable
    {
        foreach ($this->noSpamLogs->where($filter)->order("`id` DESC") as $log)
            yield new NoSpamLog($log);
    }
}
