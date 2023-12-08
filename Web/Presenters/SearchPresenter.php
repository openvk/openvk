<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Entities\{User, Club};
use openvk\Web\Models\Repositories\{Users, Clubs, Posts, Comments, Videos, Applications, Notes, Audios};
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
    private $audios;
    
    function __construct(Users $users, Clubs $clubs)
    {
        $this->users    = $users;
        $this->clubs    = $clubs;
        $this->posts    = new Posts;
        $this->comments = new Comments;
        $this->videos   = new Videos;
        $this->apps     = new Applications;
        $this->notes    = new Notes;
        $this->audios   = new Audios;
        
        parent::__construct();
    }
    
    function renderIndex(): void
    {
        $this->assertUserLoggedIn();

        $query = $this->queryParam("query") ?? "";
        $type  = $this->queryParam("type") ?? "users";
        $sorter = $this->queryParam("sort") ?? "id";
        $invert = $this->queryParam("invert") == 1 ? "ASC" : "DESC";
        $page  = (int) ($this->queryParam("p") ?? 1);
        
        # https://youtu.be/pSAWM5YuXx8

        $repos = [ 
            "groups"   => "clubs", 
            "users"    => "users",
            "posts"    => "posts",
            "comments" => "comments",
            "videos"   => "videos",
            "audios"   => "audios",
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
            case "length":
                if($type != "audios") break;
                
                $sort = "length " . $invert;
                break;
            case "listens":
                if($type != "audios") break;
    
                $sort = "listens " . $invert;
                break;  
        }

        $parameters = [
            "type"          => $this->queryParam("type"),
            "city"          => $this->queryParam("city") != "" ? $this->queryParam("city") : NULL,
            "maritalstatus" => $this->queryParam("maritalstatus") != 0 ? $this->queryParam("maritalstatus") : NULL,
            "with_photo"    => $this->queryParam("with_photo"),
            "status"        => $this->queryParam("status")     != "" ? $this->queryParam("status") : NULL,
            "politViews"    => $this->queryParam("politViews") != 0 ? $this->queryParam("politViews") : NULL,
            "email"         => $this->queryParam("email"),
            "telegram"      => $this->queryParam("telegram"),
            "site"          => $this->queryParam("site")      != "" ? "https://".$this->queryParam("site") : NULL,
            "address"       => $this->queryParam("address"),
            "is_online"     => $this->queryParam("is_online") == 1 ? 1 : NULL,
            "interests"     => $this->queryParam("interests")  != "" ? $this->queryParam("interests") : NULL,
            "fav_mus"       => $this->queryParam("fav_mus")    != "" ? $this->queryParam("fav_mus") : NULL,
            "fav_films"     => $this->queryParam("fav_films")  != "" ? $this->queryParam("fav_films") : NULL,
            "fav_shows"     => $this->queryParam("fav_shows")  != "" ? $this->queryParam("fav_shows") : NULL,
            "fav_books"     => $this->queryParam("fav_books")  != "" ? $this->queryParam("fav_books") : NULL,
            "fav_quote"     => $this->queryParam("fav_quote")  != "" ? $this->queryParam("fav_quote") : NULL,
            "hometown"      => $this->queryParam("hometown")   != "" ? $this->queryParam("hometown") : NULL,
            "before"        => $this->queryParam("datebefore") != "" ? strtotime($this->queryParam("datebefore")) : NULL,
            "after"         => $this->queryParam("dateafter")  != "" ? strtotime($this->queryParam("dateafter")) : NULL,
            "gender"        => $this->queryParam("gender")     != "" && $this->queryParam("gender") != 2 ? $this->queryParam("gender") : NULL,
            "doNotSearchPrivate" => true,
            "only_performers" => $this->queryParam("only_performers") == "on" ? "1" : NULL,
            "with_lyrics" => $this->queryParam("with_lyrics") == "on" ? true : NULL,
        ];

        $repo  = $repos[$type] or $this->throwError(400, "Bad Request", "Invalid search entity $type.");
        
        $results  = $this->{$repo}->find($query, $parameters, $sort);
        $iterator = $results->page($page, 14);
        $count    = $results->size();
        
        $this->template->iterator = iterator_to_array($iterator);
        $this->template->count    = $count;
        $this->template->type     = $type;
        $this->template->page     = $page;
        $this->template->perPage  = 14;
    }
}
