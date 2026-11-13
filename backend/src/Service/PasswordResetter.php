<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Security\TokenIssuer;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Replaces a forgotten password by a temporary one. Used by the Wichtler admin in the app and,
 * for the Wichtler admin's own account, from the command line.
 */
final readonly class PasswordResetter
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
        private TokenIssuer $tokenIssuer,
    ) {
    }

    /**
     * Signs the user out everywhere and obliges them to choose a new password after signing in
     * with the returned one. Flush afterwards.
     *
     * @return string the temporary password; it is stored hashed and cannot be retrieved again
     */
    public function reset(User $user): string
    {
        $temporaryPassword = self::generate();
        $user->setPassword($this->hasher->hashPassword($user, $temporaryPassword));
        $user->setMustChangePassword(true);
        $this->tokenIssuer->endSessions($user);

        return $temporaryPassword;
    }

    /** Four groups of four characters, easy to read out loud: no 0/O, 1/l/I. About 92 bits. */
    private static function generate(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $groups = [];
        for ($group = 0; $group < 4; ++$group) {
            $groups[$group] = '';
            for ($i = 0; $i < 4; ++$i) {
                $groups[$group] .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
            }
        }

        return implode('-', $groups);
    }
}
