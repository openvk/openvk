<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use openvk\Web\Models\Entities\User;
use openvk\Web\Models\Repositories\Aliases;
use Nette\Database\Table\ActiveRow;
use Chandler\Database\DatabaseConnection;
use Chandler\Security\User as ChandlerUser;

class Users
{
    private $context;
    private $users;
    private $aliases;
    
    function __construct()
    {
        $this->context = DatabaseConnection::i()->getContext();
        $this->users   = $this->context->table("profiles");
        $this->aliases = $this->context->table("aliases");
    }
    
    private function toUser(?ActiveRow $ar): ?User
    {
        return is_null($ar) ? NULL : new User($ar);
    }
    
    function get(int $id): ?User
    {
        return $this->toUser($this->users->get($id));
    }
    
    function getByShortURL(string $url): ?User
    {
        $shortcode = $this->toUser($this->users->where("shortcode", $url)->fetch());

        if ($shortcode)
            return $shortcode;

        $alias = (new Aliases)->getByShortcode($url);

        if (!$alias) return NULL;
        if ($alias->getType() !== "user") return NULL;
        
        return $alias->getUser();
    }
    
    function getByChandlerUser(?ChandlerUser $user): ?User
    {
        return $user ? $this->toUser($this->users->where("user", $user->getId())->fetch()) : NULL;
    }
    
    function find(string $query, array $pars = [], string $sort = "id DESC"): Util\EntityStream
    {
        $query  = "%$query%";
        $result = $this->users->where("CONCAT_WS(' ', first_name, last_name, pseudo, shortcode) LIKE ?", $query)->where("deleted", 0);
        
        $notNullParams = [];
        $nnparamsCount = 0;
        
        foreach($pars as $paramName => $paramValue)
            if($paramName != "before" && $paramName != "after" && $paramName != "gender" && $paramName != "maritalstatus" && $paramName != "politViews" && $paramName != "doNotSearchMe")
                $paramValue != NULL ? $notNullParams += ["$paramName" => "%$paramValue%"] : NULL;
            else
                $paramValue != NULL ? $notNullParams += ["$paramName" => "$paramValue"]   : NULL;

        $nnparamsCount = sizeof($notNullParams);

        if($nnparamsCount > 0) {
            foreach($notNullParams as $paramName => $paramValue) {
                switch($paramName) {
                    case "hometown":
                        $result->where("hometown LIKE ?", $paramValue);
                        break;
                    case "city":
                        $result->where("city LIKE ?", $paramValue);
                        break;
                    case "maritalstatus":
                        $result->where("marital_status ?", $paramValue);
                        break;
                    case "status":
                        $result->where("status LIKE ?", $paramValue);
                        break;
                    case "politViews":
                        $result->where("polit_views ?", $paramValue);
                        break;
                    case "email":
                        $result->where("email_contact LIKE ?", $paramValue);
                        break;
                    case "telegram":
                        $result->where("telegram LIKE ?", $paramValue);
                        break;
                    case "site":
                        $result->where("telegram LIKE ?", $paramValue);
                        break;
                    case "address":
                        $result->where("address LIKE ?", $paramValue);
                        break;
                    case "is_online":
                        $result->where("online >= ?", time() - 900);
                        break;
                    case "interests":
                        $result->where("interests LIKE ?", $paramValue);
                        break;
                    case "fav_mus":
                        $result->where("fav_music LIKE ?", $paramValue);
                        break;
                    case "fav_films":
                        $result->where("fav_films LIKE ?", $paramValue);
                        break;
                    case "fav_shows":
                        $result->where("fav_shows LIKE ?", $paramValue);
                        break;
                    case "fav_books":
                        $result->where("fav_books LIKE ?", $paramValue);
                        break;
                    case "fav_quote":
                        $result->where("fav_quote LIKE ?", $paramValue);
                        break;
                    case "before":
                        $result->where("UNIX_TIMESTAMP(since) < ?", $paramValue);
                        break;
                    case "after":
                        $result->where("UNIX_TIMESTAMP(since) > ?", $paramValue);
                        break;
                    case "gender":
                        $result->where("sex ?", $paramValue);
                        break;
                    case "doNotSearchMe":
                        $result->where("id !=", $paramValue);
                        break;
                }
            }
        }


        return new Util\EntityStream("User", $result->order($sort));
    }
    
    function getStatistics(): object
    {
        return (object) [
            "all"    => sizeof(clone $this->users),
            "active" => sizeof((clone $this->users)->where("online > 0")),
            "online" => sizeof((clone $this->users)->where("online >= ?", time() - 900)),
        ];
    }

    function getByAddress(string $address): ?User
    {
        if(substr_compare($address, "/", -1) === 0)
            $address = substr($address, 0, iconv_strlen($address) - 1);

        $serverUrl = ovk_scheme(true) . $_SERVER["SERVER_NAME"];
        if(strpos($address, $serverUrl . "/") === 0)
            $address = substr($address, iconv_strlen($serverUrl) + 1);

        if(strpos($address, "id") === 0) {
            $user = $this->get((int) substr($address, 2));
            if($user) return $user;
        }

        return $this->getByShortUrl($address);
    }

    /**
     * If you need to check if the user is an instance administrator, use `$user->getChandlerUser()->can("access")->model("admin")->whichBelongsTo(NULL)`.
     * This method is more suitable for instance administrators lists
     */
    function getInstanceAdmins(bool $excludeHidden = true): \Traversable
    {
        $query = "SELECT DISTINCT(`profiles`.`id`) FROM `ChandlerACLRelations` JOIN `profiles` ON `ChandlerACLRelations`.`user` = `profiles`.`user` COLLATE utf8mb4_unicode_520_ci WHERE `ChandlerACLRelations`.`group` IN (SELECT `group` FROM `ChandlerACLGroupsPermissions` WHERE `model` = \"admin\" AND `permission` = \"access\")";

        if($excludeHidden)
        $query .= " AND `ChandlerACLRelations`.`user` NOT IN (SELECT `user` FROM `ChandlerACLRelations` WHERE `group` IN (SELECT `group` FROM `ChandlerACLGroupsPermissions` WHERE `model` = \"hidden_admin\" AND `permission` = \"be\"))";

        $query .= " ORDER BY `profiles`.`id`;";

        $result = DatabaseConnection::i()->getConnection()->query($query);
        foreach($result as $entry)
            yield $this->get($entry->id);
    }
    
    use \Nette\SmartObject;
}
