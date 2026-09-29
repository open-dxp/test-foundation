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

## Where a test lives

A test file is named after the **subject** it covers, never after the kind of test it is.

```
tests/
  Pest.php            which test case applies where
  TestKernel.php      which bundles boot
  config/             configuration per state, see below
  Area/
    HeadlineTest.php  everything about headlines, fast checks and browser checks alike
    AccordionTest.php
```

There are no `Unit/`, `Functional/` or `Acceptance/` directories. Whether a check needs a kernel
or a browser is a property of that check, not a filing decision.

```bash
vendor/bin/pest
vendor/bin/pest --exclude-group=browser   # without the slow ones
```

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

## Reaching the application

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
PageFactory  SnippetFactory  LinkFactory  HardlinkFactory
ImageAssetFactory  AssetFolderFactory  ObjectFolderFactory
SiteFactory  UserFactory  TranslationFactory  StaticRouteFactory
```

```php
$page = PageFactory::createOne(['key' => 'about-us']);
$pages = PageFactory::createMany(5);
$admin = UserFactory::new()->admin()->create();
```

States say what kind of thing it is, attributes say what is in it:

| | |
|---|---|
| `->childOf($parent)` | put it below another element |
| `->inLocale('de_CH')` | the language the document belongs to |
| `->unpublished()` | a document or object that is not live |
| `->unsaved()` | built and handed over, never written |
| `->translationOf($source)` | a language variant of another document |
| `->controller(Controller::class, 'someAction')` | what renders this document |

A document that names no controller falls back to the application's
`opendxp.documents.default_controller`, so most tests say nothing about it.

```php
$en = PageFactory::new()->inLocale('en')->create(['key' => 'en']);
$about = PageFactory::new()->childOf($en)->inLocale('en')->create(['key' => 'about-us']);
$de = PageFactory::new()->inLocale('de')->translationOf($en)->create(['key' => 'de']);
```

A site brings a root document named after its domain, or takes the one you built:

```php
$site = SiteFactory::createOne(['mainDomain' => 'example.test']);
$home = PageFactory::new()->childOf($site->getRootDocument())->create(['key' => 'home']);
```

Every factory writes through the object's own `save()`, not through Doctrine, and it writes last,
so a state can still change the object before it goes in.

### Your own data objects

A data object's php class is generated from a class definition, so there is no factory for it here.
Keep the definition beside the tests, and the foundation installs it before the run:

```xml
<extensions>
    <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
    <bootstrap class="OpenDxp\TestFoundation\PHPUnit\InstallClassDefinitions">
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

`visit` and `as` use the kernel directly, which is fast and runs no javascript.
`playwrightAs` and `playwright` drive a real browser. Mark those `->group('browser')` so they can
be left out.

## Where a dependency belongs

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
