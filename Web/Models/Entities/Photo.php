<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use MessagePack\MessagePack;
use Nette\Utils\ImageException;
use Nette\Utils\UnknownImageFileException;
use openvk\Web\Models\Entities\Album;
use openvk\Web\Models\Repositories\Albums;
use Chandler\Database\DatabaseConnection as DB;
use Nette\InvalidStateException as ISE;
use Nette\Utils\Image;

class Photo extends Media
{
    protected $tableName     = "photos";
    protected $fileExtension = "jpeg";

    const ALLOWED_SIDE_MULTIPLIER = 7;
    
    /**
     * @throws \ImagickException
     * @throws ImageException
     * @throws UnknownImageFileException
     */
    private function resizeImage(\Imagick $image, string $outputDir, \SimpleXMLElement $size): array
    {
        $res = [false];
        $requiresProportion = ((string) $size["requireProp"]) != "none";
        if($requiresProportion) {
            $props = explode(":", (string) $size["requireProp"]);
            $px = (int) $props[0];
            $py = (int) $props[1];
            if(($image->getImageWidth() / $image->getImageHeight()) > ($px / $py)) {
                $height = (int) ceil(($px * $image->getImageWidth()) / $py);
                $image->cropImage($image->getImageWidth(), $height, 0, 0);
                $res[0] = true;
            }
        }
    
        if(isset($size["maxSize"])) {
            $maxSize = (int) $size["maxSize"];
            $sizes   = Image::calculateSize($image->getImageWidth(), $image->getImageHeight(), $maxSize, $maxSize, Image::SHRINK_ONLY | Image::FIT);
            $image->resizeImage($sizes[0], $sizes[1], \Imagick::FILTER_HERMITE, 1);
        } else if(isset($size["maxResolution"])) {
            $resolution = explode("x", (string) $size["maxResolution"]);
            $sizes = Image::calculateSize(
                $image->getImageWidth(), $image->getImageHeight(), (int) $resolution[0], (int) $resolution[1], Image::SHRINK_ONLY | Image::FIT
            );
            $image->resizeImage($sizes[0], $sizes[1], \Imagick::FILTER_HERMITE, 1);
        } else {
            throw new \RuntimeException("Malformed size description: " . (string) $size["id"]);
        }
    
        $res[1] = $image->getImageWidth();
        $res[2] = $image->getImageHeight();
        if($res[1] <= 300 || $res[2] <= 300)
            $image->writeImage("$outputDir/$size[id].gif");
        else
            $image->writeImage("$outputDir/$size[id].jpeg");
        
        $res[3] = true;
        $image->destroy();
        unset($image);
        
        return $res;
    }

    private function saveImageResizedCopies(?\Imagick $image, string $filename, string $hash): void
    {
        if(!$image) {
            $image = new \Imagick;
            $image->readImage($filename);
        }
        
        $dir = dirname($this->pathFromHash($hash));
        $dir = "$dir/$hash" . "_cropped";
        if(!is_dir($dir)) {
            @unlink($dir); # Added to transparently bypass issues with dead pesudofolders summoned by buggy SWIFT impls (selectel)
            mkdir($dir);
        }

        $sizes = simplexml_load_file(OPENVK_ROOT . "/data/photosizes.xml");
        if(!$sizes)
            throw new \RuntimeException("Could not load photosizes.xml!");

        $sizesMeta = [];
        if(OPENVK_ROOT_CONF["openvk"]["preferences"]["photos"]["photoSaving"] === "quick") {
            foreach($sizes->Size as $size)
                $sizesMeta[(string)$size["id"]] = [false, false, false, false];
        } else {
            foreach($sizes->Size as $size)
                $sizesMeta[(string)$size["id"]] = $this->resizeImage(clone $image, $dir, $size);
        }

        $sizesMeta = MessagePack::pack($sizesMeta);
        $this->stateChanges("sizes", $sizesMeta);
    }

    protected function saveFile(string $filename, string $hash): bool
    {
        $image = new \Imagick;
        $image->readImage($filename);
        $h = $image->getImageHeight();
        $w = $image->getImageWidth();
        if(($h >= ($w * Photo::ALLOWED_SIDE_MULTIPLIER)) || ($w >= ($h * Photo::ALLOWED_SIDE_MULTIPLIER)))
            throw new ISE("Invalid layout: image is too wide/short");
    
        $sizes = Image::calculateSize(
            $image->getImageWidth(), $image->getImageHeight(), 8192, 4320, Image::SHRINK_ONLY | Image::FIT
        );
        $image->resizeImage($sizes[0], $sizes[1], \Imagick::FILTER_HERMITE, 1);
        $image->writeImage($this->pathFromHash($hash));
        $this->saveImageResizedCopies($image, $filename, $hash);
        
        return true;
    }
    
    function crop(real $left, real $top, real $width, real $height): void
    {
        if(isset($this->changes["hash"]))
            $hash = $this->changes["hash"];
        else if(!is_null($this->getRecord()))
            $hash = $this->getRecord()->hash;
        else
            throw new ISE("Cannot crop uninitialized image. Please call setFile(\$_FILES[...]) first.");
        
        $image = Image::fromFile($this->pathFromHash($hash));
        $image->crop($left, $top, $width, $height);
        $image->save($this->pathFromHash($hash));
    }
    
    function isolate(): void
    {
        if(is_null($this->getRecord()))
            throw new ISE("Cannot isolate unpresisted image. Please save() it first.");
        
        DB::i()->getContext()->table("album_relations")->where("media", $this->getRecord()->id)->delete();
    }

    function getSizes(bool $upgrade = false, bool $forceUpdate = false): ?array
    {
        $sizes = $this->getRecord()->sizes;
        if(!$sizes || $forceUpdate) {
            if($forceUpdate || $upgrade || OPENVK_ROOT_CONF["openvk"]["preferences"]["photos"]["upgradeStructure"]) {
                $hash = $this->getRecord()->hash;
                $this->saveImageResizedCopies(NULL, $this->pathFromHash($hash), $hash);
                $this->save();

                return $this->getSizes();
            }

            return NULL;
        }

        $res   = [];
        $sizes = MessagePack::unpack($sizes);
        foreach($sizes as $id => $meta) {
            if(isset($meta[3]) && !$meta[3]) {
                $res[$id] = (object) [
                    "url"    => ovk_scheme(true) . $_SERVER["HTTP_HOST"] . "/photos/thumbnails/" . $this->getId() . "_$id.jpeg",
                    "width"  => NULL,
                    "height" => NULL,
                    "crop"   => NULL
                ];
                continue;
            }
            
            $url  = $this->getURL();
            $url  = str_replace(".$this->fileExtension", "_cropped/$id.", $url);
            $url .= ($meta[1] <= 300 || $meta[2] <= 300) ? "gif" : "jpeg";

            $res[$id] = (object) [
                "url"    => $url,
                "width"  => $meta[1],
                "height" => $meta[2],
                "crop"   => $meta[0]
            ];
        }

        [$x, $y] = $this->getDimensions();
        $res["UPLOADED_MAXRES"] = (object) [
            "url"    => $this->getURL(),
            "width"  => $x,
            "height" => $y,
            "crop"   => false
        ];

        return $res;
    }
    
    function forceSize(string $sizeName): bool
    {
        $hash  = $this->getRecord()->hash;
        $sizes = MessagePack::unpack($this->getRecord()->sizes);
        $size  = $sizes[$sizeName] ?? false;
        if(!$size)
            return $size;
        
        if(!isset($size[3]) || $size[3] === true)
            return true;
    
        $path = $this->pathFromHash($hash);
        $dir  = dirname($this->pathFromHash($hash));
        $dir  = "$dir/$hash" . "_cropped";
        if(!is_dir($dir)) {
            @unlink($dir);
            mkdir($dir);
        }
    
        $sizeMetas = simplexml_load_file(OPENVK_ROOT . "/data/photosizes.xml");
        if(!$sizeMetas)
            throw new \RuntimeException("Could not load photosizes.xml!");
        
        $sizeInfo = NULL;
        foreach($sizeMetas->Size as $size)
            if($size["id"] == $sizeName)
                $sizeInfo = $size;
        
        if(!$sizeInfo)
            return false;
        
        $pic = new \Imagick;
        $pic->readImage($path);
        $sizes[$sizeName] = $this->resizeImage($pic, $dir, $sizeInfo);
        
        $this->stateChanges("sizes", MessagePack::pack($sizes));
        $this->save();
        
        return $sizes[$sizeName][3];
    }

    function getVkApiSizes(): ?array
    {
        $res   = [];
        $sizes = $this->getSizes();
        if(!$sizes)
            return NULL;

        $manifest = simplexml_load_file(OPENVK_ROOT . "/data/photosizes.xml");
        if(!$manifest)
            return NULL;

        $mappings = [];
        foreach($manifest->Size as $size)
            $mappings[(string) $size["id"]] = (string) $size["vkId"];

        foreach($sizes as $id => $meta) {
            $type       = $mappings[$id] ?? $id;
            $meta->type = $type;
            $res[$type] = $meta;
        }

        return $res;
    }

    function getURLBySizeId(string $size): string
    {
        $sizes = $this->getSizes();
        if(!$sizes)
            return $this->getURL();

        $size = $sizes[$size];
        if(!$size)
            return $this->getURL();

        return $size->url;
    }

    function getDimensions(): array
    {
        $x = $this->getRecord()->width;
        $y = $this->getRecord()->height;
        if(!$x) { # no sizes in database
            $hash  = $this->getRecord()->hash;
            $image = Image::fromFile($this->pathFromHash($hash));

            $x = $image->getWidth();
            $y = $image->getHeight();
            $this->stateChanges("width", $x);
            $this->stateChanges("height", $y);
            $this->save();
        }

        return [$x, $y];
    }

    function getPageURL(): string
    {
        if($this->isAnonymous())
            return "/photos/" . base_convert((string) $this->getId(), 10, 32);

        return "/photo" . $this->getPrettyId();
    }

    function getAlbum(): ?Album
    {
        return (new Albums)->getAlbumByPhotoId($this);
    }

    function toVkApiStruct(bool $photo_sizes = true, bool $extended = false): object
    {
        $res = (object) [];

        $res->id       = $res->pid = $this->getVirtualId();
        $res->owner_id = $res->user_id = $this->getOwner()->getId();
        $res->aid      = $res->album_id = NULL;
        $res->width    = $this->getDimensions()[0];
        $res->height   = $this->getDimensions()[1];
        $res->date     = $res->created = $this->getPublicationTime()->timestamp();

        if($photo_sizes) {
            $res->sizes        = $this->getVkApiSizes();
            $res->src_small    = $res->photo_75 = $this->getURLBySizeId("miniscule");
            $res->src          = $res->photo_130 = $this->getURLBySizeId("tiny");
            $res->src_big      = $res->photo_604 = $this->getURLBySizeId("normal");
            $res->src_xbig     = $res->photo_807 = $this->getURLBySizeId("large");
            $res->src_xxbig    = $res->photo_1280 = $this->getURLBySizeId("larger");
            $res->src_xxxbig   = $res->photo_2560 = $this->getURLBySizeId("original");
            $res->src_original = $res->url = $this->getURLBySizeId("UPLOADED_MAXRES");
        }

        if($extended) {
            $res->likes       = $this->getLikesCount(); # их нету но пусть будут
            $res->comments    = $this->getCommentsCount();
            $res->tags        = 0;
            $res->can_comment = 1;
            $res->can_repost  = 0;
        }

        return $res;
    }
    
    function canBeViewedBy(?User $user = NULL): bool
    {
        if($this->isDeleted() || $this->getOwner()->isDeleted()) {
            return false;
        }

        if(!is_null($this->getAlbum())) {
            return $this->getAlbum()->canBeViewedBy($user);
        } else {
            return $this->getOwner()->canBeViewedBy($user);
        }
    }

    static function fastMake(int $owner, string $description = "", array $file, ?Album $album = NULL, bool $anon = false): Photo
    {
        $photo = new static;
        $photo->setOwner($owner);
        $photo->setDescription(iconv_substr($description, 0, 36) . "...");
        $photo->setAnonymous($anon);
        $photo->setCreated(time());
        $photo->setFile($file);
        $photo->save();

        if(!is_null($album)) {
            $album->addPhoto($photo);
            $album->setEdited(time());
            $album->save();
        }

        return $photo;
    }

    function toNotifApiStruct()
    {
        $res = (object)[];
        
        $res->id        = $this->getVirtualId();
        $res->owner_id  = $this->getOwner()->getId();
        $res->aid       = 0;
        $res->src       = $this->getURLBySizeId("tiny");
        $res->src_big   = $this->getURLBySizeId("normal");
        $res->src_small = $this->getURLBySizeId("miniscule");
        $res->text      = $this->getDescription();
        $res->created   = $this->getPublicationTime()->timestamp();

        return $res;
    }
}
