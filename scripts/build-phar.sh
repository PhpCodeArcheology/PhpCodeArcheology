#!/usr/bin/env bash
#
# Builds the PhpCodeArcheology PHAR locally.
#
# Steps:
#   1. Download Box (if missing or checksum mismatch).
#   2. Re-install composer deps without dev (--optimize-autoloader).
#   3. Build Tailwind CSS so output.css is fresh.
#   4. Compile the PHAR.
#
# The trap ensures dev dependencies are restored even if any step fails
# or the user presses Ctrl+C — so the local dev environment never ends
# up without pest/phpstan/etc.
#
set -euo pipefail

# When invoked via `composer build-phar`, Composer prepends ./vendor/bin to
# $PATH so project binaries shadow system ones. That means a plain `composer`
# call inside this script resolves to vendor/bin/composer — which breaks as
# soon as `composer install --no-dev` removes the composer/composer dev-dep
# it lives in. Find the real system Composer once and pin it for the script.
COMPOSER_BIN=""
for candidate in $(command -v composer 2>/dev/null || true) $(which -a composer 2>/dev/null || true); do
    case "${candidate}" in
        */vendor/bin/*) continue ;;
        "") continue ;;
        *) COMPOSER_BIN="${candidate}"; break ;;
    esac
done
if [ -z "${COMPOSER_BIN}" ]; then
    echo "ERROR: could not locate a system-wide composer binary" >&2
    exit 1
fi

restore_dev_deps() {
    echo ""
    echo "Restoring dev dependencies..."
    "${COMPOSER_BIN}" install --no-plugins
}
trap restore_dev_deps EXIT

bash ./scripts/download-box.sh

echo ""
echo "Installing production composer deps..."
"${COMPOSER_BIN}" install --no-dev --optimize-autoloader --no-plugins

echo ""
echo "Building Tailwind CSS..."
npm ci
npm run build:css

echo ""
echo "Compiling PHAR..."
.tools/box.phar compile

echo ""
echo "Phar built: build/phpcodearcheology.phar"
