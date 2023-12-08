<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use openvk\Web\Events\NewMessageEvent;
use openvk\Web\Models\Entities\{Correspondence, Message};
use openvk\Web\Models\Repositories\{Messages as MSGRepo, Users as USRRepo};
use openvk\VKAPI\Structures\{Message as APIMsg, Conversation as APIConvo};
use openvk\VKAPI\Handlers\Users as APIUsers;
use Chandler\Signaling\SignalManager;

final class Messages extends VKAPIRequestHandler
{
    private function resolvePeer(int $user_id = -1, int $peer_id = -1): ?int
    {
        if($user_id === -1) {
            if($peer_id === -1)
                return NULL;
            else if($peer_id < 0)
                return NULL;
            else if(($peer_id - 2000000000) > 0)
                return NULL;
            
            return $peer_id;
        }
        
        return $user_id;
    }
    
    function getById(string $message_ids, int $preview_length = 0, int $extended = 0): object
    {
        $this->requireUser();
        
        $msgs  = new MSGRepo;
        $ids   = preg_split("%, ?%", $message_ids);
        $items = [];
        foreach($ids as $id) {
            $message = $msgs->get((int) $id);
            if(!$message)
                continue;
            else if($message->getSender()->getId() !== $this->getUser()->getId() && $message->getRecipient()->getId() !== $this->getUser()->getId())
                continue;
            
            $author = $message->getSender()->getId() === $this->getUser()->getId() ? $message->getRecipient()->getId() : $message->getSender()->getId();
            $rMsg   = new APIMsg;
            
            $rMsg->id         = $message->getId();
            $rMsg->user_id    = $author;
            $rMsg->from_id    = $message->getSender()->getId();
            $rMsg->date       = $message->getSendTime()->timestamp();
            $rMsg->read_state = 1;
            $rMsg->out        = (int) ($message->getSender()->getId() === $this->getUser()->getId());
            $rMsg->body       = $message->getText(false);
            $rMsg->text       = $message->getText(false);
            $rMsg->emoji      = true;
            
            if($preview_length > 0)
                $rMsg->body = ovk_proc_strtr($rMsg->body, $preview_length);
                $rMsg->text = ovk_proc_strtr($rMsg->text, $preview_length);
            
            $items[] = $rMsg;
        }
        
        return (object) [
            "count" => sizeof($items),
            "items" => $items,
        ];
    }
    
    function send(int $user_id = -1, int $peer_id = -1, string $domain = "", int $chat_id = -1, string $user_ids = "", string $message = "", int $sticker_id = -1, int $forGodSakePleaseDoNotReportAboutMyOnlineActivity = 0,
                    string $attachment = "") # интересно почему не attachments
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        if($forGodSakePleaseDoNotReportAboutMyOnlineActivity == 0)
        {
            $this->getUser()->updOnline($this->getPlatform());
        }
                
        if($chat_id !== -1)
            $this->fail(946, "Chats are not implemented");
        else if($sticker_id !== -1)
            $this->fail(-151, "Stickers are not implemented");
        
        if(empty($message) && empty($attachment))
            $this->fail(100, "Message text is empty or invalid");
        
        # lol recursion
        if(!empty($user_ids)) {
            $rIds = [];
            $ids  = preg_split("%, ?%", $user_ids);
            if(sizeof($ids) > 100)
                $this->fail(913, "Too many recipients");
            
            foreach($ids as $id)
                $rIds[] = $this->send(-1, $id, "", -1, "", $message);
            
            return $rIds;
        }
        
        if(!empty($domain)) {
            $peer = (new USRRepo)->getByShortCode($domain);
        } else {
            $peer = $this->resolvePeer($user_id, $peer_id);
            $peer = (new USRRepo)->get($peer);
        }
        
        if(!$peer)
            $this->fail(936, "There is no peer with this id");
        
        if($this->getUser()->getId() !== $peer->getId() && !$peer->getPrivacyPermission('messages.write', $this->getUser()))
            $this->fail(945, "This chat is disabled because of privacy settings");
        
        # Finally we get to send a message!
        $chat = new Correspondence($this->getUser(), $peer);
        $msg  = new Message;
        $msg->setContent($message);
        
        $msg = $chat->sendMessage($msg, true);
        if(!$msg)
            $this->fail(950, "Internal error");
        else
            if(!empty($attachment)) {
                $attachs = parseAttachments($attachment);
                
                # Работают только фотки, остальное просто не будет отображаться.
                if(sizeof($attachs) >= 10)
                    $this->fail(15, "Too many attachments");
    
                foreach($attachs as $attach) {
                    if($attach && !$attach->isDeleted() && $attach->getOwner()->getId() == $this->getUser()->getId())
                        $msg->attach($attach);
                    else
                        $this->fail(52, "One of the attachments is invalid");
                }
            }

            return $msg->getId();
    }
    
    function delete(string $message_ids, int $spam = 0, int $delete_for_all = 0): object
    {
        $this->requireUser();
        $this->willExecuteWriteAction();
        
        $msgs  = new MSGRepo;
        $ids   = preg_split("%, ?%", $message_ids);
        $items = [];
        foreach($ids as $id) {
            $message = $msgs->get((int) $id);
            if(!$message || $message->getSender()->getId() !== $this->getUser()->getId() && $message->getRecipient()->getId() !== $this->getUser()->getId())
                $items[$id] = 0;
            
            $message->delete();
            $items[$id] = 1;
        }
        
        return (object) $items;
    }
    
    function restore(int $message_id): int
    {
        $this->requireUser();
        $this->willExecuteWriteAction();
        
        $msg = (new MSGRepo)->get($message_id);
        if(!$msg)
            return 0;
        else if($msg->getSender()->getId() !== $this->getUser()->getId())
            return 0;
        
        $msg->undelete();
        return 1;
    }
    
    function getConversations(int $offset = 0, int $count = 20, string $filter = "all", int $extended = 0, string $fields = ""): object
    {
        $this->requireUser();
        
        $convos = (new MSGRepo)->getCorrespondencies($this->getUser(), -1, $count, $offset);
        $convosCount = (new MSGRepo)->getCorrespondenciesCount($this->getUser());
        $list   = [];

        $users = [];
        foreach($convos as $convo) {
            $correspondents = $convo->getCorrespondents();
            if($correspondents[0]->getId() === $this->getUser()->getId())
                $peer = $correspondents[1];
            else
                $peer = $correspondents[0];
            
            $lastMessage = $convo->getPreviewMessage();
            
            $listConvo = new APIConvo;
            $listConvo->peer = [
                "id"       => $peer->getId(),
                "type"     => "user",
                "local_id" => $peer->getId(),
            ];
            
            $canWrite = $peer->getSubscriptionStatus($this->getUser()) === 3;
            $listConvo->can_write = [
                "allowed" => $canWrite,
            ];
            
            $lastMessagePreview = NULL;
            if(!is_null($lastMessage)) {
                $listConvo->last_message_id = $lastMessage->getId();
                
                if($lastMessage->getSender()->getId() === $this->getUser()->getId())
                    $author = $lastMessage->getRecipient()->getId();
                else
                    $author = $lastMessage->getSender()->getId();
                
                $lastMessagePreview             = new APIMsg;
                $lastMessagePreview->id         = $lastMessage->getId();
                $lastMessagePreview->user_id    = $author;
                $lastMessagePreview->from_id    = $lastMessage->getSender()->getId();
                $lastMessagePreview->date       = $lastMessage->getSendTime()->timestamp();
                $lastMessagePreview->read_state = 1;
                $lastMessagePreview->out        = (int) ($lastMessage->getSender()->getId() === $this->getUser()->getId());
                $lastMessagePreview->body       = $lastMessage->getText(false);
                $lastMessagePreview->text       = $lastMessage->getText(false);
                $lastMessagePreview->emoji      = true;
            
                if($extended == 1) {
                    $users[] = $author;
                }
            }
            
            $list[] = [
                "conversation" => $listConvo,
                "last_message" => $lastMessagePreview,
            ];
        }
        
        if($extended == 0){
            return (object) [
                "count" => $convosCount,
                "items" => $list,
            ];
        } else {
            $users[] = $this->getUser()->getId();
            $users = array_unique($users);

            return (object) [
                "count"    => $convosCount,
                "items"    => $list,
                "profiles" => (!empty($users) ? (new APIUsers)->get(implode(',', $users), $fields, 0, $count+1) : [])
            ];
        }
    }

    function getConversationsById(string $peer_ids, int $extended = 0, string $fields = "")
    {
        $this->requireUser();

        $peers = explode(',', $peer_ids);

        $output = [
            "count" => 0,
            "items" => []
        ];

        $userslist = [];

        foreach($peers as $peer) {
            if(key($peers) > 100)
                continue;

            if(is_null($user_id = $this->resolvePeer((int) $peer)))
                $this->fail(-151, "Chats are not implemented");

            $user     = (new USRRepo)->get((int) $peer);

            if($user) {
                $dialogue = new Correspondence($this->getUser(), $user);
                $iterator = $dialogue->getMessages(Correspondence::CAP_BEHAVIOUR_START_MESSAGE_ID, 0, 1, 0, false);
                $msg      = $iterator[0]->unwrap(); // шоб удобнее было
                $output['items'][] = [
                    "peer" => [
                        "id" => $user->getId(),
                        "type" => "user",
                        "local_id" => $user->getId()
                    ],
                    "last_message_id" => $msg->id,
                    "in_read" => $msg->id,
                    "out_read" => $msg->id,
                    "sort_id" => [
                        "major_id" => 0,
                        "minor_id" => $msg->id, // КОНЕЧНО ЖЕ
                    ],
                    "last_conversation_message_id" => $user->getId(),
                    "in_read_cmid" => $user->getId(),
                    "out_read_cmid" => $user->getId(),
                    "is_marked_unread" => $iterator[0]->isUnread(),
                    "important" => false, // целестора когда релиз
                    "can_write" => [
                        "allowed" => ($user->getId() === $this->getUser()->getId() || $user->getPrivacyPermission('messages.write', $this->getUser()) === true)
                    ]                
                ];
                $userslist[] = $user->getId();
            }
        }

        if($extended == 1) {
            $userslist          = array_unique($userslist);
            $output['profiles'] = (!empty($userslist) ? (new APIUsers)->get(implode(',', $userslist), $fields) : []);
        }

        $output['count'] = sizeof($output['items']);
        return (object) $output;
    }
    
    function getHistory(int $offset = 0, int $count = 20, int $user_id = -1, int $peer_id = -1, int $start_message_id = 0, int $rev = 0, int $extended = 0, string $fields = ""): object
    {
        $this->requireUser();
        
        if(is_null($user_id = $this->resolvePeer($user_id, $peer_id)))
            $this->fail(-151, "Chats are not implemented");
        
        $peer = (new USRRepo)->get($user_id);
        if(!$peer)
            $this->fail(1, "ошибка про то что пира нет");
        
        $results  = [];
        $dialogue = new Correspondence($this->getUser(), $peer);
        $iterator = $dialogue->getMessages(Correspondence::CAP_BEHAVIOUR_START_MESSAGE_ID, $start_message_id, $count, abs($offset), $rev === 1);
        foreach($iterator as $message) {
            $msgU = $message->unwrap(); # Why? As of OpenVK 2 Public Preview Two database layer doesn't work correctly and refuses to cache entities.
                                        # UPDATE: the issue seems to be caused by debug mode and json_encode (bruh_encode). ~~Dorothy
            
            $rMsg = new APIMsg;
            $rMsg->id         = $msgU->id;
            $rMsg->user_id    = $msgU->sender_id === $this->getUser()->getId() ? $msgU->recipient_id : $msgU->sender_id;
            $rMsg->from_id    = $msgU->sender_id;
            $rMsg->date       = $msgU->created;
            $rMsg->read_state = 1;
            $rMsg->out        = (int) ($msgU->sender_id === $this->getUser()->getId());
            $rMsg->body       = $message->getText(false);
            $rMsg->text       = $message->getText(false);
            $rMsg->emoji      = true;
            
            $results[] = $rMsg;
        }
        
        $output = [
            "count" => sizeof($results),
            "items" => $results,
        ];

        if ($extended == 1) {
            $users[] = $this->getUser()->getId();
            $users[] = $user_id;
            $output["profiles"] = (!empty($users) ? (new APIUsers($this->getUser()))->get(implode(',', $users), $fields) : []);
        }

        return (object) $output;
    }
    
    function getLongPollHistory(int $ts = -1, int $preview_length = 0, int $events_limit = 1000, int $msgs_limit = 1000): object
    {
        $this->requireUser();
        
        $res = [
            "history"  => [],
            "messages" => [],
            "profiles" => [],
            "new_pts"  => 0,
        ];
        
        $manager = SignalManager::i();
        $events  = $manager->getHistoryFor($this->getUser()->getId(), $ts === -1 ? NULL : $ts, min($events_limit, $msgs_limit));
        foreach($events as $event) {
            if(!($event instanceof NewMessageEvent))
                continue;
            
            $message = $this->getById((string) $event->getLongPoolSummary()->message["uuid"], $preview_length, 1)->items[0];
            if(!$message)
                continue;
            
            $res["messages"][] = $message;
            $res["history"][]  = $event->getVKAPISummary($this->getUser()->getId());
        }
        
        $res["messages"] = [
            "count" => sizeof($res["messages"]),
            "items" => $res["messages"],
        ];
        return (object) $res;
    }
    
    function getLongPollServer(int $need_pts = 1, int $lp_version = 3, ?int $group_id = NULL): array
    {
        $this->requireUser();
        
        if($group_id > 0)
            $this->fail(-151, "Not implemented");
        
        $url = "http" . (ovk_is_ssl() ? "s" : "") . "://$_SERVER[HTTP_HOST]/nim" . $this->getUser()->getId();
        $key = openssl_random_pseudo_bytes(8);
        $key = bin2hex($key) . bin2hex($key ^ ( ~CHANDLER_ROOT_CONF["security"]["secret"] | ((string) $this->getUser()->getId()) ));
        $res = [
            "key"    => $key,
            "server" => $url,
            "ts"     => time(),
        ];
        
        if($need_pts === 1)
            $res["pts"] = -1;
        
        return $res;
    }

    function edit(int $message_id, string $message = "", string $attachment = "", int $peer_id = 0)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $msg = (new MSGRepo)->get($message_id);

        if(empty($message) && empty($attachment))
            $this->fail(100, "Required parameter 'message' missing.");

        if(!$msg || $msg->isDeleted())
            $this->fail(102, "Invalid message");

        if($msg->getSender()->getId() != $this->getUser()->getId())
            $this->fail(15, "Access to message denied");
        
        if(!empty($message))
            $msg->setContent($message);

        $msg->setEdited(time());
        $msg->save(true);

        if(!empty($attachment)) {
            $attachs = parseAttachments($attachment);
            $newAttachmentsCount = sizeof($attachs);

            $postsAttachments = iterator_to_array($msg->getChildren());

            if(sizeof($postsAttachments) >= 10)
                $this->fail(15, "Message have too many attachments");

            if(($newAttachmentsCount + sizeof($postsAttachments)) > 10)
                $this->fail(158, "Message will have too many attachments");

            foreach($attachs as $attach) {
                if($attach && !$attach->isDeleted() && $attach->getOwner()->getId() == $this->getUser()->getId())
                    $msg->attach($attach);
                else
                    $this->fail(52, "One of the attachments is invalid");
            }
        }

        return 1;
    }
}
