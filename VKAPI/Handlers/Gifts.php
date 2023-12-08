<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use openvk\Web\Models\Repositories\Users as UsersRepo;
use openvk\Web\Models\Repositories\Gifts as GiftsRepo;
use openvk\Web\Models\Entities\Notifications\GiftNotification;

final class Gifts extends VKAPIRequestHandler
{
    function get(int $user_id, int $count = 10, int $offset = 0)
    {
        $this->requireUser();

        $i = 0;

        $i += $offset;

        $user = (new UsersRepo)->get($user_id);

        if(!$user || $user->isDeleted())
            $this->fail(177, "Invalid user");

        if(!$user->canBeViewedBy($this->getUser()))
            $this->fail(15, "Access denied");

        /*
        if(!$user->getPrivacyPermission('gifts.read', $this->getUser()))
            $this->fail(15, "Access denied: this user chose to hide his gifts");*/

        
        if(!$user->canBeViewedBy($this->getUser()))
            $this->fail(15, "Access denied");

        $gift_item = [];

        $userGifts = array_slice(iterator_to_array($user->getGifts(1, $count, false)), $offset);

        if(sizeof($userGifts) < 0) {
            return NULL;
        }

        foreach($userGifts as $gift) {
            if($i < $count) {
                $gift_item[] = [
                    "id"        => $i,
                    "from_id"   => $gift->anon == true ? 0 : $gift->sender->getId(),
                    "message"   => $gift->caption == NULL ? "" : $gift->caption,
                    "date"      => $gift->sent->timestamp(),
                    "gift"      => [
                        "id"          => $gift->gift->getId(),
                        "thumb_256"   => $gift->gift->getImage(2),
                        "thumb_96"    => $gift->gift->getImage(2),
                        "thumb_48"    => $gift->gift->getImage(2)
                    ],
                    "privacy"   => 0
                ];
            }
            $i+=1;
        }

        return $gift_item;
    }

    function send(int $user_ids, int $gift_id, string $message = "", int $privacy = 0)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $user = (new UsersRepo)->get((int) $user_ids);

        if(!OPENVK_ROOT_CONF['openvk']['preferences']['commerce'])
            $this->fail(105, "Commerce is disabled on this instance");
        
        if(!$user || $user->isDeleted())
            $this->fail(177, "Invalid user");

        if(!$user->canBeViewedBy($this->getUser()))
            $this->fail(15, "Access denied");

        $gift  = (new GiftsRepo)->get($gift_id);

        if(!$gift)
            $this->fail(165, "Invalid gift");
        
        $price = $gift->getPrice();
        $coinsLeft = $this->getUser()->getCoins() - $price;

        if(!$gift->canUse($this->getUser()))
            return (object)
            [
                "success"         => 0,
                "user_ids"        => $user_ids,
                "error"           => "You don't have any more of these gifts."
            ];

        if($coinsLeft < 0)
            return (object)
            [
                "success"         => 0,
                "user_ids"        => $user_ids,
                "error"           => "You don't have enough voices."
            ];

        $user->gift($this->getUser(), $gift, $message);
        $gift->used();

        $this->getUser()->setCoins($coinsLeft);
        $this->getUser()->save();

        $notification = new GiftNotification($user, $this->getUser(), $gift, $message);
        $notification->emit();

        return (object)
        [
            "success"         => 1,
            "user_ids"        => $user_ids,
            "withdraw_votes"  => $price
        ];
    }

    function delete()
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $this->fail(501, "Not implemented");
    }

    # этих методов не было в ВК, но я их добавил чтобы можно было отобразить список подарков
    function getCategories(bool $extended = false, int $page = 1)
    {
        $cats = (new GiftsRepo)->getCategories($page);
        $categ = [];
        $i = 0;

        if(!OPENVK_ROOT_CONF['openvk']['preferences']['commerce'])
            $this->fail(105, "Commerce is disabled on this instance");

        foreach($cats as $cat) {
            $categ[$i] = [
                "name"        => $cat->getName(),
                "description" => $cat->getDescription(),
                "id"          => $cat->getId(),
                "thumbnail"   => $cat->getThumbnailURL(),
                ];
            
            if($extended == true) {
                $categ[$i]["localizations"] = [];
                foreach(getLanguages() as $lang) {
                    $code = $lang["code"];
                    $categ[$i]["localizations"][$code] =
                    [
                        "name"    => $cat->getName($code),
                        "desc"    => $cat->getDescription($code),
                    ];
                }
            }
            $i++;
        }
        
        return $categ;
    }

    function getGiftsInCategory(int $id, int $page = 1)
    {
        $this->requireUser();

        if(!OPENVK_ROOT_CONF['openvk']['preferences']['commerce'])
            $this->fail(105, "Commerce is disabled on this instance");

        if(!(new GiftsRepo)->getCat($id))
            $this->fail(177, "Category not found");

        $giftz = ((new GiftsRepo)->getCat($id))->getGifts($page);
        $gifts = [];

        foreach($giftz as $gift) {
            $gifts[] = [
                "name"         => $gift->getName(),
                "image"        => $gift->getImage(2),
                "usages_left"  => (int)$gift->getUsagesLeft($this->getUser()),
                "price"        => $gift->getPrice(), # голосов
                "is_free"      => $gift->isFree()
            ];
        }

        return $gifts;
    }
}
