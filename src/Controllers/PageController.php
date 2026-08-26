<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA CONTROLLER SOURCE
File: src\Controllers\PageController.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Provides the public project-base pages that downstream teams are expected to
  extend directly into the real application.
*/

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;

final class PageController extends Controller
{
    public function home(Request $request): Response
    {
        return $this->view("pages/home", [
            "pageTitle" => "Home",
            "pageTitleHome" => true,
            "heroStats" => [
                [
                    "value" => "PHP",
                    "label" => "server-rendered delivery",
                ],
                [
                    "value" => "FNLLA",
                    "label" => "application foundation",
                ],
                [
                    "value" => "OPS",
                    "label" => "maintenance-ready",
                ],
            ],
            "proofPoints" => [
                [
                    "number" => "01",
                    "title" => "Start from a real application",
                    "text" => "The starter surface is meant to become the project itself, not a throwaway demo beside the production code.",
                ],
                [
                    "number" => "02",
                    "title" => "Keep routes and views explicit",
                    "text" => "Public pages, forms and operator flows stay readable through direct controllers, named routes and server-rendered templates.",
                ],
                [
                    "number" => "03",
                    "title" => "Leave operational paths in place",
                    "text" => "Health checks, maintenance access, developer login and framework update screens are part of the baseline from day one.",
                ],
            ],
            "serviceTracks" => [
                [
                    "title" => "Public websites",
                    "text" => "Service pages, contact journeys and content structures that can be reshaped into the real project offer.",
                ],
                [
                    "title" => "Private tools",
                    "text" => "Authenticated portals, admin panels and internal workflows can grow from the same server-rendered base.",
                ],
                [
                    "title" => "Long-lived delivery",
                    "text" => "The framework keeps update checks, release metadata and validation scripts close to the application.",
                ],
            ],
        ]);
    }

    public function about(Request $request): Response
    {
        return $this->view("pages/about", [
            "pageTitle" => "About",
            "aboutPillars" => [
                [
                    "title" => "Built for ownership",
                    "text" => "FNLLA favours code and configuration that project teams can inspect, change and maintain without a hidden build maze.",
                ],
                [
                    "title" => "Small enough to understand",
                    "text" => "The stack stays intentionally direct: routes, controllers, templates, storage, tests and deployment checks remain easy to follow.",
                ],
                [
                    "title" => "Ready to reshape",
                    "text" => "The public copy and page map are placeholders. The routing model, runtime and maintenance surfaces are the reusable foundation.",
                ],
            ],
            "aboutSteps" => [
                [
                    "number" => "1",
                    "title" => "Define the audience",
                    "text" => "Replace the starter narrative with the company, product, service and trust signals that belong to the project.",
                ],
                [
                    "number" => "2",
                    "title" => "Ship the useful surface",
                    "text" => "Keep the first release focused on routes, pages and forms that help visitors or operators complete a real task.",
                ],
                [
                    "number" => "3",
                    "title" => "Improve with checks",
                    "text" => "Use tests, linting, runtime validation and framework update reports to keep later changes deliberate.",
                ],
            ],
        ]);
    }

    public function services(Request $request): Response
    {
        return $this->view("pages/services", [
            "pageTitle" => "Services",
            "serviceCards" => [
                [
                    "title" => "Business websites",
                    "text" => "Service pages, landing pages, contact journeys and public content structures for clear project launches.",
                ],
                [
                    "title" => "Web applications",
                    "text" => "Authenticated portals, admin panels, job flows and team tools built with explicit server-rendered behaviour.",
                ],
                [
                    "title" => "FNLLA delivery",
                    "text" => "Project exports, runtime validation, framework updates and application surfaces built on the maintained stack.",
                ],
                [
                    "title" => "Handover and recovery",
                    "text" => "Environment notes, validation reports and operator documentation that keep launches and updates safer.",
                ],
            ],
            "deliverySteps" => [
                [
                    "number" => "1",
                    "title" => "Define the useful first release",
                    "text" => "Pick the smallest complete surface that can be launched, understood and improved.",
                ],
                [
                    "number" => "2",
                    "title" => "Build with operational checks",
                    "text" => "Keep linting, runtime validation, health routes and maintenance surfaces active as the project grows.",
                ],
                [
                    "number" => "3",
                    "title" => "Prepare the next iteration",
                    "text" => "Keep routes, content and components readable so the next improvement is not a rebuild.",
                ],
            ],
        ]);
    }

    public function contact(Request $request): Response
    {
        return $this->view("pages/contact", [
            "pageTitle" => "Contact",
            "contactChannels" => [
                [
                    "title" => "Project enquiry",
                    "text" => "Replace this card with the route, form or mailbox your project should use for new enquiries.",
                    "href" => "mailto:hello@example.test?subject=New%20project",
                    "label" => "hello@example.test",
                ],
                [
                    "title" => "Support route",
                    "text" => "Use this for maintenance, handover or operator support once the real application workflow is defined.",
                    "href" => "mailto:support@example.test?subject=Project%20support",
                    "label" => "support@example.test",
                ],
            ],
        ]);
    }
}
