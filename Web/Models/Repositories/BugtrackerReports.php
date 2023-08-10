<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use openvk\Web\Models\Entities\{BugReport, User};
use openvk\Web\Models\Repositories\{BugtrackerProducts};
use Nette\Database\Table\ActiveRow;
use Chandler\Database\DatabaseConnection;

class BugtrackerReports
{
    private $context;
    private $reports;
    private $reportsPerPage = 5;
    
    function __construct()
    {
        $this->context = DatabaseConnection::i()->getContext();
        $this->reports   = $this->context->table("bugs");
    }

    private function toReport(?ActiveRow $ar)
    {
        return is_null($ar) ? NULL : new BugReport($ar);
    }

    function get(int $id): ?BugReport
    {
        return $this->toReport($this->reports->get($id));
    }

    function getAllReports(User $user, int $page = 1): \Traversable
    {
        $reports = $this->reports->where(["deleted" => NULL])->order("created DESC")->page($page, 5);

        foreach($reports as $report)
            yield new BugReport($report);
    }

    function getReports(int $product_id = 0, int $priority = 0, int $page = 1, User $user = NULL): \Traversable
    {
        $filter = ["deleted" => NULL];
        $product_id && $filter["product_id"] = $product_id;
        $priority && $filter["priority"] = $priority;

        $product = (new BugtrackerProducts)->get($product_id);
        if (!$product->hasAccess($user))
            return false;

        foreach($this->reports->where($filter)->order("created DESC")->page($page, 5) as $report)
            yield new BugReport($report);
    }

    function getReportsCount(int $product_id = 0, int $priority = 0): int
    {
        $filter = ["deleted" => NULL];
        $product_id && $filter["product_id"] = $product_id;
        $priority && $filter["priority"] = $priority;

        return sizeof($this->reports->where($filter));
    }

    function getByReporter(int $reporter_id, int $page = 1): \Traversable
    {
        foreach($this->reports->where(["deleted" => NULL, "reporter" => $reporter_id])->order("created DESC")->page($page, 5) as $report)
            yield new BugReport($report);
    }

    function getCountByReporter(int $reporter_id): ?int
    {
        return sizeof($this->reports->where(["deleted" => NULL, "reporter" => $reporter_id]));
    }

    function getSuccCountByReporter(int $reporter_id): ?int
    {
        return sizeof($this->reports->where(["deleted" => NULL, "reporter" => $reporter_id, "status" => "<= 4"]));
    }
}