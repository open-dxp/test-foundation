---
name: opendxp-tests
description: Write and review tests of an OpenDXP bundle or project. Use it before you add, move or change a test, a factory, a story or a helper of a test suite, and before you hand a branch with tests over for review.
---

# Tests

Every rule here holds for every test. Apply all of them while you write, not afterwards.
[Writing Tests][writing] and [Factories and Stories][factories] explain each rule with examples.
`vendor/open-dxp/test-foundation/README.md` explains the test cases, the browser and the commands.

## The test

- The name is a sentence that says what is guaranteed: `it('lists only the published children')`.
- The body has three blocks, separated by an empty line: prepare, act, check.
- A test has exactly one action. A second action is a second test.
- Nothing is checked before the action. The result proves the precondition.
- A test checks what the package does, not PHP and not a plain setter or getter.
- The same behaviour for several cases is one test with a dataset. A dataset changes the data,
  never the course of the test. Its keys read as cases: `'a document'`, `'an object'`.

## The checks

- Use the expectations of Pest, not the assertions of PHPUnit.
- Several checks form one chain with `->and()`.
- `->throws()` only when the whole test is the action. Otherwise `expect(fn () => ...)->toThrow()`.

## The data

- A model comes from a factory. Use the states it has, like `withParent()`, instead of raw values.
  If a state is missing, add it to the factory.
- Random values come from `self::faker()`. A random value in a state goes into a closure:
  `$this->with(fn () => [...])`. Never use `uniqid()` or `rand()`.
- A fixed world with fixed values is a story in `tests/Story/`.

## The code around the tests

- A helper is a function in the test file. Once a second file needs it, it moves to
  `tests/Helpers/<subject>.php`.
- A helper does real work. It never wraps a single getter, and it never contains an expectation.
- A check that repeats is an expectation in `tests/Expectations/`.
- A shape with named keys is a `final readonly` class in `tests/Value/`, not an array.
- A test double is a named class in `tests/Application/`, named after the slot it fills, like
  `MockTranslator`. It is never an anonymous class.

## Isolation

- Every test runs in a transaction that is rolled back.
- A test restores any static or global state it changes, in `afterEach()` or `->after()`.
- `config/test.yaml` holds only what every test needs. A test that needs more runs in a test case
  that extends `EnvironmentTestCase`, with a configuration that imports `test.yaml`.
- A test against an external service skips itself when the variable of the service is missing.
- `only()`, `todo()`, `dd()` and `ray()` never reach the repository.

## The lines

- A chain that builds or checks puts every call on a line of its own, from two calls on.
  A single `expect($count)->toBe(2)` stays on one line.
- An array with two or more entries puts every entry on a line of its own.
- Arguments go one per line once one of them is not a plain value or the line exceeds 120
  characters.
- A boolean or `null` whose meaning the call does not show gets its name:
  `getChildren(includingUnpublished: true)`.
- A comment explains a reason the code cannot show. It never repeats the code.

## Before you hand it over

Read every changed test, factory, story and helper against each rule above, line by line. Run the
tests and the static checks with the OpenDXP testkit, not on the host.

[writing]: https://docs.opendxp.io/docs/core-framework/next/Development_Tools_and_Details/Testing/Writing_Tests/
[factories]: https://docs.opendxp.io/docs/core-framework/next/Development_Tools_and_Details/Testing/Factories_and_Stories/
