<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use openvk\Web\Models\Entities\{BugReport, BugReportComment};
use Nette\Database\Table\ActiveRow;
use Chandler\Database\DatabaseConnection;

class BugtrackerComments
{
    private $context;
    private $comments;
    
    function __construct()
    {
        $this->context = DatabaseConnection::i()->getContext();
        $this->comments   = $this->context->table("bt_comments");
    }

    private function toComment(?ActiveRow $ar)
    {
        return is_null($ar) ? NULL : new BugReportComment($ar);
    }

    function get(int $id): ?BugReportComment
    {
        return $this->toComment($this->comments->get($id));
    }

    function getByReport(?BugReport $report): \Traversable
    {
        foreach($this->comments->where(["report" => $report->getId()])->order("id ASC") as $comment)
            yield new BugReportComment($comment);
    }
}