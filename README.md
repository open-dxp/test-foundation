# OpenDXP Test Foundation

The test foundation gives an OpenDXP bundle or project what it needs to run tests:

- a test kernel
- test cases that boot the application and run every test in a rolled back transaction
- a browser
- the commands that build, install and check the application the tests run in

Tests are written with [Pest](https://pestphp.com). Test data is created with the
[Foundry](https://github.com/zenstruck/foundry) factories that OpenDXP ships in `OpenDxp\Test`. The
transaction comes from [DAMADoctrineTestBundle](https://github.com/dmaicher/doctrine-test-bundle).

[Testing][testing] in the documentation of OpenDXP describes how you write tests. It also describes
the test API of OpenDXP with its factories and expectations.

[testing]: https://github.com/open-dxp/opendxp/blob/1.x/doc/19_Development_Tools_and_Details/29_Testing/README.md
[writing-tests]: https://github.com/open-dxp/opendxp/blob/1.x/doc/19_Development_Tools_and_Details/29_Testing/03_Writing_Tests.md
[factories]: https://github.com/open-dxp/opendxp/blob/1.x/doc/19_Development_Tools_and_Details/29_Testing/05_Factories_and_Stories.md
[testkit]: https://github.com/open-dxp/docker-testkit
[workflows]: https://github.com/open-dxp/workflows-collection-public

## Set up a bundle

These steps take a bundle from no tests to its first green test. The examples use a bundle called
`Acme\BlogBundle`.

### 1. Require the package

```bash
composer require --dev open-dxp/test-foundation
```

Give the tests a namespace in `composer.json`:

```json
{
    "autoload-dev": {
        "psr-4": {
            "Acme\\BlogBundle\\Tests\\": "tests/"
        }
    }
}
```

### 2. Add `phpunit.xml.dist`

The paths in this file are relative to the application that runs the tests. Your bundle is a
dependency there.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/open-dxp/test-foundation/bootstrap.php" cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="acme/blog-bundle">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <extensions>
        <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
        <bootstrap class="Zenstruck\Foundry\PHPUnit\FoundryExtension"/>
        <bootstrap class="OpenDxp\TestFoundation\PHPUnit\InstallDefinitions"/>
    </extensions>
    <php>
        <env name="KERNEL_CLASS" value="Acme\BlogBundle\Tests\Application\TestKernel"/>
    </php>
</phpunit>
```

The extension of DAMA rolls back the transaction of every test. The extension of Foundry starts
Foundry for every test. Without it, no factory works. `InstallDefinitions` installs the data object
classes of the package, as [Data object classes](#data-object-classes) describes.

Add `<directory>tests/Unit</directory>` once the bundle has unit tests. PHPUnit stops when a
directory it names does not exist.

### 3. Add the test kernel

A bundle has no application of its own. The foundation builds one around it, and
`tests/Application/TestKernel.php` registers the bundle in it:

```php
<?php

declare(strict_types=1);

namespace Acme\BlogBundle\Tests\Application;

use Acme\BlogBundle\AcmeBlogBundle;
use OpenDxp\HttpKernel\BundleCollection\BundleCollection;
use OpenDxp\TestFoundation\Kernel\TestKernel as BaseTestKernel;

final class TestKernel extends BaseTestKernel
{
    public function registerBundlesToCollection(BundleCollection $collection): void
    {
        $collection->addBundle(new AcmeBlogBundle());
    }
}
```

### 4. Assign the test case

`tests/Pest.php` assigns the test case:

```php
<?php

declare(strict_types=1);

use OpenDxp\TestFoundation\TestCase;

pest()->extend(TestCase::class)->in('Feature');
```

### 5. Write the first test

`tests/Feature/Application/BootTest.php`:

```php
<?php

declare(strict_types=1);

use Acme\BlogBundle\Manager\PostManager;
use OpenDxp\TestFoundation\Container;

it('boots with the bundle and its services', function () {
    expect(Container::get(PostManager::class))->toBeInstanceOf(PostManager::class);
});
```

## Set up a project

A project is its own application. Its kernel lives in `tests/TestKernel.php`. It extends the
project's kernel and uses the `Testable` trait:

```php
<?php

declare(strict_types=1);

namespace App\Tests;

use App\Kernel;
use OpenDxp\TestFoundation\Kernel\Testable;

final class TestKernel extends Kernel
{
    use Testable;
}
```

`phpunit.xml.dist` and `tests/Pest.php` are the same as for a bundle. `KERNEL_CLASS` names
`App\Tests\TestKernel`. A project has no `tests/Application/` directory.

## Run the tests

### With the testkit

The [OpenDXP testkit][testkit] builds the application, installs OpenDXP and runs the tests of a
bundle or a project. Your checkout stays untouched:

```bash
testkit test
```

### Without the testkit

You need PHP and an empty database:

```bash
mysql -e "CREATE DATABASE blog_tests"
mkdir /tmp/blog-tests && cd /tmp/blog-tests
echo '{"minimum-stability": "dev", "prefer-stable": true}' > composer.json
composer require --no-plugins --no-scripts open-dxp/test-foundation
export DATABASE_URL=mysql://root:root@127.0.0.1:3306/blog_tests
vendor/bin/opendxp-test bundle /path/to/blog-bundle
vendor/bin/opendxp-test install
vendor/bin/pest
```

### In CI

The [reusable workflows][workflows] run exactly these commands. The testkit runs them as well. A
test that passes with the testkit therefore also passes in CI.

## Directory structure

```
tests/
    Feature/          tests that boot the application
    Unit/             tests that boot nothing
    Application/      a bundle only: the test kernel and its config/
    TestKernel.php    a project only: the test kernel
    config/           a project only: the configuration beside the kernel
    Fixtures/         the definitions of the data object classes
```

[Writing Tests][writing-tests] describes the rest of the layout and where each piece of test code
goes.

## Configuration of the test application

The test kernel reads its own configuration from the directory `config/` beside its class file.
For a bundle that is `tests/Application/config/`, and for a project `tests/config/`. The kernel
loads the configuration in this order:

1. `config/security.yaml` beside the kernel
2. the configuration of the application
3. the configuration of the foundation
4. `config/<environment>.yaml` beside the kernel, which is `config/test.yaml` by default

Symfony accepts new firewalls only from the first file that names any. A bundle that needs a
firewall of its own, like a frontend login, therefore lists all firewalls in `config/security.yaml`.

The configuration of the foundation adapts OpenDXP to tests:

- The messenger transports of OpenDXP are in memory.
- Passwords are hashed with the lowest cost.
- OpenDXP sends every mail to `debug@test-foundation.test`.
- OpenDXP writes its settings into the settings store, not into files.
- The CSRF protection of the admin is switched off.

## Test cases

| Test case             | What it does                                          |
|-----------------------|-------------------------------------------------------|
| `TestCase`            | boots the application and handles requests            |
| `BrowserTestCase`     | the same, plus a real browser                         |
| `EnvironmentTestCase` | the same, in another configuration of the application |

Every test runs in a database transaction. The transaction is rolled back after the test.

### Static state

Before every test, the test case sets the timezone from the system settings. It also sets up
OpenDXP the way the frontend sees it:

- Unpublished documents and data objects are hidden.
- Data objects inherit the values of their parents.
- Localized fields fall back to other languages.

After every test, the test case resets what OpenDXP keeps in statics: the runtime cache, the system
configuration, the admin mode, the current site and the current static route. A test restores any
other static state it changes.

### Tests without rollback

A change to the schema commits the transaction. InnoDB also writes a full text index only on
commit. A test that needs either of these runs under a test case that skips the rollback:

```php
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use OpenDxp\TestFoundation\TestCase;

#[SkipDatabaseRollback]
abstract class SchemaTestCase extends TestCase
{
}
```

> [!IMPORTANT]
> Without the rollback, nothing cleans up for you. Such a test removes what it wrote, in
> `afterEach()` or in the `tearDown()` of its test case. Anything it leaves behind is still there for
> the next run.

### Another configuration

Some behaviour only shows in another configuration, for example with the full page cache switched
on. `EnvironmentTestCase` boots the application in a Symfony environment of its own:

```php
use OpenDxp\TestFoundation\EnvironmentTestCase;

abstract class FullPageCacheTestCase extends EnvironmentTestCase
{
    protected static function environment(): string
    {
        return 'full_page_cache';
    }
}
```

The kernel then loads `config/full_page_cache.yaml` instead of `config/test.yaml`. The file
imports `test.yaml`, so the environment adds to the test configuration:

```yaml
imports:
    - { resource: test.yaml }

opendxp:
    full_page_cache:
        enabled: true
```

## Services

`Container` hands out the services of the running application:

```php
Container::get(PostManager::class);
Container::parameter('acme_blog.posts_per_page');
```

Every service whose class the package under test or its tests define is public in the test
application. Symfony would otherwise remove a private service that nothing injects, and a test could
not fetch it. The services of other packages stay as they are.

A service definition that only works because Symfony removes it shows up as an error here. Fix it
in the package's own configuration.

## Test data

### Factories

OpenDXP ships factories for its own models in `OpenDxp\Test\Factory`, with base classes for the
factories of your package. [Factories and Stories][factories] explains them.

### Data object classes

Put the definitions of your data object classes into `tests/Fixtures/`. The file name is the name of
the definition:

```
tests/Fixtures/
    classificationstores/
    fieldcollections/
    classes/Post.json
    objectbricks/
```

The `InstallDefinitions` extension installs them once, before the first test. Classes refer to
classification stores and field collections, and object bricks refer to classes. The extension
therefore installs classification stores first, then field collections, classes and object bricks.

The installation changes the schema. A schema change inside a test would end the transaction of
that test. That is why the installation runs before the tests.

The extension reads `tests/Fixtures` unless you name another directory:

```xml
<bootstrap class="OpenDxp\TestFoundation\PHPUnit\InstallDefinitions">
    <parameter name="directory" value="tests/Definitions"/>
</bootstrap>
```

A project keeps its classes in `var/classes`, and `opendxp-test install` creates them from there.

### Install something else before the first test

A PHPUnit extension of your own installs anything else that changes the schema.
`BootKernelTrait` boots the kernel that `KERNEL_CLASS` names:

```php
use OpenDxp\TestFoundation\PHPUnit\BootKernelTrait;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

final class InstallSearchIndex implements Extension
{
    use BootKernelTrait;

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $kernel = self::bootKernel();

        // install the index

        $kernel->shutdown();
    }
}
```

Register the extension in `phpunit.xml.dist` after the extensions of DAMA and Foundry.

### Files

A test that cares about the size of a file and not about its content:

```php
$path = Files::create('upload.pdf', megabytes: 25);
```

### Visitors from another country

The test application uses a small GeoIP database. It knows one address in each of these countries:
AT, BE, CH, DE, FR, HK, HU and US.

```php
$browser = Browser::start();
$browser
    ->client()
    ->setServerParameter('HTTP_CLIENT_IP', GeoIp::addressIn('CH'));
$browser->visit('/');
```

The database is set in the parameter `opendxp.geoip.db_file`. A bundle that needs other countries
sets this parameter to a database of its own.

### Repeat a failing run

Random values can make a test fail only once in a while. Foundry prints the seed of its random
values after every run:

```
Faker seed used: 1234
```

Set the environment variable `FOUNDRY_FAKER_SEED` to that number, for example in the `<php>` section
of `phpunit.xml.dist`. Every factory then produces the same values again.

## External services

A test against an external service reads its address from an environment variable:

| Variable                   | Example              |
|----------------------------|----------------------|
| `OPENDXP_TEST_REDIS_DSN`   | `redis://redis:6379` |
| `OPENDXP_OPEN_SEARCH_HOST` | `opensearch:9200`    |

The [testkit][testkit] sets a variable when its configuration lists the service under `services`.
The [Pest workflow][workflows] sets it with `enable_redis_service` or `enable_opensearch_service`. A
test skips itself when its variable is missing, as [Writing Tests][writing-tests] shows.

## The browser

```php
Browser::visit('/en/about-us')->assertSee('About us');

Browser::playwrightActingAs(UserFactory::new()->admin()->create())
    ->visit('/admin')
    ->waitUntilVisible('div#opendxp_panel_tree_objects');
```

`visit()` sends the request to the kernel directly, and no JavaScript runs. `playwright()` drives
a real browser. A test that does this extends `BrowserTestCase` and is in the group `browser`.

A browser test never waits for a fixed time. The browser waits on its own, and a `sleep` hides a
race.

### A page as a person uses it

A frontend test uses `Page`. It finds a field by its label, a region by its ARIA name, and a button
or a link by its text, so the test needs no selector:

```php
$page = Page::open('/en/contact')
    ->fillIn('Name', 'Ada')
    ->select('Topic', 'Support')
    ->press('Send');

expect($page->notifications())->toBe(['Thank you']);
```

`notifications()` reads every element with `role="alert"` or `role="status"`. `description()` reads
the text a field names with `aria-describedby`, like a help text. A bundle builds the page objects of
its own pages on top of `Page`. `Page::inBrowser()` opens the page in a real browser.

## The logged in user

A test that calls a service directly, without a request, names the logged in backend user with
`Admin`:

```php
Admin::actingAs(UserFactory::new()->admin()->create());
```

A service that asks who is logged in is then handed this user.

## Static analysis

PHPStan reads the container the test kernel writes. The `phpstan.neon` of the package lists the
paths to analyse and points at that container:

```neon
parameters:
    paths:
        - src
    symfony:
        containerXmlPath: %currentWorkingDirectory%/var/cache/test/TestContainerDebug.xml
```

## Composer keys

| The package needs it                                | Key                           |
|-----------------------------------------------------|-------------------------------|
| to run                                              | `require`                     |
| for development, and everyone can install it        | `require-dev`                 |
| never, but users may want it                        | `suggest`                     |
| only for its tests, and not everyone can install it | `extra.opendxp-test.optional` |

`extra.opendxp-test.optional` maps each package to a constraint, like `require-dev`:

```json
"extra": {
    "opendxp-test": {
        "optional": {
            "open-dxp/formbuilder-bundle": "^1.0"
        }
    }
}
```

## Commands

`vendor/bin/opendxp-test` builds and checks the application. `DATABASE_URL` and
`DATABASE_SERVER_VERSION` name the database. The PHP that runs the commands is the PHP the
application uses.

| Command                                | What it does                                                                                                        |
|----------------------------------------|---------------------------------------------------------------------------------------------------------------------|
| `bundle <path>`                        | requires the bundle with its `require-dev` and optional packages, and copies the application template and the tests |
| `bundle <path> --opendxp <constraint>` | the same, with this version of `open-dxp/opendxp`                                                                   |
| `install`                              | installs OpenDXP, the bundles and the data object classes, and warms the cache                                      |
| `analyse <path> [check]`               | runs lint, and phpstan, deptrac and phparkitect where the package configures them                                   |
| `analyse <path> --baseline`            | writes the PHPStan baseline next to `phpstan.neon`                                                                  |

Lint checks the container, the YAML configuration and the Twig templates. It lints the templates of
a bundle in the test application. A project lints them in the environment it is deployed to. That
is `prod` or `production`, whichever the project configures under `config/packages/`.

## Versions

The major version follows OpenDXP. Test foundation 1.x supports OpenDXP 1.x, and 2.x will support
OpenDXP 2.x.

The test foundation follows semantic versioning. A major version comes when existing tests would
break otherwise. That happens with a new major version of Pest, Foundry, PHPUnit or OpenDXP, or
when the foundation changes its own API.

| Test foundation | OpenDXP | PHP           | Pest                              |
|-----------------|---------|---------------|-----------------------------------|
| 1.x             | 1.5+    | 8.3, 8.4, 8.5 | 4 on PHP 8.3, 5 on PHP 8.4 and up |

Composer picks the Pest version that fits the PHP version and the other dependencies of the
package. A package whose development dependencies do not allow Pest 5 stays on Pest 4. A test
therefore uses only what Pest 4 and Pest 5 both offer.
