<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use openvk\Web\Models\Entities\Report;
use Nette\Database\Table\ActiveRow;
use Chandler\Database\DatabaseConnection;

class Reports
{
    private $context;
    private $reports;
    
    function __construct()
    {
        $this->context = DatabaseConnection::i()->getContext();
        $this->reports = $this->context->table("reports");
    }
    
    private function toReport(?ActiveRow $ar): ?Report
    {
        return is_null($ar) ? NULL : new Report($ar);
    }
    
    function getReports(int $state = 0, int $page = 1, ?string $type = NULL, ?bool $pagination = true): \Traversable
    {
        $filter = ["deleted" => 0];
        if ($type) $filter["type"] = $type;

        $reports = $this->reports->where($filter)->order("created DESC")->group("target_id, type");
        if ($pagination)
            $reports = $reports->page($page, 15);

        foreach($reports as $t)
            yield new Report($t);
    }
    
    function getReportsCount(int $state = 0): int
    {
        return sizeof($this->reports->where(["deleted" => 0, "type" => $state])->group("target_id, type"));
    }
    
    function get(int $id): ?Report
    {
        return $this->toReport($this->reports->get($id));
    }
    
    function getByContentId(int $id): ?Report
    {
        $post = $this->reports->where(["deleted" => 0, "content_id" => $id])->fetch();

        if($post)
            return new Report($post);
        else
            return null; 
    }

    function getDuplicates(string $type, int $target_id, ?int $orig = NULL, ?int $user_id = NULL): \Traversable
    {
        $filter = ["deleted" => 0, "type" => $type, "target_id" => $target_id];
        if ($orig) $filter[] = "id != $orig";
        if ($user_id) $filter["user_id"] = $user_id;

        foreach ($this->reports->where($filter) as $report)
            yield new Report($report);
    }

    use \Nette\SmartObject;
}
