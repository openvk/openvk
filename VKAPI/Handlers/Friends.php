<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use openvk\Web\Models\Repositories\Users as UsersRepo;

final class Friends extends VKAPIRequestHandler
{
	function get(int $user_id = 0, string $fields = "", int $offset = 0, int $count = 100): object
	{
		$i = 0;
		$offset++;
		$friends = [];

		$users = new UsersRepo;

		$this->requireUser();
    
    	if ($user_id == 0) {
			$user_id = $this->getUser()->getId();
		}
    
        $user = $users->get($user_id);
		
        if(!$user || $user->isDeleted())
            $this->fail(100, "Invalid user");

        if(!$user->getPrivacyPermission("friends.read", $this->getUser()))
            $this->fail(15, "Access denied: this user chose to hide his friends.");
        
        foreach($user->getFriends($offset, $count) as $friend) {
            $friends[$i] = $friend->getId();
            $i++;
        }

		$response = $friends;

		$usersApi = new Users($this->getUser());

		if(!is_null($fields))
			$response = $usersApi->get(implode(',', $friends), $fields, 0, $count);  # FIXME

		return (object) [
			"count" => $users->get($user_id)->getFriendsCount(),
			"items" => $response
		];
	}

	function getLists(): object
	{
		$this->requireUser();

		return (object) [
			"count" => 0,
			"items" => (array)[]
		];
	}

	function deleteList(): int
	{
		$this->requireUser();

		return 1;
	}

	function edit(): int
	{
		$this->requireUser();

		return 1;
	}

	function editList(): int
	{
		$this->requireUser();

		return 1;
	}

	function add(string $user_id): int
	{
		$this->requireUser();
        $this->willExecuteWriteAction();

		$users = new UsersRepo;
		$user  = $users->get(intval($user_id));
		
		if(is_null($user)) {
			$this->fail(177, "Cannot add this user to friends as user not found");
		} else if($user->getId() == $this->getUser()->getId()) {
			$this->fail(174, "Cannot add user himself as friend");
		}

		switch($user->getSubscriptionStatus($this->getUser())) {
			case 0:
				$user->toggleSubscription($this->getUser());
				return 1;

			case 1:
				$user->toggleSubscription($this->getUser());
				return 2;

			case 3:
				return 2;
			
			default:
				return 1;
		}
	}

	function delete(string $user_id): int
	{
		$this->requireUser();
        $this->willExecuteWriteAction();

		$users = new UsersRepo;

		$user = $users->get(intval($user_id));

		switch($user->getSubscriptionStatus($this->getUser())) {
			case 3:
				$user->toggleSubscription($this->getUser());
				return 1;
			
			default:
				$this->fail(15, "Access denied: No friend or friend request found.");
		}
	}

	function areFriends(string $user_ids): array
	{
		$this->requireUser();

		$users = new UsersRepo;

		$friends = explode(',', $user_ids);

		$response = [];

		for($i=0; $i < sizeof($friends); $i++) { 
			$friend = $users->get(intval($friends[$i]));

			$response[] = (object)[
				"friend_status" => $friend->getSubscriptionStatus($this->getUser()),
				"user_id" 		=> $friend->getId()
			];
		}

		return $response;
	}

	function getRequests(string $fields = "", int $offset = 0, int $count = 100, int $extended = 0): object
	{
		if ($count >= 1000)
			$this->fail(100, "One of the required parameters was not passed or is invalid.");

		$this->requireUser();

		$i = 0;
		$offset++;
		$followers = [];

		foreach($this->getUser()->getFollowers($offset, $count) as $follower) {
			$followers[$i] = $follower->getId();
			$i++;
		}

		$response = $followers;
		$usersApi = new Users($this->getUser());

		$response = $usersApi->get(implode(',', $followers), $fields, 0, $count);

		foreach($response as $user)
			$user->user_id = $user->id;

		return (object) [
			"count" => $this->getUser()->getFollowersCount(),
			"items" => $response
		];
	}
}
