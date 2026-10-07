<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Application\Controller;

use OpenDxp\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PageController extends FrontendController
{
    private const string FORM = <<<'HTML'
        <!doctype html>
        <html lang="en">
        <body>
            <nav aria-label="Main navigation"><a href="/">Home</a></nav>
            <form method="post">
                <label for="name">Name</label>
                <input id="name" name="name" value="Ada" aria-describedby="name_help" required>
                <small id="name_help">As it stands in your passport</small>
                <label for="message">Message</label>
                <textarea id="message" name="message">Hello</textarea>
                <label for="color">Color</label>
                <select id="color" name="color">
                    <option value="">Please choose</option>
                    <optgroup label="Warm"><option value="red">Red</option></optgroup>
                    <optgroup label="Cold"><option value="blue" selected>Blue</option></optgroup>
                </select>
                <label for="toppings">Toppings</label>
                <select id="toppings" name="toppings[]" multiple>
                    <option value="cheese">Cheese</option>
                    <option value="ham">Ham</option>
                    <option value="olives">Olives</option>
                </select>
                <input type="checkbox" id="newsletter" name="newsletter" value="yes" checked>
                <label for="newsletter">Newsletter</label>
                <fieldset>
                    <legend>Size</legend>
                    <label><input type="radio" name="size" value="s"> Small</label>
                    <label><input type="radio" name="size" value="l"> Large</label>
                </fieldset>
                <fieldset>
                    <legend>Extras</legend>
                    <input type="checkbox" id="extras_bag" name="extras[]" value="bag">
                    <label for="extras_bag">Bag</label>
                    <input type="checkbox" id="extras_gift" name="extras[]" value="gift">
                    <label for="extras_gift">Gift</label>
                </fieldset>
                <label for="code">Code</label>
                <input id="code" name="code" disabled>
                <label for="secret">Secret</label>
                <input id="secret" name="secret" style="display: none">
                <label for="birthday">Birthday</label>
                <div id="birthday">
                    <select name="birthday[month]"><option value="5">May</option><option value="6">Jun</option></select>
                    <input name="birthday[year]" value="2000">
                </div>
                <button type="submit">Send</button>
            </form>
        </body>
        </html>
        HTML;

    public function formAction(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return new Response(self::FORM);
        }

        $sent = '';

        foreach ($request->request->all() as $name => $value) {
            $sent .= sprintf('<li>%s: %s</li>', $name, is_array($value) ? implode(', ', $value) : $value);
        }

        return new Response(sprintf(
            '<!doctype html><html lang="en"><body>%s<ul aria-label="Sent">%s</ul></body></html>',
            '<div role="status">Thank you</div>',
            $sent,
        ));
    }
}
