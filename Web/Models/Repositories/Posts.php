<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use openvk\Web\Models\Entities\Post;
use openvk\Web\Models\Entities\User;
use Nette\Database\Table\ActiveRow;
use Chandler\Database\DatabaseConnection;

class Posts
{
    private $context;
    private $posts;
    
    function __construct()
    {
        $this->context = DatabaseConnection::i()->getContext();
        $this->posts   = $this->context->table("posts");
    }
    
    private function toPost(?ActiveRow $ar): ?Post
    {
        return is_null($ar) ? NULL : new Post($ar);
    }
    
    function get(int $id): ?Post
    {
        return $this->toPost($this->posts->get($id));
    }
    
    function getPinnedPost(int $user): ?Post
    {
        $post = (clone $this->posts)->where([
            "wall"    => $user,
            "pinned"  => true,
            "deleted" => false,
        ])->fetch();
        
        return $this->toPost($post);
    }
    
    function getPostsFromUsersWall(int $user, int $page = 1, ?int $perPage = NULL, ?int $offset = NULL): \Traversable
    {
        $perPage ??= OPENVK_DEFAULT_PER_PAGE;
        $offset ??= $perPage * ($page - 1);
        
        $pinPost = $this->getPinnedPost($user);
        if(is_null($offset) || $offset == 0) {
            if(!is_null($pinPost)) {
                if($page === 1) {
                    $perPage--;
                    
                    yield $pinPost;
                } else {
                    $offset--;
                }
            }
        } else if(!is_null($offset)) {
            $offset--;
        }
        
        $sel = $this->posts->where([
            "wall"      => $user,
            "pinned"    => false,
            "deleted"   => false,
            "suggested" => 0,
        ])->order("created DESC")->limit($perPage, $offset);
        
        foreach($sel as $post)
            yield new Post($post);
    }

    function getOwnersPostsFromWall(int $user, int $page = 1, ?int $perPage = NULL, ?int $offset = NULL): \Traversable
    {
        $perPage ??= OPENVK_DEFAULT_PER_PAGE;
        $offset ??= $perPage * ($page - 1);

        $sel = $this->posts->where([
            "wall"      => $user,
            "deleted"   => false,
            "suggested" => 0,
        ]);
        
        if($user > 0)
            $sel->where("owner", $user);
        else
            $sel->where("flags !=", 0);

        $sel->order("created DESC")->limit($perPage, $offset);
        
        foreach($sel as $post)
            yield new Post($post);
    }

    function getOthersPostsFromWall(int $user, int $page = 1, ?int $perPage = NULL, ?int $offset = NULL): \Traversable
    {
        $perPage ??= OPENVK_DEFAULT_PER_PAGE;
        $offset ??= $perPage * ($page - 1);

        $sel = $this->posts->where([
            "wall"      => $user,
            "deleted"   => false,
            "suggested" => 0,
        ]);
        
        if($user > 0)
            $sel->where("owner !=", $user);
        else
            $sel->where("flags", 0);
        
        $sel->order("created DESC")->limit($perPage, $offset);
        
        foreach($sel as $post)
            yield new Post($post);
    }
    
    function getPostsByHashtag(string $hashtag, int $page = 1, ?int $perPage = NULL): \Traversable
    {
        $hashtag = "#$hashtag";
        $sel = $this->posts
                    ->where("MATCH (content) AGAINST (? IN BOOLEAN MODE)", "+$hashtag")
                    ->where("deleted", 0)
                    ->order("created DESC")
                    ->where("suggested", 0)
                    ->page($page, $perPage ?? OPENVK_DEFAULT_PER_PAGE);
        
        foreach($sel as $post)
            yield new Post($post);
    }
    
    function getPostCountByHashtag(string $hashtag): int
    {
        $hashtag = "#$hashtag";
        $sel = $this->posts
                    ->where("content LIKE ?", "%$hashtag%")
                    ->where("deleted", 0)
                    ->where("suggested", 0);
        
        return sizeof($sel);
    }
    
    function getPostById(int $wall, int $post, bool $forceSuggestion = false): ?Post
    {
        $post = $this->posts->where(['wall' => $wall, 'virtual_id' => $post]);

        if(!$forceSuggestion) {
            $post->where("suggested", 0);
        }

        $post = $post->fetch();

        if(!is_null($post))
            return new Post($post);
        else
            return NULL;
        
    }

    function find(string $query = "", array $pars = [], string $sort = "id"): Util\EntityStream
    {
        $query  = "%$query%";

        $notNullParams = [];

        foreach($pars as $paramName => $paramValue)
            if($paramName != "before" && $paramName != "after")
                $paramValue != NULL ? $notNullParams+=["$paramName" => "%$paramValue%"]   : NULL;
            else
                $paramValue != NULL ? $notNullParams+=["$paramName" => "$paramValue"]     : NULL;

        $result = $this->posts->where("content LIKE ?", $query)->where("deleted", 0)->where("suggested", 0);
        $nnparamsCount = sizeof($notNullParams);

        if($nnparamsCount > 0) {
            foreach($notNullParams as $paramName => $paramValue) {
                switch($paramName) {
                    case "before":
                        $result->where("created < ?", $paramValue);
                        break;
                    case "after":
                        $result->where("created > ?", $paramValue);
                        break;
                }
            }
        }


        return new Util\EntityStream("Post", $result->order("$sort"));
    }

    function getPostCountOnUserWall(int $user): int
    {
        return sizeof($this->posts->where(["wall" => $user, "deleted" => 0, "suggested" => 0]));
    }

    function getOwnersCountOnUserWall(int $user): int
    {
        if($user > 0)
            return sizeof($this->posts->where(["wall" => $user, "deleted" => 0, "owner" => $user]));
        else
            return sizeof($this->posts->where(["wall" => $user, "deleted" => 0, "suggested" => 0])->where("flags !=", 0));
    }

    function getOthersCountOnUserWall(int $user): int
    {
        if($user > 0)
            return sizeof($this->posts->where(["wall" => $user, "deleted" => 0])->where("owner !=", $user));
        else
            return sizeof($this->posts->where(["wall" => $user, "deleted" => 0, "suggested" => 0])->where("flags", 0));
    }

    function getSuggestedPosts(int $club, int $page = 1, ?int $perPage = NULL, ?int $offset = NULL): \Traversable
    {
        $perPage ??= OPENVK_DEFAULT_PER_PAGE;
        $offset ??= $perPage * ($page - 1);

        $sel = $this->posts
                    ->where("deleted", 0)
                    ->where("wall", $club * -1)
                    ->order("created DESC")
                    ->where("suggested", 1)
                    ->limit($perPage, $offset);
        
        foreach($sel as $post)
            yield new Post($post);
    }

    function getSuggestedPostsCount(int $club)
    {
        return sizeof($this->posts->where(["wall" => $club * -1, "deleted" => 0, "suggested" => 1]));
    }

    function getSuggestedPostsByUser(int $club, int $user, int $page = 1, ?int $perPage = NULL, ?int $offset = NULL): \Traversable
    {
        $perPage ??= OPENVK_DEFAULT_PER_PAGE;
        $offset ??= $perPage * ($page - 1);

        $sel = $this->posts
                    ->where("deleted", 0)
                    ->where("wall", $club * -1)
                    ->where("owner", $user)
                    ->order("created DESC")
                    ->where("suggested", 1)
                    ->limit($perPage, $offset);
        
        foreach($sel as $post)
            yield new Post($post);
    }

    function getSuggestedPostsCountByUser(int $club, int $user): int
    {  
        return sizeof($this->posts->where(["wall" => $club * -1, "deleted" => 0, "suggested" => 1, "owner" => $user]));
    }

    function getCount(): int
    {
        return sizeof(clone $this->posts);
    }
}
