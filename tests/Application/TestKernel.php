<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Application;

use OpenDxp\Bundle\StaticRoutesBundle\OpenDxpStaticRoutesBundle;
use OpenDxp\HttpKernel\BundleCollection\BundleCollection;
use OpenDxp\TestFoundation\Kernel\TestKernel as Foundation;
use OpenDxp\TestFoundation\TestCase;

final class TestKernel extends Foundation
{
    /**
     * The foundation registers no services of its own. It names its own namespace so the setup
     * reads the same as everywhere else, and nothing is made public because nothing is there.
     */
    protected function getServicesClass(): string
    {
        return TestCase::class;
    }

    /**
     * Static routes live in a bundle an application opts into, and StaticRouteFactory is tested
     * here, so this application opts in. A package that uses that factory does the same in its
     * own test kernel.
     */
    public function registerBundlesToCollection(BundleCollection $collection): void
    {
        $collection->addBundle(new OpenDxpStaticRoutesBundle());
    }
}
