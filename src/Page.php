<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use LogicException;
use OpenDxp\TestFoundation\Browser as Browsers;
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Browser;
use Zenstruck\Browser\KernelBrowser;
use Zenstruck\Browser\PlaywrightBrowser;

/**
 * A page used the way a person uses it. A field is found by its label, a region by its ARIA name, a button and a link
 * by their text. A test never needs a selector.
 */
final readonly class Page
{
    private function __construct(private Browser $browser)
    {
    }

    /**
     * The page comes from the kernel, without JavaScript.
     */
    public static function open(string $path): self
    {
        return new self(
            Browsers::visit($path)->assertSuccessful(),
        );
    }

    /**
     * The page runs in a real browser, with its scripts. The test extends the browser test case.
     */
    public static function inBrowser(string $path): self
    {
        return new self(
            Browsers::playwright()->visit($path),
        );
    }

    public function click(string $link): self
    {
        return $this->follow($this->link($this->crawler(), $link));
    }

    /**
     * Follows the link in the region, like a link that two menus both show.
     */
    public function clickWithin(string $region, string $link): self
    {
        return $this->follow($this->link($this->region($region), $link));
    }

    public function press(string $button): self
    {
        $buttons = $this->crawler()->filterXPath(sprintf(
            '//button[normalize-space() = "%1$s" or @aria-label = "%1$s"]'
            . ' | //input[@type = "submit" and @value = "%1$s"]',
            $button,
        ));

        if ($buttons->count() === 0) {
            throw new LogicException(sprintf(
                'There is no button "%s". The buttons are: %s',
                $button,
                implode(', ', $this->buttons()),
            ));
        }

        // Mink finds a button by its text, but not by its aria-label.
        $locator = $buttons->attr('aria-label') === $button ? sprintf('[aria-label="%s"]', $button) : $button;

        $this->succeeded($this->browser->click($locator));

        return $this;
    }

    public function fillIn(string $label, string|int $value): self
    {
        return $this->fillField($this->findField('input|textarea', $label, $this->crawler()), $value);
    }

    /**
     * Fills in the field in the region, where a form uses the label twice.
     */
    public function fillInWithin(string $region, string $label, string|int $value): self
    {
        return $this->fillField($this->findField('input|textarea', $label, $this->region($region)), $value);
    }

    /**
     * Selects the one option whose text contains this text.
     */
    public function select(string $label, string $option): self
    {
        return $this->selectOptions($this->findField('select', $label, $this->crawler()), $label, $option);
    }

    public function selectWithin(string $region, string $label, string $option): self
    {
        return $this->selectOptions($this->findField('select', $label, $this->region($region)), $label, $option);
    }

    /**
     * Selects several options of a list that takes more than one.
     */
    public function selectEach(string $label, string ...$options): self
    {
        return $this->selectOptions($this->findField('select', $label, $this->crawler()), $label, ...$options);
    }

    /**
     * Checks every choice of a group of checkboxes that carries one of these labels.
     */
    public function checkEach(string ...$labels): self
    {
        foreach ($labels as $label) {
            $this->check($label);
        }

        return $this;
    }

    /**
     * Fills in several inputs of a field made of several, by the name of each part.
     *
     * @param array<string, string|int> $values
     */
    public function fillInParts(string $label, array $values): self
    {
        foreach ($values as $part => $value) {
            $this->fillInPart($label, $part, $value);
        }

        return $this;
    }

    /**
     * Selects in several lists of a field made of several, by the name of each part.
     *
     * @param array<string, string> $options
     */
    public function selectParts(string $label, array $options): self
    {
        foreach ($options as $part => $option) {
            $this->selectPart($label, $part, $option);
        }

        return $this;
    }

    /**
     * Fills in one input of a field made of several, like the year of a date, by the name of the part.
     */
    public function fillInPart(string $label, string $part, string|int $value): self
    {
        return $this->fillField($this->part('input', $label, $part), $value);
    }

    /**
     * Selects in one list of a field made of several, like the month of a date, by the name of the part.
     */
    public function selectPart(string $label, string $part, string $option): self
    {
        return $this->selectOptions($this->part('select', $label, $part), $label, $option);
    }

    public function check(string $label): self
    {
        $field = $this->findField('input', $label, $this->crawler());
        $name = (string) $field->attr('name');

        if ($field->attr('type') === 'radio') {
            $this->browser->selectFieldOption($name, (string) $field->attr('value'));
        } else {
            // The checkboxes of a group share their name, only the id tells them apart.
            $this->browser->checkField((string) ($field->attr('id') ?? $name));
        }

        return $this;
    }

    /**
     * Checks a choice in the region, where a form uses the label of the choice twice.
     */
    public function checkWithin(string $region, string $label): self
    {
        $field = $this->findField('input', $label, $this->region($region));

        if ($field->attr('type') === 'radio') {
            $this->browser->selectFieldOption((string) $field->attr('name'), (string) $field->attr('value'));
        } else {
            $this->browser->checkField((string) ($field->attr('id') ?? $field->attr('name')));
        }

        return $this;
    }

    /**
     * Checks several choices in the region, where a form uses their labels twice.
     */
    public function checkEachWithin(string $region, string ...$labels): self
    {
        foreach ($labels as $label) {
            $this->checkWithin($region, $label);
        }

        return $this;
    }

    /**
     * Presses a key in the browser, like Tab to leave a field, which a script may react to.
     */
    public function pressKey(string $key): self
    {
        $this->playwright()->keyboard()->press($key);

        return $this;
    }

    /**
     * Whether the browser shows the field a label names. Only a real browser knows what its styles hide.
     */
    public function isVisible(string $label): bool
    {
        return $this->located($this->field($label))->isVisible();
    }

    /**
     * Checks the choice whose label wraps it, in a group of choices the region names.
     */
    public function pick(string $region, string $choice): self
    {
        $input = $this->region($region)
            ->filterXPath(sprintf('descendant::label[normalize-space() = "%s"]//input', $choice));

        $this->browser->selectFieldOption((string) $input->attr('name'), (string) $input->attr('value'));

        return $this;
    }

    public function reload(): self
    {
        $this->succeeded($this->browser->visit((string) $this->crawler()->getUri()));

        return $this;
    }

    /**
     * The messages the page shows after the last step, like "Item added".
     *
     * @return list<string>
     */
    public function notifications(): array
    {
        return $this->crawler()
            ->filter('[role="alert"], [role="status"]')
            ->each(static fn (Crawler $message) => self::read($message));
    }

    public function shows(string $text): bool
    {
        return str_contains(self::read($this->crawler()), $text);
    }

    /**
     * How often the page shows the text, like a help text that must not appear twice.
     */
    public function occurrences(string $text): int
    {
        return substr_count(self::read($this->crawler()), $text);
    }

    /**
     * @return list<string> the text of every button on the page
     */
    public function buttons(): array
    {
        return $this->crawler()
            ->filter('button, input[type="submit"]')
            ->each(static fn (Crawler $button) => self::read($button) ?: (string) $button->attr('value'));
    }

    public function hasLink(string $link): bool
    {
        return $this->links($this->crawler(), $link)->count() > 0;
    }

    /**
     * @return list<string> the text of every label on the page
     */
    public function labels(): array
    {
        return $this->crawler()
            ->filter('label')
            ->each(static fn (Crawler $label) => self::read($label));
    }

    public function value(string $label): string
    {
        $field = $this->findField('input|textarea', $label, $this->crawler());

        // A browser keeps what a person typed in the element, not in its attribute.
        if ($this->browser instanceof PlaywrightBrowser) {
            return $this->located($field)->inputValue();
        }

        return $field->nodeName() === 'textarea' ? $field->text() : (string) $field->attr('value');
    }

    public function isChecked(string $label): bool
    {
        $field = $this->findField('input', $label, $this->crawler());

        if ($this->browser instanceof PlaywrightBrowser) {
            return $this->located($field)->isChecked();
        }

        return $field->attr('checked') !== null;
    }

    public function isRequired(string $label): bool
    {
        return $this->field($label)->attr('required') !== null;
    }

    public function isDisabled(string $label): bool
    {
        return $this->field($label)->attr('disabled') !== null;
    }

    /**
     * @return list<string> the text of every option of a list, in the order the list shows them
     */
    public function options(string $label): array
    {
        return $this->findField('select', $label, $this->crawler())
            ->filter('option')
            ->each(static fn (Crawler $option) => self::read($option));
    }

    /**
     * @return list<string> the text of every option that is selected
     */
    public function selected(string $label): array
    {
        $field = $this->findField('select', $label, $this->crawler());

        // A browser keeps the options a person selected in the element, not in their attributes.
        if ($this->browser instanceof PlaywrightBrowser) {
            return $this->located($field)->evaluate(
                'list => Array.from(list.selectedOptions, option => option.text.trim())',
            );
        }

        return $field
            ->filter('option[selected]')
            ->each(static fn (Crawler $option) => self::read($option));
    }

    /**
     * @return array<string, list<string>> the options of a list by the group that holds them
     */
    public function optionGroups(string $label): array
    {
        $groups = [];

        foreach ($this->findField('select', $label, $this->crawler())->filter('optgroup') as $group) {
            $groups[(string) $group->getAttribute('label')] = (new Crawler($group))
                ->filter('option')
                ->each(static fn (Crawler $option) => self::read($option));
        }

        return $groups;
    }

    /**
     * The text the field names as its description with aria-describedby, like a help text.
     */
    public function description(string $label): string
    {
        $described = (string) $this->findField('*', $label, $this->crawler())->attr('aria-describedby');
        $ids = array_filter(explode(' ', $described));

        return implode(' ', array_map(
            fn (string $id): string => self::read($this->crawler()->filterXPath(sprintf('//*[@id = "%s"]', $id))),
            $ids,
        ));
    }

    /**
     * The rows of a table, each as the text of its cells. A row that names a group of rows has a single cell. A cell
     * without text, like an image, an icon or a form control, is left out.
     *
     * @return list<list<string>>
     */
    public function table(string $region): array
    {
        return $this->region($region)
            ->filter('tbody tr')
            ->each(static function (Crawler $row): array {
                $cells = $row->filter('th, td')->each(static fn (Crawler $cell) => self::read($cell));

                return array_values(array_filter($cells, static fn (string $text) => $text !== ''));
            });
    }

    /**
     * @return list<string>
     */
    public function listItems(string $region): array
    {
        return $this->region($region)
            ->filter('li')
            ->each(static fn (Crawler $item) => self::read($item));
    }

    /**
     * The element a label names, for a page object that reads what this class does not.
     */
    public function field(string $label): Crawler
    {
        return $this->findField('*', $label, $this->crawler());
    }

    public function region(string $name): Crawler
    {
        $named = sprintf(
            '*[@aria-label = "%1$s"'
            . ' or @aria-labelledby = //*[normalize-space() = "%1$s"]/@id'
            . ' or self::fieldset[legend[normalize-space() = "%1$s"]]]',
            $name,
        );
        // A fieldset its legend names and a group inside it that the legend labels are the same region.
        $region = $this->crawler()->filterXPath(sprintf('//%1$s[not(descendant::%1$s)]', $named));

        if ($region->count() !== 1) {
            throw new LogicException(sprintf('The page has %d regions named "%s".', $region->count(), $name));
        }

        return $region;
    }

    public function crawler(): Crawler
    {
        return $this->browser->crawler();
    }

    public function playwright(): PageInterface
    {
        if (!$this->browser instanceof PlaywrightBrowser) {
            throw new LogicException('The page was opened without a browser. Open it with Page::inBrowser().');
        }

        return $this->browser->client()->getPage() ?? throw new LogicException('The browser has no page open.');
    }

    /**
     * The text of the element as a reader sees it, with each run of whitespace as one space.
     */
    private static function read(Crawler $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $element->text()));
    }

    /**
     * A field is named by its label, by its aria-label or by the element its aria-labelledby points to.
     *
     * @param string $elements the names of the elements it may be, like "input|textarea", or "*" for any
     */
    private function findField(string $elements, string $label, Crawler $scope): Crawler
    {
        $names = implode(' or ', array_map(
            static fn (string $element): string => $element === '*' ? 'true()' : sprintf('self::%s', $element),
            explode('|', $elements),
        ));
        $field = $scope->filterXPath(sprintf(
            'descendant-or-self::*[(%1$s) and (@aria-label = "%2$s"'
            . ' or @aria-labelledby = //*[normalize-space() = "%2$s"]/@id'
            . ' or @id = //label[normalize-space() = "%2$s"]/@for)]',
            $names,
            $label,
        ));

        if ($field->count() !== 1) {
            throw new LogicException(sprintf(
                'The page has %d fields "%s". Its labels are: %s',
                $field->count(),
                $label,
                implode(', ', $this->labels()),
            ));
        }

        return $field;
    }

    private function located(Crawler $field): LocatorInterface
    {
        return $this->playwright()->locator(sprintf('[id="%s"]', $field->attr('id')));
    }

    private function part(string $element, string $label, string $part): Crawler
    {
        $input = $this->findField('*', $label, $this->crawler())->filterXPath(sprintf(
            'descendant::%s[substring(@name, string-length(@name) - %d) = "[%s]"]',
            $element,
            strlen($part) + 1,
            $part,
        ));

        if ($input->count() !== 1) {
            throw new LogicException(sprintf('The field "%s" has %d parts "%s".', $label, $input->count(), $part));
        }

        return $input;
    }

    private function fillField(Crawler $field, string|int $value): self
    {
        $this->browser->fillField((string) $field->attr('name'), (string) $value);

        return $this;
    }

    private function selectOptions(Crawler $field, string $label, string ...$options): self
    {
        $values = array_map(
            static function (string $option) use ($field, $label): string {
                $matches = $field->filterXPath(
                    sprintf('descendant::option[contains(normalize-space(), "%s")]', $option),
                );

                if ($matches->count() !== 1) {
                    throw new LogicException(sprintf(
                        'The field "%s" has %d options with "%s".',
                        $label,
                        $matches->count(),
                        $option,
                    ));
                }

                return (string) $matches->attr('value');
            },
            $options,
        );

        $name = (string) $field->attr('name');

        if (count($values) === 1) {
            $this->browser->selectFieldOption($name, $values[0]);
        } else {
            $this->browser->selectFieldOptions($name, $values);
        }

        return $this;
    }

    private function link(Crawler $within, string $name): Crawler
    {
        $links = $this->links($within, $name);

        return $links->count() > 0 ? $links : throw new LogicException(sprintf('There is no link "%s".', $name));
    }

    private function links(Crawler $within, string $name): Crawler
    {
        return $within->filterXPath(sprintf(
            'descendant-or-self::a[normalize-space() = "%1$s" or @aria-label = "%1$s"]',
            $name,
        ));
    }

    /**
     * Only the kernel tells the status of a response.
     */
    private function succeeded(Browser $browser): void
    {
        if ($browser instanceof KernelBrowser) {
            $browser->assertSuccessful();
        }
    }

    private function follow(Crawler $link): self
    {
        $this->succeeded($this->browser->visit($link->link()->getUri()));

        return $this;
    }
}
