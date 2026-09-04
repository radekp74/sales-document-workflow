#!/usr/bin/env sh
# Black-box E2E publicznego API dokumentów sprzedażowych.
#
# Każda operacja biznesowa jest wykonywana wyłącznie przez HTTP, więc żądanie
# przechodzi przez pełny stos: HTTP -> Symfony -> Messenger -> Doctrine -> PostgreSQL.
# SQL służy tu jedynie do weryfikacji stanu końcowego, nigdy do wykonania operacji.
set -eu

BASE_URL="${E2E_BASE_URL:-http://app-test:8000}"
RESPONSE_FILE="$(mktemp)"
# shellcheck disable=SC2064
trap "rm -f '$RESPONSE_FILE'" EXIT

fail() {
    echo "$1" >&2
    [ -s "$RESPONSE_FILE" ] && cat "$RESPONSE_FILE" >&2
    exit 1
}

# Wysyła żądanie i zapisuje ciało odpowiedzi; zwraca status HTTP na stdout.
request() {
    method="$1"
    path="$2"
    body="${3:-}"

    if [ -n "$body" ]; then
        curl -sS -o "$RESPONSE_FILE" -w '%{http_code}' \
            -X "$method" "${BASE_URL}${path}" \
            -H 'Content-Type: application/json' \
            --data "$body"
    else
        curl -sS -o "$RESPONSE_FILE" -w '%{http_code}' \
            -X "$method" "${BASE_URL}${path}" \
            -H 'Content-Type: application/json'
    fi
}

expect_status() {
    expected="$1"
    actual="$2"
    label="$3"

    if [ "$actual" != "$expected" ]; then
        fail "${label}=FAIL expected=${expected} actual=${actual}"
    fi
    echo "${label}=PASS"
}

json_int() {
    sed -n "s/.*\"$1\"[[:space:]]*:[[:space:]]*\([0-9][0-9]*\).*/\1/p" "$RESPONSE_FILE"
}

body_contains() {
    grep -q "$1" "$RESPONSE_FILE" || fail "E2E_BODY_MISMATCH=$1"
}

# Zwraca pojedynczą, oznaczoną wartość z bazy. Etykieta w zapytaniu uniezależnia
# odczyt od sposobu formatowania tabeli przez dbal:run-sql.
query_labelled() {
    php bin/console dbal:run-sql "$1" 2>/dev/null | grep -o "$2" | tail -1
}

# --- gotowość serwera HTTP -------------------------------------------------
# Odpytujemy nieistniejącą ścieżkę: każda odpowiedź HTTP oznacza działający stos,
# a jednocześnie nie tworzymy przy tym żadnych danych.
i=0
until [ "$(curl -sS -o /dev/null -w '%{http_code}' "${BASE_URL}/__e2e_probe" || echo 000)" != "000" ]; do
    i=$((i + 1))
    [ "$i" -ge 30 ] && fail 'E2E_HTTP_READY=FAIL'
    sleep 1
done
echo 'E2E_HTTP_READY=PASS'

# --- E2E-001: create -> approve --------------------------------------------
status="$(request POST /sales-documents '{"contractor_id":77,"created_by":5}')"
expect_status 201 "$status" 'E2E_001_CREATE'
QUOTE_ID="$(json_int id)"
[ -n "$QUOTE_ID" ] || fail 'E2E_001_CREATE_BODY_INVALID=1'

status="$(request POST "/sales-documents/${QUOTE_ID}/approve" '{"approved_by":9}')"
expect_status 200 "$status" 'E2E_001_APPROVE'
body_contains '"type":"order"'
body_contains '"status":"approved"'
ORDER_ID="$(json_int id)"
[ "$(json_int parent_quote_id)" = "$QUOTE_ID" ] || fail 'E2E_001_PARENT_QUOTE_MISMATCH=1'
echo "E2E_001=PASS quote=${QUOTE_ID} order=${ORDER_ID}"

# --- E2E-002: nieistniejący dokument -> 404 --------------------------------
status="$(request POST /sales-documents/999999/approve '{"approved_by":9}')"
expect_status 404 "$status" 'E2E_002_MISSING_DOCUMENT'
body_contains 'Sales document not found'
echo 'E2E_002=PASS'

# --- E2E-003: niedozwolone przejście stanu -> 409 --------------------------
status="$(request POST "/sales-documents/${QUOTE_ID}/approve" '{"approved_by":9}')"
expect_status 409 "$status" 'E2E_003_INVALID_STATE'
body_contains 'cannot be approved in its current state'
echo 'E2E_003=PASS'

# --- E2E-004: poprawna semantyka pól ownership -----------------------------
# Publiczne API nie udostępnia odczytu dokumentu, a nie tworzymy endpointu
# wyłącznie na potrzeby testu — stan weryfikujemy zapytaniem do bazy.
OWNERSHIP="$(query_labelled \
    "SELECT 'OWNERSHIP=' || contractor_id || '/' || created_by FROM sales_document WHERE id = ${QUOTE_ID}" \
    'OWNERSHIP=[0-9]*/[0-9]*')"
[ "$OWNERSHIP" = 'OWNERSHIP=77/5' ] || fail "E2E_004_OWNERSHIP=FAIL actual=${OWNERSHIP}"
echo 'E2E_004_OWNERSHIP=PASS'

# --- E2E-005: niepoprawne wejście -> 400 -----------------------------------
status="$(request POST /sales-documents '{"contractor_id":77}')"
expect_status 400 "$status" 'E2E_005_INVALID_PAYLOAD'
echo 'E2E_005=PASS'

echo 'E2E=PASS'
