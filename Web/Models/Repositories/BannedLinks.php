<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use Chandler\Database\DatabaseConnection as DB;
use Nette\Database\Table\{ActiveRow, Selection};
use openvk\Web\Models\Entities\BannedLink;
use function Symfony\Component\Translation\t;

class BannedLinks
{
    private $context;
    private $bannedLinks;

    function __construct()
    {
        $this->context = DB::i()->getContext();
        $this->bannedLinks = $this->context->table("links_banned");
    }

    function toBannedLink(?ActiveRow $ar): ?BannedLink
    {
        return is_null($ar) ? NULL : new BannedLink($ar);
    }

    function get(int $id): ?BannedLink
    {
        return $this->toBannedLink($this->bannedLinks->get($id));
    }

    function getList(?int $page = 1): \Traversable
    {
        foreach($this->bannedLinks->order("id DESC")->page($page, OPENVK_DEFAULT_PER_PAGE) as $link)
            yield new BannedLink($link);
    }

    function getCount(int $page = 1): int
    {
        return sizeof($this->bannedLinks->fetch());
    }

    function getByDomain(string $domain): ?Selection
    {
        return $this->bannedLinks->where("domain", $domain);
    }

    function isDomainBanned(string $domain): bool
    {
        return sizeof($this->bannedLinks->where(["domain" => $domain, "regexp_rule" => ""])) > 0;
    }

    function genLinks($rules): \Traversable
    {
        foreach ($rules as $rule)
            yield $this->get($rule->id);
    }

    function genEntries($links, $uri): \Traversable
    {
        foreach($links as $link)
            if (preg_match($link->getRegexpRule(), $uri))
                yield $link->getId();
            else if ($this->isDomainBanned($link->getDomain()))
                yield $link->getId();
    }

    function check(string $url): ?array
    {
        $uri = str_replace(["https://", "http://"], "", $url);
        $domain = explode("/", str_replace("www.", "", $uri))[0];
        $rules = $this->getByDomain($domain);

        if (is_null($rules))
            return NULL;

        return iterator_to_array($this->genEntries($this->genLinks($rules), $uri));
    }
}