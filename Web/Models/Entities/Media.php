<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use Nette\InvalidStateException as ISE;

abstract class Media extends Postable
{
    protected $fileExtension = "oct"; #octet stream xddd
    protected $upperNodeReferenceColumnName = "owner";
    protected $processingPlaceholder = NULL;
    protected $processingTime = 30;

    function __destruct()
    {
        #Remove data, if model wasn't presisted
        if(isset($this->changes["hash"]))
            unlink($this->pathFromHash($this->changes["hash"]));
    }
    
    protected function getBaseDir(): string
    {
        $uploadSettings = OPENVK_ROOT_CONF["openvk"]["preferences"]["uploads"];
        if($uploadSettings["mode"] === "server" && $uploadSettings["server"]["kind"] === "cdn")
            return $uploadSettings["server"]["directory"];
        else
            return OPENVK_ROOT . "/storage/";
    }

    protected function checkIfFileIsProcessed(): bool
    {
        throw new \LogicException("checkIfFileIsProcessed is not implemented");
    }
    
    abstract protected function saveFile(string $filename, string $hash): bool;
    
    protected function pathFromHash(string $hash): string
    {
        $dir = $this->getBaseDir() . substr($hash, 0, 2);
        if(!is_dir($dir))
            mkdir($dir);
        
        return "$dir/$hash." . $this->fileExtension;
    }
    
    function getFileName(): string
    {
        return $this->pathFromHash($this->getRecord()->hash);
    }
    
    function getURL(): string
    {
        if(!is_null($this->processingPlaceholder))
            if(!$this->isProcessed())
                return "/assets/packages/static/openvk/$this->processingPlaceholder.$this->fileExtension";

        $hash = $this->getRecord()->hash;
        
        switch(OPENVK_ROOT_CONF["openvk"]["preferences"]["uploads"]["mode"]) {
            default:
            case "default":
            case "basic":
                return "http://" . $_SERVER['HTTP_HOST'] . "/blob_" . substr($hash, 0, 2) . "/$hash.$this->fileExtension";
            break;
            case "accelerated":
                return "http://" . $_SERVER['HTTP_HOST'] . "/openvk-datastore/$hash.$this->fileExtension";
            break;
            case "server":
                $settings = (object) OPENVK_ROOT_CONF["openvk"]["preferences"]["uploads"]["server"];
                return (
                    $settings->protocol ?? ovk_scheme() .
                    "://" . $settings->host .
                    $settings->path .
                    substr($hash, 0, 2) . "/$hash.$this->fileExtension"
                );
            break;
        }
    }
    
    function getDescription(): ?string
    {
        return $this->getRecord()->description;
    }

    protected function isProcessed(): bool
    {
        if(is_null($this->processingPlaceholder))
            return true;

        if($this->getRecord()->processed)
            return true;

        $timeDiff = time() - $this->getRecord()->last_checked;
        if($timeDiff < $this->processingTime)
            return false;

        $res = $this->checkIfFileIsProcessed();
        $this->stateChanges("last_checked", time());
        $this->stateChanges("processed", $res);
        $this->save();

        return $res;
    }
    
    function isDeleted(): bool
    {
        return (bool) $this->getRecord()->deleted;
    }
    
    function setHash(string $hash): void
    {
        throw new ISE("Setting file hash manually is forbidden");
    }
    
    function setFile(array $file): void
    {
        if($file["error"] !== UPLOAD_ERR_OK)
            throw new ISE("File uploaded is corrupted");
        
        $hash = hash_file("whirlpool", $file["tmp_name"]);
        $this->saveFile($file["tmp_name"], $hash);
        
        $this->stateChanges("hash", $hash);
    }

    function save(?bool $log = false): void
    {
        if(!is_null($this->processingPlaceholder) && is_null($this->getRecord())) {
            $this->stateChanges("processed", 0);
            $this->stateChanges("last_checked", time());
        }

        parent::save($log);
    }

    function delete(bool $softly = true): void
    {
        $deleteQuirk = ovkGetQuirk("blobs.erase-upon-deletion");
        if($deleteQuirk === 2 || ($deleteQuirk === 1 && !$softly))
            @unlink($this->getFileName());
        
        parent::delete($softly);
    }
    
    function undelete(): void
    {
        if(ovkGetQuirk("blobs.erase-upon-deletion") === 2)
            throw new \LogicException("Can't undelete model which is tied to blob, because of config constraint (quriks.yml:blobs.erase-upon-deletion)");
        
        parent::undelete();
    }
}
