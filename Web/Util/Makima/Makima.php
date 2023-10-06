<?php declare(strict_types=1);
namespace openvk\Web\Util\Makima;
use openvk\Web\Models\Entities\Photo;

class Makima
{
    private $photos;

    const ORIENT_WIDE    = 0;
    const ORIENT_REGULAR = 1;
    const ORIENT_SLIM    = 2;

    function __construct(array $photos)
    {
        if(sizeof($photos) < 2)
            throw new \LogicException("Minimum attachment count for tiled layout is 2");

        $this->photos = $photos;
    }

    private function getOrientation(Photo $photo, &$ratio): int
    {
        [$width, $height] = $photo->getDimensions();
        $ratio = $width / $height;
        if($ratio >= 1.2)
            return Makima::ORIENT_WIDE;
        else if($ratio >= 0.8)
            return Makima::ORIENT_REGULAR;
        else
            return Makima::ORIENT_SLIM;
    }

    private function calculateMultiThumbsHeight(array $ratios, float $w, float $m): float
    {
        return ($w - (sizeof($ratios) - 1) * $m) / array_sum($ratios);
    }

    private function extractSubArr(array $arr, int $from, int $to): array
    {
        return array_slice($arr, $from, sizeof($arr) - $from - (sizeof($arr) - $to));
    }

    function computeMasonryLayout(float $maxWidth, float $maxHeight): MasonryLayout
    {
        $orients = [];
        $ratios  = [];
        $count   = sizeof($this->photos);
        $result  = new MasonryLayout;

        foreach($this->photos as $photo) {
            $orients[] = $this->getOrientation($photo, $ratio);
            $ratios[]  = $ratio;
        }

        $avgRatio = array_sum($ratios) / sizeof($ratios);
        if($maxWidth < 0)
            $maxWidth = $maxHeight = 510;

        $maxRatio    = $maxWidth / $maxHeight;
        $marginWidth = $marginHeight = 2;

        switch($count) {
            case 2:
                if(
                    $orients == [Makima::ORIENT_WIDE, Makima::ORIENT_WIDE] # two wide pics
                    && $avgRatio > (1.4 * $maxRatio) && abs($ratios[0] - $ratios[1]) < 0.2 # that can be positioned on top of each other
                ) {
                    $computedHeight = ceil( min( $maxWidth / $ratios[0], min( $maxWidth / $ratios[1], ($maxHeight - $marginHeight) / 2 ) ) );

                    $result->colSizes = [1];
                    $result->rowSizes = [1, 1];
                    $result->width    = ceil($maxWidth);
                    $result->height   = $computedHeight;
                    $result->tiles    = [new ThumbTile(1, 1, $maxWidth, $computedHeight), new ThumbTile(1, 1, $maxWidth, $computedHeight)];
                } else if(
                    $orients == [Makima::ORIENT_WIDE, Makima::ORIENT_WIDE]
                    || $orients == [Makima::ORIENT_REGULAR, Makima::ORIENT_REGULAR] # two normal pics of same ratio
                ) {
                    $computedWidth = ($maxWidth - $marginWidth) / 2;
                    $height        = min( $computedWidth / $ratios[0], min( $computedWidth / $ratios[1], $maxHeight ) );

                    $result->colSizes = [1, 1];
                    $result->rowSizes = [1];
                    $result->width    = ceil($maxWidth);
                    $result->height   = ceil($height);
                    $result->tiles    = [new ThumbTile(1, 1, $computedWidth, $height), new ThumbTile(1, 1, $computedWidth, $height)];
                } else /* next to each other, different ratios */ {
                    $w0 = (
                        ($maxWidth - $marginWidth) / $ratios[1] / ( (1 / $ratios[0]) + (1 / $ratios[1]) )
                    );
                    $w1 = $maxWidth - $w0 - $marginWidth;
                    $h  = min($maxHeight, min($w0 / $ratios[0], $w1 / $ratios[1]));

                    $result->colSizes = [ceil($w0), ceil($w1)];
                    $result->rowSizes = [1];
                    $result->width    = ceil($w0 + $w1 + $marginWidth);
                    $result->height   = ceil($h);
                    $result->tiles    = [new ThumbTile(1, 1, $w0, $h), new ThumbTile(1, 1, $w1, $h)];
                }
            break;
            case 3:
                # Three wide photos, we will put two of them below and one on top
                if($orients == [Makima::ORIENT_WIDE, Makima::ORIENT_WIDE, Makima::ORIENT_WIDE]) {
                    $hCover = min($maxWidth / $ratios[0], ($maxHeight - $marginHeight) * (2 / 3));
                    $w2     = ($maxWidth - $marginWidth) / 2;
                    $h      = min($maxHeight - $hCover - $marginHeight, min($w2 / $ratios[1], $w2 / $ratios[2]));

                    $result->colSizes = [1, 1];
                    $result->rowSizes = [ceil($hCover), ceil($h)];
                    $result->width    = ceil($maxWidth);
                    $result->height   = ceil($marginHeight + $hCover + $h);
                    $result->tiles    = [
                        new ThumbTile(2, 1, $maxWidth, $hCover),
                        new ThumbTile(1, 1, $w2, $h), new ThumbTile(1, 1, $w2, $h),
                    ];
                } else /* Photos have different sizes or are not wide, so we will put one to left and two to the right */ {
                    $wCover = min($maxHeight * $ratios[0], ($maxWidth - $marginWidth) * (3 / 4));
                    $h1     = ($ratios[1] * ($maxHeight - $marginHeight) / ($ratios[2] + $ratios[1]));
                    $h0     = $maxHeight - $marginHeight - $h1;
                    $w      = min($maxWidth - $marginWidth - $wCover, min($h1 * $ratios[2], $h0 * $ratios[1]));

                    $result->colSizes = [ceil($wCover), ceil($w)];
                    $result->rowSizes = [ceil($h0), ceil($h1)];
                    $result->width    = ceil($w + $wCover + $marginWidth);
                    $result->height   = ceil($maxHeight);
                    $result->tiles    = [
                        new ThumbTile(1, 2, $wCover, $maxHeight), new ThumbTile(1, 1, $w, $h0),
                                                                  new ThumbTile(1, 1, $w, $h1),
                    ];
                }
            break;
            case 4:
                # Four wide photos, we will put one to the top and rest below
                if($orients == [Makima::ORIENT_WIDE, Makima::ORIENT_WIDE, Makima::ORIENT_WIDE, Makima::ORIENT_WIDE]) {
                    $hCover = min($maxWidth / $ratios[0], ($maxHeight - $marginHeight) / (2 / 3));
                    $h      = ($maxWidth - 2 * $marginWidth) / (array_sum($ratios) - $ratios[0]);
                    $w0     = $h * $ratios[1];
                    $w1     = $h * $ratios[2];
                    $w2     = $h * $ratios[3];
                    $h      = min($maxHeight - $marginHeight - $hCover, $h);

                    $result->colSizes = [ceil($w0), ceil($w1), ceil($w2)];
                    $result->rowSizes = [ceil($hCover), ceil($h)];
                    $result->width    = ceil($maxWidth);
                    $result->height   = ceil($hCover + $marginHeight + $h);
                    $result->tiles    = [
                                                new ThumbTile(3, 1, $maxWidth, $hCover),
                        new ThumbTile(1, 1, $w0, $h), new ThumbTile(1, 1, $w1, $h), new ThumbTile(1, 1, $w2, $h),
                    ];
                } else /* Four photos, we will put one to the left and rest to the right */ {
                    $wCover = min($maxHeight * $ratios[0], ($maxWidth - $marginWidth) * (2 / 3));
                    $w      = ($maxHeight - 2 * $marginHeight) / (1 / $ratios[1] + 1 / $ratios[2] + 1 / $ratios[3]);
                    $h0     = $w / $ratios[1];
                    $h1     = $w / $ratios[2];
                    $h2     = $w / $ratios[3] + $marginHeight;
                    $w      = min($w, $maxWidth - $marginWidth - $wCover);

                    $result->colSizes = [ceil($wCover), ceil($w)];
                    $result->rowSizes = [ceil($h0), ceil($h1), ceil($h2)];
                    $result->width    = ceil($wCover + $marginWidth + $w);
                    $result->height   = ceil($maxHeight);
                    $result->tiles    = [
                        new ThumbTile(1, 3, $wCover, $maxHeight), new ThumbTile(1, 1, $w, $h0),
                                                                  new ThumbTile(1, 1, $w, $h1),
                                                                  new ThumbTile(1, 1, $w, $h1),
                    ];
                }
            break;
            default:
                // как лопать пузырики
                $ratiosCropped = [];
                if($avgRatio > 1.1) {
                    foreach($ratios as $ratio)
                        $ratiosCropped[] = max($ratio, 1.0);
                } else {
                    foreach($ratios as $ratio)
                        $ratiosCropped[] = min($ratio, 1.0);
                }

                $tries = [];

                $firstLine;
                $secondLine;
                $thirdLine;

                # Try one line:
                $tries[$firstLine = $count] = [$this->calculateMultiThumbsHeight($ratiosCropped, $maxWidth, $marginWidth)];

                # Try two lines:
                for($firstLine = 1; $firstLine < ($count - 1); $firstLine++) {
                    $secondLine  = $count - $firstLine;
                    $key         = "$firstLine&$secondLine";
                    $tries[$key] = [
                        $this->calculateMultiThumbsHeight(array_slice($ratiosCropped, 0, $firstLine), $maxWidth, $marginWidth),
                        $this->calculateMultiThumbsHeight(array_slice($ratiosCropped, $firstLine), $maxWidth, $marginWidth),
                    ];
                }

                # Try three lines:
                for($firstLine = 1; $firstLine < ($count - 2); $firstLine++) {
                    for($secondLine  = 1; $secondLine < ($count - $firstLine - 1); $secondLine++) {
                        $thirdLine   = $count - $firstLine - $secondLine;
                        $key         = "$firstLine&$secondLine&$thirdLine";
                        $tries[$key] = [
                            $this->calculateMultiThumbsHeight(array_slice($ratiosCropped, 0, $firstLine), $maxWidth, $marginWidth),
                            $this->calculateMultiThumbsHeight($this->extractSubArr($ratiosCropped, $firstLine, $firstLine + $secondLine), $maxWidth, $marginWidth),
                            $this->calculateMultiThumbsHeight($this->extractSubArr($ratiosCropped, $firstLine + $secondLine, sizeof($ratiosCropped)), $maxWidth, $marginWidth),
                        ];
                    }
                }

                # Now let's find the most optimal configuration:
                $optimalConfiguration = $optimalDifference = NULL;
                foreach($tries as $config => $heights) {
                    $config = explode('&', (string) $config); # да да стринговые ключи пхп даже со стриктайпами автокастует к инту (см. 187)
                    $confH  = $marginHeight * (sizeof($heights) - 1);
                    foreach($heights as $h)
                        $confH += $h;

                    $confDiff = abs($confH - $maxHeight);
                    if(sizeof($config) > 1)
                        if($config[0] > $config[1] || sizeof($config) >= 2 && $config[1] > $config[2])
                            $confDiff *= 1.1;

                    if(!$optimalConfiguration || $confDigff < $optimalDifference) {
                        $optimalConfiguration = $config;
                        $optimalDifference    = $confDiff;
                    }
                }

                $thumbsRemain = $this->photos;
                $ratiosRemain = $ratiosCropped;
                $optHeights   = $tries[implode('&', $optimalConfiguration)];
                $k            = 0;

                $result->width    = ceil($maxWidth);
                $result->rowSizes = [sizeof($optHeights)];
                $result->tiles    = [];

                $totalHeight     = 0.0;
                $gridLineOffsets = [];
                $rowTiles        = []; // vector<vector<ThumbTile>>

                for($i = 0; $i < sizeof($optimalConfiguration); $i++) {
                    $lineChunksNum = $optimalConfiguration[$i];
                    $lineThumbs    = [];
                    for($j = 0; $j < $lineChunksNum; $j++)
                        $lineThumbs[] = array_shift($thumbsRemain);

                    $lineHeight   = $optHeights[$i];
                    $totalHeight += $lineHeight;

                    $result->rowSizes[$i] = ceil($lineHeight);

                    $totalWidth = 0;
                    $row        = [];
                    for($j = 0; $j < sizeof($lineThumbs); $j++) {
                        $thumbRatio = array_shift($ratiosRemain);
                        if($j == sizeof($lineThumbs) - 1)
                            $w = $maxWidth - $totalWidth;
                        else
                            $w = $thumbRatio * $lineHeight;

                        $totalWidth += ceil($w);
                        if($j < (sizeof($lineThumbs) - 1) && !in_array($totalWidth, $gridLineOffsets))
                            $gridLineOffsets[] = $totalWidth;

                        $tile = new ThumbTile(1, 1, $w, $lineHeight);
                        $result->tiles[$k++] = $row[] = $tile;
                    }

                    $rowTiles[] = $row;
                }

                sort($gridLineOffsets, SORT_NUMERIC);
                $gridLineOffsets[] = $maxWidth;

                $result->colSizes = [$gridLineOffsets[0]];
                for($i = sizeof($gridLineOffsets) - 1; $i > 0; $i--)
                    $result->colSizes[$i] = $gridLineOffsets[$i] - $gridLineOffsets[$i - 1];

                foreach($rowTiles as $row) {
                    $columnOffset = 0;
                    foreach($row as $tile) {
                        $startColumn   = $columnOffset;
                        $width         = 0;
                        $tile->colSpan = 0;
                        for($i = $startColumn; $i < sizeof($result->colSizes); $i++) {
                            $width += $result->colSizes[$i];
                            $tile->colSpan++;
                            if($width == $tile->width)
                                break;
                        }

                        $columnOffset += $tile->colSpan;
                    }
                }

                $result->height = ceil($totalHeight + $marginHeight * (sizeof($optHeights) - 1));
            break;
        }

        return $result;
    }
}
