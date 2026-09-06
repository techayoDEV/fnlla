<?php

declare(strict_types=1);

namespace Fnlla\Php\Tests\Interop;

use Fnlla\Smtp\SmtpTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class SmtpTransportTest extends TestCase
{
    public function testRealLoopbackSmtpDeliveryAndRecipientRejection(): void
    {
        foreach (["accept", "reject"] as $mode) {
            $path = sys_get_temp_dir() . "/fnlla-smtp-" . bin2hex(random_bytes(8)) . ".eml";
            $process = proc_open([PHP_BINARY, base_path("tests/Integration/smtp-server.php"), $path, $mode],
                [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
            fclose($pipes[0]);
            $address = trim((string) fgets($pipes[1]));
            try {
                self::assertMatchesRegularExpression('/^127\.0\.0\.1:\d+$/', $address);
                $transport = SmtpTransport::fromDsn("smtp://" . $address . "?auto_tls=false");
                try {
                    $transport->send(["from" => ["address" => "from@example.test", "name" => "FNLLA"],
                        "to" => ["to@example.test"], "reply_to" => "reply@example.test",
                        "subject" => "Delivery test", "html" => "<p>Hello</p>", "text" => "Hello"]);
                    self::assertSame("accept", $mode);
                    self::assertStringContainsString("Subject: Delivery test", (string) file_get_contents($path));
                    self::assertStringContainsString("Reply-To: reply@example.test", (string) file_get_contents($path));
                } catch (TransportExceptionInterface $error) {
                    self::assertSame("reject", $mode);
                    self::assertStringContainsString("550", $error->getMessage());
                }
                unset($transport);
            } finally {
                proc_terminate($process);
                fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
                if (is_file($path)) { unlink($path); }
            }
        }
    }
}
