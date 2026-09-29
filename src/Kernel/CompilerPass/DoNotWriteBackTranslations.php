<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Kernel\CompilerPass;

use OpenDxp\Bundle\CoreBundle\EventListener\DumpTranslationEntriesListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Stops the application from saving translation keys it discovered while rendering.
 *
 * Saving one issues a CREATE TABLE first, and in MySQL any statement that touches the schema
 * commits the open transaction, even when the table is already there. That transaction is what
 * isolates one test from the next, so a single rendered label without a translation would end the
 * isolation for the whole run. Writing back what rendering discovered is a convenience for an
 * editor, and a test application has no editor.
 */
final class DoNotWriteBackTranslations implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->removeDefinition(DumpTranslationEntriesListener::class);
    }
}
