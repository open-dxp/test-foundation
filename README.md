# OpenDXP Test Foundation

Everything a bundle or a project needs to test against OpenDXP: the test kernel, the test cases,
the application a bundle is tested in, and the commands that build and check that application.

Tests are written with Pest. Test data is created with Foundry factories.

```bash
composer require --dev open-dxp/test-foundation
```

## What your package needs

A `phpunit.xml.dist` beside your `composer.json`. Its paths are relative to the application that
runs the tests, where your package is a dependency:

```xml
<phpunit bootstrap="vendor/open-dxp/test-foundation/bootstrap.php" cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="open-dxp/toolbox-bundle">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <extensions>
        <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
    </extensions>
    <php>
        <env name="KERNEL_CLASS" value="OpenDxp\Bundle\ToolboxBundle\Tests\Application\TestKernel"/>
    </php>
</phpunit>
```

A namespace for `tests/`:

```json
{
    "require-dev": {
        "open-dxp/test-foundation": "1.x-dev"
    },
    "autoload-dev": {
        "psr-4": {
            "OpenDxp\\Bundle\\ToolboxBundle\\Tests\\": "tests/"
        }
    }
}
```

A test kernel and a `tests/Pest.php`, both described below.

## The kernel

A bundle has no application of its own. Its kernel lives in `tests/Application/TestKernel.php`,
extends the kernel of this package and registers the bundles the tests need:

```php
final class TestKernel extends \OpenDxp\TestFoundation\Kernel\TestKernel
{
    protected function getServicesClass(): string
    {
        return OpenDxpToolboxBundle::class;
    }

    public function registerBundlesToCollection(BundleCollection $collection): void
    {
        $collection->addBundle(new OpenDxpToolboxBundle());
    }
}
```

Every service in the namespace of the class `getServicesClass()` returns is made public, so tests
can fetch it from the container. `OpenDxpCoreBundle` and `OpenDxpAdminBundle` are registered
already.

A project is its own application. Its kernel lives in `tests/TestKernel.php`, extends the project's
kernel and uses the trait:

```php
final class TestKernel extends \App\Kernel
{
    use \OpenDxp\TestFoundation\Kernel\Testable;

    protected function getServicesClass(): string
    {
        return \App\Kernel::class;
    }
}
```

## Directory structure

```
tests/
    Pest.php            assigns the test cases
    Feature/            tests that boot the application
    Unit/               tests that do not boot the application
    Application/        the application under test: kernel, config, templates, controllers, services
    Factory/            factories for this package
    Story/              shared fixture setups
    TestCase/           the test cases this package adds
    Datasets/           Pest datasets
    Helpers/            helper functions, loaded by Pest, no classes
    Fixtures/           data files
```

- Tests live only in `Feature` and `Unit`. A browser test is a feature test with the `browser` group.
- Name a test file after the class or the behaviour it tests. Use one directory per subject.
- A project has no `Application/`, because the project is the application.
- `Helpers/` holds functions only. Its file names are lowercase, because a file in PascalCase
  promises a class.

## Test cases

`tests/Pest.php` assigns a test case to each directory:

```php
pest()->extend(TestCase::class)->use(Factories::class)->in('Feature/Area');
pest()->extend(BrowserTestCase::class)->use(Factories::class)->in('Feature/Browser');
```

|                   |                                            |
|-------------------|--------------------------------------------|
| `TestCase`        | boots the application and handles requests |
| `BrowserTestCase` | the same, plus a real browser              |
| `StateTestCase`   | the same, booted in a named state          |

A test case of your own goes into `tests/TestCase/` and is not `final`, because Pest extends it.

A state is a second configuration of the same application. The kernel loads
`tests/Application/config/<state>.yaml` for it:

```php
class ThemeTestCase extends StateTestCase
{
    protected static function state(): string
    {
        return 'theme';
    }
}
```

## Where test code lives

- State shared between `beforeEach()` and the tests of a file goes on `$this`.
- Behaviour that a group of directories shares goes on their test case, reached with `$this`.
- A stateless helper is a function in `tests/Helpers/`.
- Anything that builds a model and saves it is a Foundry factory.

Services come from `Container`, which the IDE and static analysis can resolve:

```php
Container::get(AreaManager::class);
Container::parameter('toolbox.test_state');
```

## Factories

OpenDXP core ships factories for its models from version 1.5, in `OpenDxp\Test\Factory`:

```php
$page = DocumentPageFactory::createOne(['key' => 'about-us']);
$pages = DocumentPageFactory::createMany(5);
$admin = UserFactory::new()->admin()->create();
```

A factory saves an object with the object's own `save()`, as its last step.

The PHP class of a data object is generated from its class definition. Put the definitions into
`tests/Fixtures/<kind>/`, with `classes`, `fieldcollections`, `objectbricks` and
`classificationstores` as kinds, and register the extension:

```xml
<extensions>
    <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
    <bootstrap class="OpenDxp\TestFoundation\PHPUnit\InstallDefinitions"/>
</extensions>
```

It installs every `*.json` file before the run. A definition creates tables, and every DDL statement
commits the transaction that isolates the tests. That is why it runs before the run and not in a
hook.

For a test that cares about the size of a file and not its content:

```php
$path = Files::create('upload.pdf', megabytes: 25);
```

## The browser

```php
Browser::visit('/en/about-us')->assertSee('About us');

Browser::playwrightActingAs(UserFactory::new()->admin()->create())
    ->visit('/admin')
    ->waitUntilVisible('div#opendxp_panel_tree_objects');
```

`visit()` calls the kernel directly, and no JavaScript runs. `playwright()` drives a real browser.
Such a test carries `->group('browser')`.

## Static analysis

PHPStan reads the container the test kernel writes. The package's `phpstan.neon` names its paths and
that container:

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
| to be developed, and everyone can install it        | `require-dev`                 |
| never, but users may want it                        | `suggest`                     |
| only for its tests, and not everyone can install it | `extra.opendxp-test.optional` |

```json
{
    "extra": {
        "opendxp-test": {
            "optional": ["open-dxp/content-crafter-bundle"]
        }
    }
}
```

## Building the application

`vendor/bin/opendxp-test` builds and checks the application. The OpenDXP testkit and the CI
workflows run exactly these commands, so a run anywhere comes to the same result.

A bundle needs an application around it:

```bash
echo '{"minimum-stability": "dev", "prefer-stable": true}' > composer.json
composer require --no-plugins --no-scripts open-dxp/test-foundation:<the constraint of the bundle>
vendor/bin/opendxp-test bundle /path/to/the/bundle
vendor/bin/opendxp-test install
```

A project is its own application:

```bash
composer install
vendor/bin/opendxp-test install
```

`DATABASE_URL` and `DATABASE_SERVER_VERSION` name the database. The PHP that runs the commands is
the PHP the application uses.

| Command                     | What it does                                                                     |
|-----------------------------|----------------------------------------------------------------------------------|
| `bundle <path>`             | requires the bundle, its `require-dev` and its optional packages, copies the application template and the tests |
| `install`                   | installs OpenDXP, the bundles and the data object classes, and warms the cache   |
| `analyse <path> [check]`    | runs lint, and phpstan, deptrac and phparkitect where the package configures them |
| `analyse <path> --baseline` | writes the PHPStan baseline beside `phpstan.neon`                                |

`analyse` lints the templates of a bundle in the test application. A project lints them in the
environment it is deployed to: `prod` or `production`, whichever it configures under
`config/packages/`.

## Running the tests

Locally with the [OpenDXP testkit](https://github.com/open-dxp/docker-testkit):

```bash
testkit test
testkit test -- --filter=Headline
testkit analyse
```

In CI with `open-dxp/workflows-collection-public`.

## Rules

- Name a test with a sentence that says what is guaranteed.
- Never wait for a fixed time. The browser waits on its own, and a `sleep` hides a race.
- Every test runs in a transaction that is rolled back. What a test writes outside the database, or
  writes with DDL, stays. Such a test cleans up after itself.
