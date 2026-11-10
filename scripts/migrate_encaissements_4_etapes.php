<?php
/** Migration additive : encaissements en 4 étapes (constatation, liquidation, ordonnancement, recouvrement).
 * - nouveaux statuts et colonnes de traçabilité sur treasury_receipts ;
 * - table des recouvrements (versements, partiels ou totaux) et journal des étapes ;
 * - reprise de l'existant : brouillon -> constate, valide -> recouvre (avec un versement historique).
 * Aperçu   : php scripts/migrate_encaissements_4_etapes.php
 * Appliquer: php scripts/migrate_encaissements_4_etapes.php --apply
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI uniquement.'); }
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();
$apply = in_array('--apply', $argv, true);
$changes = [];

$colonnes = [];
foreach ($pdo->query('SHOW COLUMNS FROM treasury_receipts') as $c) { $colonnes[$c['Field']] = $c; }

// 1. Statuts : les anciennes valeurs sont conservées pour la compatibilité, mais ne sont plus produites.
preg_match_all("/'([^']*)'/", $colonnes['status']['Type'], $m);
$statuts = array_values(array_unique(array_merge($m[1], ['constate', 'liquide', 'ordonnance', 'partiellement_recouvre', 'recouvre'])));
if ($statuts !== $m[1]) {
    $changes[] = "ALTER TABLE treasury_receipts MODIFY status ENUM(" . implode(',', array_map([$pdo, 'quote'], $statuts)) . ") NOT NULL DEFAULT 'constate'";
}

// 2. Le compte et le mode ne sont connus qu'au recouvrement.
if ($colonnes['account_id']['Null'] === 'NO') { $changes[] = 'ALTER TABLE treasury_receipts MODIFY account_id INT(11) NULL'; }
if ($colonnes['payment_method']['Null'] === 'NO') { $changes[] = "ALTER TABLE treasury_receipts MODIFY payment_method ENUM('especes','banque','mobile_money') NULL"; }

// 3. Colonnes de traçabilité des étapes.
$nouvelles = [
    'category_id' => 'INT(11) NULL AFTER objet',
    'estimated_amount' => 'DECIMAL(15,2) NULL AFTER amount',
    'liquidated_by' => 'INT(11) NULL', 'liquidated_at' => 'DATETIME NULL', 'liquidation_comment' => 'TEXT NULL',
    'title_number' => 'VARCHAR(60) NULL', 'ordered_by' => 'INT(11) NULL', 'ordered_at' => 'DATETIME NULL', 'due_date' => 'DATE NULL',
    'amount_collected' => 'DECIMAL(15,2) NOT NULL DEFAULT 0', 'amount_collected_usd' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
    'recovered_at' => 'DATETIME NULL', 'is_cash_sale' => 'TINYINT(1) NOT NULL DEFAULT 0',
];
foreach ($nouvelles as $nom => $definition) {
    if (!isset($colonnes[$nom])) { $changes[] = "ALTER TABLE treasury_receipts ADD COLUMN $nom $definition"; }
}

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('treasury_receipt_collections', $tables, true)) {
    $changes[] = "CREATE TABLE treasury_receipt_collections (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        receipt_id INT(11) NOT NULL,
        collection_number VARCHAR(70) NOT NULL,
        collection_date DATE NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        currency ENUM('USD','CDF') NOT NULL DEFAULT 'USD',
        exchange_rate DECIMAL(12,4) NULL,
        amount_usd DECIMAL(15,2) NOT NULL DEFAULT 0,
        payment_method ENUM('especes','banque','mobile_money') NOT NULL,
        account_id INT(11) NOT NULL,
        reference_payment VARCHAR(120) NULL,
        proof_file VARCHAR(255) NULL,
        financial_transaction_id INT(11) NULL,
        status ENUM('valide','annule') NOT NULL DEFAULT 'valide',
        created_by INT(11) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        cancelled_by INT(11) NULL, cancelled_at DATETIME NULL, cancellation_reason TEXT NULL,
        KEY idx_collection_receipt (receipt_id), KEY idx_collection_account_date (account_id, collection_date),
        KEY idx_collection_transaction (financial_transaction_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}
if (!in_array('treasury_receipt_steps', $tables, true)) {
    $changes[] = "CREATE TABLE treasury_receipt_steps (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        receipt_id INT(11) NOT NULL,
        step ENUM('constatation','liquidation','ordonnancement','recouvrement','renvoi','annulation','comptant','reprise') NOT NULL,
        amount DECIMAL(15,2) NULL,
        comment TEXT NULL,
        user_id INT(11) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_step_receipt (receipt_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}

// 4. Reprise des données existantes (idempotente).
$changes[] = "UPDATE treasury_receipts SET status='constate', estimated_amount=COALESCE(estimated_amount, amount) WHERE status='brouillon'";
$changes[] = "INSERT INTO treasury_receipt_collections
    (receipt_id, collection_number, collection_date, amount, currency, exchange_rate, amount_usd, payment_method, account_id,
     reference_payment, proof_file, financial_transaction_id, status, created_by, created_at, cancelled_by, cancelled_at, cancellation_reason)
    SELECT r.id, r.receipt_number, r.date_receipt, r.amount, r.currency, r.exchange_rate, r.amount_usd, r.payment_method, r.account_id,
           r.reference_payment, r.proof_file, r.financial_transaction_id, IF(r.status='annule','annule','valide'),
           COALESCE(r.validated_by, r.created_by), COALESCE(r.validated_at, r.created_at), r.cancelled_by, r.cancelled_at, r.cancellation_reason
    FROM treasury_receipts r
    WHERE (r.status='valide' OR (r.status='annule' AND r.financial_transaction_id IS NOT NULL))
      AND r.account_id IS NOT NULL AND r.payment_method IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM treasury_receipt_collections c WHERE c.receipt_id=r.id)";
$changes[] = "UPDATE treasury_receipts SET status='recouvre', estimated_amount=COALESCE(estimated_amount, amount),
    liquidated_by=COALESCE(liquidated_by, validated_by), liquidated_at=COALESCE(liquidated_at, validated_at),
    ordered_by=COALESCE(ordered_by, validated_by), ordered_at=COALESCE(ordered_at, validated_at),
    title_number=COALESCE(title_number, receipt_number),
    amount_collected=amount, amount_collected_usd=amount_usd, recovered_at=COALESCE(recovered_at, validated_at), is_cash_sale=1
    WHERE status='valide'";
$changes[] = "INSERT INTO treasury_receipt_steps (receipt_id, step, amount, comment, user_id, created_at)
    SELECT r.id, 'reprise', r.amount, 'Encaissement antérieur au circuit en 4 étapes (validation directe).', COALESCE(r.validated_by, r.created_by), COALESCE(r.validated_at, r.created_at)
    FROM treasury_receipts r
    WHERE NOT EXISTS (SELECT 1 FROM treasury_receipt_steps s WHERE s.receipt_id=r.id)";
$changes[] = "ALTER TABLE treasury_receipts ALTER status SET DEFAULT 'constate'";

foreach ($changes as $sql) {
    $libelle = preg_replace('/\s+/', ' ', $sql);
    if ($apply) {
        try { $pdo->exec($sql); }
        catch (PDOException $e) {
            // Les reprises dépendent des tables créées plus haut : en aperçu elles ne sont pas exécutées.
            fwrite(STDERR, 'ERREUR ' . $e->getMessage() . ' :: ' . substr($libelle, 0, 140) . PHP_EOL); exit(1);
        }
    }
    echo ($apply ? 'OK ' : 'PREVIEW ') . substr($libelle, 0, 160) . (strlen($libelle) > 160 ? '…' : '') . PHP_EOL;
}
if (!$apply) { echo "Aucune modification. Ajouter --apply pour appliquer.\n"; }
