<?php declare(strict_types=1);
use Chandler\Database\DatabaseConnection;
use Chandler\Session\Session;
use openvk\Web\Util\Localizator;
use openvk\Web\Util\Bitmask;
use function PHP81_BC\strftime;

function _ovk_check_environment(): void
{
    $problems = [];
    if(file_exists(__DIR__ . "/update.pid"))
        $problems[] = "OpenVK is updating";
    
    if(!version_compare(PHP_VERSION, "7.3.0", ">="))
        $problems[] = "Incompatible PHP version: " . PHP_VERSION . " (7.3+ required, 7.4+ recommended)";
    
    if(!is_dir(__DIR__ . "/vendor"))
        $problems[] = "Composer dependencies missing";
    
    $requiredExtensions = [
        "gd",
        "imagick",
        "fileinfo",
        "PDO",
        "pdo_mysql",
        "pdo_sqlite",
        "pcre",
        "hash",
        "curl",
        "Core",
        "iconv",
        "mbstring",
        "sodium",
        "openssl",
        "json",
        "tokenizer",
        "xml",
        "intl",
        "date",
        "session",
        "SPL",
    ];
    if(sizeof($missingExtensions = array_diff($requiredExtensions, get_loaded_extensions())) > 0)
        foreach($missingExtensions as $extension)
            $problems[] = "Missing extension $extension";
    
    if(sizeof($problems) > 0) {
        require __DIR__ . "/misc/install_err.phtml";
        exit;
    }
}

function ovkGetQuirk(string $quirk): int
{
    static $quirks = NULL;
    if(!$quirks)
        $quirks = chandler_parse_yaml(__DIR__ . "/quirks.yml");
    
    return !is_null($v = $quirks[$quirk]) ? (int) $v : 0;
}

function ovk_proc_strtr(string $string, int $length = 0): string
{
    $newString = iconv_substr($string, 0, $length);
    
    return $newString . ($string !== $newString ? "…" : ""); #if cut hasn't happened, don't append "..."
}

function knuth_shuffle(iterable $arr, int $seed): array
{
    $data   = is_array($arr) ? $arr : iterator_to_array($arr);
    $retVal = [];
    $ind    = [];
    $count  = sizeof($data);

    srand($seed, MT_RAND_PHP);

    for($i = 0; $i < $count; ++$i)
        $ind[$i] = 0;

    for($i = 0; $i < $count; ++$i) {
        do {
            $index = rand() % $count;
        } while($ind[$index] != 0);

        $ind[$index] = 1;
        $retVal[$i] = $data[$index];
    }

    # Reseed
    srand(hexdec(bin2hex(openssl_random_pseudo_bytes(4))));

    return $retVal;
}

function bmask(int $input, array $options = []): Bitmask
{
    return new Bitmask($input, $options["length"] ?? 1, $options["mappings"] ?? []);
}

function tr(string $stringId, ...$variables): string
{
    $localizer = Localizator::i();
    $lang      = Session::i()->get("lang", "ru");
    if($stringId === "__lang")
        return $lang;
    
    $output = $localizer->_($stringId, $lang);
    if(sizeof($variables) > 0) {
        if(gettype($variables[0]) === "integer") {
            $numberedStringId = NULL;
            $cardinal         = $variables[0];
            switch($cardinal) {
                case 0:
                    $numberedStringId = $stringId . "_zero";
                break;
                case 1:
                    $numberedStringId = $stringId . "_one";
                break;
                default:
                    $numberedStringId = $stringId . ($cardinal < 5 ? "_few" : "_other");
            }
            
            $newOutput = $localizer->_($numberedStringId, $lang);
            if($newOutput === "@$numberedStringId") {
                $newOutput = $localizer->_($stringId . "_other", $lang);
                if($newOutput === ("@" . $stringId . "_other"))
                    $newOutput = $output;
            }

            $output = $newOutput;
        }
        
        for($i = 0; $i < sizeof($variables); $i++)
            $output = preg_replace("%(?<!\\\\)(\\$)" . ($i + 1) . "%", (string) $variables[$i], $output);
    }
    
    return $output;
}

function setLanguage($lg): void
{
    if (isLanguageAvailable($lg))
        Session::i()->set("lang", $lg);
    else
        trigger_error("The language '$lg' is not available", E_USER_NOTICE);
}

function getLanguage(): string
{
    return Session::i()->get("lang", "ru");
}

function getLanguages(): array
{
    return chandler_parse_yaml(OPENVK_ROOT . "/locales/list.yml")['list'];
}

function isLanguageAvailable($lg): bool
{
    $lg_temp = false;
    foreach(getLanguages() as $lang) {
        if ($lang['code'] == $lg) $lg_temp = true;
    }
    return $lg_temp;
}

function getBrowsersLanguage(): array
{
    if ($_SERVER['HTTP_ACCEPT_LANGUAGE'] != NULL) return mb_split(",", mb_split(";", $_SERVER['HTTP_ACCEPT_LANGUAGE'])[0]);
    else return array();
}

function eventdb(): ?DatabaseConnection
{
    $conf = OPENVK_ROOT_CONF["openvk"]["credentials"]["eventDB"];
    if(!$conf["enable"])
        return NULL;
    
    $db = (object) $conf["database"];
    return DatabaseConnection::connect([
        "dsn"      => $db->dsn,
        "user"     => $db->user,
        "password" => $db->password,
        "caching"  => [
            "folder" => __DIR__ . "/tmp",
        ],
    ]);
}

#NOTICE: invalid name, kept for compatability
function ovk_proc_strtrim(string $string, int $length = 0): string
{
    trigger_error("ovk_proc_strtrim is deprecated, please use fully compatible ovk_proc_strtr.", E_USER_DEPRECATED);
    
    return ovk_proc_strtr($string, $length);
}

function ovk_strftime_safe(string $format, ?int $timestamp = NULL): string
{
    $sessionOffset = intval(Session::i()->get("_timezoneOffset"));
    $str = strftime($format, $timestamp + ($sessionOffset * MINUTE) * -1 ?? time() + ($sessionOffset * MINUTE) * -1, tr("__locale") !== '@__locale' ? tr("__locale") : NULL);
    if(PHP_SHLIB_SUFFIX === "dll" && version_compare(PHP_VERSION, "8.1.0", "<")) {
        $enc = tr("__WinEncoding");
        if($enc === "@__WinEncoding")
            $enc = "Windows-1251";
        
        $nStr = iconv($enc, "UTF-8", $str);
        if(!is_null($nStr))
            $str = $nStr;
    }
    
    return $str;
}

function ovk_is_ssl(): bool
{
    if(!isset($GLOBALS["requestIsSSL"])) {
        $GLOBALS["requestIsSSL"] = false;
        
        if(isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") {
            $GLOBALS["requestIsSSL"] = true;
        } else {
            $forwardedProto = $_SERVER["HTTP_X_FORWARDED_PROTO"] ?? ($_SERVER["HTTP_X_FORWARDED_PROTOCOL"] ?? ($_SERVER["HTTP_X_URL_SCHEME"] ?? ""));
            if($forwardedProto === "https")
                $GLOBALS["requestIsSSL"] = true;
            else if(($_SERVER["HTTP_X_FORWARDED_SSL"] ?? "") === "on")
                $GLOBALS["requestIsSSL"] = true;
        }
    }
    
    return $GLOBALS["requestIsSSL"];
}

function parseAttachments(string $attachments): array
{
    $attachmentsArr = explode(",", $attachments);
    $returnArr      = [];

    foreach($attachmentsArr as $attachment) {
        $attachmentType = NULL;

        if(str_contains($attachment, "photo"))
            $attachmentType = "photo";
        elseif(str_contains($attachment, "video"))
            $attachmentType = "video";
        elseif(str_contains($attachment, "note"))
            $attachmentType = "note";
        elseif(str_contains($attachment, "audio"))
            $attachmentType = "audio";

        $attachmentIds   = str_replace($attachmentType, "", $attachment);
        $attachmentOwner = (int) explode("_", $attachmentIds)[0];
        $gatoExplotano   = explode("_", $attachmentIds);
        $attachmentId    = (int) end($gatoExplotano);

        switch($attachmentType) {
            case "photo":
                $attachmentObj = (new openvk\Web\Models\Repositories\Photos)->getByOwnerAndVID($attachmentOwner, $attachmentId);
                $returnArr[]   = $attachmentObj;
                break;
            case "video":
                $attachmentObj = (new openvk\Web\Models\Repositories\Videos)->getByOwnerAndVID($attachmentOwner, $attachmentId);
                $returnArr[]   = $attachmentObj;
                break;
            case "note":
                $attachmentObj = (new openvk\Web\Models\Repositories\Notes)->getNoteById($attachmentOwner, $attachmentId);
                $returnArr[]   = $attachmentObj;
                break;
            case "audio":
                $attachmentObj = (new openvk\Web\Models\Repositories\Audios)->getByOwnerAndVID($attachmentOwner, $attachmentId);
                $returnArr[]   = $attachmentObj;
                break;
        }
    }

    return $returnArr;
}

function getEntity(int $id) {
    if($id > 0)
        return (new openvk\Web\Models\Repositories\Users)->get($id);
    
    return (new openvk\Web\Models\Repositories\Clubs)->get(abs($id));
}

function ovk_scheme(bool $with_slashes = false): string
{
    $scheme = ovk_is_ssl() ? "https" : "http";
    if($with_slashes)
        $scheme .= "://";
    
    return $scheme;
}

return (function() {
    _ovk_check_environment();
    require __DIR__ . "/vendor/autoload.php";

    setlocale(LC_TIME, "POSIX");

    # TODO: Default language in config
    if(Session::i()->get("lang") == NULL) {
        $languages = array_reverse(getBrowsersLanguage());
        foreach($languages as $lg) {
            if(isLanguageAvailable($lg)) setLanguage($lg);    
        }
    }
    
    if(empty($_SERVER["REQUEST_SCHEME"]))
        $_SERVER["REQUEST_SCHEME"] = empty($_SERVER["HTTPS"]) ? "HTTP" : "HTTPS";

    $showCommitHash = true; # plz remove when release
    if(is_dir($gitDir = OPENVK_ROOT . "/.git") && $showCommitHash)
        $ver = trim(`git --git-dir="$gitDir" log --pretty="%h" -n1 HEAD` ?? "Unknown version") . "-nightly";
    else
        $ver = "Public Technical Preview 4";

    # Unix time constants
    define('MINUTE', 60);
    define('HOUR', 60 * MINUTE);
    define('DAY', 24 * HOUR);
    define('WEEK', 7 * DAY);
    define('MONTH', 30 * DAY);
    define('YEAR', 365 * DAY);

    define("nullptr", NULL);
    define("OPENVK_DEFAULT_INSTANCE_NAME", "OpenVK", false);
    define("OPENVK_VERSION", "Altair Preview ($ver)", false);
    define("OPENVK_DEFAULT_PER_PAGE", 10, false);
    define("__OPENVK_ERROR_CLOCK_IN_FUTURE", "Server clock error: FK1200-DTF", false);
});
