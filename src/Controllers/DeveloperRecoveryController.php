<?php

declare(strict_types=1);

namespace Fnlla\Php\Controllers;

use Fnlla\Php\Http\Request;
use Fnlla\Php\Http\Response;
use Fnlla\Php\Maintenance\DeveloperPasswordRecovery;
use Fnlla\Php\Queue\QueueManager;
use Fnlla\Php\Support\Logger;

final class DeveloperRecoveryController extends Controller
{
    private const SESSION_KEY = "developer.recovery_token";

    public function show(Request $request, DeveloperPasswordRecovery $recovery): Response
    {
        return $this->screen($recovery, "request");
    }

    public function send(Request $request, DeveloperPasswordRecovery $recovery, QueueManager $queue): Response
    {
        if (!$recovery->enabled()) {
            return $this->screen($recovery, "request");
        }
        try {
            $email = $request->input("email", "");
            $recovery->request(is_string($email) ? $email : "", $request->ip(), $queue);
        } catch (\Throwable) {
            Logger::write("error", "Developer recovery request could not be queued.", ["event" => "developer_recovery_queue_failed"]);
        }
        return $this->screen($recovery, "sent");
    }

    public function edit(Request $request, DeveloperPasswordRecovery $recovery): Response
    {
        if (!$recovery->enabled()) {
            return $this->screen($recovery, "invalid");
        }
        if ($request->query("token") !== null) {
            $token = $request->query("token");
            session_store()->forget(self::SESSION_KEY);
            if (is_string($token) && $recovery->valid($token)) {
                session_store()->regenerate();
                session_store()->put(self::SESSION_KEY, $token);
            }
            // Mail scanners may follow this URL, but only an explicit CSRF-protected POST consumes it.
            return $this->privateResponse($this->redirect(route("developer.password.reset")));
        }
        $valid = $recovery->valid((string) session_store()->get(self::SESSION_KEY, ""));
        return $this->screen($recovery, $valid ? "reset" : "invalid");
    }

    public function update(Request $request, DeveloperPasswordRecovery $recovery): Response
    {
        if (!$recovery->enabled()) {
            return $this->screen($recovery, "invalid");
        }
        $token = (string) session_store()->get(self::SESSION_KEY, "");
        $password = $request->input("password", "");
        $confirmation = $request->input("password_confirmation", "");
        if (!is_string($password) || !is_string($confirmation) || $password !== $confirmation
            || strlen($password) < 12 || strlen($password) > 72 || trim($password) !== $password || str_contains($password, "\0")) {
            return $this->screen($recovery, $recovery->valid($token) ? "reset" : "invalid", "Use 12 to 72 bytes, no leading or trailing spaces, and enter the same password twice.", 422);
        }
        try {
            $changed = $recovery->reset($token, $password);
        } catch (\Throwable) {
            Logger::write("error", "Developer password recovery could not save credentials.", ["event" => "developer_recovery_write_failed"]);
            return $this->screen($recovery, "invalid", "The password could not be changed. Request a new link or contact the project owner.", 409);
        }
        session_store()->forget(self::SESSION_KEY);
        regenerate_csrf_token();
        return $this->screen($recovery, $changed ? "complete" : "invalid", "", $changed ? 200 : 422);
    }

    private function screen(DeveloperPasswordRecovery $recovery, string $step, string $error = "", int $status = 200): Response
    {
        if (!$recovery->enabled()) {
            return $this->privateResponse(Response::text("Not Found", 404));
        }
        return $this->privateResponse($this->view("developer/recovery", [
            "pageTitle" => "Developer Account Recovery", "step" => $step, "recoveryError" => $error,
        ], $status, "layouts/developer"));
    }

    private function privateResponse(Response $response): Response
    {
        return $response->withHeaders(["Cache-Control" => "private, no-store", "Referrer-Policy" => "no-referrer", "X-Robots-Tag" => "noindex, nofollow"]);
    }
}
