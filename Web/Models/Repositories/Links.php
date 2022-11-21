<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use openvk\Web\Models\Entities\Link;
use Chandler\Database\DatabaseConnection;

class Links
{
    private $context;
    private $links;
    
    function __construct()
    {
        $this->context = DatabaseConnection::i()->getContext();
        $this->links   = $this->context->table("links");
    }
    
    function get(int $id): ?Link
    {
        $link = $this->links->get($id);
        if(!$link) return NULL;
        
        return new Link($link);
    }

    function getByOwnerId(int $ownerId, int $page = 1, ?int $perPage = NULL): \Traversable
    {
        $perPage = $perPage ?? OPENVK_DEFAULT_PER_PAGE;
        $links   = $this->links->where("owner", $ownerId)->page($page, $perPage);

        foreach($links as $link)
            yield new Link($link);
    }
    
    function getCountByOwnerId(int $id): int
    {
        return sizeof($this->links->where("owner", $id));
    }
}
