<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONTROLLER SOURCE
File: src\Controllers\DocsController.php
Copyright (c) 2026 TechAyo LTD (techayo.co.uk). Released under the MIT License.
===============================================================================

FNLLA PHP is produced, maintained and distributed by TechAyo LTD
(techayo.co.uk). This repository is the authoritative maintainer workspace for
the FNLLA PHP framework released under the MIT License and its related delivery scripts, tests,
templates and release metadata.

Purpose:
- Serves the maintained local documentation workspace through the application runtime.
*/

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Response;

final class DocsController extends Controller
{
    private const DOCUMENTS = [
        [
            "file" => "index.html",
            "title" => "Overview",
            "summary" => "Repository contract, supported stack and the shortest path to understanding what FNLLA PHP is.",
            "kind" => "Reference",
        ],
        [
            "file" => "distribution.html",
            "title" => "Distribution",
            "summary" => "What belongs in the maintainer repository, what stays out of exports and how release metadata is treated.",
            "kind" => "Reference",
        ],
        [
            "file" => "fnlla-web.html",
            "title" => "UI Runtime",
            "summary" => "The one supported UI runtime boundary and the operational rules around the vendored runtime layer.",
            "kind" => "Reference",
        ],
        [
            "file" => "getting-started.html",
            "title" => "Getting Started",
            "summary" => "Local boot flow, environment setup and the baseline commands for confirming the stack works.",
            "kind" => "Reference",
        ],
        [
            "file" => "building.html",
            "title" => "Building",
            "summary" => "Where routes, controllers, views, scripts and assets live when you start shaping a real project.",
            "kind" => "Reference",
        ],
        [
            "file" => "api.html",
            "title" => "API",
            "summary" => "The maintained runtime surface for routing, middleware, responses and related framework behaviors.",
            "kind" => "Reference",
        ],
        [
            "file" => "guides.html",
            "title" => "Guides",
            "summary" => "The long-form guide hub generated from the Markdown sources stored under docs/.",
            "kind" => "Reference",
        ],
        [
            "file" => "starting-a-new-project.html",
            "title" => "Starting a New Project",
            "summary" => "The official workflow for exporting a downstream project instead of building directly inside techayoDEV/fnlla-php.",
            "kind" => "Guide",
        ],
        [
            "file" => "building-with-fnlla-php.html",
            "title" => "Building with FNLLA PHP",
            "summary" => "The implementation guide for routes, controllers, views, forms, auth and MySQL-backed application work.",
            "kind" => "Guide",
        ],
        [
            "file" => "project-scripts-reference.html",
            "title" => "Project Scripts Reference",
            "summary" => "What the project-facing scripts are responsible for and which ones belong only in the maintainer workspace.",
            "kind" => "Guide",
        ],
    ];

    public function index(): Response
    {
        if (!has_local_docs_workspace()) {
            return $this->view("pages/not-found", [
                "pageTitle" => "Not Found",
            ], 404);
        }

        $documents = self::DOCUMENTS;

        return $this->view("pages/docs", [
            "pageTitle" => "Docs",
            "pageTitleSection" => "Framework",
            "guideDocuments" => array_values(array_filter(
                $documents,
                static fn (array $document): bool => $document["kind"] === "Guide"
            )),
            "referenceDocuments" => array_values(array_filter(
                $documents,
                static fn (array $document): bool => $document["kind"] === "Reference"
            )),
        ]);
    }

    public function page(string $page): Response
    {
        if (!has_local_docs_workspace() || !$this->isAllowedDocument($page)) {
            return $this->view("pages/not-found", [
                "pageTitle" => "Not Found",
            ], 404);
        }

        $contents = (string) file_get_contents(base_path("docs/" . $page));
        $contents = $this->rewriteDocumentLinks($contents);

        return Response::html($contents, 200, [
            "X-Robots-Tag" => "noindex, nofollow",
        ]);
    }

    public function stylesheet(): Response
    {
        return $this->assetResponse("docs/assets/docs.css", "text/css; charset=UTF-8");
    }

    public function script(): Response
    {
        return $this->assetResponse("docs/assets/docs.js", "application/javascript; charset=UTF-8");
    }

    public function brandIcon(): Response
    {
        return $this->assetResponse("docs/assets/brand/fnlla-php.svg", "image/svg+xml");
    }

    private function assetResponse(string $relativePath, string $contentType): Response
    {
        if (!has_local_docs_workspace()) {
            return Response::text("Not Found", 404);
        }

        $absolutePath = base_path($relativePath);

        if (!is_file($absolutePath)) {
            return Response::text("Not Found", 404);
        }

        return new Response((string) file_get_contents($absolutePath), 200, [
            "Content-Type" => $contentType,
            "Cache-Control" => "no-cache",
            "X-Robots-Tag" => "noindex, nofollow",
        ]);
    }

    private function rewriteDocumentLinks(string $contents): string
    {
        $rewritten = str_replace(
            [
                "../public/vendor/fnlla-web/assets/css/fnlla-web.css",
                "../public/vendor/fnlla-web/assets/js/fnlla-web.js",
                "./assets/docs.css",
                "./assets/docs.js",
                "./assets/brand/fnlla-web.svg",
                "./assets/brand/fnlla-php.svg",
            ],
            [
                asset("vendor/fnlla-web/assets/css/fnlla-web.css"),
                asset("vendor/fnlla-web/assets/js/fnlla-web.js"),
                route("docs.asset.stylesheet"),
                route("docs.asset.script"),
                route("docs.asset.brand"),
                route("docs.asset.brand"),
            ],
            $contents
        );

        $rewritten = preg_replace_callback(
            '/href="\.\/([a-z0-9\-]+\.html)"/i',
            static fn (array $matches): string => 'href="' . route("docs.page", [
                "page" => $matches[1],
            ]) . '"',
            $rewritten
        );

        return is_string($rewritten) ? $rewritten : $contents;
    }

    private function isAllowedDocument(string $page): bool
    {
        foreach (self::DOCUMENTS as $document) {
            if ($document["file"] === $page) {
                return true;
            }
        }

        return false;
    }
}
