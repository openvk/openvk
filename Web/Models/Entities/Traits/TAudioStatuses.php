<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities\Traits;
use openvk\Web\Models\Repositories\Audios;
use Chandler\Database\DatabaseConnection;

trait TAudioStatuses 
{
    function isBroadcastEnabled(): bool
    {
        if($this->getRealId() < 0) return true;
        return (bool) $this->getRecord()->audio_broadcast_enabled;
    }

    function getCurrentAudioStatus()
    {
        if(!$this->isBroadcastEnabled()) return NULL;

        $audioId = $this->getRecord()->last_played_track;

        if(!$audioId) return NULL;
        $audio = (new Audios)->get($audioId);

        if(!$audio || $audio->isDeleted())
            return NULL;

        $listensTable = DatabaseConnection::i()->getContext()->table("audio_listens");
        $lastListen   = $listensTable->where([
            "entity" => $this->getRealId(),
            "audio"  => $audio->getId(),
            "time >"   => (time() - $audio->getLength()) - 10,
        ])->fetch();

        if($lastListen)
            return $audio;

        return NULL;
    }
}
