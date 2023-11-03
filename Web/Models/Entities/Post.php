<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use Chandler\Database\DatabaseConnection as DB;
use openvk\Web\Models\Repositories\{Clubs, Users};
use openvk\Web\Models\RowModel;
use openvk\Web\Models\Entities\Notifications\LikeNotification;

class Post extends Postable
{
    protected $tableName = "posts";
    protected $upperNodeReferenceColumnName = "wall";

    private function setLikeRecursively(bool $liked, User $user, int $depth): void
    {
        $searchData = [
            "origin" => $user->getId(),
            "model"  => static::class,
            "target" => $this->getRecord()->id,
        ];

        if((sizeof(DB::i()->getContext()->table("likes")->where($searchData)) > 0) !== $liked) {
            if($this->getOwner(false)->getId() !== $user->getId() && !($this->getOwner() instanceof Club) && !$this instanceof Comment)
                (new LikeNotification($this->getOwner(false), $this, $user))->emit();

            parent::setLike($liked, $user);
        }

        if($depth < ovkGetQuirk("wall.repost-liking-recursion-limit"))
            foreach($this->getChildren() as $attachment)
                if($attachment instanceof Post)
                    $attachment->setLikeRecursively($liked, $user, $depth + 1);
    }
    
    /**
     * May return fake owner (group), if flags are [1, (*)]
     * 
     * @param bool $honourFlags - check flags
     */
    function getOwner(bool $honourFlags = true, bool $real = false): RowModel
    {
        if($honourFlags && $this->isPostedOnBehalfOfGroup()) {
            if($this->getRecord()->wall < 0)
                return (new Clubs)->get(abs($this->getRecord()->wall));
        }
        
        return parent::getOwner($real);
    }
    
    function getPrettyId(): string
    {
        return $this->getRecord()->wall . "_" . $this->getVirtualId();
    }
    
    function getTargetWall(): int
    {
        return $this->getRecord()->wall;
    }

    function getWallOwner()
    {
        $w = $this->getRecord()->wall;
        if($w < 0)
            return (new Clubs)->get(abs($w));

        return (new Users)->get($w);
    }
    
    function getRepostCount(): int
    {
        return sizeof(
            $this->getRecord()
                 ->related("attachments.attachable_id")
                 ->where("attachable_type", get_class($this))
        );
    }
    
    function isPinned(): bool
    {
        return (bool) $this->getRecord()->pinned;
    }

    function getSource(bool $format = false)
    {
        if(!$format) {
            return $this->getRecord()->source;
        }

        return $this->formatLinks($this->getRecord()->source);
    }

    function setSource(string $source)
    {
        $src = $source;

        if(iconv_strlen($source) > 50) 
            throw new \LengthException("Link is too long.");
        
        if(!preg_match("/^(http:\/\/|https:\/\/)*[а-яА-ЯёЁa-z0-9\-_]+(\.[а-яА-ЯёЁa-z0-9\-_]+)+(\/\S*)*$/iu", $source))
            throw new \LogicException("Invalid link");

        if(!str_contains($source, "https://") && !str_contains($source, "http://"))
            $src = "https://" . $source;

        $this->stateChanges("source", $src);
    }
    
    function isAd(): bool
    {
        return (bool) $this->getRecord()->ad;
    }
    
    function isPostedOnBehalfOfGroup(): bool
    {
        return ($this->getRecord()->flags & 0b10000000) > 0;
    }
    
    function isSigned(): bool
    {
        return ($this->getRecord()->flags & 0b01000000) > 0;
    }

    function isDeactivationMessage(): bool
    {
        return (($this->getRecord()->flags & 0b00100000) > 0) && ($this->getRecord()->owner > 0);
    }
    
    function isUpdateAvatarMessage(): bool
    {
        return (($this->getRecord()->flags & 0b00010000) > 0) && ($this->getRecord()->owner > 0);
    }

    function isExplicit(): bool
    {
        return (bool) $this->getRecord()->nsfw;
    }
    
    function isDeleted(): bool
    {
        return (bool) $this->getRecord()->deleted;
    }
    
    function getOwnerPost(): int
    {
        return $this->getOwner(false)->getId();
    }

    function getPlatform(bool $forAPI = false): ?string
    {
        $platform = $this->getRecord()->api_source_name;
        if($forAPI) {
            switch ($platform) {
                case 'openvk_refresh_android':
                case 'openvk_legacy_android':
                    return 'android';
                    break;

                case 'openvk_ios':
                case 'openvk_legacy_ios':
                    return 'iphone';
                    break;
                
                case 'vika_touch': // кика хохотач ахахахаххахахахахах
                case 'vk4me':
                    return 'mobile';
                    break;

                case NULL:
                    return NULL;
                    break;
                
                default:
                    return 'api';
                    break;
            }
        } else {
            return $platform;
        }
    }

    function getPlatformDetails(): array
    {
        $clients = simplexml_load_file(OPENVK_ROOT . "/data/clients.xml");

        foreach($clients as $client) {
            if($client['tag'] == $this->getPlatform()) {
                return [
                    "tag"  => $client['tag'],
                    "name" => $client['name'],
                    "url"  => $client['url'],
                    "img"  => $client['img']
                ];
                break;
            }
        }

        return [
            "tag"  => $this->getPlatform(),
            "name" => NULL,
            "url"  => NULL,
            "img"  => NULL
        ];
    }
    
    function pin(): void
    {
        DB::i()
            ->getContext()
            ->table("posts")
            ->where([
                "wall"   => $this->getTargetWall(),
                "pinned" => true,
            ])
            ->update(["pinned" => false]);
        
        $this->stateChanges("pinned", true);
        $this->save();
    }
    
    function unpin(): void
    {
        $this->stateChanges("pinned", false);
        $this->save();
    }
    
    function canBePinnedBy(User $user): bool
    {
        if($this->getTargetWall() < 0)
            return (new Clubs)->get(abs($this->getTargetWall()))->canBeModifiedBy($user);
        
        return $this->getTargetWall() === $user->getId();
    }
    
    function canBeDeletedBy(User $user): bool
    {
        return $this->getOwnerPost() === $user->getId() || $this->canBePinnedBy($user);
    }
    
    function setContent(string $content): void
    {
        if(ctype_space($content))
            throw new \LengthException("Content length must be at least 1 character (not counting whitespaces).");
        else if(iconv_strlen($content) > OPENVK_ROOT_CONF["openvk"]["preferences"]["wall"]["postSizes"]["maxSize"])
            throw new \LengthException("Content is too large.");
        
        $this->stateChanges("content", $content);
    }

    function toggleLike(User $user): bool
    {
        $liked = parent::toggleLike($user);

        if($this->getOwner(false)->getId() !== $user->getId() && !($this->getOwner() instanceof Club) && !$this instanceof Comment)
            (new LikeNotification($this->getOwner(false), $this, $user))->emit();

        foreach($this->getChildren() as $attachment)
            if($attachment instanceof Post)
                $attachment->setLikeRecursively($liked, $user, 2);

        return $liked;
    }

    function setLike(bool $liked, User $user): void
    {
        $this->setLikeRecursively($liked, $user, 1);
    }
    
    function deletePost(): void 
    {
        $this->setDeleted(1);
        $this->unwire();
        $this->save();
    }

    function canBeEditedBy(?User $user = NULL): bool
    {
        if(!$user)
            return false;

        if($this->isDeactivationMessage() || $this->isUpdateAvatarMessage())
            return false;

        if($this->getTargetWall() > 0)
            return $this->getPublicationTime()->timestamp() + WEEK > time() && $user->getId() == $this->getOwner(false)->getId();

        return $user->getId() == $this->getOwner(false)->getId();
    }
    
    use Traits\TRichText;
}
