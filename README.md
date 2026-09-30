# OpenDXP Test Foundation

This package contains everything a bundle or a project needs to run tests against OpenDXP: the test
kernel, the test cases, factories for OpenDXP's models, and the application a bundle is tested in.

Tests are written with Pest. Test data is created with Foundry factories.

```bash
composer require --dev open-dxp/test-foundation
```

## What your package needs

Four files, checked in with your code.

A `phpunit.xml.dist` next to your `composer.json`. The paths in it are relative to the application
that runs the tests, and in that application your package is a dependency:

```xml
<phpunit bootstrap="vendor/open-dxp/test-foundation/bootstrap.php" cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="open-dxp/toolbox-bundle">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <extensions>
        <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
    </extensions>
    <php>
        <env name="KERNEL_CLASS" value="OpenDxp\Bundle\ToolboxBundle\Tests\TestKernel"/>
    </php>
</phpunit>
```

A namespace for `tests/`:

```json
{
    "require-dev": { "open-dxp/test-foundation": "1.x-dev" },
    "autoload-dev": { "psr-4": { "OpenDxp\\Bundle\\ToolboxBundle\\Tests\\": "tests/" } }
}
```

And `tests/TestKernel.php` and `tests/Pest.php`, both described below.

## The kernel

A bundle has no application of its own. Extend the kernel from this package and register the
bundles your tests need:

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

`getServicesClass()` returns a class name. Every service in the namespace of that class is made
public, so your tests can fetch it from the container. Returning the class instead of a string
means your IDE renames it together with the class.

Register only what is missing. `OpenDxpCoreBundle` is registered by
`Kernel::registerCoreBundlesToCollection`, and it registers `OpenDxpAdminBundle` as well.

A project already has a kernel. Use the trait instead:

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

## Where a test lives

Name a test file after the thing it tests, not after the kind of test it is.

```
tests/
  Pest.php            assigns the test cases
  TestKernel.php      registers the bundles
  config/             configuration per state
  Area/
    HeadlineTest.php  all tests for headlines, with or without a browser
```

`tests/Pest.php` assigns a test case to a directory:

```php
pest()->extend(TestCase::class)->use(Factories::class)->in('Area', 'Document');
pest()->extend(BrowserTestCase::class)->use(Factories::class)->in('Browser');
pest()->extend(ThemeTestCase::class)->use(Factories::class)->in('Theme');
```

|                   |                                            |
|-------------------|--------------------------------------------|
| `TestCase`        | boots the application and handles requests |
| `BrowserTestCase` | the same, plus a real browser              |
| `StateTestCase`   | the same, booted in a named state          |

A state is a second configuration of the same application.
Give it a name, and the kernel loads `tests/config/<state>.yaml` after all other configuration:

```php
final class ThemeTestCase extends StateTestCase
{
    protected static function state(): string
    {
        return 'theme';
    }
}
```

Use this when your package has to work with two different configurations. One directory per state.

## Reaching the application

```php
Container::get(AreaManager::class);
Container::parameter('toolbox.test_state');
Container::environment();
```

`self::getContainer()` works inside a Pest closure as well, but neither the IDE nor static analysis
can resolve it. Use `Container`.

## Fixtures

Create every object a test needs with a factory:

```
PageFactory  SnippetFactory  LinkFactory  HardlinkFactory
ImageAssetFactory  AssetFolderFactory  ObjectFolderFactory
SiteFactory  UserFactory  TranslationFactory  StaticRouteFactory
```

```php
$page = PageFactory::createOne(['key' => 'about-us']);
$pages = PageFactory::createMany(5);
$admin = UserFactory::new()->admin()->create();
```

States describe what kind of object you want. Attributes set its values.

|                                                 |                                        |
|-------------------------------------------------|----------------------------------------|
| `->childOf($parent)`                            | put it below another element           |
| `->inLocale('de_CH')`                           | the language the document belongs to   |
| `->unpublished()`                               | a document or object that is not live  |
| `->unsaved()`                                   | build the object, but do not save it   |
| `->translationOf($source)`                      | a language variant of another document |
| `->controller(Controller::class, 'someAction')` | what renders this document             |

A document without a controller uses the application's `opendxp.documents.default_controller`, so
most tests do not set one.

Some factories have states of their own:

|                                  |                                                                             |
|----------------------------------|-----------------------------------------------------------------------------|
| `LinkFactory`, `HardlinkFactory` | `->to($document)` the document it points at                                 |
| `SiteFactory`                    | `->rootedAt($page)`, `->alsoAt(['www.example.test'])`, `->settings([...])`  |
| `UserFactory`                    | `->admin()`                                                                 |
| `TranslationFactory`             | `->saying(['en' => 'Read more'])`, `->forAdmin()`                           |
| `StaticRouteFactory`             | `->at($pattern, $reverse)`, `->controller(Controller::class, 'someAction')` |

```php
$en = PageFactory::new()->inLocale('en')->create(['key' => 'en']);
$about = PageFactory::new()->childOf($en)->inLocale('en')->create(['key' => 'about-us']);
$de = PageFactory::new()->inLocale('de')->translationOf($en)->create(['key' => 'de']);
```

A site creates a root document named after its domain. You can also pass your own:

```php
$site = SiteFactory::createOne(['mainDomain' => 'example.test']);
$home = PageFactory::new()->childOf($site->getRootDocument())->create(['key' => 'home']);
```

A factory saves an object with the object's own `save()` method, not through Doctrine. It saves as
the last step, so a state can still change the object before it is written.

### Your own data objects

The PHP class of a data object is generated from a class definition, so this package has no factory
for it. Put the definition next to your tests and it is installed before the test run:

```xml
<extensions>
    <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
    <bootstrap class="OpenDxp\TestFoundation\PHPUnit\InstallClassDefinitions">
        <parameter name="directory" value="tests/Fixtures/classes"/>
    </bootstrap>
</extensions>
```

Every `*.json` file in that directory becomes a class named after the file. Installing a class
definition creates tables. MySQL commits the running transaction on every DDL statement, and that
transaction is what isolates the tests. This is why the installation runs before the test run and
not in a `beforeAll`.

Then write the factory:

```php
final class ProductFactory extends AbstractDataObjectFactory
{
    public static function class(): string
    {
        return Product::class;
    }
}
```

### Files

For tests that care about the size of a file and not about its contents:

```php
$path = Files::sized('upload.pdf', megabytes: 25);
```

## The browser

```php
Browser::visit('/en/about-us')->assertSee('About us');
Browser::as($user)->visit('/admin');

Browser::playwrightAs(UserFactory::new()->admin()->create())
    ->visit('/admin')
    ->waitUntilVisible('div#opendxp_panel_tree_objects');
```

`visit` and `as` call the kernel directly. That is fast, but no javascript runs. `playwright` and
`playwrightAs` drive a real browser. Mark those tests so they can be excluded from a run:

```php
it('serves a backend an editor can work in', function () {
    // ...
})->group('browser');
```

## Static analysis

The test kernel writes its container under its own name. Point `phpstan.neon` at that file when you
use the Symfony extension:

```neon
parameters:
    symfony:
        containerXmlPath: %currentWorkingDirectory%/var/cache/test/TestContainerDebug.xml
```

## Where a dependency belongs

| The package needs it                                | Key                           |
|-----------------------------------------------------|-------------------------------|
| to run                                              | `require`                     |
| to be developed, and everyone can install it        | `require-dev`                 |
| never, but users may want it                        | `suggest`                     |
| only for its tests, and not everyone can install it | `extra.opendxp-test.optional` |

The last row exists because composer has no key for this case. `require-dev` would force everyone
who works on the package to install it, which does not work for a package that is not published.
`suggest` addresses the users of your package, not its test application.

```json
{
    "extra": {
        "opendxp-test": {
            "optional": ["open-dxp/toolbox-bundle"]
        }
    }
}
```

The testkit and the CI workflows read this key and install what it lists.

## Running the tests

Locally with the OpenDXP testkit. It builds the application in a workspace of its own:

```bash
ddev test toolbox-bundle
ddev test toolbox-bundle -- --filter=Headline
```

In CI with `open-dxp/workflows-collection-public`, which builds the same application.

## Rules

- Name a test with a sentence that says what is guaranteed, not with a method name.
- Never wait for a fixed time. The browser layer waits on its own, and a `sleep` hides a race instead of fixing it.
- A test closure is not bound to the test case, so `self::` and `$this` do not reach the application. Use `Container` and the factories.
- Tests run in a transaction that is rolled back afterwards. Everything written outside the database stays, so a test that writes a file has to delete it again.
