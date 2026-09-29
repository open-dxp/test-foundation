<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Factory;

use OpenDxp\Model\AbstractModel;
use Zenstruck\Foundry\ObjectFactory;

/**
 * A factory whose object writes itself once it has been built.
 *
 * @template T of AbstractModel
 *
 * @extends ObjectFactory<T>
 */
abstract class AbstractSavingFactory extends ObjectFactory
{
    /**
     * Foundry runs the hooks from the highest priority down, so writing last is what leaves a
     * state room to still change the object.
     */
    private const int WRITE = -1000;

    private bool $writes = true;

    /**
     * An object that is built and handed over, but never written.
     */
    public function unsaved(): static
    {
        $clone = clone $this;
        $clone->writes = false;

        return $clone;
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(
            static function (AbstractModel $model, array $parameters, self $factory): void {
                if ($factory->writes) {
                    $model->save();
                }
            },
            self::WRITE,
        );
    }
}
