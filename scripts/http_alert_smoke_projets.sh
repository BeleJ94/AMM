#!/usr/bin/env bash
set -euo pipefail

MYSQL_BIN="/Applications/XAMPP/xamppfiles/bin/mysql"
BASE_URL="http://localhost/AMM"
COOKIE_FILE="/tmp/amm_cookies.txt"

USER_ID="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT id FROM users WHERE actif=1 ORDER BY id ASC LIMIT 1;")"
PROJECT_ID=1
STAMP="$(date +%Y%m%d%H%M%S)"
JALON_TITLE="HTTP_ALERT_JALON_$STAMP"
TACHE_TITLE="HTTP_ALERT_TACHE_$STAMP"

$MYSQL_BIN -u root -e "USE amm_erp; INSERT INTO project_milestones (project_id, phase_id, titre, description, date_butoir, statut, created_by, created_at) VALUES ($PROJECT_ID, NULL, '$JALON_TITLE', 'Alert test jalon', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'non_demarre', $USER_ID, NOW());"
$MYSQL_BIN -u root -e "USE amm_erp; INSERT INTO project_tasks (project_id, phase_id, assigned_to, titre, description, deadline, priorite, statut, created_by, created_at) VALUES ($PROJECT_ID, NULL, $USER_ID, '$TACHE_TITLE', 'Alert test tache', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'moyenne', 'a_faire', $USER_ID, NOW());"

JALON_ID="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT id FROM project_milestones WHERE titre='$JALON_TITLE' ORDER BY id DESC LIMIT 1;")"
TASK_ID="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT id FROM project_tasks WHERE titre='$TACHE_TITLE' ORDER BY id DESC LIMIT 1;")"

BEFORE_NOTIFS="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT COUNT(*) FROM notifications WHERE user_id=$USER_ID AND (message LIKE 'Jalon en retard: $JALON_TITLE%' OR message LIKE 'Tache en retard: $TACHE_TITLE%');")"

CREDS="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT email, password FROM users WHERE id=$USER_ID LIMIT 1;")"
EMAIL="$(echo "$CREDS" | awk '{print $1}')"
PASSWORD="$(echo "$CREDS" | awk '{print $2}')"

LOGIN_HTML="$(curl -s -c "$COOKIE_FILE" "$BASE_URL/auth/login")"
CSRF="$(echo "$LOGIN_HTML" | sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' | head -n1)"

curl -s -L -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/auth/login" \
  --data-urlencode "email=$EMAIL" \
  --data-urlencode "password=$PASSWORD" \
  --data-urlencode "csrf_token=$CSRF" > /dev/null

MILESTONE_STATUS="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT statut FROM project_milestones WHERE id=$JALON_ID;")"
MILESTONE_ALERT_SET="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT IF(alert_sent_at IS NULL,'no','yes') FROM project_milestones WHERE id=$JALON_ID;")"
TASK_ALERT_SET="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT IF(alert_sent_at IS NULL,'no','yes') FROM project_tasks WHERE id=$TASK_ID;")"
AFTER_NOTIFS="$($MYSQL_BIN -u root -N -e "USE amm_erp; SELECT COUNT(*) FROM notifications WHERE user_id=$USER_ID AND (message LIKE 'Jalon en retard: $JALON_TITLE%' OR message LIKE 'Tache en retard: $TACHE_TITLE%');")"

echo "ALERT_MILESTONE_STATUS=$MILESTONE_STATUS"
echo "ALERT_MILESTONE_ALERT_SENT=$MILESTONE_ALERT_SET"
echo "ALERT_TASK_ALERT_SENT=$TASK_ALERT_SET"
echo "ALERT_NOTIFS_BEFORE=$BEFORE_NOTIFS"
echo "ALERT_NOTIFS_AFTER=$AFTER_NOTIFS"

$MYSQL_BIN -u root -e "USE amm_erp; DELETE FROM notifications WHERE user_id=$USER_ID AND (message LIKE 'Jalon en retard: $JALON_TITLE%' OR message LIKE 'Tache en retard: $TACHE_TITLE%');"
$MYSQL_BIN -u root -e "USE amm_erp; DELETE FROM project_milestones WHERE id=$JALON_ID;"
$MYSQL_BIN -u root -e "USE amm_erp; DELETE FROM project_tasks WHERE id=$TASK_ID;"
