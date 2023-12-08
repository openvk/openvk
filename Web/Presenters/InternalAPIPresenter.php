<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Repositories\{Posts, Comments};
use MessagePack\MessagePack;
use Chandler\Session\Session;

final class InternalAPIPresenter extends OpenVKPresenter
{
    private function fail(int $code, string $message): void
    {
        header("HTTP/1.1 400 Bad Request");
        exit(MessagePack::pack([
            "brpc"  => 1,
            "error" => [
                "code"    => $code,
                "message" => $message,
            ],
            "id" => hexdec(hash("crc32b", (string) time())),
        ]));
    }
    
    private function succ($payload): void
    {
        exit(MessagePack::pack([
            "brpc"   => 1,
            "result" => $payload,
            "id"     => hexdec(hash("crc32b", (string) time())),
        ]));
    }
    
    function renderRoute(): void
    {
        if($_SERVER["REQUEST_METHOD"] !== "POST") {
            header("HTTP/1.1 405 Method Not Allowed");
            exit("ты дебил это точка апи");
        }
        try {
            $input = (object) MessagePack::unpack(file_get_contents("php://input"));
        } catch (\Exception $ex) {
            $this->fail(-32700, "Parse error");
        }
        
        if(is_null($input->brpc ?? NULL) || is_null($input->method ?? NULL))
            $this->fail(-32600, "Invalid BIN-RPC");
        else if($input->brpc !== 1)
            $this->fail(-32610, "Invalid version");
        
        $method = explode(".", $input->method);
        if(sizeof($method) !== 2)
            $this->fail(-32601, "Procedure not found");
        
        [$class, $method] = $method;
        $class = '\openvk\ServiceAPI\\' . $class;
        if(!class_exists($class))
            $this->fail(-32601, "Procedure not found");
        
        $handler = new $class(is_null($this->user) ? NULL : $this->user->identity);
        if(!is_callable([$handler, $method]))
            $this->fail(-32601, "Procedure not found");
        
        try {
            $params = array_merge($input->params ?? [], [function($data) {
                $this->succ($data);
            }, function(int $errno, string $errstr) {
                $this->fail($errno, $errstr);
            }]);
            $handler->{$method}(...$params);
        } catch(\TypeError $te) {
            $this->fail(-32602, "Invalid params");
        } catch(\Exception $ex) {
            $this->fail(-32603, "Uncaught " . get_class($ex));
        }
    }

    function renderTimezone() {
        if($_SERVER["REQUEST_METHOD"] !== "POST") {
            header("HTTP/1.1 405 Method Not Allowed");
            exit("ты дебил это метод апи");
        }
        $sessionOffset = Session::i()->get("_timezoneOffset");
        if(is_numeric($this->postParam("timezone", false))) {
            $postTZ = intval($this->postParam("timezone", false));
            if ($postTZ != $sessionOffset || $sessionOffset == null) {
                Session::i()->set("_timezoneOffset", $postTZ ? $postTZ : 3 * MINUTE );
                $this->returnJson([
                    "success" => 1 # If it's new value
                ]);
            } else {
                $this->returnJson([
                    "success" => 2 # If it's the same value (if for some reason server will call this func)
                ]);
            }
        } else {
            $this->returnJson([
                "success" => 0
            ]);
        }
    }

    function renderGetPhotosFromPost(int $owner_id, int $post_id) {
        if($_SERVER["REQUEST_METHOD"] !== "POST") {
            header("HTTP/1.1 405 Method Not Allowed");
            exit("иди нахуй заебал");
        }

        if($this->postParam("parentType", false) == "post") {
            $post = (new Posts)->getPostById($owner_id, $post_id, true);
        } else {
            $post = (new Comments)->get($post_id);
        }
    

        if(is_null($post)) {
            $this->returnJson([
                "success" => 0
            ]);
        } else {
            $response = [];
            $attachments = $post->getChildren();
            foreach($attachments as $attachment) 
            {
                if($attachment instanceof \openvk\Web\Models\Entities\Photo)
                {
                    $response[] = [
                        "url" => $attachment->getURLBySizeId('normal'),
                        "id"  => $attachment->getPrettyId()
                    ];
                }
            }
            $this->returnJson([
                "success" => 1,
                "body" => $response
            ]);
        }
    }
}
