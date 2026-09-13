<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Finca;
use App\Entity\Usuario;
use App\Security\FincaAccess;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class FincaVoter extends Voter
{
    public const VIEW = 'FINCA_VIEW';
    public const MANAGE = 'FINCA_MANAGE';

    public function __construct(private readonly FincaAccess $fincaAccess)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Finca && \in_array($attribute, [self::VIEW, self::MANAGE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $usuario = $token->getUser();

        if (!$usuario instanceof Usuario) {
            return false;
        }

        \assert($subject instanceof Finca);

        return match ($attribute) {
            self::VIEW => $this->fincaAccess->canView($usuario, $subject),
            self::MANAGE => $this->fincaAccess->canManage($usuario, $subject),
        };
    }
}
