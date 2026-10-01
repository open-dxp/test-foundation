---
name: opendxp-tests
description: How tests are written for an OpenDXP bundle or project. Read before adding, moving or changing a test.
---

# Writing tests in OpenDXP

Pest runs the tests. Foundry builds the data. `open-dxp/test-foundation` supplies the kernel,
the test cases and the factories.

## What a package needs

Four things, all versioned.

A `phpunit.xml.dist` beside the `composer.json`. Paths in it are relative to the application the
tests run in, where the package is a dependency:

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

A namespace mapped onto `tests/`, and the foundation under `require-dev`:

```json
{
    "require-dev": { "open-dxp/test-foundation": "1.x-dev" },
    "autoload-dev": { "psr-4": { "OpenDxp\\Bundle\\ToolboxBundle\\Tests\\": "tests/" } }
}
```

A `tests/TestKernel.php` and a `tests/Pest.php`.

## Directory structure

The `tests` directory contains only tests. Everything the tests need is in a separate directory
next to them.

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

`Unit` contains tests that do not boot the application. Add the directory when a package has such a
test.

Name a test file after the class or the behaviour it tests, not after the type of test. Use one
directory per subject inside `Feature`.

A project does not need an `Application` directory, because the project is the application. Its
test kernel goes directly into `tests`:

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

```bash
vendor/bin/pest
vendor/bin/pest --exclude-group=browser   # without the slow ones
```

## Naming

A method says what it does, in the words the framework around it already uses.

- A read is named after what it returns: `Container::environment()`, `Browser::playwright()`.
- A write starts with `set`, as it does in Symfony and in OpenDXP: `Container::setFactory()`,
  `Site::setCurrentSite()`.
- An action is a verb: `ClassDefinitions::install()`, `Files::create()`, `Browser::visit()`.
- When a library this foundation builds on already has a name for something, use that name.
  `Browser::actingAs()` is called that because zenstruck/browser calls it that.

Do not name a method `of()` or `from()` unless it constructs the thing it is named after.

## The kernel

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

`getServicesClass()` names a class, not a string. Every service in that class's namespace is made
public, so a test can ask the container for one, and a rename carries the test setup with it.

List only what the tests need on top of what is there. `OpenDxpCoreBundle` comes from
`Kernel::registerCoreBundlesToCollection` and pulls `OpenDxpAdminBundle` after itself. A bundle
that declares its dependencies the same way brings them along too. A lower priority loads later,
which matters when one bundle overrides another.

A project extends its own `App\Kernel` and uses the trait instead:

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

## Which test case applies where

`tests/Pest.php` binds a test case to a directory. Nothing else decides it.

```php
pest()->extend(TestCase::class)->use(Factories::class)->in('Area', 'Document');
pest()->extend(BrowserTestCase::class)->use(Factories::class)->in('Browser');
pest()->extend(ThemeTestCase::class)->use(Factories::class)->in('Theme');
```

| | |
|---|---|
| `TestCase` | a booted application and a request |
| `BrowserTestCase` | the same, plus a real browser |
| `StateTestCase` | the same, booted in a named state |

A state is a second configuration of the same application. Name it, and the kernel loads
`tests/config/<state>.yaml` on top of everything else:

```php
final class ThemeTestCase extends StateTestCase
{
    protected static function state(): string
    {
        return 'theme';
    }
}
```

That is how a package tests two configurations of itself, one directory per state. It replaces
booting a different configuration in the middle of a test.

## Accessing services

```php
Container::get(AreaManager::class);   // typed, navigable
Container::parameter('toolbox.test_state');
Container::environment();
```

`self::getContainer()` also works inside a Pest closure, but no editor and no static analysis can
follow it, so it is not used.

## Fixtures

Every object a test needs comes from a factory. The foundation ships them for what OpenDXP itself
has:

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

States say what kind of thing it is, attributes say what is in it:

| | |
|---|---|
| `->withParent($parent)` | put it below another element |
| `->withLocale('de_CH')` | the language the document belongs to |
| `->unpublished()` | a document or object that is not live |
| `->unsaved()` | built and handed over, never written |
| `->withTranslationOf($source)` | a language variant of another document |
| `->withController(Controller::class, 'someAction')` | what renders this document |

A document that names no controller falls back to the application's
`opendxp.documents.default_controller`, so most tests say nothing about it.

```php
$en = DocumentPageFactory::new()->withLocale('en')->create(['key' => 'en']);
$about = DocumentPageFactory::new()->withParent($en)->withLocale('en')->create(['key' => 'about-us']);
$de = DocumentPageFactory::new()->withLocale('de')->withTranslationOf($en)->create(['key' => 'de']);
```

A site brings a root document named after its domain, or takes the one you built:

```php
$site = SiteFactory::createOne(['mainDomain' => 'example.test']);
$home = DocumentPageFactory::new()->withParent($site->getRootDocument())->create(['key' => 'home']);
```

Every factory writes through the object's own `save()`, not through Doctrine, and it writes last,
so a state can still change the object before it goes in.

### Your own data objects

A data object's php class is generated from a class definition, so there is no factory for it here.
Keep the definition beside the tests, and the foundation installs it before the run:

```xml
<extensions>
    <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
    <bootstrap class="OpenDxp\TestFoundation\PHPUnit\InstallDefinitions">
        <parameter name="directory" value="tests/Fixtures/classes"/>
    </bootstrap>
</extensions>
```

Every `*.json` in that directory becomes a class named after the file. Then write the factory:

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

For the tests that are about a file rather than its contents:

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

`visit` and `as` use the kernel directly, which is fast and runs no javascript.
`playwrightAs` and `playwright` drive a real browser. Mark those `->group('browser')` so they can
be left out.

## Choosing the composer key

| The package needs it | Key |
|---|---|
| to run | `require` |
| to be developed, and anyone can install it | `require-dev` |
| never, but users may want it | `suggest` |
| only for its tests, and not everyone can install it | `extra.opendxp-test.optional` |

The last row exists because composer has no key for it. `require-dev` would make a package a
condition for contributing, which is wrong when it is not published, and `suggest` speaks to the
people who install the package rather than to its test application. The testkit adds those
packages to the test application when a local checkout of them exists, and the test kernel guards
with `class_exists`, so the suite boots either way.

```json
{
    "extra": {
        "opendxp-test": {
            "optional": ["open-dxp/content-crafter-bundle"]
        }
    }
}
```

## Rules

- A test name is a sentence that says what is guaranteed, not a method name.
- No fixed waits. The browser layer waits by itself; a `sleep` hides a race instead of fixing it.
- A test closure is not bound to the test case, so `self::` and `$this` do not reach the
  application. `Container` and the factories do.
- Tests are isolated by a transaction that is rolled back. Anything written outside the database
  is not, so a test that writes a file cleans up after itself.
