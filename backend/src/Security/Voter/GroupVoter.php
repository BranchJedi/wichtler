<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Entity\WichtelGroup;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, WichtelGroup> */
final class GroupVoter extends Voter
{
    /** See the group: joined members and the Wichtler admin. */
    public const string VIEW = 'GROUP_VIEW';
    /** Edit, delete, invite, draw: the group admin and the Wichtler admin. */
    public const string MANAGE = 'GROUP_MANAGE';

    /** See every participant's draw result: the Wichtler admin only, never the group admin. */
    public const string VIEW_ALL_ASSIGNMENTS = 'GROUP_VIEW_ALL_ASSIGNMENTS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::MANAGE, self::VIEW_ALL_ASSIGNMENTS], true) && $subject instanceof WichtelGroup;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => true === $subject->getMembership($user)?->hasJoined(),
            self::MANAGE => $subject->getOwner() === $user,
            self::VIEW_ALL_ASSIGNMENTS => false,
        };
    }
}
