<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use OpenDxp\Model\DataObject\ClassDefinition;
use RuntimeException;

/**
 * Installs a data object class from the definition a package keeps beside its tests.
 *
 * This is not a factory and there is no factory for it. Installing a class writes PHP files into
 * the application, which outlives the transaction a test runs in, so it happens once for a suite
 * and not once per test. Put the call in a `beforeAll`, and let a factory over the generated
 * class create the objects.
 */
final class ClassDefinitions
{
    /**
     * Installs the class, or hands back the one that is already there.
     */
    public static function install(string $name, string $definition): ClassDefinition
    {
        $installed = ClassDefinition::getByName($name);

        if ($installed instanceof ClassDefinition) {
            return $installed;
        }

        $json = file_get_contents($definition);

        if ($json === false) {
            throw new RuntimeException(sprintf('There is no class definition at %s.', $definition));
        }

        $class = new ClassDefinition();
        $class->setName($name);
        $class->setId($name);
        $class->setUserOwner(1);

        ClassDefinition\Service::importClassDefinitionFromJson($class, $json, true);
        $class->save();

        return ClassDefinition::getByName($name)
            ?? throw new RuntimeException(sprintf('The class %s was not installed.', $name));
    }
}
