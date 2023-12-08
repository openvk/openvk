<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use Chandler\Database\DatabaseConnection;
use openvk\Web\Util\Shell\Exceptions\UnknownCommandException;
use openvk\Web\Util\Shell\Shell;

/**
 * @method setName(string)
 * @method setPerformer(string)
 * @method setLyrics(string)
 * @method setExplicit(bool)
 */
class Audio extends Media
{
    protected $tableName     = "audios";
    protected $fileExtension = "mpd";

    # Taken from winamp :D
    const genres = [
        'Blues','Big Band','Classic Rock','Chorus','Country','Easy Listening','Dance','Acoustic','Disco','Humour','Funk','Speech','Grunge','Chanson','Hip-Hop','Opera','Jazz','Chamber Music','Metal','Sonata','New Age','Symphony','Oldies','Booty Bass','Other','Primus','Pop','Porn Groove','R&B','Satire','Rap','Slow Jam','Reggae','Club','Rock','Tango','Techno','Samba','Industrial','Folklore','Alternative','Ballad','Ska','Power Ballad','Death Metal','Rhythmic Soul','Pranks','Freestyle','Soundtrack','Duet','Euro-Techno','Punk Rock','Ambient','Drum Solo','Trip-Hop','A Cappella','Vocal','Euro-House','Jazz+Funk','Dance Hall','Fusion','Goa','Trance','Drum & Bass','Classical','Club-House','Instrumental','Hardcore','Acid','Terror','House','Indie','Game','BritPop','Sound Clip','Negerpunk','Gospel','Polsk Punk','Noise','Beat','AlternRock','Christian Gangsta Rap','Bass','Heavy Metal','Soul','Black Metal','Punk','Crossover','Space','Contemporary Christian','Meditative','Christian Rock','Instrumental Pop','Merengue','Instrumental Rock','Salsa','Ethnic','Thrash Metal','Gothic','Anime','Darkwave','JPop','Techno-Industrial','Synthpop','Electronic','Abstract','Pop-Folk','Art Rock','Eurodance','Baroque','Dream','Bhangra','Southern Rock','Big Beat','Comedy','Breakbeat','Cult','Chillout','Gangsta Rap','Downtempo','Top 40','Dub','Christian Rap','EBM','Pop / Funk','Eclectic','Jungle','Electro','Native American','Electroclash','Cabaret','Emo','New Wave','Experimental','Psychedelic','Garage','Rave','Global','Showtunes','IDM','Trailer','Illbient','Lo-Fi','Industro-Goth','Tribal','Jam Band','Acid Punk','Krautrock','Acid Jazz','Leftfield','Polka','Lounge','Retro','Math Rock','Musical','New Romantic','Rock & Roll','Nu-Breakz','Hard Rock','Post-Punk','Folk','Post-Rock','Folk-Rock','Psytrance','National Folk','Shoegaze','Swing','Space Rock','Fast Fusion','Trop Rock','Bebob','World Music','Latin','Neoclassical','Revival','Audiobook','Celtic','Audio Theatre','Bluegrass','Neue Deutsche Welle','Avantgarde','Podcast','Gothic Rock','Indie Rock','Progressive Rock','G-Funk','Psychedelic Rock','Dubstep','Symphonic Rock','Garage Rock','Slow Rock','Psybient','Psychobilly','Touhou'
    ];

    # Taken from: https://web.archive.org/web/20220322153107/https://dev.vk.com/reference/objects/audio-genres
    const vkGenres = [
        "Rock"               => 1,
        "Pop"                => 2,
        "Rap"                => 3,
        "Hip-Hop"            => 3, # VK API lists №3 as Rap & Hip-Hop, but these genres are distinct in OpenVK
        "Easy Listening"     => 4,
        "House"              => 5,
        "Dance"              => 5,
        "Instrumental"       => 6,
        "Metal"              => 7,
        "Alternative"        => 21,
        "Dubstep"            => 8,
        "Jazz"               => 1001,
        "Blues"              => 1001,
        "Drum & Bass"        => 10,
        "Trance"             => 11,
        "Chanson"            => 12,
        "Ethnic"             => 13,
        "Acoustic"           => 14,
        "Vocal"              => 14,
        "Reggae"             => 15,
        "Classical"          => 16,
        "Indie Pop"          => 17,
        "Speech"             => 19,
        "Disco"              => 22,
        "Other"              => 18,
    ];

    private function fileLength(string $filename): int
    {
        if(!Shell::commandAvailable("ffmpeg") || !Shell::commandAvailable("ffprobe"))
            throw new \Exception();

        $error   = NULL;
        $streams = Shell::ffprobe("-i", $filename, "-show_streams", "-select_streams a", "-loglevel error")->execute($error);
        if($error !== 0)
            throw new \DomainException("$filename is not recognized as media container");
        else if(empty($streams) || ctype_space($streams))
            throw new \DomainException("$filename does not contain any audio streams");

        $vstreams = Shell::ffprobe("-i", $filename, "-show_streams", "-select_streams v", "-loglevel error")->execute($error);
        
        # check if audio has cover (attached_pic)
        preg_match("%attached_pic=([0-1])%", $vstreams, $hasCover);
        if(!empty($vstreams) && !ctype_space($vstreams) && ((int)($hasCover[1]) !== 1))
            throw new \DomainException("$filename is a video");

        $durations = [];
        preg_match_all('%duration=([0-9\.]++)%', $streams, $durations);
        if(sizeof($durations[1]) === 0)
            throw new \DomainException("$filename does not contain any meaningful audio streams");

        $length = 0;
        foreach($durations[1] as $duration) {
            $duration = floatval($duration);
            if($duration < 1.0 || $duration > 65536.0)
                throw new \DomainException("$filename does not contain any meaningful audio streams");
            else
                $length = max($length, $duration);
        }

        return (int) round($length, 0, PHP_ROUND_HALF_EVEN);
    }

    /**
     * @throws \Exception
     */
    protected function saveFile(string $filename, string $hash): bool
    {
        $duration = $this->fileLength($filename);

        $kid = openssl_random_pseudo_bytes(16);
        $key = openssl_random_pseudo_bytes(16);
        $tok = openssl_random_pseudo_bytes(28);
        $ss  = ceil($duration / 15);

        $this->stateChanges("kid", $kid);
        $this->stateChanges("key", $key);
        $this->stateChanges("token", $tok);
        $this->stateChanges("segment_size", $ss);
        $this->stateChanges("length", $duration);

        try {
            $args = [
                str_replace("enabled", "available", OPENVK_ROOT),
                str_replace("enabled", "available", $this->getBaseDir()),
                $hash,
                $filename,

                bin2hex($kid),
                bin2hex($key),
                bin2hex($tok),
                $ss,
            ];

            if(Shell::isPowershell()) {
                Shell::powershell("-executionpolicy bypass", "-File", __DIR__ . "/../shell/processAudio.ps1", ...$args)
                ->start();
            } else {
                Shell::bash(__DIR__ . "/../shell/processAudio.sh", ...$args) // Pls workkkkk
                ->start();                                                    // idk, not tested :")
            }

            # Wait until processAudio will consume the file
            $start = time();
            while(file_exists($filename))
                if(time() - $start > 5)
                    throw new \RuntimeException("Timed out waiting FFMPEG");

         } catch(UnknownCommandException $ucex) {
             exit(OPENVK_ROOT_CONF["openvk"]["debug"] ? "bash/pwsh is not installed" : VIDEOS_FRIENDLY_ERROR);
         }

        return true;
    }

    function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    function getPerformer(): string
    {
        return $this->getRecord()->performer;
    }

    function getName(): string
    {
        return $this->getPerformer() . " — " . $this->getTitle();
    }

    function getGenre(): ?string
    {
        return $this->getRecord()->genre;
    }

    function getLyrics(): ?string
    {
        return !is_null($this->getRecord()->lyrics) ? htmlspecialchars($this->getRecord()->lyrics, ENT_DISALLOWED | ENT_XHTML) : NULL;
    }

    function getLength(): int
    {
        return $this->getRecord()->length;
    }

    function getFormattedLength(): string
    {
        $len  = $this->getLength();
        $mins = floor($len / 60);
        $secs = $len - ($mins * 60);

        return (
            str_pad((string) $mins, 2, "0", STR_PAD_LEFT)
            . ":" .
            str_pad((string) $secs, 2, "0", STR_PAD_LEFT)
        );
    }

    function getSegmentSize(): float
    {
        return $this->getRecord()->segment_size;
    }

    function getListens(): int
    {
        return $this->getRecord()->listens;
    }

    function getOriginalURL(bool $force = false): string
    {
        $disallowed = !OPENVK_ROOT_CONF["openvk"]["preferences"]["music"]["exposeOriginalURLs"] && !$force;
        if(!$this->isAvailable() || $disallowed)
            return ovk_scheme(true)
                . $_SERVER["HTTP_HOST"] . ":"
                . $_SERVER["HTTP_PORT"]
                . "/assets/packages/static/openvk/audio/nomusic.mp3";

        $key = bin2hex($this->getRecord()->token);

        return str_replace(".mpd", "_fragments", $this->getURL()) . "/original_$key.mp3";
    }

    function getURL(?bool $force = false): string
    {
        if ($this->isWithdrawn()) return "";

        return parent::getURL();
    }

    function getKeys(): array
    {
        $keys[bin2hex($this->getRecord()->kid)] = bin2hex($this->getRecord()->key);

        return $keys;
    }

    function isAnonymous(): bool
    {
        return false;
    }

    function isExplicit(): bool
    {
        return (bool) $this->getRecord()->explicit;
    }

    function isWithdrawn(): bool
    {
        return (bool) $this->getRecord()->withdrawn;
    }

    function isUnlisted(): bool
    {
        return (bool) $this->getRecord()->unlisted;
    }

    # NOTICE may flush model to DB if it was just processed
    function isAvailable(): bool
    {
        if($this->getRecord()->processed)
            return true;

        # throttle requests to isAvailable to prevent DoS attack if filesystem is actually an S3 storage
        if(time() - $this->getRecord()->checked < 5)
            return false;

        try {
            $fragments = str_replace(".mpd", "_fragments", $this->getFileName());
            $original = "original_" . bin2hex($this->getRecord()->token) . ".mp3";
            if(file_exists("$fragments/$original")) {
                # Original gets uploaded after fragments
                $this->stateChanges("processed", 0x01);

                return true;
            }
        } finally {
            $this->stateChanges("checked", time());
            $this->save();
        }

        return false;
    }

    function isInLibraryOf($entity): bool
    {
        return sizeof(DatabaseConnection::i()->getContext()->table("audio_relations")->where([
            "entity" => $entity->getId() * ($entity instanceof Club ? -1 : 1),
            "audio"  => $this->getId(),
        ])) != 0;
    }

    function add($entity): bool
    {
        if($this->isInLibraryOf($entity))
            return false;

        $entityId  = $entity->getId() * ($entity instanceof Club ? -1 : 1);
        $audioRels = DatabaseConnection::i()->getContext()->table("audio_relations");
        if(sizeof($audioRels->where("entity", $entityId)) > 65536)
            throw new \OverflowException("Can't have more than 65536 audios in a playlist");

        $audioRels->insert([
            "entity" => $entityId,
            "audio"  => $this->getId(),
        ]);

        return true;
    }

    function remove($entity): bool
    {
        if(!$this->isInLibraryOf($entity))
            return false;

        DatabaseConnection::i()->getContext()->table("audio_relations")->where([
            "entity" => $entity->getId() * ($entity instanceof Club ? -1 : 1),
            "audio"  => $this->getId(),
        ])->delete();

        return true;
    }

    function listen($entity, Playlist $playlist = NULL): bool
    {
        $listensTable = DatabaseConnection::i()->getContext()->table("audio_listens");
        $lastListen   = $listensTable->where([
            "entity" => $entity->getRealId(),
            "audio"  => $this->getId(),
        ])->order("index DESC")->fetch();
        
        if(!$lastListen || (time() - $lastListen->time >= $this->getLength())) {
            $listensTable->insert([
                "entity" => $entity->getRealId(),
                "audio"  => $this->getId(),
                "time"   => time(),
                "playlist" => $playlist ? $playlist->getId() : NULL,
            ]);

            if($entity instanceof User) {
                $this->stateChanges("listens", ($this->getListens() + 1));
                $this->save();

                if($playlist) {
                    $playlist->incrementListens();
                    $playlist->save();
                }
            }

            $entity->setLast_played_track($this->getId());
            $entity->save();

            return true;
        }

        $lastListen->update([
            "time" => time(),
        ]);

        return false;
    }

    /**
     * Returns compatible with VK API 4.x, 5.x structure.
     *
     * Always sets album(_id) to NULL at this time.
     * If genre is not present in VK genre list, fallbacks to "Other".
     * The url and manifest properties will be set to false if the audio can't be played (processing, removed).
     *
     * Aside from standard VK properties, this method will also return some OVK extended props:
     * 1. added - Is in the library of $user?
     * 2. editable - Can be edited by $user?
     * 3. withdrawn - Removed due to copyright request?
     * 4. ready - Can be played at this time?
     * 5. genre_str - Full name of genre, NULL if it's undefined
     * 6. manifest - URL to MPEG-DASH manifest
     * 7. keys - ClearKey DRM keys
     * 8. explicit - Marked as NSFW?
     * 9. searchable - Can be found via search?
     * 10. unique_id - Unique ID of audio
     *
     * @notice that in case if exposeOriginalURLs is set to false in config, "url" will always contain link to nomusic.mp3,
     * unless $forceURLExposure is set to true.
     *
     * @notice may trigger db flush if the audio is not processed yet, use with caution on unsaved models.
     *
     * @param ?User $user user, relative to whom "added", "editable" will be set
     * @param bool $forceURLExposure force set "url" regardless of config
     */
    function toVkApiStruct(?User $user = NULL, bool $forceURLExposure = false): object
    {
        $obj = (object) [];
        $obj->unique_id  = base64_encode((string) $this->getId());
        $obj->id         = $obj->aid = $this->getVirtualId();
        $obj->artist     = $this->getPerformer();
        $obj->title      = $this->getTitle();
        $obj->duration   = $this->getLength();
        $obj->album_id   = $obj->album = NULL; # i forgor to implement
        $obj->url        = false;
        $obj->manifest   = false;
        $obj->keys       = false;
        $obj->genre_id   = $obj->genre = self::vkGenres[$this->getGenre() ?? ""] ?? 18; # return Other if no match
        $obj->genre_str  = $this->getGenre();
        $obj->owner_id   = $this->getOwner()->getId();
        if($this->getOwner() instanceof Club)
            $obj->owner_id *= -1;

        $obj->lyrics = NULL;
        if(!is_null($this->getLyrics()))
            $obj->lyrics = $this->getId();

        $obj->added      = $user && $this->isInLibraryOf($user);
        $obj->editable   = $user && $this->canBeModifiedBy($user);
        $obj->searchable = !$this->isUnlisted();
        $obj->explicit   = $this->isExplicit();
        $obj->withdrawn  = $this->isWithdrawn();
        $obj->ready      = $this->isAvailable() && !$obj->withdrawn;
        if($obj->ready) {
            $obj->url      = $this->getOriginalURL($forceURLExposure);
            $obj->manifest = $this->getURL();
            $obj->keys     = $this->getKeys();
        }

        return $obj;
    }

    function setOwner(int $oid): void
    {
        # WARNING: API implementation won't be able to handle groups like that, don't remove
        if($oid <= 0)
            throw new \OutOfRangeException("Only users can be owners of audio!");

        $this->stateChanges("owner", $oid);
    }

    function setGenre(string $genre): void
    {
        if(!in_array($genre, Audio::genres)) {
            $this->stateChanges("genre", NULL);
            return;
        }

        $this->stateChanges("genre", $genre);
    }

    function setCopyrightStatus(bool $withdrawn = true): void {
        $this->stateChanges("withdrawn", $withdrawn);
    }

    function setSearchability(bool $searchable = true): void {
        $this->stateChanges("unlisted", !$searchable);
    }

    function setToken(string $tok): void {
        throw new \LogicException("Changing keys is not supported.");
    }

    function setKid(string $kid): void {
        throw new \LogicException("Changing keys is not supported.");
    }

    function setKey(string $key): void {
        throw new \LogicException("Changing keys is not supported.");
    }

    function setLength(int $len): void {
        throw new \LogicException("Changing length is not supported.");
    }

    function setSegment_Size(int $len): void {
        throw new \LogicException("Changing length is not supported.");
    }

    function delete(bool $softly = true): void
    {
        $ctx = DatabaseConnection::i()->getContext();
        $ctx->table("audio_relations")->where("audio", $this->getId())
            ->delete();
        $ctx->table("audio_listens")->where("audio", $this->getId())
            ->delete();
        $ctx->table("playlist_relations")->where("media", $this->getId())
            ->delete();

        parent::delete($softly);
    }
}
