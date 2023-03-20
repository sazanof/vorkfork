<?php
declare(strict_types=1);

namespace Vorkfork\Auth;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use Vorkfork\Application\Session;
use Vorkfork\Core\Exceptions\UserNotFoundException;
use Vorkfork\Core\Models\User;
use Vorkfork\Database\Entity;
use Vorkfork\Security\PasswordHasher;

class Auth implements IAuthenticate
{
    private const SESSION_KEY = 'vf_uid';

    protected static ?User $user = null;

    protected function checkLifetime()
    {
        //TODO
    }

    private function checkSessionUid(User $user): bool
    {
        return Session::get(self::SESSION_KEY) === $user->getId();
    }

    /**
     * @throws UserNotFoundException
     */
    public static function login(string $username, string $password, bool $remember = false): ?User
    {
        if (self::check($username, $password)) {
            self::$user = Auth::user();
            Session::set(self::SESSION_KEY, self::$user->getId());
            return self::$user;
        }
        return null;
    }

    /**
     * @return bool
     */
    public static function logout(): bool
    {
        Session::delete(self::SESSION_KEY);
        return true;
    }

    /**
     * Check if authenticate data is correct
     * @param string $username
     * @param string $password
     * @return bool
     * @throws UserNotFoundException
     */
    public static function check(string $username, string $password): bool
    {
        $user = User::repository()->findByUsername($username);
        if (is_null($user)) {
            throw new UserNotFoundException();
        }
        if (PasswordHasher::validate($user->getPassword(), $password)) {
            self::$user = $user;
            return true;
        }
    }

    /**
     * @return User
     */
    public static function getLoginUser(): User
    {
        if (is_null(self::$user)) {
            self::$user = User::repository()->findById(self::getLoginUserID());
        }
        return self::user();
    }

    public static function getLoginUserID()
    {
        return Session::get(self::SESSION_KEY);
    }

    /**
     * @return bool
     */
    public static function isAuthenticated(): bool
    {
        if (!is_null(self::getLoginUserID())) {
            return self::getLoginUser() !== null;
        }
        return false;
    }

    /**
     * @return User|null
     */
    public static function user(): ?User
    {
        return self::$user;
    }
}