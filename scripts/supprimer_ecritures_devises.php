<?php
/** Correction ciblée des écritures 9 et 12. Aperçu par défaut ; --apply pour appliquer. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Treasury.php';

$apply = in_array('--apply', $argv, true);
$pdo = getDB();
try {
    $pdo->beginTransaction();
    $account = $pdo->query('SELECT * FROM financial_accounts WHERE id=3 FOR UPDATE')->fetch();
    if (!$account || $account['devise'] !== 'USD' || $account['type_compte'] !== 'caisse') {
        throw new RuntimeException('Le compte 3 ne correspond plus à la caisse USD attendue.');
    }
    $rows = $pdo->query('SELECT * FROM financial_transactions WHERE id IN (9,12) ORDER BY id FOR UPDATE')->fetchAll();
    if (!$rows) {
        $pdo->rollBack();
        echo "Aucune des deux écritures ne subsiste. Aucune modification.\n";
        exit;
    }
    $expected = [9 => ['273000.00', '2026-10-14'], 12 => ['2345000.00', '2026-10-16']];
    foreach ($rows as $row) {
        [$amount, $date] = $expected[(int)$row['id']];
        if ((int)$row['compte_id'] !== 3 || $row['devise'] !== 'CDF'
            || $row['statut'] !== 'valide' || $row['type_flux'] !== 'revenu'
            || $row['montant'] !== $amount || $row['date_operation'] !== $date
            || !empty($row['contrat_id'])) {
            throw new RuntimeException('Écriture modifiée ou liée à un contrat : arrêt sans modification.');
        }
    }
    foreach (['SELECT id FROM treasury_payments WHERE financial_transaction_id IN (9,12) FOR UPDATE',
              'SELECT id FROM treasury_bank_statement_lines WHERE matched_transaction_id IN (9,12) FOR UPDATE'] as $sql) {
        if ($pdo->query($sql)->fetch()) throw new RuntimeException('Paiement ou rapprochement lié : arrêt sans modification.');
    }
    $receipts = $pdo->query('SELECT * FROM treasury_receipts WHERE financial_transaction_id IN (9,12) FOR UPDATE')->fetchAll();
    foreach ($receipts as $receipt) {
        if (!in_array((int)$receipt['id'], [5,7], true) || (int)$receipt['account_id'] !== 3) {
            throw new RuntimeException('Encaissement lié inattendu : arrêt sans modification.');
        }
    }
    $sessions = $pdo->query("SELECT * FROM treasury_cash_sessions WHERE cash_account_id=3 AND session_date>='2026-10-14' ORDER BY session_date FOR UPDATE")->fetchAll();
    foreach ($sessions as $session) {
        if ($session['status'] !== 'ouverte') throw new RuntimeException('Une journée de caisse est clôturée : arrêt sans modification.');
    }
    foreach ($rows as $row) echo "Suppression prévue : #{$row['id']} / {$row['montant']} CDF / {$row['date_operation']}\n";
    echo count($receipts) . " encaissement(s) à annuler ; " . count($sessions) . " journée(s) à recalculer.\n";
    if (!$apply) {
        $pdo->rollBack();
        echo "Aperçu seulement. Ajouter --apply pour appliquer.\n";
        exit;
    }
    $backup = ['created_at'=>date(DATE_ATOM), 'account'=>$account, 'transactions'=>$rows, 'receipts'=>$receipts, 'cash_sessions'=>$sessions];
    $path = __DIR__ . '/../logs/sauvegarde_ecritures_9_12_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.json';
    if (file_put_contents($path, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
        throw new RuntimeException('Sauvegarde impossible : arrêt sans modification.');
    }
    $pdo->exec("UPDATE treasury_receipts SET status='annule', cancelled_at=NOW(), cancellation_reason='Suppression ciblée des écritures CDF 9 et 12 rattachées par erreur à la caisse USD' WHERE financial_transaction_id IN (9,12)");
    $pdo->exec('DELETE FROM financial_transactions WHERE id IN (9,12)');
    // Les clés étrangères détachent les encaissements annulés (ON DELETE SET NULL).
    $treasury = new Treasury();
    $opening = $pdo->prepare("UPDATE treasury_cash_sessions SET opening_balance=(SELECT COALESCE(SUM(CASE WHEN type_flux='revenu' THEN montant ELSE -montant END),0) FROM financial_transactions WHERE compte_id=3 AND statut='valide' AND date_operation<?) WHERE id=?");
    foreach ($sessions as $session) {
        $opening->execute([$session['session_date'], $session['id']]);
        if (!$treasury->recalculerSessionCaisse(3, $session['session_date'])) throw new RuntimeException('Recalcul de caisse impossible.');
    }
    $pdo->commit();
    echo "Correction effectuée. Sauvegarde : $path\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
