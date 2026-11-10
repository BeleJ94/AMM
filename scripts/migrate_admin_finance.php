<?php
/** Migration additive : profil « Administrateur Finance » (rôle admin_finance).
 * Aperçu   : php scripts/migrate_admin_finance.php
 * Appliquer: php scripts/migrate_admin_finance.php --apply
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI uniquement.'); }
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();
$apply = in_array('--apply', $argv, true);

$col = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'role'")->fetch();
preg_match_all("/'([^']*)'/", $col['Type'], $matches);
if (in_array('admin_finance', $matches[1], true)) { echo "Base déjà à jour.\n"; exit(0); }

$values = array_merge($matches[1], ['admin_finance']);
$sql = "ALTER TABLE `users` MODIFY `role` ENUM(" . implode(',', array_map([$pdo, 'quote'], $values)) . ") NOT NULL DEFAULT 'agent'";
if ($apply) { $pdo->exec($sql); }
echo ($apply ? 'OK ' : 'PREVIEW ') . $sql . PHP_EOL;
if (!$apply) { echo "Aucune modification. Ajouter --apply pour appliquer.\n"; }
