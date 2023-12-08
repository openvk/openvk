<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use openvk\Web\Models\Repositories\Clubs as ClubsRepo;
use openvk\Web\Models\Repositories\Users as UsersRepo;
use openvk\Web\Models\Repositories\Posts as PostsRepo;
use openvk\Web\Models\Entities\Club;

final class Groups extends VKAPIRequestHandler
{
    function get(int $user_id = 0, string $fields = "", int $offset = 0, int $count = 6, bool $online = false, string $filter = "groups"): object 
    {
        $this->requireUser();

        if($user_id == 0) {
        	foreach($this->getUser()->getClubs($offset, $filter == "admin", $count, true) as $club)
        		$clbs[] = $club;
        	$clbsCount = $this->getUser()->getClubCount();
        } else {
        	$users = new UsersRepo;
        	$user  = $users->get($user_id);

            if(is_null($user) || $user->isDeleted())
        		$this->fail(15, "Access denied");

            if(!$user->getPrivacyPermission('groups.read', $this->getUser()))
                $this->fail(15, "Access denied: this user chose to hide his groups.");
          
        	foreach($user->getClubs($offset, $filter == "admin", $count, true) as $club)
        		$clbs[] = $club;

        	$clbsCount = $user->getClubCount();
        }
        
        $rClubs;

        $ic = sizeof($clbs);
        if(sizeof($clbs) > $count)
            $ic = $count;

        if(!empty($clbs)) {
            for($i=0; $i < $ic; $i++) { 
                $usr = $clbs[$i];
                if(is_null($usr)) { 

                } else {
                    $rClubs[$i] = (object) [
                        "id" => $usr->getId(),
                        "name" => $usr->getName(),
                        "screen_name" => $usr->getShortCode(),
                        "is_closed" => false,
                        "can_access_closed" => true,
                    ];

                    $flds = explode(',', $fields);

                    foreach($flds as $field) { 
                        switch($field) {
                            case "verified":
                                $rClubs[$i]->verified = intval($usr->isVerified());
                                break;
                            case "has_photo":
                                $rClubs[$i]->has_photo = is_null($usr->getAvatarPhoto()) ? 0 : 1;
                                break;
                            case "photo_max_orig":
                                $rClubs[$i]->photo_max_orig = $usr->getAvatarURL();
                                break;
                            case "photo_max":
                                $rClubs[$i]->photo_max = $usr->getAvatarURL("original"); // ORIGINAL ANDREI CHINITEL 🥵🥵🥵🥵
                                break;
                            case "photo_50":
                                $rClubs[$i]->photo_50 = $usr->getAvatarURL();
                                break;
                            case "photo_100":
                                $rClubs[$i]->photo_100 = $usr->getAvatarURL("tiny");
                                break;
                            case "photo_200":
                                $rClubs[$i]->photo_200 = $usr->getAvatarURL("normal");
                                break;
                            case "photo_200_orig":
                                $rClubs[$i]->photo_200_orig = $usr->getAvatarURL("normal");
                                break;
                            case "photo_400_orig":
                                $rClubs[$i]->photo_400_orig = $usr->getAvatarURL("normal");
                                break;
                            case "members_count":
                                $rClubs[$i]->members_count = $usr->getFollowersCount();
                                break;
                            case "can_suggest":
                                $rClubs[$i]->can_suggest = !$usr->canBeModifiedBy($this->getUser()) && $usr->getWallType() == 2;
                                break;
                            # unstandard feild
                            case "suggested_count":
                                if($usr->getWallType() != 2) {
                                    $rClubs[$i]->suggested_count = NULL;
                                    break;
                                }
                                
                                $rClubs[$i]->suggested_count = $usr->getSuggestedPostsCount($this->getUser());
                                    
                                break;
                        }
                    }
                }
            }
        } else {
            $rClubs = [];
        }

        return (object) [
        	"count" => $clbsCount,
        	"items" => $rClubs
        ];
    }

    function getById(string $group_ids = "", string $group_id = "", string $fields = "", int $offset = 0, int $count = 500): ?array
    {
        /* Both offset and count SHOULD be used only in OpenVK code, 
           not in your app or script, since it's not oficially documented by VK */

        $clubs = new ClubsRepo;
		
        if(empty($group_ids) && !empty($group_id)) 
            $group_ids = $group_id;
        
        if(empty($group_ids) && empty($group_id))
            $this->fail(100, "One of the parameters specified was missing or invalid: group_ids is undefined");
		
        $clbs = explode(',', $group_ids);
        $response = array();

        $ic = sizeof($clbs);

        if(sizeof($clbs) > $count)
			$ic = $count;

        $clbs = array_slice($clbs, $offset * $count);


        for($i=0; $i < $ic; $i++) {
            if($i > 500 || $clbs[$i] == 0) 
                break;

            if($clbs[$i] < 0)
                $this->fail(100, "ты ошибся чутка, у айди группы убери минус");

            $clb = $clubs->get((int) $clbs[$i]);
            if(is_null($clb)) {
                $response[$i] = (object)[
                    "id"          => intval($clbs[$i]),
                    "name"        => "DELETED",
                    "screen_name" => "club".intval($clbs[$i]),
                    "type"        => "group",
                    "description" => "This group was deleted or it doesn't exist"
                ];   
            } else if($clbs[$i] == NULL) {

            } else {
                $response[$i] = (object)[
                    "id"                => $clb->getId(),
                    "name"              => $clb->getName(),
                    "screen_name"       => $clb->getShortCode() ?? "club".$clb->getId(),
                    "is_closed"         => false,
                    "type"              => "group",
                    "is_member"         => !is_null($this->getUser()) ? (int) $clb->getSubscriptionStatus($this->getUser()) : 0,
                    "can_access_closed" => true,
                ];

                $flds = explode(',', $fields);

                foreach($flds as $field) { 
                    switch($field) {
			            case "verified":
			                $response[$i]->verified = intval($clb->isVerified());
			                break;
			            case "has_photo":
			                $response[$i]->has_photo = is_null($clb->getAvatarPhoto()) ? 0 : 1;
			                break;
			            case "photo_max_orig":
			                $response[$i]->photo_max_orig = $clb->getAvatarURL();
			                break;
			            case "photo_max":
			                $response[$i]->photo_max = $clb->getAvatarURL();
			                break;
                        case "photo_50":
                            $response[$i]->photo_50 = $clb->getAvatarURL();
                            break;
                        case "photo_100":
                            $response[$i]->photo_100 = $clb->getAvatarURL("tiny");
                            break;
                        case "photo_200":
                            $response[$i]->photo_200 = $clb->getAvatarURL("normal");
                            break;
                        case "photo_200_orig":
                            $response[$i]->photo_200_orig = $clb->getAvatarURL("normal");
                            break;
                        case "photo_400_orig":
                            $response[$i]->photo_400_orig = $clb->getAvatarURL("normal");
                            break;
			            case "members_count":
			                $response[$i]->members_count = $clb->getFollowersCount();
			                break;
			            case "site":
			                $response[$i]->site = $clb->getWebsite();
			                break;
                        case "description":
			                $response[$i]->description = $clb->getDescription();
                            break;
                        case "can_suggest":
                            $response[$i]->can_suggest = !$clb->canBeModifiedBy($this->getUser()) && $clb->getWallType() == 2;
                            break;
                        # unstandard feild
                        case "suggested_count":
                            if($clb->getWallType() != 2) {
                                $response[$i]->suggested_count = NULL;
                                break;
                            }

                            $response[$i]->suggested_count = $clb->getSuggestedPostsCount($this->getUser());
                            break;
                        case "contacts":
                            $contacts;
                            $contactTmp = $clb->getManagers(1, true);

                            foreach($contactTmp as $contact)
                                $contacts[] = array(
                                    "user_id" => $contact->getUser()->getId(),
                                    "desc"    => $contact->getComment()
                                );

			                $response[$i]->contacts = $contacts;
			                break;
                        case "can_post":
                            if(!is_null($this->getUser()))
                                if($clb->canBeModifiedBy($this->getUser()))
                                    $response[$i]->can_post = true;
                                else
                                    $response[$i]->can_post = $clb->canPost();
                            break;
                    }
                }
            }
        }

        return $response;
    }

    function search(string $q, int $offset = 0, int $count = 100)
    {
        $clubs = new ClubsRepo;
        
        $array = [];
		$find  = $clubs->find($q);

        foreach ($find as $group)
            $array[] = $group->getId();

        return (object) [
        	"count" => $find->size(),
        	"items" => $this->getById(implode(',', $array), "", "is_admin,is_member,is_advertiser,photo_50,photo_100,photo_200", $offset, $count)
            /*
             * As there is no thing as "fields" by the original documentation
             * i'll just bake this param by the example shown here: https://dev.vk.com/method/groups.search 
             */
        ];
    }

    function join(int $group_id)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();
        
        $club = (new ClubsRepo)->get($group_id);
        
        $isMember = !is_null($this->getUser()) ? (int) $club->getSubscriptionStatus($this->getUser()) : 0;

        if($isMember == 0)
            $club->toggleSubscription($this->getUser());

        return 1;
    }

    function leave(int $group_id)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();
        
        $club = (new ClubsRepo)->get($group_id);
        
        $isMember = !is_null($this->getUser()) ? (int) $club->getSubscriptionStatus($this->getUser()) : 0;

        if($isMember == 1)
            $club->toggleSubscription($this->getUser());

        return 1;
    }

    function create(string $title, string $description = "", string $type = "group", int $public_category = 1, int $public_subcategory = 1, int $subtype = 1)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $club = new Club;

        $club->setName($title);
        $club->setAbout($description);
        $club->setOwner($this->getUser()->getId());
        $club->save();

        $club->toggleSubscription($this->getUser());

        return $this->getById((string)$club->getId());
    }

    function edit(
                int $group_id, 
                string $title = NULL, 
                string $description = NULL, 
                string $screen_name = NULL, 
                string $website = NULL, 
                int    $wall = -1, 
                int    $topics = NULL, 
                int    $adminlist = NULL,
                int    $topicsAboveWall = NULL,
                int    $hideFromGlobalFeed = NULL,
                int    $audio = NULL)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $club = (new ClubsRepo)->get($group_id);

        if(!$club) $this->fail(203, "Club not found");
        if(!$club || !$club->canBeModifiedBy($this->getUser())) $this->fail(15, "You can't modify this group.");
        if(!empty($screen_name) && !$club->setShortcode($screen_name)) $this->fail(103, "Invalid shortcode.");

        !empty($title)              ? $club->setName($title) : NULL;
        !empty($description)        ? $club->setAbout($description) : NULL;
        !empty($screen_name)        ? $club->setShortcode($screen_name) : NULL;
        !empty($website)            ? $club->setWebsite((!parse_url($website, PHP_URL_SCHEME) ? "https://" : "") . $website) : NULL;
        
        try {
            $wall != -1 ? $club->setWall($wall) : NULL;
        } catch(\Exception $e) {
            $this->fail(50, "Invalid wall value");
        }
        
        !empty($topics)             ? $club->setEveryone_Can_Create_Topics($topics) : NULL;
        !empty($adminlist)          ? $club->setAdministrators_List_Display($adminlist) : NULL;
        !empty($topicsAboveWall)    ? $club->setDisplay_Topics_Above_Wall($topicsAboveWall) : NULL;
        !empty($hideFromGlobalFeed) ? $club->setHide_From_Global_Feed($hideFromGlobalFeed) : NULL;
        
        in_array($audio, [0, 1]) ? $club->setEveryone_can_upload_audios($audio) : NULL;

        try {
            $club->save();
        } catch(\TypeError $e) {
            $this->fail(15, "Nothing changed");
        } catch(\Exception $e) {
            $this->fail(18, "An unknown error occurred: maybe you set an incorrect value?");
        }

        return 1;
    }

    function getMembers(string $group_id, string $sort = "id_asc", int $offset = 0, int $count = 100, string $fields = "", string $filter = "any")
    {
        # bdate,can_post,can_see_all_posts,can_see_audio,can_write_private_message,city,common_count,connections,contacts,country,domain,education,has_mobile,last_seen,lists,online,online_mobile,photo_100,photo_200,photo_200_orig,photo_400_orig,photo_50,photo_max,photo_max_orig,relation,relatives,schools,sex,site,status,universities
        $club = (new ClubsRepo)->get((int) $group_id);
        if(!$club) 
            $this->fail(125, "Invalid group id");

        $sorter = "follower ASC";

        switch($sort) {
            default:
            case "time_asc":
            case "id_asc":
                $sorter = "follower ASC";
                break;
            case "time_desc":
            case "id_desc":
                $sorter = "follower DESC";
                break;
        }

        $members = array_slice(iterator_to_array($club->getFollowers(1, $count, $sorter)), $offset);
        $arr = (object) [
            "count" => count($members), 
            "items" => array()];
        
        $filds = explode(",", $fields);

        $i = 0;
        foreach($members as $member) {
            if($i > $count) {
                break;
            }

            $arr->items[] = (object) [
                "id" => $member->getId(),
                "first_name" => $member->getFirstName(),
                "last_name" => $member->getLastName(),
            ];

            foreach($filds as $fild) {
                $canView = $member->canBeViewedBy($this->getUser());
                switch($fild) {
                    case "bdate":
                        if(!$canView) {
                            $arr->items[$i]->bdate = "01.01.1970";
                            break;
                        }

                        $arr->items[$i]->bdate = $member->getBirthday() ? $member->getBirthday()->format('%e.%m.%Y') : NULL;
                        break;
                    case "can_post":
                        $arr->items[$i]->can_post = $club->canBeModifiedBy($member);
                        break;
                    case "can_see_all_posts":
                        $arr->items[$i]->can_see_all_posts = 1;
                        break;
                    case "can_see_audio":
                        $arr->items[$i]->can_see_audio = 1;
                        break;
                    case "can_write_private_message":
                        $arr->items[$i]->can_write_private_message = 0;
                        break;
                    case "common_count":
                        $arr->items[$i]->common_count = 420;
                        break;
                    case "connections":
                        $arr->items[$i]->connections = 1;
                        break;
                    case "contacts":
                        if(!$canView) {
                            $arr->items[$i]->contacts = "secret@gmail.com";
                            break;
                        }

                        $arr->items[$i]->contacts = $member->getContactEmail();
                        break;
                    case "country":
                        $arr->items[$i]->country = 1;
                        break;
                    case "domain":
                        $arr->items[$i]->domain = "";
                        break;
                    case "education":
                        $arr->items[$i]->education = "";
                        break;
                    case "has_mobile":
                        $arr->items[$i]->has_mobile = false;
                        break;
                    case "last_seen":
                        if(!$canView) {
                            $arr->items[$i]->last_seen = 0;
                            break;
                        }

                        $arr->items[$i]->last_seen = $member->getOnline()->timestamp();
                        break;
                    case "lists":
                        $arr->items[$i]->lists = "";
                        break;
                    case "online":
                        if(!$canView) {
                            $arr->items[$i]->online = false;
                            break;
                        }

                        $arr->items[$i]->online = $member->isOnline();
                        break;
                    case "online_mobile":
                        if(!$canView) {
                            $arr->items[$i]->online_mobile = false;
                            break;
                        }

                        $arr->items[$i]->online_mobile = $member->getOnlinePlatform() == "android" || $member->getOnlinePlatform() == "iphone" || $member->getOnlinePlatform() == "mobile";
                        break;
                    case "photo_100":
                        $arr->items[$i]->photo_100 = $member->getAvatarURL("tiny");
                        break;
                    case "photo_200":
                        $arr->items[$i]->photo_200 = $member->getAvatarURL("normal");
                        break;
                    case "photo_200_orig":
                        $arr->items[$i]->photo_200_orig = $member->getAvatarURL("normal");
                        break;
                    case "photo_400_orig":
                        $arr->items[$i]->photo_400_orig = $member->getAvatarURL("normal");
                        break;
                    case "photo_max":
                        $arr->items[$i]->photo_max = $member->getAvatarURL("original");
                        break;
                    case "photo_max_orig":
                        $arr->items[$i]->photo_max_orig = $member->getAvatarURL();
                        break;
                    case "relation":
                        $arr->items[$i]->relation = $member->getMaritalStatus();
                        break;
                    case "relatives":
                        $arr->items[$i]->relatives = 0;
                        break;
                    case "schools":
                        $arr->items[$i]->schools = 0;
                        break;
                    case "sex":
                        if(!$canView) {
                            $arr->items[$i]->sex = -1;
                            break;
                        }

                        $arr->items[$i]->sex = $member->isFemale() ? 1 : 2;
                        break;
                    case "site":
                        if(!$canView) {
                            $arr->items[$i]->site = NULL;
                            break;
                        }

                        $arr->items[$i]->site = $member->getWebsite();
                        break;
                    case "status":
                        if(!$canView) {
                            $arr->items[$i]->status = "r";
                            break;
                        }

                        $arr->items[$i]->status = $member->getStatus();
                        break;
                    case "universities":
                        $arr->items[$i]->universities = 0;
                        break;
                }
            }
            $i++;
        }
        return $arr;
    }

    function getSettings(string $group_id)
    {
        $this->requireUser();
        $club = (new ClubsRepo)->get((int)$group_id);

        if(!$club || !$club->canBeModifiedBy($this->getUser()))
            $this->fail(15, "You can't get settings of this group.");

        $arr = (object) [
            "title"          => $club->getName(),
            "description"    => $club->getDescription() != NULL ? $club->getDescription() : "",
            "address"        => $club->getShortcode(),
            "wall"           => $club->getWallType(), # отличается от вкшных но да ладно
            "photos"         => 1,
            "video"          => 0,
            "audio"          => $club->isEveryoneCanUploadAudios() ? 1 : 0,
            "docs"           => 0,
            "topics"         => $club->isEveryoneCanCreateTopics() == true ? 1 : 0,
            "wiki"           => 0,
            "messages"       => 0,
            "obscene_filter" => 0,
            "obscene_stopwords" => 0,
            "obscene_words"  => "",
            "access"         => 1,
            "subject"        => 1,
            "subject_list"   => [
                0 => "в", 
                1 => "опенвк", 
                2 => "нет", 
                3 => "категорий", 
                4 => "групп", 
            ],
            "rss"            => "/club".$club->getId()."/rss",
            "website"        => $club->getWebsite(),
            "age_limits"     => 0,
            "market"         => [],
        ];

        return $arr;
    }

    function isMember(string $group_id, int $user_id, string $user_ids = "", bool $extended = false)
    {
        $this->requireUser();
        $id = $user_id != NULL ? $user_id : explode(",", $user_ids);

        if($group_id < 0)
            $this->fail(228, "Remove the minus from group_id");

        $club = (new ClubsRepo)->get((int)$group_id);
        $usver = (new UsersRepo)->get((int)$id);

        if(!$club || $group_id == 0)
            $this->fail(203, "Invalid club");

        if(!$usver || $usver->isDeleted() || $user_id == 0)
            $this->fail(30, "Invalid user");

        if($extended == false) {
            return $club->getSubscriptionStatus($usver) ? 1 : 0;
        } else {
            return (object)
            [
                "member"     => $club->getSubscriptionStatus($usver) ? 1 : 0,
                "request"    => 0,
                "invitation" => 0,
                "can_invite" => 0,
                "can_recall" => 0 
            ];
        }
    }

    function remove(int $group_id, int $user_id)
    {
        $this->requireUser();

        $this->fail(501, "Not implemented");
    }
}
