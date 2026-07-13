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
- Provides the public starter pages that downstream teams are expected to extend
  directly into the real application.
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
            "starterPrinciples" => [
                [
                    "title" => "Starter-first development",
                    "text" => "The shipped skeleton is the application base itself, so teams extend it instead of building a second front-end beside it.",
                ],
                [
                    "title" => "Section-based composition",
                    "text" => "Most page work should happen through repeated section and container blocks, which keeps views clear and predictable.",
                ],
                [
                    "title" => "One built-in runtime",
                    "text" => "FNLLA already ships the runtime used by the starter, so layout and page work can stay inside one supported UI contract.",
                ],
            ],
            "growthSteps" => [
                [
                    "number" => "1",
                    "title" => "Reshape the public page map",
                    "text" => "Adjust the starter routes, labels and navigation until they match the real information architecture of the project.",
                ],
                [
                    "number" => "2",
                    "title" => "Replace section content deliberately",
                    "text" => "Swap placeholder headlines, copy and cards with actual product sections while keeping the page structure readable.",
                ],
                [
                    "number" => "3",
                    "title" => "Attach real workflows",
                    "text" => "Expand the starter with forms, auth, data capture, dashboards or integrations only where the project actually needs them.",
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
                    "title" => "Who this starter is for",
                    "text" => "Teams that want the first version of the site or application to already feel like project code rather than framework decoration.",
                ],
                [
                    "title" => "How this page should evolve",
                    "text" => "Replace the placeholder narrative with the real company, product or service story without changing the starter model itself.",
                ],
                [
                    "title" => "What stays shared",
                    "text" => "The runtime, routing model, controller seam and validation flow stay stable while the visible product story changes.",
                ],
            ],
            "aboutSteps" => [
                [
                    "number" => "1",
                    "title" => "Start with the real narrative",
                    "text" => "Define the organization, offer, audience and trust markers that belong in the first public version of the project.",
                ],
                [
                    "number" => "2",
                    "title" => "Grow the page through sections",
                    "text" => "Add or remove whole sections as the page grows instead of stuffing every new message into one oversized block.",
                ],
                [
                    "number" => "3",
                    "title" => "Keep the structure reusable",
                    "text" => "Use the same section and container rhythm the other starter pages follow so the application stays coherent.",
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
                    "title" => "Service websites",
                    "text" => "Use this starter as the basis for a clear service page map, enquiry flow and structured presentation of what is offered.",
                ],
                [
                    "title" => "Portals and internal tools",
                    "text" => "Start from the same shell, then attach auth, dashboards, queues and data workflows where the project needs them.",
                ],
                [
                    "title" => "Server-rendered product surfaces",
                    "text" => "The starter remains useful when the project grows past simple marketing pages into richer application behavior.",
                ],
            ],
            "deliverySteps" => [
                [
                    "number" => "1",
                    "title" => "Map the real offer",
                    "text" => "Replace placeholder cards with the actual services, modules or delivery tracks the project needs to communicate.",
                ],
                [
                    "number" => "2",
                    "title" => "Connect the right CTA",
                    "text" => "Decide whether this page should drive visitors into sign-up, booking or an authenticated workflow.",
                ],
                [
                    "number" => "3",
                    "title" => "Keep the page extendable",
                    "text" => "Leave room for future sections such as pricing, proof, FAQs or case studies without breaking the starter rhythm.",
                ],
            ],
        ]);
    }
}
