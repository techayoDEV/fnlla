<?php

declare(strict_types=1);

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Response;

final class CoreHomeController extends Controller
{
    public function index(): Response
    {
        return $this->view("pages/home", ["pageTitle" => (string) config("app.name")]);
    }

    public function health(): Response
    {
        return Response::json(["status" => "ok"]);
    }
}
