<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use Nette\Utils\Image;
use Nette\Utils\UnknownImageFileException;
use openvk\Web\Models\Repositories\Users;
use openvk\Web\Models\Repositories\Clubs;
use openvk\Web\Models\RowModel;

class Link extends RowModel
{
    protected $tableName = "links";
    
    private function getIconsDir(): string
    {
        $uploadSettings = OPENVK_ROOT_CONF["openvk"]["preferences"]["uploads"];
        if($uploadSettings["mode"] === "server" && $uploadSettings["server"]["kind"] === "cdn")
            return $uploadSettings["server"]["directory"];
        else
            return OPENVK_ROOT . "/storage/";
    }
    
    function getId(): int
    {
        return $this->getRecord()->id;
    }
    
    function getOwner(): RowModel
    {
        $ownerId = (int) $this->getRecord()->owner;

        if($ownerId > 0)
            return (new Users)->get($ownerId);
        else
            return (new Clubs)->get($ownerId * -1);
    }
    
    function getTitle(): string
    {
        return $this->getRecord()->title;
    }

    function getDescription(): ?string
    {
        return $this->getRecord()->description;
    }

    function getDescriptionOrDomain(): string
    {
        $description = $this->getDescription();

        if(is_null($description))
            return $this->getDomain();
        else
            return $description;
    }
    
    function getUrl(): string
    {
        return $this->getRecord()->url;
    }
    
    function getIconUrl(): string
    {
        $serverUrl = ovk_scheme(true) . $_SERVER["HTTP_HOST"];
        if(is_null($this->getRecord()->icon_hash))
            return "$serverUrl/assets/packages/static/openvk/img/camera_200.png";
    
        $hash = $this->getRecord()->icon_hash;
        switch(OPENVK_ROOT_CONF["openvk"]["preferences"]["uploads"]["mode"]) {
            default:
            case "default":
            case "basic":
                return "$serverUrl/blob_" . substr($hash, 0, 2) . "/$hash" . "_link_icon.png";
            case "accelerated":
                return "$serverUrl/openvk-datastore/$hash" . "_link_icon.png";
            case "server":
                $settings = (object) OPENVK_ROOT_CONF["openvk"]["preferences"]["uploads"]["server"];
                return (
                    $settings->protocol ?? ovk_scheme() .
                    "://" . $settings->host .
                    $settings->path .
                    substr($hash, 0, 2) . "/$hash" . "_link_icon.png"
                );
        }
    }
    
    function setIcon(array $file): int
    {
        if($file["error"] !== UPLOAD_ERR_OK)
            return -1;
        
        try {
            $image = Image::fromFile($file["tmp_name"]);
        } catch (UnknownImageFileException $e) {
            return -2;
        }
    
        $hash = hash_file("adler32", $file["tmp_name"]);
        if(!is_dir($this->getIconsDir() . substr($hash, 0, 2)))
            if(!mkdir($this->getIconsDir() . substr($hash, 0, 2)))
                return -3;
        
        $image->resize(140, 140, Image::STRETCH);
        $image->save($this->getIconsDir() . substr($hash, 0, 2) . "/$hash" . "_link_icon.png");
        
        $this->stateChanges("icon_hash", $hash);
        
        return 0;
    }

    function getDomain(): string
    {
        return parse_url($this->getUrl(), PHP_URL_HOST);
    }

    use Traits\TOwnable;
}
