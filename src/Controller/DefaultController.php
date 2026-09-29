<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Controller;

use OpenDxp\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a document renders through when it names no controller of its own.
 *
 * OpenDXP falls back to `opendxp.documents.default_controller`, and the foundation points that
 * here. It lives in the package rather than in the application template, so nothing has to be
 * copied into the source tree of the package under test.
 */
final class DefaultController extends FrontendController
{
    public function defaultAction(): Response
    {
        return $this->render('default.html.twig');
    }
}
