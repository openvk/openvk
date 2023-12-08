<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use Chandler\Signaling\SignalManager;
use openvk\Web\Events\NewMessageEvent;
use openvk\Web\Models\Repositories\{Users, Clubs, Messages};
use openvk\Web\Models\Entities\{Message, Correspondence};

final class MessengerPresenter extends OpenVKPresenter
{
    private $messages;
    private $signaler;
    protected $presenterName = "messenger";

    function __construct(Messages $messages)
    {
        $this->messages = $messages;
        $this->signaler = SignalManager::i();

        parent::__construct();
    }
    
    private function getCorrespondent(int $id): object
    {
        if($id > 0)
            return (new Users)->get($id);
        else if($id < 0)
            return (new Clubs)->get(abs($id));
        else if($id === 0)
            return $this->user->identity;
    }
    
    function renderIndex(): void
    {
        $this->assertUserLoggedIn();

        if(isset($_GET["sel"]))
            $this->pass("openvk!Messenger->app", $_GET["sel"]);
        
        $page = (int) ($_GET["p"] ?? 1);
        $correspondences = iterator_to_array($this->messages->getCorrespondencies($this->user->identity, $page));

        // #КакаоПрокакалось

        $this->template->corresps = $correspondences;
        $this->template->paginatorConf = (object) [
            "count"   => $this->messages->getCorrespondenciesCount($this->user->identity),
            "page"    => (int) ($_GET["p"] ?? 1),
            "amount"  => sizeof($this->template->corresps),
            "perPage" => OPENVK_DEFAULT_PER_PAGE,
        ];
    }
    
    function renderApp(int $sel): void
    {
        $this->assertUserLoggedIn();
        
        $correspondent = $this->getCorrespondent($sel);
        if(!$correspondent)
            $this->notFound();

        if(!$this->user->identity->getPrivacyPermission('messages.write', $correspondent))
        {
            $this->flash("err", tr("warning"), tr("user_may_not_reply"));
        }
        
        $this->template->selId         = $sel;
        $this->template->correspondent = $correspondent;
    }
    
    function renderEvents(int $randNum): void
    {
        $this->assertUserLoggedIn();
        
        header("Content-Type: application/json");
        $this->signaler->listen(function($event, $id) {
            exit(json_encode([[
                "UUID"  => $id,
                "event" => $event->getLongPoolSummary(),
            ]]));
        }, $this->user->id);
    }
    
    function renderVKEvents(int $id): void
    {
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: application/json");
        
        if($this->queryParam("act") !== "a_check")
            exit(header("HTTP/1.1 400 Bad Request"));
        else if(!$this->queryParam("key"))
            exit(header("HTTP/1.1 403 Forbidden"));
        
        $key       = $this->queryParam("key");
        $payload   = hex2bin(substr($key, 0, 16));
        $signature = hex2bin(substr($key, 16));
        if(($signature ^ ( ~CHANDLER_ROOT_CONF["security"]["secret"] | ((string) $id))) !== $payload) {
            exit(json_encode([
                "failed" => 3,
            ]));
        }
        
        $legacy = $this->queryParam("version") < 3;

        $time = intval($this->queryParam("wait"));
        
        if($time > 60)
            $time = 60;
        elseif($time == 0)
        	$time = 25; // default
        
        $this->signaler->listen(function($event, $eId) use ($id) {
            exit(json_encode([
                "ts"      => time(),
                "updates" => [
                    $event->getVKAPISummary($id),
                ],
            ]));
        }, $id, $time);
    }
    
    function renderApiGetMessages(int $sel, int $lastMsg): void
    {
        $this->assertUserLoggedIn();
        
        $correspondent = $this->getCorrespondent($sel);
        if(!$correspondent)
            $this->notFound();
        
        $messages       = [];
        $correspondence = new Correspondence($this->user->identity, $correspondent);
        foreach($correspondence->getMessages(1, $lastMsg === 0 ? NULL : $lastMsg, NULL, 0) as $message)
            $messages[] = $message->simplify();
        
        header("Content-Type: application/json");
        exit(json_encode($messages));
    }
    
    function renderApiWriteMessage(int $sel): void
    {
        $this->assertUserLoggedIn();
        $this->willExecuteWriteAction();
        
        if(empty($this->postParam("content"))) {
            header("HTTP/1.1 400 Bad Request");
            exit("<b>Argument error</b>: param 'content' expected to be string, undefined given.");
        }
        
        $sel = $this->getCorrespondent($sel);
        if($sel->getId() !== $this->user->id && !$sel->getPrivacyPermission('messages.write', $this->user->identity))
            exit(header("HTTP/1.1 403 Forbidden"));
        
        $cor = new Correspondence($this->user->identity, $sel);
        $msg = new Message;
        $msg->setContent($this->postParam("content"));
        $cor->sendMessage($msg);
        
        header("HTTP/1.1 202 Accepted");
        header("Content-Type: application/json");
        exit(json_encode($msg->simplify()));
    }
}
