<?php

declare(strict_types=1);

// Loopback-only SMTP fixture. Accepts one connection and never relays mail.
$server = stream_socket_server("tcp://127.0.0.1:0", $errno, $error);
if ($server === false) { throw new RuntimeException($error); }
echo stream_socket_get_name($server, false) . "\n";
flush();
$client = stream_socket_accept($server, 15);
if ($client === false) { exit(2); }
stream_set_timeout($client, 10);
fwrite($client, "220 fnlla.test ESMTP\r\n");
$data = false;
$message = "";
while (($line = fgets($client, 8192)) !== false) {
    if ($data) {
        if ($line === ".\r\n") {
            file_put_contents($argv[1], $message);
            fwrite($client, "250 queued locally\r\n");
            $data = false;
        } else { $message .= $line; if (strlen($message) > 65536) { exit(3); } }
        continue;
    }
    if (str_starts_with($line, "EHLO")) { fwrite($client, "250 fnlla.test\r\n"); }
    elseif (str_starts_with($line, "RCPT") && ($argv[2] ?? "") === "reject") { fwrite($client, "550 rejected by fixture\r\n"); }
    elseif (str_starts_with($line, "DATA")) { $data = true; fwrite($client, "354 end with dot\r\n"); }
    elseif (str_starts_with($line, "QUIT")) { fwrite($client, "221 bye\r\n"); break; }
    else { fwrite($client, "250 ok\r\n"); }
}
fclose($client);
fclose($server);
