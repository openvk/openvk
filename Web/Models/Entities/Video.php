<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Util\Shell\Shell;
use openvk\Web\Util\Shell\Exceptions\{ShellUnavailableException, UnknownCommandException};
use openvk\Web\Models\VideoDrivers\VideoDriver;
use Nette\InvalidStateException as ISE;

define("VIDEOS_FRIENDLY_ERROR", "Uploads are disabled on this instance :<", false);

class Video extends Media
{
    const TYPE_DIRECT = 0;
    const TYPE_EMBED  = 1;
    
    protected $tableName     = "videos";
    protected $fileExtension = "mp4";

    protected $processingPlaceholder = "video/rendering";
    
    protected function saveFile(string $filename, string $hash): bool
    {
        if(!Shell::commandAvailable("ffmpeg") || !Shell::commandAvailable("ffprobe"))
            exit(VIDEOS_FRIENDLY_ERROR);
        
        $error     = NULL;
        $streams   = Shell::ffprobe("-i", $filename, "-show_streams", "-select_streams v", "-loglevel error")->execute($error);
        if($error !== 0)
            throw new \DomainException("$filename is not a valid video file");
        else if(empty($streams) || ctype_space($streams))
            throw new \DomainException("$filename does not contain any video streams");
        
        $durations = [];
        preg_match_all('%duration=([0-9\.]++)%', $streams, $durations);
        if(sizeof($durations[1]) === 0)
            throw new \DomainException("$filename does not contain any meaningful video streams");
        
        foreach($durations[1] as $duration)
            if(floatval($duration) < 1.0)
                throw new \DomainException("$filename does not contain any meaningful video streams");
        
        try {
            if(!is_dir($dirId = dirname($this->pathFromHash($hash))))
                mkdir($dirId);
            
            $dir = $this->getBaseDir();
            $ext = Shell::isPowershell() ? "ps1" : "sh";
            $cmd = Shell::isPowershell() ? "powershell" : "bash";
            Shell::$cmd(__DIR__ . "/../shell/processVideo.$ext", OPENVK_ROOT, $filename, $dir, $hash)->start(); #async :DDD
        } catch(ShellUnavailableException $suex) {
            exit(OPENVK_ROOT_CONF["openvk"]["debug"] ? "Shell is unavailable" : VIDEOS_FRIENDLY_ERROR);
        } catch(UnknownCommandException $ucex) {
            exit(OPENVK_ROOT_CONF["openvk"]["debug"] ? "bash is not installed" : VIDEOS_FRIENDLY_ERROR);
        }
        
        usleep(200100);
        return true;
    }

    protected function checkIfFileIsProcessed(): bool
    {
        if($this->getType() != Video::TYPE_DIRECT)
            return true;

        if(!file_exists($this->getFileName())) {
            if((time() - $this->getRecord()->last_checked) > 3600) {
                # TODO notify that video processor is probably dead
            }

            return false;
        }

        return true;
    }

    function getName(): string
    {
        return $this->getRecord()->name;
    }
    
    function getType(): int
    {
        if(!is_null($this->getRecord()->hash))
            return Video::TYPE_DIRECT;
        else if(!is_null($this->getRecord()->link))
            return Video::TYPE_EMBED;
    }
    
    function getVideoDriver(): ?VideoDriver
    {
        if($this->getType() !== Video::TYPE_EMBED)
            return NULL;
        
        [$videoDriver, $pointer] = explode(":", $this->getRecord()->link);
        $videoDriver = "openvk\\Web\\Models\\VideoDrivers\\$videoDriver" . "VideoDriver";
        if(!class_exists($videoDriver))
            return NULL;
        
        return new $videoDriver($pointer);
    }
    
    function getThumbnailURL(): string
    {
        if($this->getType() === Video::TYPE_DIRECT) {
            if(!$this->isProcessed())
                return "/assets/packages/static/openvk/video/rendering.apng";

            return preg_replace("%\.[A-z0-9]++$%", ".gif", $this->getURL());
        } else {
            return $this->getVideoDriver()->getThumbnailURL();
        }
    }

    function getOwnerVideo(): int
    {
        return $this->getRecord()->owner;
    }

    function getApiStructure(?User $user = NULL): object
    {
        $fromYoutube = $this->getType() == Video::TYPE_EMBED;
        $res = (object)[
            "type" => "video",
            "video" => [
                "can_comment" => 1,
                "can_like" => 1,  // we don't h-have wikes in videos
                "can_repost" => 1,
                "can_subscribe" => 1,
                "can_add_to_faves" => 0,
                "can_add" => 0,
                "comments" => $this->getCommentsCount(),
                "date" => $this->getPublicationTime()->timestamp(),
                "description" => $this->getDescription(),
                "duration" => 0, // я хуй знает как получить длину видео
                "image" => [
                    [
                        "url" => $this->getThumbnailURL(),
                        "width" => 320,
                        "height" => 240,
                        "with_padding" => 1
                    ]
                ],
                "width" => 640,
                "height" => 480,
                "id" => $this->getVirtualId(),
                "owner_id" => $this->getOwner()->getId(),
                "user_id" => $this->getOwner()->getId(),
                "title" => $this->getName(),
                "is_favorite" => false,
                "player" => !$fromYoutube ? $this->getURL() : $this->getVideoDriver()->getURL(),
                "files" => !$fromYoutube ? [
                    "mp4_480" => $this->getURL()	
                ] : NULL,
                "platform" => $fromYoutube ? "youtube" : NULL,
                "added" => 0,
                "repeat" => 0,
                "type" => "video",
                "views" => 0,
                "reposts" => [
                    "count" => 0,
                    "user_reposted" => 0
                ]
            ]
        ];

        if(!is_null($user)) {
            $res->video["likes"] = [
                "count" => $this->getLikesCount(),
                "user_likes" => $this->hasLikeFrom($user)
            ];
        }

        return $res;
    }
    
    function toVkApiStruct(?User $user): object
    {
        return $this->getApiStructure($user);
    }

    function setLink(string $link): string
    {
        if(preg_match(file_get_contents(__DIR__ . "/../VideoDrivers/regex/youtube.txt"), $link, $matches)) {
            $pointer = "YouTube:$matches[1]";
        } else if(preg_match(file_get_contents(__DIR__ . "/../VideoDrivers/regex/vimeo.txt"), $link, $matches)) {
            $pointer = "Vimeo:$matches[1]";
        } else {
            throw new ISE("Invalid link");
        }
        
        $this->stateChanges("link", $pointer);
        
        return $pointer;
    }

    function isDeleted(): bool
    {
        if ($this->getRecord()->deleted == 1)
            return TRUE;
        else
            return FALSE;
    }

    function deleteVideo(): void 
    {
        $this->setDeleted(1);
        $this->unwire();
        $this->save();
    }
    
    static function fastMake(int $owner, string $name = "Unnamed Video.ogv", string $description = "", array $file, bool $unlisted = true, bool $anon = false): Video
    {
        if(OPENVK_ROOT_CONF['openvk']['preferences']['videos']['disableUploading'])
            exit(VIDEOS_FRIENDLY_ERROR);

        $video = new Video;
        $video->setOwner($owner);
        $video->setName(ovk_proc_strtr($name, 61));
        $video->setDescription(ovk_proc_strtr($description, 300));
        $video->setAnonymous($anon);
        $video->setCreated(time());
        $video->setFile($file);
        $video->setUnlisted($unlisted);
        $video->save();
        
        return $video;
    }
    
    function canBeViewedBy(?User $user = NULL): bool
    {
        if($this->isDeleted() || $this->getOwner()->isDeleted()) {
            return false;
        }

        if(get_class($this->getOwner()) == "openvk\\Web\\Models\\Entities\\User") {
            return $this->getOwner()->canBeViewedBy($user) && $this->getOwner()->getPrivacyPermission('videos.read', $user);
        } else {
            # Groups doesn't have videos but ok
            return $this->getOwner()->canBeViewedBy($user);
        }
    }
    
    function toNotifApiStruct()
    {
        $fromYoutube = $this->getType() == Video::TYPE_EMBED;
        $res = (object)[];
        
        $res->id          = $this->getVirtualId();
        $res->owner_id    = $this->getOwner()->getId();
        $res->title       = $this->getName();
        $res->description = $this->getDescription();
        $res->duration    = "22";
        $res->link        = "/video".$this->getOwner()->getId()."_".$this->getVirtualId();
        $res->image       = $this->getThumbnailURL();
        $res->date        = $this->getPublicationTime()->timestamp();
        $res->views       = 0;
        $res->player      = !$fromYoutube ? $this->getURL() : $this->getVideoDriver()->getURL();

        return $res;
    }
}
