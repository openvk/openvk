<?php declare(strict_types=1);
namespace openvk\Web\Presenters;

final class BlobPresenter extends OpenVKPresenter
{
    protected $banTolerant   = true;

    private function getDirName($dir): string
    {
        if(gettype($dir) === "integer") {
            $dir = (string) $dir;
            if(strlen($dir) < 2) #Must have been a number with 1 digit
                $dir = "0$dir";
        }
        
        return $dir;
    }
    
    function renderFile(/*string*/ $dir, string $name, string $format)
    {
        header("Access-Control-Allow-Origin: *");

        $dir  = $this->getDirName($dir);
        $base = realpath(OPENVK_ROOT . "/storage/$dir");
        $path = realpath(OPENVK_ROOT . "/storage/$dir/$name.$format");
        if(!$path) # Will also check if file exists since realpath fails on ENOENT
            $this->notFound();
        else if(strpos($path, $path) !== 0) # Prevent directory traversal and storage container escape
            $this->notFound();
        
        if(isset($_SERVER["HTTP_IF_NONE_MATCH"]))
            exit(header("HTTP/1.1 304 Not Modified"));
        
        header("Content-Type: " . mime_content_type($path));
        header("Content-Size: " . filesize($path));
        header("Cache-Control: public, max-age=1210000");
        header("X-Accel-Expires: 1210000");
        header("ETag: W/\"" . hash_file("snefru", $path) . "\"");
        
        readfile($path);
        exit;
      }
}
