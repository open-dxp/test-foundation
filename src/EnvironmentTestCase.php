<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use OpenDxp\Cache\RuntimeCache;
use OpenDxp\SystemSettingsConfig;
use OpenDxp\Tool;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class EnvironmentTestCase extends TestCase
{
    abstract protected static function environment(): string;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::forgetSystemSettings();
    }

    public static function tearDownAfterClass(): void
    {
        self::forgetSystemSettings();
        parent::tearDownAfterClass();
    }

    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel(['environment' => static::environment()] + $options);
    }

    /**
     * Core keeps the system settings and the languages of the first kernel a process boots in statics. A kernel of
     * another environment would read them, and the tests after this one would read the settings of this one.
     */
    private static function forgetSystemSettings(): void
    {
        (static function (): void {
            self::$locationAwareConfigRepository = null;
        })->bindTo(null, SystemSettingsConfig::class)();
        (static function (): void {
            self::$validLanguages = [];
            self::$requiredLanguages = [];
        })->bindTo(null, Tool::class)();

        if (RuntimeCache::isRegistered('opendxp_system_settings_config')) {
            RuntimeCache::getInstance()->offsetUnset('opendxp_system_settings_config');
        }
    }
}
