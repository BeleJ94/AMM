#!/usr/bin/env bash
set -euo pipefail

MYSQL_BIN="/Applications/XAMPP/xamppfiles/bin/mysql"
BASE_URL="http://localhost/AMM"
COOKIE_FILE="/tmp/amm_cookies.txt"

CREDS="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT email, password FROM users WHERE actif=1 ORDER BY id ASC LIMIT 1;")"
EMAIL="$(echo "$CREDS" | awk '{print $1}')"
PASSWORD="$(echo "$CREDS" | awk '{print $2}')"

LOGIN_HTML="$(curl -s -c "$COOKIE_FILE" "$BASE_URL/auth/login")"
CSRF="$(echo "$LOGIN_HTML" | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -n1)"

LOGIN_RESP="$(curl -s -L -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/auth/login" \
  --data-urlencode "email=$EMAIL" \
  --data-urlencode "password=$PASSWORD" \
  --data-urlencode "csrf_token=$CSRF")"

P1="$(curl -s -L -b "$COOKIE_FILE" "$BASE_URL/projets/liste")"
P2="$(curl -s -L -b "$COOKIE_FILE" "$BASE_URL/projets/calendrier?mode=mensuel")"
P3="$(curl -s -L -b "$COOKIE_FILE" "$BASE_URL/projets/voir/1")"

contains() {
  local text="$1"
  local pattern="$2"
  if echo "$text" | grep -Eq "$pattern"; then
    echo yes
  else
    echo no
  fi
}

echo "EMAIL=$EMAIL"
echo "LOGIN_OK=$(contains "$LOGIN_RESP" "Dashboard|Accueil|Deconnexion|Deconnexion")"
echo "LISTE_FATAL=$(contains "$P1" "Fatal error|Uncaught PDOException|SQLSTATE")"
echo "CAL_FATAL=$(contains "$P2" "Fatal error|Uncaught PDOException|SQLSTATE")"
echo "VOIR_FATAL=$(contains "$P3" "Fatal error|Uncaught PDOException|SQLSTATE")"
echo "LISTE_HAS_PROJECTS=$(contains "$P1" "Module Projets|projet\\(s\\)|Nouveau projet")"
echo "CAL_HAS_TITLE=$(contains "$P2" "Calendrier Projets|Vue mensuelle|Semaine du")"
echo "VOIR_HAS_TITLE=$(contains "$P3" "Phases|Jalons|Taches projet|Liens inter-modules")"
