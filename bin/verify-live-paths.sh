#!/usr/bin/env bash
# Read-only verification of the endpoint paths that are NOT in the official
# OpenAPI v2 spec, plus the /id/{id} record-path convention.
#
# Sends GET requests only — no data is created, changed or deleted.
#
# URL and token are read from .env (MINDTWO_WECLAPP_URL, MINDTWO_WECLAPP_API_KEY)
# unless WECLAPP_URL / WECLAPP_TOKEN are already exported. The token is never
# printed.
#
# Usage (from the repository root):
#   bash bin/verify-live-paths.sh
#
#   WECLAPP_URL="https://your-tenant.weclapp.com/webapp/api/v2" \
#   bash bin/verify-live-paths.sh

set -uo pipefail

if [ -f .env ]; then
  if [ -z "${WECLAPP_TOKEN:-}" ]; then
    WECLAPP_TOKEN=$(sed -n 's/^MINDTWO_WECLAPP_API_KEY=//p' .env | head -1 | tr -d '"'"'"'\r')
  fi
  if [ -z "${WECLAPP_URL:-}" ]; then
    WECLAPP_URL=$(sed -n 's/^MINDTWO_WECLAPP_URL=//p' .env | head -1 | tr -d '"'"'"'\r')
  fi
fi

: "${WECLAPP_URL:?set WECLAPP_URL (e.g. https://tenant.weclapp.com/webapp/api/v2)}"
: "${WECLAPP_TOKEN:?no token — set MINDTWO_WECLAPP_API_KEY in .env or export WECLAPP_TOKEN}"

BASE="${WECLAPP_URL%/}"

probe() {
  local label="$1" path="$2"
  local code
  code=$(curl -s --compressed -o /dev/null -w '%{http_code}' \
    -H "AuthenticationToken: ${WECLAPP_TOKEN}" \
    -H 'Accept: application/json' \
    "${BASE}/${path}")
  printf '%-52s %-46s %s\n' "$label" "/$path" "$code"
}

printf '%-52s %-46s %s\n' 'CHECK' 'PATH' 'HTTP'
printf '%s\n' '--------------------------------------------------------------------------------------------------------'

echo
echo '# Resources absent from the spec (expect 404 if the spec is authoritative)'
probe 'customer as its own resource'        'customer?pageSize=1'
probe 'supplier as its own resource'        'supplier?pageSize=1'
probe 'project as its own resource'         'project?pageSize=1'

echo
echo '# Spec-backed equivalents (expect 200)'
probe 'party'                               'party?pageSize=1'
probe 'party filtered to customers'         'party?customer-eq=true&pageSize=1'
probe 'party filtered to suppliers'         'party?supplier-eq=true&pageSize=1'
probe 'salesOrder in project mode'          'salesOrder?projectModeActive-eq=true&pageSize=1'

echo
echo '# Record-path convention (expect 200 for /id/{id}, 404 for the bare form)'
FIRST_ID=$(curl -s --compressed -H "AuthenticationToken: ${WECLAPP_TOKEN}" -H 'Accept: application/json' \
  "${BASE}/article?pageSize=1" \
  | sed -n 's/.*"id"[[:space:]]*:[[:space:]]*"\{0,1\}\([0-9]\{1,\}\).*/\1/p' | head -1)

if [ -n "${FIRST_ID}" ]; then
  probe "article/id/${FIRST_ID} (spec shape)"  "article/id/${FIRST_ID}"
  probe "article/${FIRST_ID} (old shape)"      "article/${FIRST_ID}"
else
  echo 'Could not read an article id — skipping record-path check.'
fi

echo
echo 'Interpreting the result:'
echo '  * customer/supplier 404 -> the /party switch already applied is correct.'
echo '  * project 200          -> keep the Project endpoint; note it is undocumented.'
echo '  * project 404          -> remap projects onto salesOrder + projectModeActive.'
