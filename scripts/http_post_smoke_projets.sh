#!/usr/bin/env bash
set -euo pipefail

MYSQL_BIN="/Applications/XAMPP/xamppfiles/bin/mysql"
BASE_URL="http://localhost/AMM"
COOKIE_FILE="/tmp/amm_cookies.txt"
PROJECT_ID=1

CREDS="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT email, password FROM users WHERE actif=1 ORDER BY id ASC LIMIT 1;")"
EMAIL="$(echo "$CREDS" | awk '{print $1}')"
PASSWORD="$(echo "$CREDS" | awk '{print $2}')"

LOGIN_HTML="$(curl -s -c "$COOKIE_FILE" "$BASE_URL/auth/login")"
CSRF="$(echo "$LOGIN_HTML" | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -n1)"

curl -s -L -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/auth/login" \
  --data-urlencode "email=$EMAIL" \
  --data-urlencode "password=$PASSWORD" \
  --data-urlencode "csrf_token=$CSRF" > /dev/null

# Refresh token from project detail page in authenticated context
DETAIL_HTML="$(curl -s -L -b "$COOKIE_FILE" "$BASE_URL/projets/voir/$PROJECT_ID")"
CSRF="$(echo "$DETAIL_HTML" | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -n1)"

STAMP="$(date +%Y%m%d%H%M%S)"
PHASE_NAME="HTTP_SMOKE_PHASE_$STAMP"
MILESTONE_TITLE="HTTP_SMOKE_JALON_$STAMP"
TASK_TITLE="HTTP_SMOKE_TACHE_$STAMP"
LINK_LABEL="HTTP_SMOKE_LINK_$STAMP"

PHASE_RESP="$(curl -s -L -b "$COOKIE_FILE" -X POST "$BASE_URL/projets/phase/$PROJECT_ID" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "nom=$PHASE_NAME" \
  --data-urlencode "description=Test phase HTTP" \
  --data-urlencode "ordre_phase=0" \
  --data-urlencode "progress_pct=10" \
  --data-urlencode "date_debut=$(date +%F)" \
  --data-urlencode "date_fin=$(date -v+7d +%F 2>/dev/null || date -d '+7 days' +%F)")"

JALON_RESP="$(curl -s -L -b "$COOKIE_FILE" -X POST "$BASE_URL/projets/jalon/$PROJECT_ID" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "titre=$MILESTONE_TITLE" \
  --data-urlencode "description=Test jalon HTTP" \
  --data-urlencode "date_butoir=$(date -v+5d +%F 2>/dev/null || date -d '+5 days' +%F)")"

TACHE_RESP="$(curl -s -L -b "$COOKIE_FILE" -X POST "$BASE_URL/projets/tache/$PROJECT_ID" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "titre=$TASK_TITLE" \
  --data-urlencode "description=Test tache HTTP" \
  --data-urlencode "priorite=moyenne" \
  --data-urlencode "statut=a_faire" \
  --data-urlencode "deadline=$(date -v+6d +%F 2>/dev/null || date -d '+6 days' +%F)")"

LINK_RESP="$(curl -s -L -b "$COOKIE_FILE" -X POST "$BASE_URL/projets/lien/$PROJECT_ID" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "module_type=document" \
  --data-urlencode "entity_id=99999" \
  --data-urlencode "label=$LINK_LABEL")"

MILESTONE_ID="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT id FROM project_milestones WHERE project_id=$PROJECT_ID AND titre='$MILESTONE_TITLE' ORDER BY id DESC LIMIT 1;")"
TASK_ID="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT id FROM project_tasks WHERE project_id=$PROJECT_ID AND titre='$TASK_TITLE' ORDER BY id DESC LIMIT 1;")"

JALON_STATUT_RESP=""
if [[ -n "${MILESTONE_ID}" ]]; then
  JALON_STATUT_RESP="$(curl -s -L -b "$COOKIE_FILE" -X POST "$BASE_URL/projets/jalon-statut/$MILESTONE_ID" \
    --data-urlencode "csrf_token=$CSRF" \
    --data-urlencode "project_id=$PROJECT_ID" \
    --data-urlencode "statut=atteint")"
fi

TACHE_STATUT_RESP=""
if [[ -n "${TASK_ID}" ]]; then
  TACHE_STATUT_RESP="$(curl -s -L -b "$COOKIE_FILE" -X POST "$BASE_URL/projets/tache-statut/$TASK_ID" \
    --data-urlencode "csrf_token=$CSRF" \
    --data-urlencode "project_id=$PROJECT_ID" \
    --data-urlencode "statut=en_cours")"
fi

fatal_in() {
  local text="$1"
  if echo "$text" | grep -Eq "Fatal error|Uncaught PDOException|SQLSTATE"; then
    echo yes
  else
    echo no
  fi
}

contains_smoke() {
  local text="$1"
  local pattern="$2"
  if echo "$text" | grep -Eq "$pattern"; then
    echo yes
  else
    echo no
  fi
}

echo "PHASE_FATAL=$(fatal_in "$PHASE_RESP")"
echo "JALON_FATAL=$(fatal_in "$JALON_RESP")"
echo "TACHE_FATAL=$(fatal_in "$TACHE_RESP")"
echo "LIEN_FATAL=$(fatal_in "$LINK_RESP")"
echo "JALON_STATUT_FATAL=$(fatal_in "$JALON_STATUT_RESP")"
echo "TACHE_STATUT_FATAL=$(fatal_in "$TACHE_STATUT_RESP")"

PHASE_DB_COUNT="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT COUNT(*) FROM project_phases WHERE project_id=$PROJECT_ID AND nom='$PHASE_NAME';")"
JALON_DB_COUNT="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT COUNT(*) FROM project_milestones WHERE project_id=$PROJECT_ID AND titre='$MILESTONE_TITLE';")"
TACHE_DB_COUNT="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT COUNT(*) FROM project_tasks WHERE project_id=$PROJECT_ID AND titre='$TASK_TITLE';")"
LIEN_DB_COUNT="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT COUNT(*) FROM project_links WHERE project_id=$PROJECT_ID AND label='$LINK_LABEL';")"

echo "PHASE_CREATED_DB=$([[ "$PHASE_DB_COUNT" -gt 0 ]] && echo yes || echo no)"
echo "JALON_CREATED_DB=$([[ "$JALON_DB_COUNT" -gt 0 ]] && echo yes || echo no)"
echo "TACHE_CREATED_DB=$([[ "$TACHE_DB_COUNT" -gt 0 ]] && echo yes || echo no)"
echo "LIEN_CREATED_DB=$([[ "$LIEN_DB_COUNT" -gt 0 ]] && echo yes || echo no)"

# Cleanup smoke artifacts
$MYSQL_BIN -u root -e "USE amm_erp; DELETE FROM project_links WHERE project_id=$PROJECT_ID AND label='$LINK_LABEL';"
$MYSQL_BIN -u root -e "USE amm_erp; DELETE FROM project_tasks WHERE project_id=$PROJECT_ID AND titre='$TASK_TITLE';"
$MYSQL_BIN -u root -e "USE amm_erp; DELETE FROM project_milestones WHERE project_id=$PROJECT_ID AND titre='$MILESTONE_TITLE';"
$MYSQL_BIN -u root -e "USE amm_erp; DELETE FROM project_phases WHERE project_id=$PROJECT_ID AND nom='$PHASE_NAME';"
