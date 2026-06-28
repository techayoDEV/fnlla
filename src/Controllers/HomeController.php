<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONTROLLER SOURCE
File: src\Controllers\HomeController.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Provides HTTP-facing controller behavior for maintained framework flows and demos.
*/

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Validation\ValidationException;

final class HomeController extends Controller
{
    public function home(Request $request): Response
    {
        return $this->view("pages/home", [
            "pageTitle" => "FNLLA PHP",
            "featureCards" => [
                [
                    "title" => "Routing that stays readable",
                    "text" => "Define routes in one file, keep handlers explicit and let middleware and the container handle the shared plumbing.",
                ],
                [
                    "title" => "Views with no template engine tax",
                    "text" => "Use plain PHP templates and lean on FNLLA UI for layout, components and responsive structure.",
                ],
                [
                    "title" => "Built for production hardening",
                    "text" => "The framework now includes middleware, DI, auth, validation, query builder and CLI-driven migrations as a stronger base for real delivery.",
                ],
            ],
            "runtimeTabs" => [
                [
                    "label" => "Routing",
                    "title" => "Front controller, middleware and dynamic routes",
                    "text" => "Routes now support middleware, dependency injection, HEAD and OPTIONS responses plus parameter placeholders such as /projects/{project}.",
                ],
                [
                    "label" => "Data",
                    "title" => "Query builder and migrations",
                    "text" => "The database layer is intentionally lean: PDO-backed connections, a small query builder and CLI migrations for schema changes.",
                ],
                [
                    "label" => "Auth",
                    "title" => "Session-backed authentication",
                    "text" => "A session guard, auth middleware and provider-based user lookup are included so protected areas do not need custom plumbing each time.",
                ],
            ],
        ]);
    }

    public function about(Request $request): Response
    {
        return $this->view("pages/about", [
            "pageTitle" => "About FNLLA PHP",
            "principles" => [
                "Keep the runtime small enough that the whole request flow is easy to trace.",
                "Prefer local, published assets over external dependencies for the UI layer.",
                "Use plain PHP for templates so teams can onboard quickly without a custom DSL.",
                "Add production primitives deliberately: middleware, DI, validation, auth, logging and migrations.",
            ],
        ]);
    }

    public function contact(Request $request): Response
    {
        return $this->view("pages/contact", [
            "pageTitle" => "Contact FNLLA PHP",
        ]);
    }

    public function sendContact(Request $request): Response
    {
        $payload = [
            "name" => trim((string) $request->input("name", "")),
            "company" => trim((string) $request->input("company", "")),
            "email" => trim((string) $request->input("email", "")),
            "scope" => trim((string) $request->input("scope", "")),
            "brief" => trim((string) $request->input("brief", "")),
        ];

        try {
            $this->validate($payload, [
                "name" => ["required", "string", "min:2", "max:120"],
                "company" => ["nullable", "string", "max:120"],
                "email" => ["required", "email", "max:160"],
                "scope" => ["required", "in:Platform advisory,Implementation support,Operational support"],
                "brief" => ["required", "string", "min:12", "max:3000"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("old", $payload);
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "A few fields still need attention",
                "text" => "Review the highlighted inputs and submit the form again.",
                "toast" => false,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("contact") . "#contact-form");
        }

        flash_set("status", [
            "variant" => "success",
            "title" => "Request captured",
            "text" => "The example form completed successfully and the framework flashed the confirmation into the next request.",
            "toast" => true,
        ]);
        mailer()->to((string) env("CONTACT_NOTIFICATION_EMAIL", "team@example.com"))->send(
            "New contact form submission",
            "<p><strong>Name:</strong> " . h($payload["name"]) . "</p><p><strong>Email:</strong> " . h($payload["email"]) . "</p><p><strong>Scope:</strong> " . h($payload["scope"]) . "</p><p><strong>Brief:</strong> " . nl2br(h($payload["brief"])) . "</p>",
            "Name: {$payload["name"]}\nEmail: {$payload["email"]}\nScope: {$payload["scope"]}\nBrief: {$payload["brief"]}"
        );
        event("contact.form.submitted", [
            "payload" => $payload,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("contact") . "#contact-form");
    }
}
