<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Entities\{User, Club};
use openvk\Web\Models\Repositories\{Users, Clubs, Posts, Comments, Videos, Applications, Notes};
use Chandler\Database\DatabaseConnection;

final class SearchPresenter extends OpenVKPresenter
{
    private $users;
    private $clubs;
    private $posts;
    private $comments;
    private $videos;
    private $apps;
    private $notes;
    
    function __construct(Users $users, Clubs $clubs)
    {
        $this->users    = $users;
        $this->clubs    = $clubs;
        $this->posts    = new Posts;
        $this->comments = new Comments;
        $this->videos   = new Videos;
        $this->apps     = new Applications;
        $this->notes    = new Notes;
        
        parent::__construct();
    }
    
    function renderIndex(): void
    {
        $query = $this->requestParam("query") ?? "";
        $type  = $this->requestParam("type") ?? "users";
        $sorter = $this->requestParam("sort") ?? "id";
        $invert = $this->requestParam("invert") == 1 ? "ASC" : "DESC";
        $page  = (int) ($this->requestParam("p") ?? 1);
        
        $this->willExecuteWriteAction();
        if($query != "")
            $this->assertUserLoggedIn();
        
        # https://youtu.be/pSAWM5YuXx8

        $repos = [ 
            "groups"   => "clubs", 
            "users"    => "users",
            "posts"    => "posts",
            "comments" => "comments",
            "videos"   => "videos",
            "audios"   => "posts",
            "apps"     => "apps",
            "notes"    => "notes"
        ];

        switch($sorter) {
            default:
            case "id":
                $sort = "id " . $invert;
                break;
            case "name":
                $sort = "first_name " . $invert;
                break;   
            case "rating":
                $sort = "rating " . $invert;
                break;   
        }

        $parameters = [
            "type"          => $this->requestParam("type"),
            "city"          => $this->requestParam("city") != "" ? $this->requestParam("city") : NULL,
            "maritalstatus" => $this->requestParam("maritalstatus") != 0 ? $this->requestParam("maritalstatus") : NULL,
            "with_photo"    => $this->requestParam("with_photo"),
            "status"        => $this->requestParam("status")     != "" ? $this->requestParam("status") : NULL,
            "politViews"    => $this->requestParam("politViews") != 0 ? $this->requestParam("politViews") : NULL,
            "email"         => $this->requestParam("email"),
            "telegram"      => $this->requestParam("telegram"),
            "site"          => $this->requestParam("site")      != "" ? "https://".$this->requestParam("site") : NULL,
            "address"       => $this->requestParam("address"),
            "is_online"     => $this->requestParam("is_online") == 1 ? 1 : NULL,
            "interests"     => $this->requestParam("interests")  != "" ? $this->requestParam("interests") : NULL,
            "fav_mus"       => $this->requestParam("fav_mus")    != "" ? $this->requestParam("fav_mus") : NULL,
            "fav_films"     => $this->requestParam("fav_films")  != "" ? $this->requestParam("fav_films") : NULL,
            "fav_shows"     => $this->requestParam("fav_shows")  != "" ? $this->requestParam("fav_shows") : NULL,
            "fav_books"     => $this->requestParam("fav_books")  != "" ? $this->requestParam("fav_books") : NULL,
            "fav_quote"     => $this->requestParam("fav_quote")  != "" ? $this->requestParam("fav_quote") : NULL,
            "hometown"      => $this->requestParam("hometown")   != "" ? $this->requestParam("hometown") : NULL,
            "before"        => $this->requestParam("datebefore") != "" ? strtotime($this->requestParam("datebefore")) : NULL,
            "after"         => $this->requestParam("dateafter")  != "" ? strtotime($this->requestParam("dateafter")) : NULL,
            "gender"        => $this->requestParam("gender")     != "" && $this->requestParam("gender") != 2 ? $this->requestParam("gender") : NULL
        ];

        $repo  = $repos[$type] or $this->throwError(400, "Bad Request", "Invalid search entity $type.");
        
        $results  = $this->{$repo}->find($query, $parameters, $sort);
        $iterator = $results->page($page);
        $count    = $results->size();
        
        $this->template->iterator = iterator_to_array($iterator);
        $this->template->count    = $count;
        $this->template->type     = $type;
        $this->template->page     = $page;
    }
}
