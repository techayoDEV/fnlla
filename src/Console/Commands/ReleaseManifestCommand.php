<?php

declare(strict_types=1);

namespace Fnlla\Php\Console\Commands;

use Fnlla\Php\Console\Command;
use Fnlla\Php\Support\ReleaseArtifactBuilder;

final class ReleaseManifestCommand extends Command
{
    public function name(): string
    {
        return "release:manifest";
    }

    public function description(): string
    {
        return "Generate a release manifest with artefact hashes and optional HMAC signature.";
    }

    public function handle(array $arguments): int
    {
        $builder = $this->container->make(ReleaseArtifactBuilder::class);
        $output = $this->optionValue($arguments, "--output") ?? $builder->defaultOutputPath("fnlla-release-manifest.json");
        $sbom = $builder->defaultOutputPath("fnlla-sbom.cdx.json");
        $checksums = $builder->defaultOutputPath("SHA256SUMS");

        if (!is_file($sbom)) {
            $builder->buildSbom($sbom);
        }

        if (!is_file($checksums)) {
            $builder->buildChecksums($checksums);
        }

        $result = $builder->buildManifest($output, [
            "sbom" => $sbom,
            "checksums" => $checksums,
        ]);

        if (in_array("--json", $arguments, true)) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        }

        $this->line("Release manifest written: " . $result["path"]);
        $this->line("Signed: " . ($result["signed"] ? "yes" : "no"));

        return 0;
    }

    private function optionValue(array $arguments, string $name): ?string
    {
        foreach ($arguments as $index => $argument) {
            if ($argument === $name && isset($arguments[$index + 1])) {
                return (string) $arguments[$index + 1];
            }

            if (str_starts_with((string) $argument, $name . "=")) {
                return substr((string) $argument, strlen($name) + 1);
            }
        }

        return null;
    }
}
