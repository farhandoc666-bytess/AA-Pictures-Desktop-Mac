#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")"
echo "A&A PICTURES — MAC INSTALLER BUILDER"
if [[ "$(uname -s)" != "Darwin" ]]; then echo "Run this script on a Mac, not Windows or Linux."; exit 1; fi
if [[ ! -f "aa-desktop-public-key.pem" ]]; then echo "Missing aa-desktop-public-key.pem (download public signing key from WordPress)."; exit 1; fi
if ! command -v node >/dev/null || ! command -v npm >/dev/null; then echo "Install Node.js LTS first from https://nodejs.org/"; exit 1; fi
if [[ "${AAD_ALLOW_UNSIGNED_MAC:-0}" != "1" ]]; then
  if ! security find-identity -v -p codesigning 2>/dev/null | grep -q 'Developer ID Application:' && [[ -z "${CSC_LINK:-}" ]]; then
    echo "A Developer ID Application signing certificate is required before publishing Mac downloads."
    echo "For testing only, run: AAD_ALLOW_UNSIGNED_MAC=1 ./BUILD-MAC.command"
    exit 1
  fi
  if [[ -z "${APPLE_API_KEY:-}" ]] && [[ -z "${APPLE_ID:-}" ]] && [[ -z "${APPLE_TEAM_ID:-}" ]]; then
    echo "Apple notarization credentials are not configured. Configure them before publishing."
    echo "For local testing only: AAD_ALLOW_UNSIGNED_MAC=1 ./BUILD-MAC.command"
    exit 1
  fi
fi
npm install
npm test
node scripts/build.js script "$(pwd)/aa-desktop-public-key.pem" mac
node scripts/build.js media "$(pwd)/aa-desktop-public-key.pem" mac
echo "Built DMG files in dist/script and dist/media. Before release, verify Apple signing and notarization."
