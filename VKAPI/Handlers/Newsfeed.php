<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use Chandler\Database\DatabaseConnection;
use openvk\Web\Models\Repositories\Posts as PostsRepo;
use openvk\Web\Models\Entities\User;
use openvk\VKAPI\Handlers\Wall;

final class Newsfeed extends VKAPIRequestHandler
{
    function get(string $fields = "", int $start_from = 0, int $offset = 0, int $count = 30, int $extended = 0, int $forGodSakePleaseDoNotReportAboutMyOnlineActivity = 0)
    {
        $this->requireUser();

        if($forGodSakePleaseDoNotReportAboutMyOnlineActivity == 0)
        {
            $this->getUser()->updOnline($this->getPlatform());
        }
        
        $id    = $this->getUser()->getId();
        $subs  = DatabaseConnection::i()
                    ->getContext()
                    ->table("subscriptions")
                    ->where("follower", $id);
        $ids   = array_map(function($rel) {
            return $rel->target * ($rel->model === "openvk\Web\Models\Entities\User" ? 1 : -1);
        }, iterator_to_array($subs));
        $ids[] = $this->getUser()->getId();
        
        $posts = DatabaseConnection::i()
                    ->getContext()
                    ->table("posts")
                    ->select("id")
                    ->where("wall IN (?)", $ids)
                    ->where("deleted", 0)
                    ->where("id < (?)", empty($start_from) ? PHP_INT_MAX : $start_from)
                    ->order("created DESC");

        $rposts = [];
        foreach($posts->page((int) ($offset + 1), $count) as $post)
            $rposts[] = (new PostsRepo)->get($post->id)->getPrettyId();

        $response = (new Wall)->getById(implode(',', $rposts), $extended, $fields, $this->getUser());
        $response->next_from = end(end($posts->page((int) ($offset + 1), $count))); // ну и костыли пиздец конечно)
        
        return $response;
    }

    function getGlobal(string $fields = "", int $start_from = 0, int $offset = 0, int $count = 30, int $extended = 0)
    {
        $this->requireUser();
        
        $queryBase = "FROM `posts` LEFT JOIN `groups` ON GREATEST(`posts`.`wall`, 0) = 0 AND `groups`.`id` = ABS(`posts`.`wall`) WHERE (`groups`.`hide_from_global_feed` = 0 OR `groups`.`name` IS NULL) AND `posts`.`deleted` = 0";

        if($this->getUser()->getNsfwTolerance() === User::NSFW_INTOLERANT)
            $queryBase .= " AND `nsfw` = 0";

        $start_from = empty($start_from) ? PHP_INT_MAX : $start_from;
        $posts = DatabaseConnection::i()->getConnection()->query("SELECT `posts`.`id` " . $queryBase . " AND `posts`.`id` < " . $start_from . " ORDER BY `created` DESC LIMIT " . $count . " OFFSET " . $offset);
        
        $rposts = [];
        $ids = [];
        foreach($posts as $post) {
            $rposts[] = (new PostsRepo)->get($post->id)->getPrettyId();
            $ids[] = $post->id;
        }

        $response = (new Wall)->getById(implode(',', $rposts), $extended, $fields, $this->getUser());
        $response->next_from = end($ids);
        
        return $response;
    }
}
