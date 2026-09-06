# Complete Composer distribution

This export uses techayodev/fnlla-core and techayodev/fnlla-complete as real
Composer dependencies. Both package sources are bundled for offline first boot;
Composer installs copies into vendor. The application PageController keeps its
legacy namespace and src location for route compatibility. UI assets, configuration,
routes and views are still published project files, not uninstallable packages.

Run composer install, composer test:unit and composer analyse. Production uses
composer install --no-dev --optimize-autoloader. Do not edit vendor. Package sources
in packages are a local release channel, not a public registry publication.

To upgrade, replace both bundled package directories with a reviewed release,
update the exact root constraints together, run composer update techayodev/fnlla-core
techayodev/fnlla-complete, then run project tests. Commit composer.lock. Publish
assets/config changes deliberately; this package preview does not automate that
migration. Stage the resulting application as a separate immutable deployment.
In-place framework updates refuse this layout to avoid creating a shadow src tree.

Exact constraints are intentional while published assets and PHP packages must
be upgraded together. Composer may warn about those pins under `validate --strict`;
do not widen them just to silence that warning. Install from the committed lock
for deployments. This preview is not the default stable distribution contract.

Do not delete fnlla-complete from this preset and expect application routes and
published views to disappear. For panel-free applications use the Core preset.
Independent analytics/customer-review package extraction remains a separate task.
