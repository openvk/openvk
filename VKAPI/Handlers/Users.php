<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use openvk\Web\Models\Entities\User;
use openvk\Web\Models\Repositories\Users as UsersRepo;
use openvk\Web\Models\Repositories\Blacklists;

final class Users extends VKAPIRequestHandler
{
    function get(string $user_ids = "0", string $fields = "", int $offset = 0, int $count = 100, User $authuser = null /* костыль(( */): array 
    {
		if($authuser == NULL) $authuser = $this->getUser();

        $users = new UsersRepo;
		if($user_ids == "0")
			$user_ids = (string) $authuser->getId();
		
        $usrs = explode(',', $user_ids);
        $response = array();

        $ic = sizeof($usrs);

        if(sizeof($usrs) > $count)
			$ic = $count;

        $usrs = array_slice($usrs, $offset * $count);

        for($i=0; $i < $ic; $i++) { 
			if($usrs[$i] != 0) {
				$usr = $users->get((int) $usrs[$i]);
				if(is_null($usr) || $usr->isDeleted()) {
					$response[$i] = (object)[
						"id" 		  => (int) $usrs[$i],
						"first_name"  => "DELETED",
						"last_name"   => "",
						"deactivated" => "deleted"
					];
				} else if($usr->isBanned()) {
					$response[$i] = (object)[
						"id"          => $usr->getId(),
						"first_name"  => $usr->getFirstName(),
						"last_name"   => $usr->getLastName(),
						"deactivated" => "banned",
						"ban_reason"  => $usr->getBanReason()
					];
				} else if($usrs[$i] == NULL) {

				} else {
					$response[$i] = (object)[
						"id"                => $usr->getId(),
						"first_name"        => $usr->getFirstName(),
						"last_name"         => $usr->getLastName(),
						"is_closed"         => (new Blacklists)->isBanned($usr, $authuser),
						"can_access_closed" => !(new Blacklists)->isBanned($usr, $authuser),
                        "blacklisted"       => (new Blacklists)->isBanned($usr, $authuser),
                        "blacklisted_by_me" => (new Blacklists)->isBanned($authuser, $usr)
					];

					$flds = explode(',', $fields);

        if (!(new Blacklists)->isBanned($usr, $authuser))
					foreach($flds as $field) { 
						switch($field) {
							case "verified":
								$response[$i]->verified = intval($usr->isVerified());
								break;
							case "sex":
								$response[$i]->sex = $usr->isFemale() ? 1 : 2;
								break;
							case "has_photo":
								$response[$i]->has_photo = is_null($usr->getAvatarPhoto()) ? 0 : 1;
								break;
							case "photo_max_orig":
								$response[$i]->photo_max_orig = $usr->getAvatarURL();
								break;
							case "photo_max":
								$response[$i]->photo_max = $usr->getAvatarURL("original");
								break;
							case "photo_50":
								$response[$i]->photo_50 = $usr->getAvatarURL();
								break;
							case "photo_100":
								$response[$i]->photo_100 = $usr->getAvatarURL("tiny");
								break;
							case "photo_200":
								$response[$i]->photo_200 = $usr->getAvatarURL("normal");
								break;
							case "photo_200_orig": # вообще не ебу к чему эта строка ну пусть будет кек
								$response[$i]->photo_200_orig = $usr->getAvatarURL("normal");
								break;
							case "photo_400_orig":
								$response[$i]->photo_400_orig = $usr->getAvatarURL("normal");
								break;
							
							# Она хочет быть выебанной видя матан
							# Покайфу когда ты Виет а вокруг лишь дискриминант

							# ору а когда я это успел написать
							# вова кстати не матерись в коде мамка же спалит азщазаззазщазазаззазазазх
							case "status":
								if($usr->getStatus() != NULL)
									$response[$i]->status = $usr->getStatus();
								break;
							case "screen_name":
								if($usr->getShortCode() != NULL)
									$response[$i]->screen_name = $usr->getShortCode();
								break;
							case "friend_status":
								switch($usr->getSubscriptionStatus($authuser)) {
									case 3:
										# NOTICE falling through
									case 0:
										$response[$i]->friend_status = $usr->getSubscriptionStatus($authuser);
										break;
									case 1:
										$response[$i]->friend_status = 2;
										break;
									case 2:
										$response[$i]->friend_status = 1;
										break;
								}
								break;
							case "last_seen":
								if ($usr->onlineStatus() == 0) {
									$platform = $usr->getOnlinePlatform(true);
									switch ($platform) {
										case 'iphone':
											$platform = 2;
											break;

										case 'android':
											$platform = 4;
											break;

										case NULL:
											$platform = 7;
											break;
										
										default:
											$platform = 1;
											break;
									}

									$response[$i]->last_seen = (object) [
										"platform" => $platform,
										"time"     => $usr->getOnline()->timestamp()
									];
								}
							case "music":
								$response[$i]->music = $usr->getFavoriteMusic();
								break;
							case "movies":
								$response[$i]->movies = $usr->getFavoriteFilms();
								break;
							case "tv":
								$response[$i]->tv = $usr->getFavoriteShows();
								break;
							case "books":
								$response[$i]->books = $usr->getFavoriteBooks();
								break;
							case "city":
								$response[$i]->city = $usr->getCity();
								break;
							case "interests":
								$response[$i]->interests = $usr->getInterests();
								break;
							case "rating":
								$response[$i]->rating = $usr->getRating();
								break;	 
						}
					}

					if (!(new Blacklists)->isBanned($usr, $authuser))
                        if($usr->getOnline()->timestamp() + 300 > time())
                            $response[$i]->online = 1;
                        else
                            $response[$i]->online = 0;
				}
			}
        }

        return $response;
    }

    function getFollowers(int $user_id, string $fields = "", int $offset = 0, int $count = 100): object
    {
        $offset++;
        $followers = [];

        $users = new UsersRepo;

        $this->requireUser();

        $authuser = $this->getUser();
        $target = $users->get($user_id);

        if ((new Blacklists)->isBanned($target, $authuser))
            $this->fail(15, "Access denied: User is blacklisted");

        foreach($target->getFollowers($offset, $count) as $follower)
            $followers[] = $follower->getId();

        $response = $followers;

        if(!is_null($fields))
        	$response = $this->get(implode(',', $followers), $fields, 0, $count);

        return (object) [
            "count" => $target->getFollowersCount(),
            "items" => $response
        ];
    }

    function search(string $q, 
                    string $fields = "", 
                    int $offset = 0, 
                    int $count = 100,
                    string $city = "",
                    string $hometown = "",
                    int $sex = 2,
                    int $status = 0, # это про marital status
                    bool $online = false,
                    # дальше идут параметры которых нету в vkapi но есть на сайте
                    string $profileStatus = "", # а это уже нормальный статус
                    int $sort = 0,
                    int $before = 0,
                    int $politViews = 0,
                    int $after = 0,
                    string $interests = "",
                    string $fav_music = "",
                    string $fav_films = "",
                    string $fav_shows = "",
                    string $fav_books = "",
                    string $fav_quotes = ""
                    )
    {
        $users = new UsersRepo;
        
        $sortg = "id ASC";

        $nfilds = $fields;

        switch($sort) {
            case 0:
                $sortg = "id DESC";
                break;
            case 1:
                $sortg = "id ASC";
                break;
            case 2:
                $sortg = "first_name DESC";
                break;
            case 3:
                $sortg = "first_name ASC";
                break;
            case 4:
                $sortg = "rating DESC";

                if(!str_contains($nfilds, "rating")) {
                    $nfilds .= "rating";
                }

                break;
            case 5:
                $sortg = "rating DESC";

                if(!str_contains($nfilds, "rating")) {
                    $nfilds .= "rating";
                }

                break;
        }

        $array = [];

        $parameters = [
            "city"            => !empty($city) ? $city : NULL,
            "hometown"        => !empty($hometown) ? $hometown : NULL,
            "gender"          => $sex < 2 ? $sex : NULL,
            "maritalstatus"   => (bool)$status ? $status : NULL,
            "politViews"      => (bool)$politViews ? $politViews : NULL,
            "is_online"       => $online ? 1 : NULL,
            "status"          => !empty($profileStatus) ? $profileStatus : NULL,
            "before"          => $before != 0 ? $before : NULL,
            "after"           => $after  != 0 ? $after : NULL,
            "interests"       => !empty($interests) ? $interests : NULL,
            "fav_music"       => !empty($fav_music) ? $fav_music : NULL,
            "fav_films"       => !empty($fav_films) ? $fav_films : NULL,
            "fav_shows"       => !empty($fav_shows) ? $fav_shows : NULL,
            "fav_books"       => !empty($fav_books) ? $fav_books : NULL,
            "fav_quotes"      => !empty($fav_quotes) ? $fav_quotes : NULL,
        ];

        $find  = $users->find($q, $parameters, $sortg);

        foreach ($find as $user)
            $array[] = $user->getId();

        return (object) [
        	"count" => $find->size(),
        	"items" => $this->get(implode(',', $array), $nfilds, $offset, $count)
        ];
    }
}
