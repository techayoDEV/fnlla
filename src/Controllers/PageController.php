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
use Fnlla\Php\Validation\ValidationException;
use RuntimeException;

final class PageController extends Controller
{
    public function home(Request $request): Response
    {
        return $this->view("pages/home", [
            "pageTitle" => "Home",
            "pageTitleHome" => true,
            "heroStats" => [
                [
                    "value" => "Setup",
                    "label" => "project identity and private access",
                ],
                [
                    "value" => "OPS",
                    "label" => "developer operations panel",
                ],
                [
                    "value" => "PHP",
                    "label" => "server-rendered products",
                ],
            ],
            "proofPoints" => [
                [
                    "number" => "01",
                    "title" => "Start with application foundations",
                    "text" => "The exported project keeps public pages, private flows, customer review and operational controls in one inspectable PHP application.",
                ],
                [
                    "number" => "02",
                    "title" => "Use AI where it is accountable",
                    "text" => "Local project knowledge is not an AI model. The built-in FIONN AI gateway connects to TechAyo's separate AI service with a developer account and API access; OpenAI API and Anthropic API are optional integrations.",
                ],
                [
                    "number" => "03",
                    "title" => "Keep the client close to delivery",
                    "text" => "Preview access, customer review, maintenance controls, analytics, project logs and release readiness are available before the first handover.",
                ],
            ],
            "serviceTracks" => [
                [
                    "title" => "Web product delivery",
                    "text" => "Service pages, contact journeys, protected portals and product workflows can grow from the same server-rendered base.",
                ],
                [
                    "title" => "Developer operations",
                    "text" => "Named developer access, setup, preview, service control, audit export, notifications, analytics and framework updates stay in the panel.",
                ],
                [
                    "title" => "Review changes with context",
                    "text" => "Project context, triage, review packs and technical-debt checks support maintenance. Remote AI assistance requires a separately configured provider and explicit access.",
                ],
            ],
        ]);
    }

    public function about(Request $request): Response
    {
        return $this->view("pages/about", [
            "pageTitle" => "About this project",
            "pageTitleSection" => "Story, trust and delivery model",
            "pageHero" => [
                "eyebrow" => "About",
                "title" => "Project story, trust markers and delivery model in one clear place.",
                "text" => "Use this page to explain the real organisation, product or service behind the build, then back it with the proof visitors need before they move forward.",
                "meta" => ["Project story", "Trust markers", "Delivery model"],
            ],
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
            "pageTitle" => "Services and delivery paths",
            "pageTitleSection" => "Offers, modules and workflows",
            "pageHero" => [
                "eyebrow" => "Services",
                "title" => "Useful services, modules and workflows shaped for the first real release.",
                "text" => "Use this page to present what the project offers, how each path works and which next step a visitor or operator should take.",
                "meta" => ["Service pages", "Product modules", "Workflow entry points"],
            ],
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
            "pageTitle" => "Contact and enquiry flow",
            "pageTitleSection" => "Working form, validation and follow-up",
            "pageHero" => [
                "eyebrow" => "Contact",
                "title" => "A working enquiry flow ready to become the project contact route.",
                "text" => "Start with a real form that already handles CSRF, validation, old input, flash feedback, honeypot spam friction and mail/log delivery.",
                "meta" => ["CSRF", "Validation", "Log mailer"],
                "actions" => [
                    [
                        "href" => "#contact-form",
                        "label" => "Open form",
                        "variant" => "primary",
                    ],
                    [
                        "href" => route("privacy") . "#cookies",
                        "label" => "Privacy note",
                        "variant" => "outline",
                    ],
                ],
            ],
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

    public function submitContact(Request $request): Response
    {
        $payload = [
            "contact_name" => trim((string) $request->input("contact_name", "")),
            "contact_email" => strtolower(trim((string) $request->input("contact_email", ""))),
            "contact_phone" => trim((string) $request->input("contact_phone", "")),
            "contact_subject" => trim((string) $request->input("contact_subject", "")),
            "contact_message" => trim((string) $request->input("contact_message", "")),
            "contact_consent" => (string) $request->input("contact_consent", ""),
            "contact_website" => trim((string) $request->input("contact_website", "")),
        ];

        if ($payload["contact_website"] !== "") {
            $this->recordContactSubmission($request, "spam_honeypot", $payload);
            flash_set("status", [
                "variant" => "success",
                "title" => "Message received",
                "text" => "Thanks. The enquiry has been received.",
                "toast" => true,
            ]);
            regenerate_csrf_token();

            return $this->redirect(route("contact") . "#contact-form");
        }

        try {
            $validated = $this->validate($payload, [
                "contact_name" => ["required", "string", "min:2", "max:120"],
                "contact_email" => ["required", "email", "max:160"],
                "contact_phone" => ["nullable", "string", "max:40"],
                "contact_subject" => ["required", "string", "min:3", "max:140"],
                "contact_message" => ["required", "string", "min:10", "max:2000"],
                "contact_consent" => ["required", "string", "in:1"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("errors", $exception->errors());
            flash_set("old", $payload);
            flash_set("status", [
                "variant" => "warning",
                "title" => "Contact form needs attention",
                "text" => "Review the highlighted fields and send the enquiry again.",
                "toast" => false,
            ]);
            $this->recordContactSubmission($request, "validation_failed", $payload);
            regenerate_csrf_token();

            return $this->redirect(route("contact") . "#contact-form");
        }

        try {
            $recipient = trim((string) config("mail.contact_recipient", ""));
            $recipient = $recipient !== "" ? $recipient : (string) config("mail.from.address", "no-reply@example.com");

            mailer()->sendFormSubmission($recipient, "New website enquiry", [
                "Name" => $validated["contact_name"],
                "Email" => $validated["contact_email"],
                "Phone" => (string) ($validated["contact_phone"] ?? ""),
                "Subject" => $validated["contact_subject"],
                "Message" => $validated["contact_message"],
                "Request ID" => $request->requestId(),
            ]);
        } catch (RuntimeException $exception) {
            flash_set("errors", [
                "contact_message" => "The message could not be sent right now.",
            ]);
            flash_set("old", $payload);
            flash_set("status", [
                "variant" => "danger",
                "title" => "Contact delivery failed",
                "text" => "The form validated, but mail delivery failed. Check mail configuration and try again.",
                "toast" => false,
            ]);
            $this->recordContactSubmission($request, "mail_failed", $payload);
            regenerate_csrf_token();

            return $this->redirect(route("contact") . "#contact-form");
        }

        $this->recordContactSubmission($request, "sent", $validated);
        flash_set("status", [
            "variant" => "success",
            "title" => "Message sent",
            "text" => "Thanks. The enquiry has been sent and the project team can follow up.",
            "toast" => true,
        ]);
        regenerate_csrf_token();

        return $this->redirect(route("contact") . "#contact-form");
    }

    public function terms(Request $request): Response
    {
        return $this->view("pages/legal", [
            "pageTitle" => "Terms and Conditions",
            "pageHero" => [
                "eyebrow" => "Website terms",
                "title" => "Terms and Conditions",
                "text" => "This starter text is a practical baseline for a standard informational website or early web application. Replace the placeholders with the real organisation, service model, jurisdiction and customer process before launch.",
                "meta" => ["Starter terms", "Editable copy", "Project review required"],
            ],
            "legalEyebrow" => "Website terms",
            "legalTitle" => "Terms and Conditions",
            "legalIntro" => "This starter text is a practical baseline for a standard informational website or early web application. Replace the placeholders with the real organisation, service model, jurisdiction and customer process before launch.",
            "legalSections" => [
                [
                    "title" => "Use of this website",
                    "items" => [
                        "Visitors may use this website for lawful purposes and should not attempt to disrupt, reverse engineer, abuse or gain unauthorised access to the service.",
                        "Content is provided for general information unless a specific written agreement, quote, subscription or service contract states otherwise.",
                    ],
                ],
                [
                    "title" => "Accounts, forms and submitted information",
                    "items" => [
                        "Where the project includes accounts, forms or booking flows, users are responsible for providing accurate information and keeping access credentials secure.",
                        "The site owner may reject, suspend or remove submissions that are incomplete, unlawful, misleading, abusive or technically harmful.",
                    ],
                ],
                [
                    "title" => "Availability and changes",
                    "items" => [
                        "The website may be changed, paused or withdrawn for maintenance, security, operational or business reasons.",
                        "The developer should add project-specific support channels, service levels and cancellation rules where the application provides paid services.",
                    ],
                ],
                [
                    "title" => "Liability and governing terms",
                    "items" => [
                        "Nothing in this starter text limits liability where it cannot lawfully be limited.",
                        "Replace this paragraph with the correct governing law, dispute process and any consumer, business-to-business or sector-specific wording needed for the final project.",
                    ],
                ],
            ],
            "legalNote" => "Developer note: treat this as editable starter copy, not legal advice. Review it with the project owner before production release.",
        ]);
    }

    public function privacy(Request $request): Response
    {
        return $this->view("pages/legal", [
            "pageTitle" => "Privacy Policy",
            "pageHero" => [
                "eyebrow" => "Privacy and cookies",
                "title" => "Privacy Policy",
                "text" => "This starter privacy policy gives the project a responsible default for forms, sessions and cookie choices. Replace every placeholder with the real controller identity, contact route, retention period and lawful basis before launch.",
                "meta" => ["Forms", "Sessions", "Cookie choices"],
            ],
            "legalEyebrow" => "Privacy and cookies",
            "legalTitle" => "Privacy Policy",
            "legalIntro" => "This starter privacy policy gives the project a responsible default for forms, sessions and cookie choices. Replace every placeholder with the real controller identity, contact route, retention period and lawful basis before launch.",
            "legalSections" => [
                [
                    "title" => "Information this project may collect",
                    "items" => [
                        "Contact details, messages, account details or file uploads submitted through forms that the final application enables.",
                        "Technical information needed to operate the service, including session identifiers, security events, request metadata and error diagnostics.",
                    ],
                ],
                [
                    "title" => "How information is used",
                    "items" => [
                        "To respond to enquiries, provide requested functionality, maintain security, troubleshoot issues and improve the reliability of the application.",
                        "Optional analytics or marketing tools should load only after a suitable consent choice allows them.",
                    ],
                ],
                [
                    "title" => "Sharing, storage and retention",
                    "items" => [
                        "Personal information should be shared only with processors or services required to operate the final project, such as hosting, email, analytics or support tools.",
                        "The developer should define concrete retention periods for enquiries, accounts, files, logs, backups and cookie consent records.",
                    ],
                ],
                [
                    "title" => "Cookies",
                    "id" => "cookies",
                    "items" => [
                        "Essential cookies or local storage may be used for sessions, CSRF protection, security and saving privacy choices.",
                        "Analytics and marketing cookies are optional and should stay disabled until the visitor accepts them through Cookie Settings.",
                    ],
                ],
                [
                    "title" => "Rights and contact",
                    "items" => [
                        "Add the real privacy contact for access, correction, deletion, objection, restriction, portability and complaint requests.",
                        "If the application serves regulated sectors or multiple jurisdictions, replace this starter text with reviewed project-specific wording.",
                    ],
                ],
            ],
            "legalNote" => "Developer note: this snippet is intentionally generic. It reduces blank-page work, but it must be adapted to the final controller, market and data flow.",
        ]);
    }

    private function recordContactSubmission(Request $request, string $status, array $payload): void
    {
        $path = storage_path("framework/developer/form-submissions.jsonl");
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $entry = [
            "schema" => "fnlla.public_contact_submission.v1",
            "submitted_at_utc" => gmdate(DATE_ATOM),
            "status" => $status,
            "route" => "contact",
            "subject" => substr((string) ($payload["contact_subject"] ?? ""), 0, 140),
            "mail_driver" => (string) config("mail.default", "log"),
            "recipient_configured" => trim((string) config("mail.contact_recipient", "")) !== "",
            "request_id" => $request->requestId(),
        ];

        file_put_contents($path, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
