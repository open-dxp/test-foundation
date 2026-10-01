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

## Directory structure

The `tests` directory contains only tests. Everything the tests need is in a separate directory next to them.

```
tests/
    Pest.php            assigns the test cases
    Feature/            all tests
        Area/
            HeadlineTest.php
    Unit/               tests that do not need the application
    Application/        the application under test
        TestKernel.php
        config/         one file per state
        templates/
        Controller/
        Service/
    Support/            code the tests use
        TestCase/
        Factory/
        Story/
    Fixtures/           data files such as class definitions and images
```

`Feature` contains every test that boots the application. Browser tests belong there too. A browser
test is a feature test that uses a real browser, and it is marked with the `browser` group rather
than by its directory.

`Unit` contains tests that do not boot the application. Add the directory when a package has such a test.

Name a test file after the class or the behaviour it tests, not after the type of test. Use one directory per subject inside `Feature`.

A project does not need an `Application` directory, because the project is the application. 
Its test kernel goes directly into `tests`:

```
tests/
    Pest.php
    TestKernel.php
    Feature/
        Application/
            BootTest.php
        Browser/
            BackendTest.php
```

### Test cases

`tests/Pest.php` assigns a test case to a directory:

```php
pest()->extend(TestCase::class)->use(Factories::class)->in('Feature');
pest()->extend(BrowserTestCase::class)->use(Factories::class)->in('Feature/Browser');
```

A test file that needs a different test case than its directory declares it:

```php
uses(ThemeTestCase::class);
```

|                   |                                            |
|-------------------|--------------------------------------------|
| `TestCase`        | boots the application and handles requests |
| `BrowserTestCase` | the same, plus a real browser              |
| `StateTestCase`   | the same, booted in a named state          |

A state is a second configuration of the same application. Give the state a name, and the kernel
loads `<state>.yaml` from the `config` directory next to it:

```php
final class ThemeTestCase extends StateTestCase
{
    protected static function state(): string
    {
        return 'theme';
    }
}
```

The kernel reads the state configuration from its own directory. That is why `TestKernel.php` and
`config` are both in `Application`. Use a state when the package has to work with more than one
configuration.

## Accessing services

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
DocumentPageFactory  DocumentSnippetFactory  DocumentLinkFactory  DocumentHardlinkFactory
AssetImageFactory  AssetFolderFactory  DataObjectFolderFactory
SiteFactory  UserFactory  TranslationFactory  StaticRouteFactory
```

```php
$page = DocumentPageFactory::createOne(['key' => 'about-us']);
$pages = DocumentPageFactory::createMany(5);
$admin = UserFactory::new()->admin()->create();
```

States describe what kind of object you want. Attributes set its values.

|                                                 |                                        |
|-------------------------------------------------|----------------------------------------|
| `->withParent($parent)`                            | put it below another element           |
| `->withLocale('de_CH')`                           | the language the document belongs to   |
| `->unpublished()`                               | a document or object that is not live  |
| `->unsaved()`                                   | build the object, but do not save it   |
| `->withTranslationOf($source)`                      | a language variant of another document |
| `->withController(Controller::class, 'someAction')` | what renders this document             |

A document without a controller uses the application's `opendxp.documents.default_controller`, so
most tests do not set one.

Some factories have states of their own:

|                                  |                                                                             |
|----------------------------------|-----------------------------------------------------------------------------|
| `DocumentLinkFactory`                    | `->withTarget($document)` the document it points at                                 |
| `DocumentHardlinkFactory`                | `->withSource($document)` the document it mirrors                           |
| `SiteFactory`                    | `->withRoot($page)`, `->withDomains(['www.example.test'])`, `->withSettings([...])`  |
| `UserFactory`                    | `->admin()`                                                                 |
| `TranslationFactory`             | `->withTranslations(['en' => 'Read more'])`, `->admin()`                           |
| `StaticRouteFactory`             | `->withPattern($pattern, $reverse)`, `->withController(Controller::class, 'someAction')` |

```php
$en = DocumentPageFactory::new()->withLocale('en')->create(['key' => 'en']);
$about = DocumentPageFactory::new()->withParent($en)->withLocale('en')->create(['key' => 'about-us']);
$de = DocumentPageFactory::new()->withLocale('de')->withTranslationOf($en)->create(['key' => 'de']);
```

A site creates a root document named after its domain. You can also pass your own:

```php
$site = SiteFactory::createOne(['mainDomain' => 'example.test']);
$home = DocumentPageFactory::new()->withParent($site->getRootDocument())->create(['key' => 'home']);
```

A factory saves an object with the object's own `save()` method, not through Doctrine. It saves as
the last step, so a state can still change the object before it is written.

### Your own data objects

The PHP class of a data object is generated from a class definition, so this package has no factory
for it. Put the definition next to your tests and it is installed before the test run:

```xml
<extensions>
    <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
    <bootstrap class="OpenDxp\TestFoundation\PHPUnit\InstallDefinitions">
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
$path = Files::create('upload.pdf', megabytes: 25);
```

## The browser

```php
Browser::visit('/en/about-us')->assertSee('About us');
Browser::actingAs($user)->visit('/admin');

Browser::playwrightActingAs(UserFactory::new()->admin()->create())
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

## Choosing the composer key

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
