<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Kernel\CompilerPass;

use OpenDxp\Bundle\CoreBundle\EventListener\DumpTranslationEntriesListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Saving a translation key issues a CREATE TABLE first, and in MySQL any statement that touches
 * the schema commits the open transaction, even when the table is already there. That transaction
 * isolates one test from the next, so a single rendered label without a translation would end the
 * isolation for the whole run.
 */
final class DoNotWriteBackTranslations implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->removeDefinition(DumpTranslationEntriesListener::class);
    }
}
