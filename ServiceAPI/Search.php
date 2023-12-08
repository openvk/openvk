<?php declare(strict_types=1);
namespace openvk\ServiceAPI;
use openvk\Web\Models\Entities\{User, Club};
use openvk\Web\Models\Repositories\{Users, Clubs, Videos};
use Chandler\Database\DatabaseConnection;

class Search implements Handler
{
    protected $user;
    private $users;
    private $clubs;
    private $videos;

    function __construct(?User $user)
    {
        $this->user = $user;
        $this->users    = new Users;
        $this->clubs    = new Clubs;
        $this->videos   = new Videos;
    }

    function fastSearch(string $query, string $type = "users", callable $resolve, callable $reject)
    {
        if($query == "" || strlen($query) < 3)
            $reject(12, "No input or input < 3");

        $repo;
        $sort;

        switch($type) {
            default:
            case "users":
                $repo = (new Users);
                $sort = "rating DESC";

                break;
            case "groups":
                $repo = (new Clubs);
                $sort = "id ASC";

                break;
            case "videos":
                $repo = (new Videos);
                $sort = "created ASC";

                break;
        }

        $res = $repo->find($query, ["doNotSearchMe" => $this->user->getId(), "doNotSearchPrivate" => true,], $sort);

        $results  = array_slice(iterator_to_array($res), 0, 5);
            
        $count = sizeof($results);

        $arr = [
            "count" => $count,
            "items" => []
        ];

        if(sizeof($results) < 1) {
            $reject(2, "No results");
        }

        foreach($results as $res) {  
            $arr["items"][] = [
                "id"          => $res->getId(),
                "name"        => $type == "users"  ? $res->getCanonicalName() : $res->getName(),
                "avatar"      => $type != "videos" ? $res->getAvatarUrl() : $res->getThumbnailURL(),
                "url"         => $type != "videos" ? $res->getUrl() : "/video".$res->getPrettyId(),
                "description" => ovk_proc_strtr($res->getDescription() ?? "...", 40)
            ];
        }

        $resolve($arr);
    }
}
