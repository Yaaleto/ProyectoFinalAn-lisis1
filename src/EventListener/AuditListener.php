<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Concerns\AuditableInterface;
use App\Entity\Usuario;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Asigna la autoría desde el usuario autenticado justo antes de escribir en la base.
 * Se usa onFlush porque solo ahí se conocen las ediciones reales (registros con cambios).
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class AuditListener
{
    public function __construct(private readonly Security $security)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $ahora = new DateTimeImmutable();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if (!$entity instanceof AuditableInterface) {
                continue;
            }
            $autor = $this->usuarioAutenticado() ?? $entity->getCreadoPor()
                ?? throw new LogicException('Un alta auditada requiere un usuario autenticado.');
            $entity->registrarCreacion($autor, $ahora);
            $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata($entity::class), $entity);
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof AuditableInterface) {
                continue;
            }
            $autor = $this->usuarioAutenticado()
                ?? throw new LogicException('Una edición auditada requiere un usuario autenticado.');
            $entity->registrarModificacion($autor, $ahora);
            $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata($entity::class), $entity);
        }
    }

    private function usuarioAutenticado(): ?Usuario
    {
        $usuario = $this->security->getUser();

        return $usuario instanceof Usuario ? $usuario : null;
    }
}
