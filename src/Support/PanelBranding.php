<?php

declare(strict_types=1);

namespace Fnlla\Php\Support;

final class PanelBranding
{
    /**
     * @return array{name:string, tagline:string, edition:string, edition_label:string, logo:?string, mark:string, url:string, copyright:string, powered_by:string, powered_by_url:string, configured:bool, white_label_enabled:bool}
     */
    public static function state(): array
    {
        $enabled = (bool) config("panel_branding.white_label_enabled", false);
        $configuredName = trim((string) config("panel_branding.name", ""));
        $configuredTagline = trim((string) config("panel_branding.tagline", ""));
        $configuredLogo = trim((string) config("panel_branding.logo", "auto"));
        $configuredUrl = trim((string) config("panel_branding.url", ""));
        $configuredCopyright = trim((string) config("panel_branding.copyright", ""));
        $poweredByUrl = trim((string) config("framework.official_url", "https://fnlla.com"));

        if (!$enabled) {
            $name = FrameworkIdentity::PRODUCT_NAME;

            return [
                "name" => $name,
                "tagline" => trim((string) config("framework.brand.message", "Build from blueprint.")),
                "edition" => ProjectProfile::edition(),
                "edition_label" => ProjectProfile::editionLabel(),
                "logo" => framework_brand_asset("favicon"),
                "mark" => self::mark($name),
                "url" => $poweredByUrl,
                "copyright" => "",
                "powered_by" => "Powered by " . ProjectProfile::edition(),
                "powered_by_url" => $poweredByUrl,
                "configured" => false,
                "white_label_enabled" => false,
            ];
        }

        $fallbackName = trim((string) config("app.name", "FNLLA Project"));
        $name = $configuredName !== "" ? $configuredName : ($fallbackName !== "" ? $fallbackName : "Project workspace");
        $tagline = $configuredTagline !== "" ? $configuredTagline : trim((string) config("app.tagline", ""));
        $url = $configuredUrl !== "" ? $configuredUrl : trim((string) config("app.base_url", ""));

        return [
            "name" => $name,
            "tagline" => $tagline,
            "edition" => ProjectProfile::edition(),
            "edition_label" => ProjectProfile::editionLabel(),
            "logo" => self::logoAsset($configuredLogo, $name),
            "mark" => self::mark($name),
            "url" => $url,
            "copyright" => $configuredCopyright,
            "powered_by" => "Powered by " . ProjectProfile::edition(),
            "powered_by_url" => $poweredByUrl,
            "configured" => $configuredName !== "" || $configuredTagline !== "" || !in_array(strtolower($configuredLogo), ["", "auto"], true) || $configuredUrl !== "" || $configuredCopyright !== "",
            "white_label_enabled" => true,
        ];
    }

    public static function mark(?string $name = null): string
    {
        $name = trim((string) ($name ?? config("panel_branding.name", "")));
        if ($name === "") {
            $name = trim((string) config("app.name", "FN"));
        }
        $tokens = array_values(array_filter(
            preg_split('/[^A-Za-z0-9]+/', $name) ?: [],
            static fn (string $token): bool => $token !== ""
        ));

        if (count($tokens) >= 2) {
            return strtoupper(substr($tokens[0], 0, 1) . substr($tokens[1], 0, 1));
        }

        $compact = preg_replace('/[^A-Za-z0-9]/', "", $name) ?? "";

        return strtoupper(substr($compact !== "" ? $compact : "FN", 0, 2));
    }

    public static function logoAsset(?string $path = null, ?string $name = null): ?string
    {
        $configured = trim((string) ($path ?? config("panel_branding.logo", "auto")));

        if ($configured === "" || strtolower($configured) === "none") {
            return null;
        }

        if (strtolower($configured) === "auto") {
            $projectLogo = project_brand_logo_asset();

            if ($projectLogo !== null) {
                return $projectLogo;
            }

            $brandName = trim((string) ($name ?? config("panel_branding.name", "")));
            if ($brandName === "") {
                $brandName = trim((string) config("app.name", "FNLLA"));
            }
            $brandName = strtolower($brandName);
            $configured = $brandName === "fnlla" ? "assets/brand/fnlla/favicon.svg" : "";
        }

        if ($configured === "") {
            return null;
        }

        if (filter_var($configured, FILTER_VALIDATE_URL) !== false) {
            return $configured;
        }

        $normalizedPath = ltrim(str_replace("\\", "/", $configured), "/");

        if ($normalizedPath === "" || !is_file(public_path($normalizedPath))) {
            return null;
        }

        return asset($normalizedPath);
    }
}
