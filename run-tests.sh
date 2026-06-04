#!/usr/bin/env bash
# Runs the Identity Postman collection via Newman and writes an HTML report
# to public/docs/ so the FE team can review the API surface without Postman.
#
# Usage:
#   ./run-tests.sh                       # run the whole collection
#   ./run-tests.sh --folder "Users"      # run a single folder
#   ./run-tests.sh --folder "Auth (Public)"
#
# Any flag accepted by `newman run` can be passed through, e.g.:
#   ./run-tests.sh --bail folder         # stop on first failure
#   ./run-tests.sh --delay-request 250
#
# Outputs (overwritten/appended every run):
#   public/docs/identity-latest.html               ← share this with FE
#   public/docs/identity-<UTC-timestamp>.html      ← history snapshot
#
# Heads-up: with the current "no assertions" collection, Newman shows only
# HTTP-level red/green. For a proper pass/fail matrix, add pm.test() blocks
# to each request and re-run.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COLLECTION="${ROOT}/postman/identity-collection.json"
ENVIRONMENT="${ROOT}/postman/identity-environment.json"
REPORT_DIR="${ROOT}/public/docs"

# ── 1. Sanity-check the inputs ─────────────────────────────────────────
for f in "${COLLECTION}" "${ENVIRONMENT}"; do
    if [[ ! -f "${f}" ]]; then
        echo "✗ Missing required file: ${f}" >&2
        exit 1
    fi
done

# ── 2. Ensure newman + html reporter are installed ─────────────────────
ensure_npm() {
    if ! command -v npm >/dev/null 2>&1; then
        echo "✗ npm is not installed."
        echo "  Install Node.js (≥18) + npm first, then re-run this script."
        echo "  On Ubuntu/WSL:  sudo apt install -y nodejs npm"
        exit 1
    fi
}

npm_install_global() {
    local pkg="$1"
    if ! npm install -g "${pkg}" >/dev/null 2>&1; then
        echo "ℹ Global install of ${pkg} needs elevated permissions — retrying with sudo…"
        sudo npm install -g "${pkg}"
    fi
}

if ! command -v newman >/dev/null 2>&1; then
    ensure_npm
    echo "ℹ newman not found — installing globally via npm…"
    npm_install_global "newman"
fi

# htmlextra is a node module, not a CLI — probe the global npm root for it.
NPM_GLOBAL_ROOT="$(npm root -g 2>/dev/null || true)"
if [[ -z "${NPM_GLOBAL_ROOT}" || ! -d "${NPM_GLOBAL_ROOT}/newman-reporter-htmlextra" ]]; then
    ensure_npm
    echo "ℹ newman-reporter-htmlextra not found — installing globally via npm…"
    npm_install_global "newman-reporter-htmlextra"
fi

# ── 3. Prepare the report directory ────────────────────────────────────
mkdir -p "${REPORT_DIR}"
TIMESTAMP="$(date -u +'%Y%m%dT%H%M%SZ')"
REPORT_HISTORY="${REPORT_DIR}/identity-${TIMESTAMP}.html"
REPORT_LATEST="${REPORT_DIR}/identity-latest.html"

# ── 4. Show what we're about to do ─────────────────────────────────────
BASE_URL=$(grep -oE '"key"[[:space:]]*:[[:space:]]*"base_url"[^}]*"value"[[:space:]]*:[[:space:]]*"[^"]+"' "${ENVIRONMENT}" \
    | head -1 | grep -oE 'http[s]?://[^"]+' || true)

echo
echo "▶ Newman run"
echo "  collection:   ${COLLECTION#${ROOT}/}"
echo "  environment:  ${ENVIRONMENT#${ROOT}/}"
echo "  target:       ${BASE_URL:-<unresolved>}"
echo "  HTML report:  ${REPORT_HISTORY#${ROOT}/}"
echo "                ${REPORT_LATEST#${ROOT}/} (latest pointer)"
echo

# ── 5. Run newman with CLI + HTMLExtra reporters ───────────────────────
# We intentionally do NOT `exec` here so we can still publish the latest
# symlink after newman exits (success OR failure).
set +e
newman run "${COLLECTION}" \
    -e "${ENVIRONMENT}" \
    --reporters cli,htmlextra \
    --color on \
    --delay-request 100 \
    --reporter-htmlextra-export "${REPORT_HISTORY}" \
    --reporter-htmlextra-title "Identity Module — API Report" \
    --reporter-htmlextra-browserTitle "Identity API" \
    --reporter-htmlextra-darkTheme \
    --reporter-htmlextra-showOnlyFails false \
    --reporter-htmlextra-displayProgressBar \
    "$@"
NEWMAN_STATUS=$?
set -e

# ── 6. Refresh the stable "latest" pointer so FE always opens the newest run
if [[ -f "${REPORT_HISTORY}" ]]; then
    cp -f "${REPORT_HISTORY}" "${REPORT_LATEST}"
    echo
    echo "✓ HTML report written:"
    echo "    file:  ${REPORT_LATEST}"
    echo "    open:  xdg-open ${REPORT_LATEST}   # Linux"
    echo "           explorer.exe \$(wslpath -w ${REPORT_LATEST})   # WSL→Windows"
fi

exit "${NEWMAN_STATUS}"
