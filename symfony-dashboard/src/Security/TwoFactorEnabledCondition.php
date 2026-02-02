<?php

namespace App\Security;

use App\Entity\MailUser;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Condition\TwoFactorConditionInterface;

/**
 * Only trigger 2FA when the user has explicitly enabled it.
 *
 * This prevents the 2FA flow from activating for accounts that haven't completed setup.
 */
class TwoFactorEnabledCondition implements TwoFactorConditionInterface
{
    public function shouldPerformTwoFactorAuthentication(AuthenticationContextInterface $context): bool
    {
        $user = $context->getUser();

        if (!$user instanceof MailUser) {
            return false;
        }

        // MailUser::isTotpAuthenticationEnabled() already checks both flag + secret.
        return $user->isTotpAuthenticationEnabled();
    }
}

