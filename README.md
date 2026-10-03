# OpenDXP Test Foundation

The test foundation gives an OpenDXP bundle or project everything it needs for tests:

- a test kernel
- test cases that boot the application
- factories for test data
- a browser
- the commands that build, install and check the application the tests run in

Tests are written with [Pest](https://pestphp.com). Test data is created with
[Foundry](https://github.com/zenstruck/foundry).

## Quick start for a bundle

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
        <bootstrap class="OpenDxp\TestFoundation\PHPUnit\InstallDefinitions"/>
    </extensions>
    <php>
        <env name="KERNEL_CLASS" value="Acme\BlogBundle\Tests\Application\TestKernel"/>
    </php>
</phpunit>
```

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
    protected function getServicesClass(): string
    {
        return AcmeBlogBundle::class;
    }

    public function registerBundlesToCollection(BundleCollection $collection): void
    {
        $collection->addBundle(new AcmeBlogBundle());
    }
}
```

### 4. Assign the test case

`tests/Pest.php`:

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

### 6. Run it

The [OpenDXP testkit](https://github.com/open-dxp/docker-testkit) builds the application, installs
OpenDXP and runs the tests. Your checkout stays untouched.

```bash
testkit test
```

Without the testkit, the same steps run by hand. You need PHP and an empty database:

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

CI runs exactly these commands. A test that passes with the testkit therefore passes in CI.

## Quick start for a project

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

    protected function getServicesClass(): string
    {
        return Kernel::class;
    }
}
```

`phpunit.xml.dist` and `tests/Pest.php` are the same as for a bundle. `KERNEL_CLASS` names
`App\Tests\TestKernel`. A project has no `tests/Application/` directory.

## Directory structure

```
tests/
    Pest.php            assigns the test cases
    Feature/            tests that boot the application
    Unit/               tests that do not boot the application
    Application/        the application of a bundle: kernel, config, templates, controllers
    Factory/            the factories of this package
    Story/              fixture setups that several tests share
    TestCase/           the test cases of this package
    Datasets/           Pest datasets
    Helpers/            helper functions, no classes
    Fixtures/           data files and definitions
```

- Tests live only in `Feature` and `Unit`.
- A browser test is a feature test in the group `browser`.
- Name a test file after the class or the behaviour it tests.
- Pest loads every file in `Helpers/` on its own. The file names there are lowercase, because a
  file name in PascalCase promises a class.

## Test cases

| Test case             | What it does                                          |
|-----------------------|-------------------------------------------------------|
| `TestCase`            | boots the application and handles requests            |
| `BrowserTestCase`     | the same, plus a real browser                         |
| `EnvironmentTestCase` | the same, in another configuration of the application |

Every test runs in a database transaction. The transaction is rolled back after the test. A test
that writes files or changes the schema cleans up after itself.

A test case of your own goes into `tests/TestCase/`. It is not `final`, because Pest extends it.

### Another configuration

Some behaviour only shows in another configuration, for example with the full page cache switched
on. `EnvironmentTestCase` boots the application in a Symfony environment of its own. The kernel
loads `tests/Application/config/<environment>.yaml` for it:

```php
class FullPageCacheTestCase extends EnvironmentTestCase
{
    protected static function environment(): string
    {
        return 'full_page_cache';
    }
}
```

## Services

`Container` hands out the services of the running application:

```php
Container::get(PostManager::class);
Container::parameter('acme_blog.posts_per_page');
```

Every service of the bundle is public in the test application. A service definition that only
works because Symfony removes unused services shows up as an error here. Fix it in the bundle's
own configuration.

## Test data

### Factories

OpenDXP ships factories for its own models in `OpenDxp\Test\Factory`:

```php
$page = DocumentPageFactory::createOne(['key' => 'about-us']);
$pages = DocumentPageFactory::createMany(5);
$image = AssetImageFactory::createOne();
$admin = UserFactory::new()->admin()->create();
```

A factory saves the object as its last step. `unsaved()` leaves that step out.

### Data object classes

Put the definitions of your data object classes into `tests/Fixtures/`:

```
tests/Fixtures/
    classificationstores/
    fieldcollections/
    classes/Post.json
    objectbricks/
```

The `InstallDefinitions` extension installs them once, before the first test. The installation
changes the schema, and a schema change would end the transaction of a running test. That is why
it runs before the tests and not inside them.

A factory for a class of your own extends `AbstractDataObjectFactory`:

```php
/**
 * @extends AbstractDataObjectFactory<Post>
 */
final class PostFactory extends AbstractDataObjectFactory
{
    public static function class(): string
    {
        return Post::class;
    }

    protected function defaults(): array
    {
        return [
            ...parent::defaults(),
            'title' => self::faker()->sentence(),
        ];
    }
}
```

`AbstractDocumentFactory`, `AbstractElementFactory` and `AbstractSavingFactory` are the base
classes for other models.

### Files

A test that cares about the size of a file and not about its content:

```php
$path = Files::create('upload.pdf', megabytes: 25);
```

### Visitors from another country

The test application uses a small GeoIP database. It knows one address in each of these countries:
AT, BE, CH, DE, FR, HK, HU and US.

```php
$client->setServerParameter('HTTP_CLIENT_IP', GeoIp::addressIn('CH'));
```

The database is set in the parameter `opendxp.geoip.db_file`. A bundle that needs other countries
sets this parameter to a database of its own.

## The browser

```php
Browser::visit('/en/about-us')->assertSee('About us');

Browser::playwrightActingAs(UserFactory::new()->admin()->create())
    ->visit('/admin')
    ->waitUntilVisible('div#opendxp_panel_tree_objects');
```

`visit()` sends the request to the kernel directly, and no JavaScript runs. `playwright()` drives
a real browser. A test that does this extends `BrowserTestCase` and is in the group `browser`.

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

## Commands

`vendor/bin/opendxp-test` builds and checks the application. `DATABASE_URL` and
`DATABASE_SERVER_VERSION` name the database. The PHP that runs the commands is the PHP the
application uses.

| Command                     | What it does                                                                                                         |
|-----------------------------|----------------------------------------------------------------------------------------------------------------------|
| `bundle <path>`             | requires the bundle with its `require-dev` and optional packages, and copies the application template and the tests |
| `install`                   | installs OpenDXP, the bundles and the data object classes, and warms the cache                                       |
| `analyse <path> [check]`    | runs lint, and phpstan, deptrac and phparkitect where the package configures them                                    |
| `analyse <path> --baseline` | writes the PHPStan baseline next to `phpstan.neon`                                                                   |

`analyse` lints the templates of a bundle in the test application. A project lints them in the
environment it is deployed to. That is `prod` or `production`, whichever the project configures
under `config/packages/`.

## Rules

- The name of a test is a sentence that says what is guaranteed.
- A test never waits for a fixed time. The browser waits on its own, and a `sleep` hides a race.
- State that `beforeEach()` shares with the tests of a file goes on `$this`.
- Behaviour that several directories share goes on their test case.
- A helper without state is a function in `tests/Helpers/`.
- Anything that builds a model and saves it is a factory.

## Versions

The test foundation follows semantic versioning. A major version comes when existing tests would
break otherwise. That happens with a new major version of Pest, Foundry, PHPUnit or OpenDXP, or
when the foundation changes its own API.

| Test foundation | OpenDXP | Pest | PHP           |
|-----------------|---------|------|---------------|
| 1.x             | 1.5+    | 4    | 8.3, 8.4, 8.5 |
