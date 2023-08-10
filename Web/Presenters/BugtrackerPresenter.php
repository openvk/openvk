<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use Chandler\Session\Session;
use Chandler\Database\DatabaseConnection as DB;
use openvk\Web\Models\Repositories\{BugtrackerProducts, BugtrackerReports, BugtrackerComments, Users};
use openvk\Web\Models\Entities\{BugtrackerProduct, BugReport};

final class BugtrackerPresenter extends OpenVKPresenter
{
    private $reports;
    private $products;
    private $comments;
    
    function __construct(BugtrackerReports $reports, BugtrackerProducts $products, BugtrackerComments $comments)
    {
        $this->reports  = $reports;
        $this->products = $products;
        $this->comments = $comments;
        
        parent::__construct();
    }

    function renderIndex(): void
    {
        $this->assertUserLoggedIn();
        $this->template->mode = in_array($this->queryParam("act"), ["list", "products", "new_product", "reporter", "new"]) ? $this->queryParam("act") : "list";

        if($this->queryParam("act") === "show")
            $this->redirect("/bug" . $this->queryParam("id"));

        $this->template->user = $this->user;
        $this->template->page = (int) ($this->queryParam("p") ?? 1);

        $this->template->open_products = $this->products->getOpen();

        switch ($this->template->mode) {
            case 'reporter':
                $this->template->reporter = (new Users)->get((int) $this->queryParam("id"));
                $this->template->reporter_stats = [$this->reports->getCountByReporter((int) $this->queryParam("id")), $this->reports->getSuccCountByReporter((int) $this->queryParam("id"))];

                $this->template->iterator = $this->reports->getByReporter((int) $this->queryParam("id"), $this->template->page);
                $this->template->count = $this->reports->getCountByReporter((int) $this->queryParam("id"));
                break;
            
            case 'products':
                $this->template->filter = $this->queryParam("filter") ?? "all";
                $this->template->count = $this->products->getCount($this->template->filter, $this->user->identity);
                $this->template->iterator = $this->products->getFiltered($this->user->identity, $this->template->filter, $this->template->page);
                break;

            default:
                $this->template->count    = $this->reports->getReportsCount((int) $this->queryParam("product"), (int) $this->queryParam("priority"), $this->user->identity);
                $this->template->iterator = $this->queryParam("product") 
                    ? $this->reports->getReports((int) $this->queryParam("product"), (int) $this->queryParam("priority"), $this->template->page, $this->user->identity) 
                    : $this->reports->getAllReports($this->user->identity, $this->template->page);
                break;
        }

        $this->template->isModerator = $this->user->identity->isBtModerator();
    }

    function renderView(int $id): void
    {
        $this->assertUserLoggedIn();

        $this->template->user = $this->user;

        if ($this->template->user->identity->isBannedInBt())
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));

        if (!$this->reports->get($id)->getProduct()->hasAccess($this->template->user->identity))
            $this->flashFail("err", tr("forbidden"));

        if ($this->reports->get($id)) {
            $this->template->bug = $this->reports->get($id);
            $this->template->reporter = $this->template->bug->getReporter();
            $this->template->comments = $this->comments->getByReport($this->template->bug);

            $this->template->isModerator = $this->user->identity->isBtModerator();
        } else {
            $this->flashFail("err", tr("bug_tracker_report_not_found"));
        }
    }

    function renderChangeStatus(int $report_id): void
    {
        $this->assertUserLoggedIn();
        $this->assertNoCSRF();
        $this->willExecuteWriteAction();

        if ($this->user->identity->isBannedInBt())
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));

        $status = $this->postParam("status");
        $comment = $this->postParam("text");
        $points = $this->postParam("points-count");
        $list = [
            tr("bug_tracker_status_open"),
            tr("bug_tracker_status_under_review"),
            tr("bug_tracker_status_in_progress"),
            tr("bug_tracker_status_fixed"),
            tr("bug_tracker_status_closed"),
            tr("bug_tracker_status_requires_adjustment"),
            tr("bug_tracker_status_locked"),
            tr("bug_tracker_status_rejected"),
            "Переоткрыт"
        ];

        $report = (new BugtrackerReports)->get($report_id);
        $report->setStatus($status);

        if ($points >= 0)
            DB::i()->getContext()->query("UPDATE `profiles` SET `coins` = `coins` + " . $points . " WHERE `id` = " . $report->getReporter()->getId());
        else
            $this->flashFail("err", tr("error"), "Число голосов должно быть больше или равно 0.");

        $report->save();

        $this->createComment($report, $comment, tr("bug_tracker_new_report_status") . " — $list[$status]", TRUE, FALSE, $points);
        $this->flashFail("succ", tr("changes_saved"), tr("bug_tracker_new_report_status") . " — $list[$status]");
    }

    function renderChangePriority(int $report_id): void
    {
        $this->assertUserLoggedIn();
        $this->assertNoCSRF();
        $this->willExecuteWriteAction();

        if ($this->user->identity->isBannedInBt())
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));

        $priority = $this->postParam("priority");
        $comment = $this->postParam("text");
        $points = $this->postParam("points-count");
        $list = [
            tr("bug_tracker_priority_feature"),
            tr("bug_tracker_priority_low"),
            tr("bug_tracker_priority_medium"),
            tr("bug_tracker_priority_high"),
            tr("bug_tracker_priority_critical"),
            tr("bug_tracker_priority_vulnerability")
        ];

        $report = (new BugtrackerReports)->get($report_id);
        $report->setPriority($priority);

        if ($points >= 0)
            DB::i()->getContext()->query("UPDATE `profiles` SET `coins` = `coins` + " . $points . " WHERE `id` = " . $report->getReporter()->getId());
        else
            $this->flashFail("err", tr("error"), "Число голосов должно быть больше или равно 0.");

        $report->save();

        $this->createComment($report, $comment, tr("bug_tracker_new_report_priority") . " — $list[$priority]", TRUE, FALSE, $points);
        $this->flashFail("succ", tr("changes_saved"), tr("bug_tracker_new_report_priority") . " — $list[$priority]");
    }

    function createComment(?BugReport $report, string $text, string $label = "", bool $is_moder = FALSE, bool $is_hidden = FALSE, string $point_actions = NULL)
    {
        if ($this->user->identity->isBannedInBt())
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));

        $moder = $this->user->identity->isBtModerator();

        if (!$text && !$label)
            $this->flashFail("err", tr("error"), tr("bug_tracker_empty_comment"));

        if (in_array($report->getRawStatus(), [5, 6]) && !$moder)
            $this->flashFail("err", tr("forbidden"));

        DB::i()->getContext()->table("bt_comments")->insert([
            "report" => $report->getId(),
            "author" => $this->user->identity->getId(),
            "is_moder" => $moder === $is_moder,
            "is_hidden" => $moder === $is_hidden,
            "point_actions" => $point_actions,
            "text" => $text,
            "label" => $label,
            "created" => time()
        ]);

        $this->flashFail("succ", tr("bug_tracker_success"), tr("bug_tracker_comment_sent"));
    }

    function renderAddComment(int $report_id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();

        if ($this->user->identity->isBannedInBt())
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));

        $text = $this->postParam("text");
        $is_moder = (bool) $this->postParam("is_moder");
        $is_hidden = (bool) $this->postParam("is_hidden");

        $this->createComment($this->reports->get($report_id), $text, "", $is_moder, $is_hidden);
    }

    function renderCreate(): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();

        if ($this->user->identity->isBannedInBt())
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));

        $title = $this->postParam("title");
        $text = $this->postParam("text");
        $priority = $this->postParam("priority");
        $product = $this->postParam("product");
        $device = $this->postParam("device");

        if (!$title || !$text || !$product || !$device || $priority === NULL)
            $this->flashFail("err", tr("error"), tr("bug_tracker_fields_error"));
        
        $id = DB::i()->getContext()->table("bugs")->insert([
            "reporter" => $this->user->identity->getId(),
            "title" => $title,
            "text" => $text,
            "product_id" => $product,
            "device" => $device,
            "priority" => $priority,
            "created" => time()
        ]);

        $this->redirect("/bug$id");
    }

    function renderCreateProduct(): void
    {
        $this->assertUserLoggedIn();
        $this->assertNoCSRF();
        $this->willExecuteWriteAction();

        if ($this->user->identity->isBannedInBt())
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));

        if (!$this->user->identity->isBtModerator())
            $this->flashFail("err", tr("forbidden"));

        $title = $this->postParam("title");
        $description = $this->postParam("description");
        $is_closed = (bool) $this->postParam("is_closed");
        $is_private = (bool) $this->postParam("is_private");

        DB::i()->getContext()->table("bt_products")->insert([
            "creator_id" => $this->user->identity->getId(),
            "title" => $title,
            "description" => $description,
            "created" => time(),
            "closed" => $is_closed,
            "private" => $is_private
        ]);

        $this->redirect("/bugtracker?act=products");
    }

    function renderReproduced(int $report_id): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();

        if ($this->user->identity->isBannedInBt())
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));

        $report = (new BugtrackerReports)->get($report_id);
        
        if ($report->getReporter()->getId() === $this->user->identity->getId())
            $this->flashFail("err", tr("forbidden"));

        DB::i()->getContext()->table("bugs")->where("id", $report_id)->update(["reproduced" => $report->getReproducedCount() + 1]);

        $this->flashFail("succ", tr("bug_tracker_success"), tr("bug_tracker_reproduced_text"));
    }

    function renderManageAccess(int $product_id): void
    {
        $this->assertUserLoggedIn();
        $this->assertNoCSRF();
        $this->willExecuteWriteAction();

        if ($this->user->identity->isBannedInBt())
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));

        if (!$this->user->identity->isBtModerator())
            $this->flashFail("err", tr("forbidden"));

        $user = (new Users)->get((int) $this->postParam("uid"));
        $product = $this->products->get($product_id);
        $action = $this->postParam("action");

        if ($action === "give") {
            if (!$product->isPrivate() || $product->hasAccess($user))
            $this->flashFail("err", "Ошибка", $user->getCanonicalName() . " уже имеет доступ к продукту " . $product->getCanonicalName());

            DB::i()->getContext()->table("bt_products_access")->insert([
                "created" => time(),
                "tester" => $user->getId(),
                "product" => $product_id,
                "moderator" => $this->user->identity->getId()
            ]);

            $this->flashFail("succ", "Успех", $user->getCanonicalName() . " теперь имеет доступ к продукту " . $product->getCanonicalName());
        } else {
            if ($user->isBtModerator())
                $this->flashFail("err", "Ошибка", "Невозможно забрать доступ к продукту у модератора.");

            if (!$product->hasAccess($user))
                $this->flashFail("err", "Ошибка", $user->getCanonicalName() . " и так не имеет доступа  к продукту " . $product->getCanonicalName());

            DB::i()->getContext()->table("bt_products_access")->where([
                "tester" => $user->getId(),
                "product" => $product_id,
            ])->delete();

            $this->flashFail("succ", "Успех", $user->getCanonicalName() . " теперь не имеет доступа к продукту " . $product->getCanonicalName());
        }
    }

    function renderManagePrivacy(int $product_id): void
    {
        $this->assertUserLoggedIn();
        $this->assertNoCSRF();
        $this->willExecuteWriteAction();

        if ($this->user->identity->isBannedInBt())
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));

        if (!$this->user->identity->isBtModerator())
            $this->flashFail("err", tr("forbidden"));

        $user = (new Users)->get((int) $this->postParam("uid"));
        $product = $this->products->get($product_id);
        $action = $this->postParam("action");

        if ($action == "open") {
            if (!$product->isPrivate())
                $this->flashFail("err", "Ошибка", "Продукт " . $product->getCanonicalName() . " и так открытый.");

            DB::i()->getContext()->table("bt_products")->where("id", $product_id)->update(["private" => 0]);

            $this->flashFail("succ", "Успех", "Продукт " . $product->getCanonicalName() . " теперь открытый.");
        } else {
           if ($product->isPrivate())
                $this->flashFail("err", "Ошибка", "Продукт " . $product->getCanonicalName() . " и так приватный.");

            DB::i()->getContext()->table("bt_products")->where("id", $product_id)->update(["private" => 1]);

            $this->flashFail("succ", "Успех", "Продукт " . $product->getCanonicalName() . " теперь приватный.");
        }
    }

    function renderManageStatus(int $product_id): void
    {
        $this->assertUserLoggedIn();
        $this->assertNoCSRF();
        $this->willExecuteWriteAction();

        if ($this->user->identity->isBannedInBt())
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));

        if (!$this->user->identity->isBtModerator())
            $this->flashFail("err", tr("forbidden"));

        $user = (new Users)->get((int) $this->postParam("uid"));
        $product = $this->products->get($product_id);
        $action = $this->postParam("action");

        if ($action == "open") {
            if (!$product->isClosed())
                $this->flashFail("err", "Ошибка", "Продукт " . $product->getCanonicalName() . " и так открытый.");
            
            DB::i()->getContext()->table("bt_products")->where("id", $product_id)->update(["closed" => 0]);

            $this->flashFail("succ", "Успех", "Продукт " . $product->getCanonicalName() . " теперь открытый.");
        } else {
            if ($product->isClosed())
                $this->flashFail("err", "Ошибка", "Продукт " . $product->getCanonicalName() . " и так закрытый.");
            
            DB::i()->getContext()->table("bt_products")->where("id", $product_id)->update(["closed" => 1]);

            $this->flashFail("succ", "Успех", "Продукт " . $product->getCanonicalName() . " теперь закрытый.");
        }
    }

    function renderKickTester(int $uid): void
    {
        $this->assertUserLoggedIn();
        $this->assertNoCSRF();
        $this->willExecuteWriteAction();

        if ($this->user->identity->isBannedInBt())
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));

        if (!$this->user->identity->isBtModerator())
            $this->flashFail("err", tr("forbidden"));

        $user = (new Users)->get($uid);

        $comment = $this->postParam("comment") ?? "";

        $user->setBlock_in_bt_reason($comment);
        $user->save();

        if ($this->postParam("ban_reports"))
            DB::i()->getConnection()->query("UPDATE bugs SET status = 6 WHERE reporter = " . $uid);

        $this->flashFail("succ", "Успех", $user->getCanonicalName() . " был исключён из программы OVK Testers.");
    }

    function renderUnbanTester(int $uid): void
    {
        $this->assertUserLoggedIn();
        $this->assertNoCSRF();
        $this->willExecuteWriteAction();

        if ($this->user->identity->isBannedInBt())
            $this->flashFail("err", tr("not_enough_permissions"), tr("not_enough_permissions_comment"));

        if (!$this->user->identity->isBtModerator())
            $this->flashFail("err", tr("forbidden"));

        $user = (new Users)->get($uid);

        $user->setBlock_in_bt_reason(NULL);
        $user->save();

        if ($this->postParam("unban_reports"))
            DB::i()->getConnection()->query("UPDATE bugs SET status = 8 WHERE reporter = " . $uid);

        $this->flashFail("succ", "Успех", $user->getCanonicalName() . " был разблокирован в баг-трекере.");
    }
}