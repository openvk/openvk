<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use openvk\Web\Models\Entities\{BugtrackerProduct, BugtrackerPrivateProduct, User};
use openvk\Web\Models\Repositories\BugtrackerProducts;
use Nette\Database\Table\ActiveRow;
use Chandler\Database\DatabaseConnection;

class BugtrackerPrivateProducts
{
    private $context;
    private $private_products;
    
    function __construct()
    {
        $this->context = DatabaseConnection::i()->getContext();
        $this->private_products   = $this->context->table("bt_products_access");
    }

    private function toPrivateProduct(?ActiveRow $ar)
    {
        return is_null($ar) ? NULL : new BugtrackerPrivateProduct($ar);
    }

    function get(int $id): ?BugtrackerPrivateProduct
    {
        return $this->toPrivateProduct($this->private_products->get($id));
    }

    function getAll(int $page = 1): \Traversable
    {
        $products = $this->private_products
                        ->order("created DESC")
                        ->page($page, 5);

        foreach($products as $product)
            yield (new BugtrackerProducts)->get($product->product);
    }

    function getForUser(User $user, int $page = 1): \Traversable
    {
            
        $products = $this->private_products
                            ->where(["tester" => $user->getId()])
                            ->order("id ASC")
                            ->page($page, 5);

        foreach($products as $product)
            yield (new BugtrackerProducts)->get($product->product);
    }

    function getCount(User $user): ?int
    {
        if ($user->isBtModerator())
            return sizeof($this->getAll());

        return sizeof($this->private_products->where(["tester" => $user->getId()]));
    }
}