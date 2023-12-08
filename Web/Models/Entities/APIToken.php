<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Models\RowModel;
use openvk\Web\Models\Repositories\Users;
use Nette\InvalidStateException as ISE;

class APIToken extends RowModel
{
    protected $tableName = "api_tokens";
    
    function getUser(): User
    {
        return (new Users)->get($this->getRecord()->user);
    }
    
    function getSecret(): string
    {
        return $this->getRecord()->secret;
    }
    
    function getFormattedToken(): string
    {
        return $this->getId() . "-" . chunk_split($this->getSecret(), 8, "-") . "jill";
    }

    function getPlatform(): ?string
    {
        return $this->getRecord()->platform;
    }
    
    function isRevoked(): bool
    {
        return $this->isDeleted();
    }
    
    function setUser(User $user): void
    {
        $this->stateChanges("user", $user->getId());
    }
    
    function setSecret(string $secret): void
    {
        throw new ISE("Setting secret manually is prohbited");
    }
    
    function revoke(): void
    {
        $this->delete();
    }
    
    function save(?bool $log = false): void
    {
        if(is_null($this->getRecord()))
            $this->stateChanges("secret", bin2hex(openssl_random_pseudo_bytes(36)));
        
        parent::save();
    }
}
