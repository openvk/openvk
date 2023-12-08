<?php declare(strict_types=1);
namespace openvk\Web\Util\Makima;

class ThumbTile {
    public $width;
    public $height;
    public $rowSpan;
    public $colSpan;

    function __construct(int $rs, int $cs, float $w, float $h)
    {
        [$this->width, $this->height, $this->rowSpan, $this->colSpan] = [ceil($w), ceil($h), $rs, $cs];
    }
}
