#!/usr/bin/env bash
# Smoke tests du portail client — NON DESTRUCTIFS (Phase 8).
#
# Aucune écriture métier : seulement des lectures, des refus attendus et, si un
# compte de test est fourni, une connexion suivie de sa déconnexion (un jeton
# créé puis révoqué, une ligne d'audit « login »).
#
#   API_URL=https://api.example.com \
#   PORTAL_URL=https://client.example.com \
#   SMOKE_EMAIL=lecteur@client.example SMOKE_PASSWORD=... \
#   bash scripts/customer-portal-smoke.sh
#
# PORTAL_URL, SMOKE_EMAIL et SMOKE_PASSWORD sont facultatifs. Utiliser un compte
# **lecteur** dédié, jamais un administrateur. Le mot de passe n'est jamais affiché.
set -u

API="${API_URL:?API_URL est obligatoire (ex. https://api.example.com)}"
API="${API%/}"
PORTAL="${PORTAL_URL:-}"
PORTAL="${PORTAL%/}"
NOWHERE='01JZZZZZZZZZZZZZZZZZZZZZZZ'
failures=0

pass() { printf '  ok    %s\n' "$1"; }
fail() { printf '  ÉCHEC %s — %s\n' "$1" "$2"; failures=$((failures + 1)); }

# status <méthode> <url> [en-têtes curl…] → code HTTP
status() {
  local method=$1 url=$2; shift 2
  curl -s -o /dev/null -w '%{http_code}' -X "$method" -H 'Accept: application/json' "$@" "$url"
}

# page <url> → code HTTP, demandé comme un navigateur (HTML, pas JSON).
page() { curl -s -o /dev/null -w '%{http_code}' -H 'Accept: text/html' "$1"; }

expect_status() {
  local name=$1 expected=$2 actual=$3
  if [ "$actual" = "$expected" ]; then pass "$name ($actual)"; else fail "$name" "attendu $expected, reçu $actual"; fi
}

echo "API : $API"

# 1. Santé : base, stockage, cache (CheckApplicationHealth).
expect_status 'santé /up' 200 "$(status GET "$API/up")"

# 2. Refus sans jeton.
expect_status 'contexte sans jeton' 401 "$(status GET "$API/api/v1/customer/context")"
expect_status 'commande sans jeton' 401 "$(status GET "$API/api/v1/customer/orders/$NOWHERE")"
expect_status 'API client sans clé' 401 "$(status GET "$API/api/v1/client/me")"

# 3. Suivi public : un jeton inconnu donne un 404 générique, sans détail interne.
body=$(curl -s -H 'Accept: application/json' "$API/api/v1/public/tracking/smoke-$(date +%s)-unknown-token-000000000000")
if printf '%s' "$body" | grep -qE 'SQLSTATE|App\\\\|vendor/|"trace"'; then
  fail 'suivi public inconnu' 'la réponse expose un détail interne'
else
  pass 'suivi public inconnu : aucun détail interne'
fi

# 4. En-têtes de sécurité.
headers=$(curl -s -D - -o /dev/null -H 'Accept: application/json' "$API/api/v1/customer/context")
for header in 'X-Content-Type-Options: nosniff' 'X-Frame-Options: DENY' 'Referrer-Policy: no-referrer'; do
  if printf '%s' "$headers" | grep -qi "^$header"; then pass "en-tête $header"; else fail "en-tête $header" 'absent'; fi
done
case "$API" in
  https://*) printf '%s' "$headers" | grep -qi '^Strict-Transport-Security' && pass 'HSTS' || fail 'HSTS' 'absent sur HTTPS' ;;
esac

# 5. Portail : SPA servie, routes profondes renvoyées vers index.html, CORS.
if [ -n "$PORTAL" ]; then
  echo "Portail : $PORTAL"
  expect_status 'page d’accueil du portail' 200 "$(page "$PORTAL/")"
  expect_status 'route profonde /orders (fallback SPA)' 200 "$(page "$PORTAL/orders")"
  if curl -s -H 'Accept: text/html' "$PORTAL/" | grep -q 'localhost'; then fail 'index.html' 'contient « localhost »'; else pass 'index.html sans localhost'; fi
  allow=$(curl -s -D - -o /dev/null -X OPTIONS -H "Origin: $PORTAL" -H 'Access-Control-Request-Method: POST' \
    "$API/api/v1/customer/auth/login" | tr -d '\r' | grep -i '^Access-Control-Allow-Origin:' | cut -d' ' -f2)
  if [ "$allow" = "$PORTAL" ]; then pass 'CORS : origine du portail autorisée'; else fail 'CORS' "Access-Control-Allow-Origin=« $allow »"; fi
fi

# 6. Parcours authentifié en lecture seule, puis déconnexion.
if [ -n "${SMOKE_EMAIL:-}" ] && [ -n "${SMOKE_PASSWORD:-}" ]; then
  echo "Compte : $SMOKE_EMAIL"
  login=$(curl -s -H 'Accept: application/json' -H 'Content-Type: application/json' \
    --data "{\"email\":\"$SMOKE_EMAIL\",\"password\":\"$SMOKE_PASSWORD\",\"deviceName\":\"smoke-test\"}" \
    "$API/api/v1/customer/auth/login")
  token=$(printf '%s' "$login" | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
  if [ -z "$token" ]; then
    fail 'connexion' 'aucun jeton (identifiants, compte inactif ou plusieurs clients)'
  else
    pass 'connexion'
    auth=(-H "Authorization: Bearer $token")
    expect_status 'contexte' 200 "$(status GET "$API/api/v1/customer/context" "${auth[@]}")"
    expect_status 'liste des commandes' 200 "$(status GET "$API/api/v1/customer/orders?perPage=1" "${auth[@]}")"
    expect_status 'commande inexistante' 404 "$(status GET "$API/api/v1/customer/orders/$NOWHERE" "${auth[@]}")"
    expect_status 'customerId refusé' 422 "$(status GET "$API/api/v1/customer/orders?customerId=$NOWHERE" "${auth[@]}")"
    expect_status 'déconnexion' 204 "$(status POST "$API/api/v1/customer/auth/logout" "${auth[@]}")"
    expect_status 'jeton révoqué' 401 "$(status GET "$API/api/v1/customer/context" "${auth[@]}")"
  fi
fi

echo
if [ "$failures" -eq 0 ]; then echo 'SMOKE OK'; exit 0; fi
echo "SMOKE EN ÉCHEC : $failures vérification(s)"; exit 1
