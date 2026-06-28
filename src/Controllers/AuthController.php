<?php

declare(strict_types=1);

/*
===============================================================================
FNLLA PHP CONTROLLER SOURCE
File: src\Controllers\AuthController.php
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

use Fnlla\Php\Auth\AuthManager;
use Fnlla\Php\Http\Request;
use Fnlla\Php\Validation\ValidationException;

final class AuthController extends Controller
{
    public function __construct(private AuthManager $auth)
    {
    }

    public function loginForm(Request $request): \Fnlla\Php\Http\Response
    {
        if ($this->auth->check()) {
            return $this->redirect(route("dashboard"));
        }

        return $this->view("pages/login", [
            "pageTitle" => __("messages.sign_in"),
        ]);
    }

    public function login(Request $request): \Fnlla\Php\Http\Response
    {
        $payload = [
            "email" => trim((string) $request->input("email", "")),
            "password" => (string) $request->input("password", ""),
        ];

        try {
            $this->validate($payload, [
                "email" => ["required", "email", "max:160"],
                "password" => ["required", "string", "min:8", "max:255"],
            ]);
        } catch (ValidationException $exception) {
            flash_set("old", ["email" => $payload["email"]]);
            flash_set("errors", $exception->errors());
            flash_set("status", [
                "variant" => "warning",
                "title" => "Check your credentials",
                "text" => "The sign-in form still needs valid input.",
                "toast" => false,
            ]);

            return $this->redirect(route("login"));
        }

        if (!$this->auth->attempt($payload)) {
            flash_set("old", ["email" => $payload["email"]]);
            flash_set("errors", [
                "email" => "These credentials do not match our records.",
            ]);
            flash_set("status", [
                "variant" => "danger",
                "title" => "Sign in failed",
                "text" => "These credentials do not match our records.",
                "toast" => false,
            ]);

            return $this->redirect(route("login"));
        }

        flash_set("status", [
            "variant" => "success",
            "title" => "Signed in",
            "text" => "You now have access to authenticated areas.",
            "toast" => true,
        ]);

        return $this->redirect(route("dashboard"));
    }

    public function dashboard(Request $request): \Fnlla\Php\Http\Response
    {
        $this->authorize("view-dashboard");

        return $this->view("pages/dashboard", [
            "pageTitle" => __("messages.dashboard"),
            "currentUser" => $this->auth->user(),
        ]);
    }

    public function admin(Request $request): \Fnlla\Php\Http\Response
    {
        $this->authorize("manage-admin-area");

        return $this->view("pages/admin", [
            "pageTitle" => __("messages.admin_area"),
            "currentUser" => $this->auth->user(),
        ]);
    }

    public function logout(Request $request): \Fnlla\Php\Http\Response
    {
        $this->auth->logout();
        flash_set("status", [
            "variant" => "success",
            "title" => "Signed out",
            "text" => "Your session has been closed.",
            "toast" => false,
        ]);

        return $this->redirect(route("home"));
    }
}
