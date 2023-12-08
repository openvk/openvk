<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities\Notifications;
use openvk\Web\Models\RowModel;
use openvk\Web\Models\Entities\{User};
use openvk\Web\Util\DateTime;
use RdKafka\{Conf, Producer};

class Notification
{
    private $recipient;
    private $originModel;
    private $targetModel;
    private $time;
    private $data;
    
    protected $actionCode = NULL;
    protected $threshold  = -1;
    
    function __construct(User $recipient, $originModel, $targetModel, ?int $time = NULL, string $data = "")
    {
        $this->recipient   = $recipient;
        $this->originModel = $originModel;
        $this->targetModel = $targetModel;
        $this->time        = $time ?? time();
        $this->data        = $data;
    }
    
    private function encodeType(object $model): int
    {
        return (int) json_decode(file_get_contents(__DIR__ . "/../../../../data/modelCodes.json"), true)[get_class($model)];
    }
    
    function reverseModelOrder(): bool
    {
        return false;
    }
    
    function getActionCode(): int
    {
        return $this->actionCode;
    }
    
    function setActionCode(int $code): void
    {
        $this->actionCode = $this->actionCode ?? $code;
    }
    
    function getTemplatePath(): string
    {
        return implode("_", [
            "./../components/notifications/$this->actionCode/",
            $this->encodeType($this->originModel),
            $this->encodeType($this->targetModel),
            ".xml"
        ]);
    }
    
    function getRecipient(): User
    {
        return $this->recipient;
    }
    
    function getModel(int $index): RowModel
    {
        switch($index) {
            case 0:
                return $this->originModel;
            case 1:
                return $this->targetModel;
        }
    }
    
    function getData(): string
    {
        return $this->data;
    }
    
    function getDateTime(): DateTime
    {
        return new DateTime($this->time);
    }
    
    function emit(): bool
    {
        if(!($e = eventdb()))
            return false;
        
        $data = [
            "recipient"         => $this->recipient->getId(),
            "originModelType"   => $this->encodeType($this->originModel),
            "originModelId"     => $this->originModel->getId(),
            "targetModelType"   => $this->encodeType($this->targetModel),
            "targetModelId"     => $this->targetModel->getId(),
            "actionCode"        => $this->actionCode,
            "additionalPayload" => $this->data,
            "timestamp"         => $this->time,
        ];
        
        $edb = $e->getConnection();
        if($this->threshold !== -1) {
            # Event is thersholded, check if there is similar event
            $query = <<<'QUERY'
                SELECT * FROM `notifications` WHERE `recipientType`=0 AND `recipientId`=? AND `originModelType`=? AND `originModelId`=? AND `targetModelType`=? AND `targetModelId`=? AND `modelAction`=? AND `additionalData`=? AND `timestamp` > (? - ?)
QUERY;
            $result = $edb->query($query, ...array_merge(array_values($data), [ $this->threshold ]));
            if($result->getRowCount() > 0)
                return false;
        }
         
        $edb->query("INSERT INTO notifications VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?)", ...array_values($data));
        
        $kafkaConf = OPENVK_ROOT_CONF["openvk"]["credentials"]["notificationsBroker"];
        if($kafkaConf["enable"]) {
            $kafkaConf  = $kafkaConf["kafka"];
            $brokerConf = new Conf();
            $brokerConf->set("log_level", (string) LOG_DEBUG);
            $brokerConf->set("debug", "all");
            
            $producer = new Producer($brokerConf);
            $producer->addBrokers($kafkaConf["addr"] . ":" . $kafkaConf["port"]);
            
            $descriptor = implode(",", [
                str_replace("\\", ".", get_class($this)),
                $this->recipient->getId(),
                base64_encode(serialize((object) $data)),
            ]);
            
            $notifTopic = $producer->newTopic($kafkaConf["topic"]);
            $notifTopic->produce(RD_KAFKA_PARTITION_UA, RD_KAFKA_MSG_F_BLOCK, $descriptor);
            $producer->flush(100);
        }
        
        return true;
    }

    function getVkApiInfo()
    {
        $origin_m = $this->encodeType($this->originModel);
        $target_m = $this->encodeType($this->targetModel);

        $info = [
            "type"     => "",
            "parent"   => NULL,
            "feedback" => NULL,
        ];

        switch($this->getActionCode()) {
            case 0:
                $info["type"]     = "like_post";
                $info["parent"]   = $this->getModel(0)->toNotifApiStruct();
                $info["feedback"] = $this->getModel(1)->toVkApiStruct();
                break;
            case 1:
                $info["type"]     = "copy_post";
                $info["parent"]   = $this->getModel(0)->toNotifApiStruct();
                $info["feedback"] = NULL; # todo
                break;
            case 2:
                switch($origin_m) {
                    case 19:
                        $info["type"] = "comment_video";
                        $info["parent"] = $this->getModel(0)->toNotifApiStruct();
                        $info["feedback"] = NULL; # айди коммента не сохраняется в бд( ну пиздец блять
                        break;
                    case 13:
                        $info["type"] = "comment_photo";
                        $info["parent"] = $this->getModel(0)->toNotifApiStruct();
                        $info["feedback"] = NULL;
                        break;
                    # unstandart (vk forgor about notes)
                    case 10:
                        $info["type"] = "comment_note";
                        $info["parent"] = $this->getModel(0)->toVkApiStruct();
                        $info["feedback"] = NULL;
                        break;
                    case 14:
                        $info["type"] = "comment_post";
                        $info["parent"] = $this->getModel(0)->toNotifApiStruct();
                        $info["feedback"] = NULL;
                        break;
                    # unused (users don't have topics bruh)
                    case 21:
                        $info["type"] = "comment_topic";
                        $info["parent"] = $this->getModel(0)->toVkApiStruct(0, 90);
                        break;
                    default:
                        $info["type"] = "comment_unknown";
                        break;
                }

                break;
            case 3:
                $info["type"] = "wall";
                $info["feedback"] = $this->getModel(0)->toNotifApiStruct();
                break;
            case 4:
                switch($target_m) {
                    case 14:
                        $info["type"] = "mention";
                        $info["feedback"] = $this->getModel(1)->toNotifApiStruct();
                        break;
                    case 19:
                        $info["type"]   = "mention_comment_video";
                        $info["parent"] = $this->getModel(1)->toNotifApiStruct();
                        break;
                    case 13:
                        $info["type"] = "mention_comment_photo";
                        $info["parent"] = $this->getModel(1)->toNotifApiStruct();
                        break;
                    # unstandart
                    case 10:
                        $info["type"] = "mention_comment_note";
                        $info["parent"] = $this->getModel(1)->toVkApiStruct();
                        break;
                    case 21:
                        $info["type"] = "mention_comments";
                        break;
                    default:
                        $info["type"] = "mention_comment_unknown";
                        break;
                }
                break;
            case 5:
                $info["type"]   = "make_you_admin";
                $info["parent"] = $this->getModel(0)->toVkApiStruct($this->getModel(1));
                break;
            # Нужно доделать после мержа #935
            case 6:
                $info["type"] = "wall_publish";
                break;
            # В вк не было такого уведомления, так что unstandart
            case 7:
                $info["type"] = "new_posts_in_club";
                break;
            # В вк при передаче подарков приходит сообщение, а не уведомление, так что unstandart
            case 9601:
                $info["type"]   = "sent_gift";
                $info["parent"] = $this->getModel(1)->toVkApiStruct($this->getModel(1));
                break;
            case 9602:
                $info["type"] = "voices_transfer";
                $info["parent"] = $this->getModel(1)->toVkApiStruct($this->getModel(1));
                break;
            case 9603:
                $info["type"] = "up_rating";
                $info["parent"] = $this->getModel(1)->toVkApiStruct($this->getModel(1));
                $info["parent"]->count = $this->getData();
                break;
            default:
                $info["type"] = NULL;
                break;
        }

        return $info;
    }

    function toVkApiStruct()
    {
        $res = (object)[];

        $info = $this->getVkApiInfo();
        $res->type     = $info["type"];
        $res->date     = $this->getDateTime()->timestamp();
        $res->parent   = $info["parent"];
        $res->feedback = $info["feedback"];
        $res->reply    = NULL; # Ответы на комментарии не реализованы
        return $res;
    }
}
