<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Controller;

use OpenDxp\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Response;

final class DefaultController extends FrontendController
{
    public function defaultAction(): Response
    {
        return $this->render('default.html.twig');
    }
}
