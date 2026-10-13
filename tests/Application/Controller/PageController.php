<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation\Tests\Application\Controller;

use OpenDxp\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PageController extends FrontendController
{
    public function formAction(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->render('page/form.html.twig');
        }

        if ($request->request->get('name') === '') {
            return $this->render(
                'page/rejected.html.twig',
                response: new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY),
            );
        }

        return $this->render(
            'page/sent.html.twig',
            ['sent' => $request->request->all()],
        );
    }
}
