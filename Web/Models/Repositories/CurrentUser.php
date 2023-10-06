<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use openvk\Web\Models\Entities\User;

class CurrentUser
{
    private static $instance = null;
    private $user;
    private $ip;
    private $useragent;

    public function __construct(?User $user = NULL, ?string $ip = NULL, ?string $useragent = NULL)
    {
        if ($user)
            $this->user = $user;

        if ($ip)
            $this->ip = $ip;

        if ($useragent)
            $this->useragent = $useragent;
    }

    public static function get($user, $ip, $useragent)
    {
        if (self::$instance === null) self::$instance = new self($user, $ip, $useragent);
        return self::$instance;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getIP(): string
    {
        return $this->ip;
    }

    public function getUserAgent(): string
    {
        return $this->useragent;
    }

    public static function i()
    {
        return self::$instance;
    }
}
