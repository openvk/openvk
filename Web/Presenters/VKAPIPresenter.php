<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use Chandler\Security\Authenticator;
use Chandler\Database\DatabaseConnection as DB;
use openvk\VKAPI\Exceptions\APIErrorException;
use openvk\Web\Models\Entities\{User, APIToken};
use openvk\Web\Models\Repositories\{Users, APITokens};
use lfkeitel\phptotp\{Base32, Totp};
use WhichBrowser;

final class VKAPIPresenter extends OpenVKPresenter
{
    private function logRequest(string $object, string $method): void
    {
        $date   = date(DATE_COOKIE);
        $params = json_encode($_REQUEST);
        $log    = "[$date] $object.$method called with $params\r\n";
        file_put_contents(OPENVK_ROOT . "/VKAPI/debug.log", $log, FILE_APPEND | LOCK_EX);
    }
    
    private function fail(int $code, string $message, string $object, string $method): void
    {
        header("HTTP/1.1 400 Bad API Call");
        header("Content-Type: application/json");
        
        $payload = [
            "error_code"     => $code,
            "error_msg"      => $message,
            "request_params" => [
                [
                    "key"   => "method",
                    "value" => "$object.$method",
                ],
                [
                    "key"   => "oauth",
                    "value" => 1,
                ],
            ],
        ];
        
        foreach($_GET as $key => $value)
            array_unshift($payload["request_params"], [ "key" => $key, "value" => $value ]);
        
        exit(json_encode($payload));
    }

    private function twofaFail(int $userId): void
    {
        header("HTTP/1.1 401 Unauthorized");
        header("Content-Type: application/json");
        
        $payload = [
            "error"             => "need_validation",
            "error_description" => "use app code",
            "validation_type"   => "2fa_app",
            "validation_sid"    => "2fa_".$userId."_2839041_randommessdontread",
            "phone_mask"        => "+374 ** *** 420",
            "redirect_url"      => "https://http.cat/418", // Not implemented yet :( So there is a photo of cat :3
            "validation_resend" => "nowhere"
        ];
        
        exit(json_encode($payload));
    }
    
    private function badMethod(string $object, string $method): void
    {
        $this->fail(3, "Unknown method passed.", $object, $method);
    }
    
    private function badMethodCall(string $object, string $method, string $param): void
    {
        $this->fail(100, "Required parameter '$param' missing.", $object, $method);
    }
    
    function onStartup(): void
    {
        parent::onStartup();
        
        # idk, but in case we will ever support non-standard HTTP credential authflow
        $origin = "*";
        if(isset($_SERVER["HTTP_REFERER"])) {
            $refOrigin = parse_url($_SERVER["HTTP_REFERER"], PHP_URL_SCHEME) . "://" . parse_url($_SERVER["HTTP_REFERER"], PHP_URL_HOST);
            if($refOrigin !== false)
                $origin = $refOrigin;
        }
        
        if(!is_null($this->queryParam("requestPort")))
            $origin .= ":" . ((int) $this->queryParam("requestPort"));
        
        header("Access-Control-Allow-Origin: $origin");
        
        if($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
            header("Access-Control-Allow-Methods: POST, PUT, DELETE");
            header("Access-Control-Allow-Headers: " . $_SERVER["HTTP_ACCESS_CONTROL_REQUEST_HEADERS"]);
            header("Access-Control-Max-Age: -1");
            exit; # Terminate request processing as this is definitely a CORS preflight request.
        }
    }

    function renderPhotoUpload(string $signature): void
    {
        $secret            = CHANDLER_ROOT_CONF["security"]["secret"];
        $queryString       = rawurldecode($_SERVER["QUERY_STRING"]);
        $computedSignature = hash_hmac("sha3-224", $queryString, $secret);
        if(!(strlen($signature) == 56 && sodium_memcmp($signature, $computedSignature) == 0)) {
            header("HTTP/1.1 422 Unprocessable Entity");
            exit("Try harder <3");
        }

        $data = unpack("vDOMAIN/Z10FIELD/vMF/vMP/PTIME/PUSER/PGROUP", base64_decode($queryString));
        if((time() - $data["TIME"]) > 600) {
            header("HTTP/1.1 422 Unprocessable Entity");
            exit("Expired");
        }

        $folder   = __DIR__ . "/../../tmp/api-storage/photos";
        $maxSize  = OPENVK_ROOT_CONF["openvk"]["preferences"]["uploads"]["api"]["maxFileSize"];
        $maxFiles = OPENVK_ROOT_CONF["openvk"]["preferences"]["uploads"]["api"]["maxFilesPerDomain"];
        $usrFiles = sizeof(glob("$folder/$data[USER]_*.oct"));
        if($usrFiles >= $maxFiles) {
            header("HTTP/1.1 507 Insufficient Storage");
            exit("There are $maxFiles pending already. Please save them before uploading more :3");
        }

        # Not multifile
        if($data["MF"] === 0) {
            $file = $_FILES[$data["FIELD"]];
            if(!$file) {
                header("HTTP/1.0 400");
                exit("No file");
            } else if($file["error"] != UPLOAD_ERR_OK) {
                header("HTTP/1.0 500");
                exit("File could not be consumed");
            } else if($file["size"] > $maxSize) {
                header("HTTP/1.0 507 Insufficient Storage");
                exit("File is too big");
            }

            move_uploaded_file($file["tmp_name"], "$folder/$data[USER]_" . ($usrFiles + 1) . ".oct");
            header("HTTP/1.0 202 Accepted");

            $photo = $data["USER"] . "|" . ($usrFiles + 1) . "|" . $data["GROUP"];
            exit(json_encode([
                "server" => "ephemeral",
                "photo"  => $photo,
                "hash"   => hash_hmac("sha3-224", $photo, $secret),
            ]));
        }

        $files = [];
        for($i = 1; $i <= 5; $i++) {
            $file = $_FILES[$data["FIELD"] . $i] ?? NULL;
            if (!$file || $file["error"] != UPLOAD_ERR_OK || $file["size"] > $maxSize) {
                continue;
            } else if((sizeof($files) + $usrFiles) > $maxFiles) {
                # Clear uploaded files since they can't be saved anyway
                foreach($files as $f)
                    unlink($f);

                header("HTTP/1.1 507 Insufficient Storage");
                exit("There are $maxFiles pending already. Please save them before uploading more :3");
            }

            $files[++$usrFiles] = move_uploaded_file($file["tmp_name"], "$folder/$data[USER]_$usrFiles.oct");
        }

        if(sizeof($files) === 0) {
            header("HTTP/1.0 400");
            exit("No file");
        }

        $filesManifest = [];
        foreach($files as $id => $file)
            $filesManifest[] = ["keyholder" => $data["USER"], "resource" => $id, "club" => $data["GROUP"]];

        $filesManifest = json_encode($filesManifest);
        $manifestHash  = hash_hmac("sha3-224", $filesManifest, $secret);
        header("HTTP/1.0 202 Accepted");
        exit(json_encode([
            "server"      => "ephemeral",
            "photos_list" => $filesManifest,
            "album_id"    => "undefined",
            "hash"        => $manifestHash,
        ]));
    }
    
    function renderRoute(string $object, string $method): void
    {
        $authMechanism = $this->queryParam("auth_mechanism") ?? "token";
        if($authMechanism === "roaming") {
            if(!$this->user->identity)
                $this->fail(5, "User authorization failed: roaming mechanism is selected, but user is not logged in.", $object, $method);
            else
                $identity = $this->user->identity;
        } else {
            if(is_null($this->requestParam("access_token"))) {
                $identity = NULL;
            } else {
                $token = (new APITokens)->getByCode($this->requestParam("access_token"));
                if(!$token) {
                    $identity = NULL;
                } else {
                    $identity = $token->getUser();
                    $platform = $token->getPlatform();
                }
            }
        }
        
        if(!is_null($identity) && $identity->isBanned())
            $this->fail(18, "User account is deactivated", $object, $method);
        
        $object       = ucfirst(strtolower($object));
        $handlerClass = "openvk\\VKAPI\\Handlers\\$object";
        if(!class_exists($handlerClass))
            $this->badMethod($object, $method);
        
        $handler = new $handlerClass($identity, $platform);
        if(!is_callable([$handler, $method]))
            $this->badMethod($object, $method);
        
        $route  = new \ReflectionMethod($handler, $method);
        $params = [];
        foreach($route->getParameters() as $parameter) {
            $val = $this->requestParam($parameter->getName());
            if(is_null($val)) {
                if($parameter->allowsNull())
                    $val = NULL;
                else if($parameter->isDefaultValueAvailable())
                    $val = $parameter->getDefaultValue();
                else if($parameter->isOptional())
                    $val = NULL;
                else
                    $this->badMethodCall($object, $method, $parameter->getName());
            }
            
            try {
                settype($val, $parameter->getType()->getName());
                $params[] = $val;
            } catch (\Throwable $e) {
                // Just ignore the exception, since
                // some args are intended for internal use
            }
        }
        
        define("VKAPI_DECL_VER", $this->requestParam("v") ?? "4.100", false);
        
        try {
            $res = $handler->{$method}(...$params);
        } catch(APIErrorException $ex) {
            $this->fail($ex->getCode(), $ex->getMessage(), $object, $method);
        }
        
        $result = json_encode([
            "response" => $res,
        ]);
        
        $size = strlen($result);
        header("Content-Type: application/json");
        header("Content-Length: $size");
        exit($result);
    }
    
    function renderTokenLogin(): void
    {
        if($this->requestParam("grant_type") !== "password")
            $this->fail(7, "Invalid grant type", "internal", "acquireToken");
        else if(is_null($this->requestParam("username")) || is_null($this->requestParam("password")))
            $this->fail(100, "Password and username not passed", "internal", "acquireToken");
        
        $chUser = DB::i()->getContext()->table("ChandlerUsers")->where("login", $this->requestParam("username"))->fetch();
        if(!$chUser)
            $this->fail(28, "Invalid username or password", "internal", "acquireToken");
        
        $auth = Authenticator::i();
        if(!$auth->verifyCredentials($chUser->id, $this->requestParam("password")))
            $this->fail(28, "Invalid username or password", "internal", "acquireToken");
        
        $uId  = $chUser->related("profiles.user")->fetch()->id;
        $user = (new Users)->get($uId);

        $code = $this->requestParam("code");
        if($user->is2faEnabled() && !($code === (new Totp)->GenerateToken(Base32::decode($user->get2faSecret())) || $user->use2faBackupCode((int) $code))) {
            if($this->requestParam("2fa_supported") == "1")
                $this->twofaFail($user->getId());
            else
                $this->fail(28, "Invalid 2FA code", "internal", "acquireToken");
        }
        
        $platform = $this->requestParam("client_name");

        $token = new APIToken;
        $token->setUser($user);
        $token->setPlatform($platform ?? (new WhichBrowser\Parser(getallheaders()))->toString());
        $token->save();
        
        $payload = json_encode([
            "access_token" => $token->getFormattedToken(),
            "expires_in"   => 0,
            "user_id"      => $uId,
        ]);
        
        $size = strlen($payload);
        header("Content-Type: application/json");
        header("Content-Length: $size");
        exit($payload);
    }
}
