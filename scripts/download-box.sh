#!/usr/bin/env bash
#
# Downloads the humbug/box PHAR into .tools/box.phar and verifies the
# SHA-256 checksum against the pinned version.
#
# Pinned: Box 4.7.0 (2026-03-18)
# Review by: 2026-07-24 (approx. 3 months)
#
set -euo pipefail

BOX_VERSION="4.7.0"
BOX_SHA256="3d390eeaec33288098fe83f8a54c60cc575cb6be295f38ff4482b4b4f26f8d52"
BOX_URL="https://github.com/box-project/box/releases/download/${BOX_VERSION}/box.phar"

TOOLS_DIR=".tools"
TARGET="${TOOLS_DIR}/box.phar"

mkdir -p "${TOOLS_DIR}"

if [ -f "${TARGET}" ]; then
    if command -v shasum >/dev/null 2>&1; then
        existing=$(shasum -a 256 "${TARGET}" | awk '{print $1}')
    else
        existing=$(sha256sum "${TARGET}" | awk '{print $1}')
    fi
    if [ "${existing}" = "${BOX_SHA256}" ]; then
        echo "Box ${BOX_VERSION} already present at ${TARGET} (checksum OK)"
        exit 0
    fi
    echo "Existing box.phar has wrong checksum; re-downloading..."
    rm -f "${TARGET}"
fi

echo "Downloading Box ${BOX_VERSION}..."
if command -v curl >/dev/null 2>&1; then
    curl -fsSL -o "${TARGET}" "${BOX_URL}"
elif command -v wget >/dev/null 2>&1; then
    wget -q -O "${TARGET}" "${BOX_URL}"
else
    echo "ERROR: neither curl nor wget available" >&2
    exit 1
fi

if command -v shasum >/dev/null 2>&1; then
    downloaded=$(shasum -a 256 "${TARGET}" | awk '{print $1}')
else
    downloaded=$(sha256sum "${TARGET}" | awk '{print $1}')
fi

if [ "${downloaded}" != "${BOX_SHA256}" ]; then
    echo "ERROR: SHA-256 mismatch for ${TARGET}" >&2
    echo "  expected: ${BOX_SHA256}" >&2
    echo "  got:      ${downloaded}" >&2
    rm -f "${TARGET}"
    exit 1
fi

chmod +x "${TARGET}"
echo "Box ${BOX_VERSION} installed at ${TARGET}"
