<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use openvk\Web\Models\Entities\{BugtrackerProduct, BugtrackerPrivateProduct, User};
use openvk\Web\Models\Repositories\{BugtrackerPrivateProducts};

use Nette\Database\Table\ActiveRow;
use Chandler\Database\DatabaseConnection;

class BugtrackerProducts
{
    private $context;
    private $products;
    private $private_products;
    
    function __construct()
    {
        $this->context = DatabaseConnection::i()->getContext();
        $this->products           = $this->context->table("bt_products");
    }

    private function toProduct(?ActiveRow $ar)
    {
        return is_null($ar) ? NULL : new BugtrackerProduct($ar);
    }

    function get(int $id): ?BugtrackerProduct
    {
        return $this->toProduct($this->products->get($id));
    }

    function getAll(int $page = 1): \Traversable
    {
        $products = $this->products
                        ->where(["private" => 0])
                        ->order("created DESC")
                        ->page($page, 5);

        foreach($products as $product)
            yield new BugtrackerProduct($product);
    }

    function getOpen(int $page = 1, bool $private = FALSE): \Traversable
    {
        $products = $this->products
                        ->where([
                            "closed" => 0, 
                            "private" => $private
                        ])
                        ->order("id ASC")
                        ->page($page, 5);

        foreach($products as $product)
            yield new BugtrackerProduct($product);
    }

    function getClosed(int $page = 1, bool $private = FALSE): \Traversable
    {
        $products = $this->products
                        ->where([
                            "closed" => 1, 
                            "private" => $private
                        ])
                        ->order("id ASC")
                        ->page($page, 5);

        foreach($products as $product)
            yield new BugtrackerProduct($product);
    }

    function getPrivate(int $page = 1): \Traversable
    {
       $products = $this->products
                        ->where([
                            "private" => 1
                        ])
                        ->order("id ASC")
                        ->page($page, 5);

        foreach($products as $product)
            yield new BugtrackerProduct($product);
    }

    function getPrivateForUser(User $user, int $page = 1): \Traversable
    {
        if (!$user->isBtModerator()) {
            return (new BugtrackerPrivateProducts)->getForUser($user, $page);
        } else {
            return (new BugtrackerPrivateProducts)->getAll($page);
        }
    }

    function getFiltered(User $tester, string $type = "all", int $page = 1): \Traversable
    {
        switch ($type) {
            case 'open':
                return $this->getOpen($page);
                break;

            case 'closed':
                return $this->getClosed($page);
                break;

            case 'private':
                if ($tester->isBtModerator())
                    return $this->getPrivate($page);

                return (new BugtrackerPrivateProducts)->getForUser($tester, $page);
                break;
            
            default:
                return $this->getAll($page);
                break;
        }
    }

    function getCount(string $filter = "all", User $user = NULL): ?int
    {
        switch ($filter) {
            case 'open':
                return sizeof($this->products->where(["closed" => 0, "private" => 0]));
                break;

            case 'closed':
                return sizeof($this->products->where(["closed" => 1, "private" => 0]));

            case 'private':
                return (new BugtrackerPrivateProducts)->getCount($user);
                break;

            default:
                return sizeof($this->products->where(["private" => 0]));
                break;
        }
    }
}