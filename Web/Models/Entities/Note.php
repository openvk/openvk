<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use HTMLPurifier_Config;
use HTMLPurifier;

class Note extends Postable
{
    protected $tableName = "notes";
    
    protected function renderHTML(): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set("Attr.AllowedClasses", []);
        $config->set("Attr.DefaultInvalidImageAlt", "Unknown image");
        $config->set("AutoFormat.AutoParagraph", true);
        $config->set("AutoFormat.Linkify", true);
        $config->set("URI.Base", "//$_SERVER[SERVER_NAME]/");
        $config->set("URI.Munge", "/away.php?xinf=%n.%m:%r&css=%p&to=%s");
        $config->set("URI.MakeAbsolute", true);
        $config->set("HTML.Doctype", "XHTML 1.1");
        $config->set("HTML.TidyLevel", "heavy");
        $config->set("HTML.AllowedElements", [
            "div",
            "h3",
            "h4",
            "h5",
            "h6",
            "p",
            "i",
            "b",
            "a",
            "del",
            "ins",
            "sup",
            "sub",
            "table",
            "thead",
            "tbody",
            "tr",
            "td",
            "th",
            "img",
            "ul",
            "ol",
            "li",
            "hr",
            "br",
            "acronym",
            "blockquote",
            "cite",
            "span",
        ]);
        $config->set("HTML.AllowedAttributes", [
            "table.summary",
            "td.abbr",
            "th.abbr",
            "a.href",
            "img.src",
            "img.alt",
            "img.style",
            "div.style",
            "div.title",
            "span.class",
            "p.class",
        ]);
        $config->set("CSS.AllowedProperties", [
            "float",
            "height",
            "width",
            "max-height",
            "max-width",
            "font-weight",
        ]);
        $config->set("Attr.AllowedClasses", [
            "underline",
        ]);
    
        $source = NULL;
        if(is_null($this->getRecord())) {
            if(isset($this->changes["source"]))
                $source = $this->changes["source"];
            else
                throw new \LogicException("Can't render note without content set.");
        } else {
            $source = $this->getRecord()->source;
        }
        
        $purifier = new HTMLPurifier($config);
        return $purifier->purify($source);
    }
    
    function getName(): string
    {
        return $this->getRecord()->name;
    }
    
    function getPreview(int $length = 25): string
    {
        return ovk_proc_strtr(strip_tags($this->getRecord()->source), $length);
    }
    
    function getText(): string
    {
        if(is_null($this->getRecord()))
            return $this->renderHTML();
        
        $cached = $this->getRecord()->cached_content;
        if(!$cached) {
            $cached = $this->renderHTML();
            $this->setCached_Content($cached);
            $this->save();
        }
        
        return $cached;
    }

    function getSource(): string
    {
        return $this->getRecord()->source;
    }
    
    function canBeViewedBy(?User $user = NULL): bool
    {
        if($this->isDeleted() || $this->getOwner()->isDeleted()) {
            return false;
        }

        return $this->getOwner()->getPrivacyPermission('notes.read', $user) && $this->getOwner()->canBeViewedBy($user);
    }

    function toVkApiStruct(): object
    {
        $res = (object) [];

        $res->type          = "note";
        $res->id            = $this->getVirtualId();
        $res->owner_id      = $this->getOwner()->getId();
        $res->title         = $this->getName();
        $res->text          = $this->getText();
        $res->date          = $this->getPublicationTime()->timestamp();
        $res->comments      = $this->getCommentsCount();
        $res->read_comments = $this->getCommentsCount();
        $res->view_url      = "/note".$this->getOwner()->getId()."_".$this->getVirtualId();
        $res->privacy_view  = 1;
        $res->can_comment   = 1;
        $res->text_wiki     = "r";

        return $res;
    }
}
