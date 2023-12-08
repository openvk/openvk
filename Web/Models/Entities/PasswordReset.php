<?php declare(strict_types=1);
namespace openvk\Web\Models\Entities;
use openvk\Web\Models\Repositories\Users;
use openvk\Web\Models\RowModel;
use openvk\Web\Util\DateTime;

class PasswordReset extends RowModel
{
    protected $tableName = "password_resets";
    
    function getUser(): User
    {
        return (new Users)->get($this->getRecord()->profile);
    }
    
    function getKey(): string
    {
        return $this->getRecord()->key;
    }
    
    function getToken(): string
    {
        return $this->getKey();
    }
    
    function getCreationTime(): DateTime
    {
        return new DateTime($this->getRecord()->timestamp);
    }
    
    /**
     * User can request password reset only if he does not have any "new" password resets.
     * Password reset becomes "old" after 5 minutes and one second.
     */
    function isNew(): bool
    {
        return $this->getRecord()->timestamp > (time() - (5 * MINUTE));
    }
    
    /**
     * Token is valid only for 3 days.
     */
    function isStillValid(): bool
    {
        return $this->getRecord()->timestamp > (time() - (3 * DAY));
    }
    
    function verify(string $token): bool
    {
        try {
            return $this->isStillValid() ? sodium_memcmp($this->getKey(), $token) : false;
        } catch(\SodiumException $ex) {
            return false;
        }
    }
    
    function save(?bool $log = false): void
    {
        $this->stateChanges("key", base64_encode(openssl_random_pseudo_bytes(46)));
        $this->stateChanges("timestamp", time());
        
        parent::save($log);
    }
}
