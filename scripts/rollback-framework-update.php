<?php

declare(strict_types=1);

// Recovery intentionally avoids application bootstrap, which may be the broken component.
require dirname(__DIR__) . "/src/Support/FrameworkUpdateTransaction.php";

try {
    (new \Fnlla\Php\Support\FrameworkUpdateTransaction(dirname(__DIR__)))->recover();
    fwrite(STDOUT, "Previous framework files restored. Run project validation before serving traffic.\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
