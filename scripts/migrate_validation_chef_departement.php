<?php
/** Migration additive : visa du chef de département sur les demandes de fonds + catégorie budgétaire RH.
 * Aperçu   : php scripts/migrate_validation_chef_departement.php
 * Appliquer: php scripts/migrate_validation_chef_departement.php --apply
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI uniquement.'); }
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();
$apply = in_array('--apply', $argv, true);
$changes = [];

function ajouterValeursEnum(PDO $pdo, string $table, string $column, array $newValues, string $default = ''): ?string {
    $col = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetch();
    preg_match_all("/'([^']*)'/", $col['Type'], $matches);
    $values = array_values(array_unique(array_merge($matches[1], $newValues)));
    if ($values === $matches[1]) { return null; }
    $list = implode(',', array_map([$pdo, 'quote'], $values));
    return "ALTER TABLE `$table` MODIFY `$column` ENUM($list) NOT NULL" . ($default !== '' ? ' DEFAULT ' . $pdo->quote($default) : '');
}

foreach ([
    ajouterValeursEnum($pdo, 'treasury_fund_requests', 'status', ['a_valider_chef'], 'brouillon'),
    ajouterValeursEnum($pdo, 'treasury_request_approvals', 'approval_level', ['chef_service']),
] as $sql) { if ($sql !== null) { $changes[] = $sql; } }

$stmt = $pdo->prepare("SELECT COUNT(*) FROM financial_categories WHERE nom = 'RH' AND type_flux = 'depense'");
$stmt->execute();
if (!(int)$stmt->fetchColumn()) {
    $changes[] = "INSERT INTO financial_categories (nom, type_flux, actif, created_at) VALUES ('RH', 'depense', 1, NOW())";
}

foreach ($changes as $sql) {
    if ($apply) { $pdo->exec($sql); }
    echo ($apply ? 'OK ' : 'PREVIEW ') . $sql . PHP_EOL;
}
if (empty($changes)) { echo "Base déjà à jour.\n"; }
elseif (!$apply) { echo "Aucune modification. Ajouter --apply pour appliquer.\n"; }
