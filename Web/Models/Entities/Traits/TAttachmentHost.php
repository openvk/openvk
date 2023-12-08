<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities\Traits;
use openvk\Web\Models\Entities\{Attachable, Photo};
use openvk\Web\Util\Makima\Makima;
use Chandler\Database\DatabaseConnection;

trait TAttachmentHost
{
    private function composeAttachmentRequestData(Attachable $attachment): array
    {
        return [
            "target_type"     => get_class($this),
            "target_id"       => $this->getId(),
            "attachable_type" => get_class($attachment),
            "attachable_id"   => $attachment->getId(),
        ];
    }
    
    function getChildren(): \Traversable
    {
        $sel = DatabaseConnection::i()->getContext()
                                      ->table("attachments")
                                      ->where("target_id", $this->getId())
                                      ->where("attachments.target_type", get_class($this));
        foreach($sel as $rel) {
            $repoName = $rel->attachable_type . "s";
            $repoName = str_replace("Entities", "Repositories", $repoName);
            $repo     = new $repoName;
            
            yield $repo->get($rel->attachable_id);
        }
    }

    function getChildrenWithLayout(int $w, int $h = -1): object
    {
        if($h < 0)
            $h = $w;

        $children = $this->getChildren();
        $skipped  = $photos = $result = [];
        foreach($children as $child) {
            if($child instanceof Photo) {
                $photos[] = $child;
                continue;
            }

            $skipped[] = $child;
        }

        $height = "unset";
        $width  = $w;
        if(sizeof($photos) < 2) {
            if(isset($photos[0]))
                $result[] = ["100%", "unset", $photos[0], "unset"];
        } else {
            $mak    = new Makima($photos);
            $layout = $mak->computeMasonryLayout($w, $h);
            $height = $layout->height;
            $width  = $layout->width;
            for($i = 0; $i < sizeof($photos); $i++) {
                $tile = $layout->tiles[$i];
                $result[] = [$tile->width . "px", $tile->height . "px", $photos[$i], "left"];
            }
        }

        return (object) [
            "width"  => $width . "px",
            "height" => $height . "px",
            "tiles"  => $result,
            "extras" => $skipped,
        ];
    }
    
    function attach(Attachable $attachment): void
    {
        DatabaseConnection::i()->getContext()
                               ->table("attachments")
                               ->insert($this->composeAttachmentRequestData($attachment));
    }
    
    function detach(Attachable $attachment): bool
    {
        $res = DatabaseConnection::i()->getContext()
                               ->table("attachments")
                               ->where($this->composeAttachmentRequestData($attachment))
                               ->delete();
        
        return $res > 0;
    }
    
    function unwire(): void
    {
        $this->getRecord()
                    ->related("attachments.target_id")
                    ->where("attachments.target_type", get_class($this))
                    ->delete();
    }
}
