<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use Chandler\Database\DatabaseConnection;
use openvk\Web\Models\Entities\{Gift, GiftCategory};

class Gifts
{
    private $context;
    private $gifts;
    private $cats;
    
    function __construct()
    {
        $this->context = DatabaseConnection::i()->getContext();
        $this->gifts   = $this->context->table("gifts");
        $this->cats    = $this->context->table("gift_categories");
    }
    
    function get(int $id): ?Gift
    {
        $gift = $this->gifts->get($id);
        if(!$gift)
            return NULL;
        
        return new Gift($gift);
    }
    
    function getCat(int $id): ?GiftCategory
    {
        $cat = $this->cats->get($id);
        if(!$cat)
            return NULL;
        
        return new GiftCategory($cat);
    }
    
    function getCategories(int $page, ?int $perPage = NULL, &$count = nullptr): \Traversable
    {
        $cats  = $this->cats->where("deleted", false);
        $count = $cats->count();
        $cats  = $cats->page($page, $perPage ?? OPENVK_DEFAULT_PER_PAGE);
        foreach($cats as $cat)
            yield new GiftCategory($cat);
    }

    function getCategoriesCount(): int
    {
        $cats  = $this->cats->where("deleted", false);
        return $cats->count();
    }
}
