<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use openvk\VKAPI\Exceptions\APIErrorException;
use openvk\Web\Models\Entities\IP;
use openvk\Web\Models\Entities\User;
use openvk\Web\Models\Repositories\IPs;

abstract class VKAPIRequestHandler
{
    protected $user;
    protected $platform;
    
    function __construct(?User $user = NULL, ?string $platform = NULL)
    {
        $this->user     = $user;
        $this->platform = $platform;
    }
    
    protected function fail(int $code, string $message): void
    {
        throw new APIErrorException($message, $code);
    }
    
    protected function getUser(): ?User
    {
        return $this->user;
    }
    
    protected function getPlatform(): ?string
    {
        return $this->platform ?? "";
    }
    
    protected function userAuthorized(): bool
    {
        return !is_null($this->getUser());
    }
    
    protected function requireUser(): void
    {
        if(!$this->userAuthorized())
            $this->fail(5, "User authorization failed: no access_token passed.");
    }
    
    protected function willExecuteWriteAction(): void
    {
        $ip  = (new IPs)->get(CONNECTING_IP);
        $res = $ip->rateLimit();
    
        if(!($res === IP::RL_RESET || $res === IP::RL_CANEXEC)) {
            if($res === IP::RL_BANNED && OPENVK_ROOT_CONF["openvk"]["preferences"]["security"]["rateLimits"]["autoban"]) {
                $this->user->ban("User account has been suspended for breaking API terms of service", false);
                $this->fail(18, "User account has been suspended due to repeated violation of API rate limits.");
            }
            
            $this->fail(29, "You have been rate limited.");
        }
    }
}
