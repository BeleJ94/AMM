<?php
/** Test d'intégration du circuit d'encaissement en 4 étapes. Crée des données isolées et les supprime à la fin.
 * Usage : php scripts/smoke_encaissements_4_etapes.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI uniquement.'); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/FinancialAccountGuard.php';
require_once __DIR__ . '/../models/Treasury.php';
require_once __DIR__ . '/../models/FinancialTracking.php';
$pdo = getDB();
$treasury = new Treasury();
$finance = new FinancialTracking();
$token = 'RECEIPT-TEST-' . bin2hex(random_bytes(6));
$users = []; $receipts = []; $account = 0; $checks = 0;

function rcAssert(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
function rcRefuse(callable $action, string $message): void {
    try { $action(); } catch (DomainException $e) { rcAssert(true, $message); return; }
    throw new RuntimeException('Refus attendu : ' . $message);
}
function rcReceipt(int $id): array {
    global $treasury;
    return $treasury->trouverEncaissementParId($id) ?: [];
}

try {
    foreach (['agent', 'comptable_senior', 'directeur_financier', 'tresorier'] as $role) {
        $stmt = $pdo->prepare('INSERT INTO users (nom,email,password,role,service,actif) VALUES (?,?,?,?,?,1)');
        $stmt->execute([$token . ' ' . $role, $token . '-' . $role . '@example.invalid', bin2hex(random_bytes(30)), $role, $token]);
        $users[$role] = (int)$pdo->lastInsertId();
    }
    [$constateur, $liquidateur, $ordonnateur, $caissier] = array_values($users);
    $stmt = $pdo->prepare("INSERT INTO financial_accounts (nom,type_compte,devise,actif) VALUES (?,'banque','USD',1)");
    $stmt->execute([$token]); $account = (int)$pdo->lastInsertId();

    // 1. Constatation : aucune écriture, aucun compte.
    $id = $treasury->creerEncaissement(['payer_name' => $token, 'objet' => 'Facture test', 'amount' => 1000, 'currency' => 'USD', 'date_receipt' => date('Y-m-d'), 'created_by' => $constateur]);
    $receipts[] = $id;
    $r = rcReceipt($id);
    rcAssert($r['status'] === 'constate' && (float)$r['estimated_amount'] === 1000.0 && $r['account_id'] === null, 'Constatation incorrecte.');

    // 2. Liquidation : séparation, motif obligatoire en cas d'écart.
    rcRefuse(fn() => $treasury->liquiderEncaissement($id, $constateur, 1000), 'Le constateur ne liquide pas.');
    rcRefuse(fn() => $treasury->liquiderEncaissement($id, $liquidateur, 900), 'Écart sans motif refusé.');
    rcRefuse(fn() => $treasury->ordonnancerEncaissement($id, $ordonnateur), 'Pas d’ordonnancement avant liquidation.');
    $treasury->liquiderEncaissement($id, $liquidateur, 900, 'Remise commerciale');
    $r = rcReceipt($id);
    rcAssert($r['status'] === 'liquide' && (float)$r['amount'] === 900.0 && (float)$r['amount_usd'] === 900.0, 'Liquidation incorrecte.');

    // 3. Ordonnancement : titre de recette.
    rcRefuse(fn() => $treasury->ordonnancerEncaissement($id, $liquidateur), 'Le liquidateur n’ordonnance pas.');
    rcRefuse(fn() => $treasury->recouvrerEncaissement($id, $caissier, ['amount' => 100, 'payment_method' => 'banque', 'account_id' => $account]), 'Pas de recouvrement avant ordonnancement.');
    $treasury->ordonnancerEncaissement($id, $ordonnateur, date('Y-m-d', strtotime('+30 days')));
    $r = rcReceipt($id);
    rcAssert($r['status'] === 'ordonnance' && strpos((string)$r['title_number'], 'TR-') === 0 && !empty($r['due_date']), 'Ordonnancement incorrect.');

    // 4. Recouvrement partiel puis total.
    $versement = ['payment_method' => 'banque', 'account_id' => $account, 'collection_date' => date('Y-m-d'), 'reference_payment' => 'VIR-1'];
    rcRefuse(fn() => $treasury->recouvrerEncaissement($id, $ordonnateur, $versement + ['amount' => 100]), 'L’ordonnateur ne recouvre pas.');
    rcRefuse(fn() => $treasury->recouvrerEncaissement($id, $caissier, $versement + ['amount' => 901]), 'Dépassement du reste refusé.');
    rcRefuse(fn() => $treasury->recouvrerEncaissement($id, $caissier, ['amount' => 100, 'payment_method' => 'especes', 'account_id' => $account]), 'Mode incompatible avec le compte refusé.');
    $treasury->recouvrerEncaissement($id, $caissier, $versement + ['amount' => 400]);
    $r = rcReceipt($id);
    rcAssert($r['status'] === 'partiellement_recouvre' && (float)$r['amount_collected'] === 400.0 && (float)$r['remaining_amount'] === 500.0, 'Recouvrement partiel incorrect.');
    rcAssert($treasury->annulerEncaissement($id, $liquidateur, 'test') === false, 'Annulation interdite après un versement.');
    $treasury->recouvrerEncaissement($id, $caissier, $versement + ['amount' => 500]);
    $r = rcReceipt($id);
    rcAssert($r['status'] === 'recouvre' && (float)$r['amount_collected'] === 900.0 && !empty($r['recovered_at']), 'Recouvrement total incorrect.');
    $versements = $treasury->versementsEncaissement($id);
    rcAssert(count($versements) === 2 && $versements[1]['collection_number'] === $r['receipt_number'] . '/02', 'Numérotation des versements incorrecte.');
    $stmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(montant),0) FROM financial_transactions WHERE compte_id=? AND type_flux='revenu' AND statut='valide'");
    $stmt->execute([$account]); [$nbTx, $totalTx] = $stmt->fetch(PDO::FETCH_NUM);
    rcAssert((int)$nbTx === 2 && (float)$totalTx === 900.0, 'Une écriture par versement est attendue.');

    // 5. Annulation de l'écriture d'un versement : le reste à recouvrer est rétabli.
    $finance->annulerTransaction((int)$versements[1]['financial_transaction_id'], $liquidateur, 'Chèque rejeté');
    $r = rcReceipt($id);
    rcAssert($r['status'] === 'partiellement_recouvre' && (float)$r['amount_collected'] === 400.0, 'L’annulation d’écriture doit rétablir le reste à recouvrer.');
    rcAssert($treasury->versementsEncaissement($id)[1]['status'] === 'annule', 'Le versement doit être marqué annulé.');
    $etapes = array_column($treasury->etapesEncaissement($id), 'step');
    rcAssert($etapes === ['constatation', 'liquidation', 'ordonnancement', 'recouvrement', 'recouvrement'], 'Journal des étapes incorrect.');

    // 6. Renvoi et annulation avant recouvrement.
    $id2 = $treasury->creerEncaissement(['payer_name' => $token, 'objet' => 'Renvoi', 'amount' => 50, 'currency' => 'USD', 'date_receipt' => date('Y-m-d'), 'created_by' => $constateur]);
    $receipts[] = $id2;
    $treasury->liquiderEncaissement($id2, $liquidateur, 50);
    rcRefuse(fn() => $treasury->renvoyerEncaissement($id2, $ordonnateur, ''), 'Motif de renvoi obligatoire.');
    $treasury->renvoyerEncaissement($id2, $ordonnateur, 'Pièce manquante');
    rcAssert(rcReceipt($id2)['status'] === 'constate', 'Le renvoi doit revenir à la constatation.');
    rcAssert($treasury->annulerEncaissement($id2, $liquidateur, 'Doublon') === true && rcReceipt($id2)['status'] === 'annule', 'Annulation avant recouvrement attendue.');

    // 7. Encaissement comptant : circuit complet, une écriture, marqué comptant.
    $id3 = $treasury->encaisserComptant(['payer_name' => $token, 'objet' => 'Guichet', 'amount' => 75, 'currency' => 'USD', 'date_receipt' => date('Y-m-d'),
        'payment_method' => 'banque', 'account_id' => $account, 'reference_payment' => 'G-1'], $caissier);
    $receipts[] = $id3;
    $r = rcReceipt($id3);
    rcAssert($r['status'] === 'recouvre' && (int)$r['is_cash_sale'] === 1 && (float)$r['amount_collected'] === 75.0 && !empty($r['title_number']), 'Encaissement comptant incorrect.');
    // Annuler l'unique écriture d'un comptant clôture l'encaissement, comme avant le circuit en 4 étapes.
    $finance->annulerTransaction((int)$treasury->versementsEncaissement($id3)[0]['financial_transaction_id'], $liquidateur, 'Erreur de saisie');
    rcAssert(rcReceipt($id3)['status'] === 'annule', 'Un comptant sans écriture valide doit être annulé.');

    // 8. Compatibilité : validation directe d'une créance portant déjà son compte.
    $id4 = $treasury->creerEncaissement(['payer_name' => $token, 'objet' => 'Ancien flux', 'amount' => 20, 'currency' => 'USD', 'date_receipt' => date('Y-m-d'),
        'payment_method' => 'banque', 'account_id' => $account, 'created_by' => $constateur]);
    $receipts[] = $id4;
    rcAssert($treasury->validerEncaissement($id4, $caissier) === true && rcReceipt($id4)['status'] === 'recouvre', 'La validation directe doit recouvrer en une fois.');

    // 9. Filtres du registre.
    $ids = array_map('intval', array_column($treasury->listerEncaissements(['status' => 'a_recouvrer', 'recherche' => $token], 50, 0), 'id'));
    rcAssert($ids === [$id], 'Le filtre « À recouvrer » doit isoler les titres non soldés.');

    echo json_encode(['status' => 'ok', 'checks' => $checks], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['status' => 'echec', 'checks' => $checks, 'erreur' => $e->getMessage(), 'ligne' => $e->getLine()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    $echec = true;
} finally {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    if ($receipts) {
        $in = implode(',', array_map('intval', $receipts));
        $pdo->exec("DELETE FROM treasury_receipt_steps WHERE receipt_id IN ($in)");
        $pdo->exec("DELETE FROM treasury_receipt_collections WHERE receipt_id IN ($in)");
        $pdo->exec("DELETE FROM treasury_receipts WHERE id IN ($in)");
    }
    if ($account) {
        $pdo->prepare('DELETE FROM financial_transactions WHERE compte_id=?')->execute([$account]);
        $pdo->prepare('DELETE FROM financial_accounts WHERE id=?')->execute([$account]);
    }
    if ($users) {
        $in = implode(',', array_map('intval', $users));
        $pdo->exec("DELETE FROM audit_logs WHERE user_id IN ($in)");
        $pdo->exec("DELETE FROM users WHERE id IN ($in)");
    }
}
exit(!empty($echec) ? 1 : 0);
