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
                    "value" => "Pages",
                    "label" => "Home, about and contact are ready to reshape.",
                ],
                [
                    "value" => "Form",
                    "label" => "Validation and mail/log delivery are wired.",
                ],
                [
                    "value" => "Panel",
                    "label" => "Private setup and operations stay available.",
                ],
            ],
        ]);
    }

    public function about(Request $request): Response
    {
        return $this->view("pages/about", [
            "pageTitle" => "About",
            "pageTitleSection" => "Project context",
            "pageHero" => [
                "eyebrow" => "About",
                "title" => "A simple place for the project story.",
                "text" => "Say what this is, who it helps and what someone should do next.",
            ],
            "aboutPillars" => [
                [
                    "title" => "Purpose",
                    "text" => "Describe the product, service or organisation in plain language.",
                ],
                [
                    "title" => "Trust",
                    "text" => "Add only the proof that helps visitors decide with confidence.",
                ],
                [
                    "title" => "Next step",
                    "text" => "Point people to the contact route or the first useful workflow.",
                ],
            ],
        ]);
    }

    public function contact(Request $request): Response
    {
        return $this->view("pages/contact", [
            "pageTitle" => "Contact",
            "pageTitleSection" => "Send an enquiry",
            "pageHero" => [
                "eyebrow" => "Contact",
                "title" => "Send a project enquiry.",
                "text" => "A small working form with validation and delivery already wired.",
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
