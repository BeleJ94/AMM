<?php
/** Tests d'intégration ; toutes les données de test sont annulées. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/FinancialTracking.php';
require_once __DIR__ . '/../models/Treasury.php';
$pdo = getDB();
function refuse(callable $action, string $label): void {
    try { $action(); } catch (DomainException $e) { echo "OK : $label\n"; return; }
    throw new RuntimeException("Échec : $label");
}
try {
    $pdo->beginTransaction();
    $finance = new FinancialTracking();
    $treasury = new Treasury();
    $account = ['nom'=>'TEST devise ' . bin2hex(random_bytes(6)), 'type_compte'=>'caisse', 'devise'=>'USD', 'reference_compte'=>''];
    $id = $finance->enregistrerCompte($account);
    $category = $pdo->query("SELECT id FROM financial_categories WHERE type_flux='revenu' LIMIT 1")->fetchColumn();
    if (!$category) throw new RuntimeException('Une catégorie revenu est nécessaire.');
    $data = ['compte_id'=>$id, 'devise'=>'CDF', 'montant'=>10, 'taux_change'=>2800, 'date_operation'=>date('Y-m-d'), 'type_flux'=>'revenu', 'categorie_id'=>$category, 'partenaire'=>'Test', 'description'=>'Test devise', 'piece_jointe'=>null];
    refuse(fn()=>$finance->creerTransaction($data), 'transaction CDF sur compte USD refusée');
    $receipt = ['account_id'=>$id, 'currency'=>'CDF', 'amount'=>10, 'exchange_rate'=>2800, 'payment_method'=>'especes', 'payer_name'=>'Test', 'objet'=>'Test devise', 'receipt_number'=>'TEST-' . bin2hex(random_bytes(6))];
    refuse(fn()=>$treasury->creerEncaissement($receipt), 'encaissement CDF sur compte USD refusé');
    $receipt['currency']='USD'; $receipt['payment_method']='banque';
    refuse(fn()=>$treasury->creerEncaissement($receipt), 'mode banque sur caisse refusé');
    $data['devise']='USD';
    $transactionId = $finance->creerTransaction($data);
    echo "OK : transaction USD sur compte USD créée\n";
    refuse(fn()=>$finance->enregistrerCompte(array_replace($account, ['devise'=>'CDF']), $id), 'changement de devise avec historique refusé');
    // Simuler un ancien brouillon incohérent pour tester la seconde barrière.
    $pdo->prepare("UPDATE financial_transactions SET devise='CDF' WHERE id=?")->execute([$transactionId]);
    refuse(fn()=>$finance->validerTransaction($transactionId, 0), 'validation ancien brouillon incohérent refusée');
    $receipt['payment_method']='especes';
    $receiptId = $treasury->creerEncaissement($receipt);
    echo "OK : encaissement USD sur caisse USD créé\n";
    $pdo->prepare("UPDATE treasury_receipts SET currency='CDF' WHERE id=?")->execute([$receiptId]);
    refuse(fn()=>$treasury->validerEncaissement($receiptId, 0), 'validation ancien encaissement incohérent refusée');
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Tests terminés ; données de test annulées.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(1);
}
