<?php
/** Focused CLI checks. Only temporary fixtures created here are removed afterwards. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI uniquement.'); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/FinancialTracking.php';
require_once __DIR__ . '/../models/Treasury.php';
require_once __DIR__ . '/../controllers/FinancialTrackingController.php';
$pdo = getDB();
$finance = new FinancialTracking();
$treasury = new Treasury();
$tag = 'SMOKE-CANCEL-' . bin2hex(random_bytes(6));
$fixtures = [];
$checks = 0;

function cancellationAssert(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
function cancellationInsert(string $table, array $data): int {
    global $pdo, $fixtures;
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', array_keys($data)) . '`) VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')';
    $pdo->prepare($sql)->execute(array_values($data));
    $id = (int)$pdo->lastInsertId();
    $fixtures[$table][] = $id;
    return $id;
}
function cancellationRow(string $table, int $id): array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM `' . $table . '` WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: [];
}
function cancellationExpectBlocked(callable $operation, string $message): void {
    try { $operation(); } catch (DomainException $e) { cancellationAssert($e->getMessage() !== '', $message); return; }
    throw new RuntimeException($message);
}

try {
    $columns = $pdo->query('SHOW COLUMNS FROM treasury_payments')->fetchAll(PDO::FETCH_UNIQUE);
    if (!isset($columns['cancelled_at'])) { throw new RuntimeException('Appliquer la migration migrate_treasury_requests.php avant ce test.'); }
    $userId = (int)$pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
    $categoryId = (int)$pdo->query("SELECT id FROM financial_categories WHERE type_flux = 'depense' LIMIT 1")->fetchColumn();
    cancellationAssert($userId > 0 && $categoryId > 0, 'Utilisateur et catégorie nécessaires.');
    $cashId = cancellationInsert('financial_accounts', ['nom'=>$tag.' Caisse','type_compte'=>'caisse','devise'=>'USD','actif'=>1]);
    $bankId = cancellationInsert('financial_accounts', ['nom'=>$tag.' Banque','type_compte'=>'banque','devise'=>'USD','actif'=>1]);
    $makeTransaction = function (int $accountId, string $type = 'depense') use ($tag, $categoryId, $userId): int {
        return cancellationInsert('financial_transactions', [
            'reference_unique'=>$tag.'-'.bin2hex(random_bytes(3)), 'date_operation'=>date('Y-m-d'),
            'type_flux'=>$type,'categorie_id'=>$categoryId,'compte_id'=>$accountId,'montant'=>25,
            'devise'=>'USD','montant_usd'=>25,'statut'=>'valide','created_by'=>$userId,
        ]);
    };

    // Rights are checked by the controller, including legacy Direction finance profiles.
    $controller = (new ReflectionClass(FinancialTrackingController::class))->newInstanceWithoutConstructor();
    $canCancel = new ReflectionMethod($controller, 'peutAnnulerTransaction');
    foreach (['admin'=>true,'directeur_financier'=>true,'directeur_general'=>true,'comptable_senior'=>false,'tresorier'=>false,'agent'=>false] as $role=>$expected) {
        $_SESSION = ['user_id'=>$userId,'user_role'=>$role,'user_service'=>'Finance'];
        cancellationAssert($canCancel->invoke($controller) === $expected, 'Droit d’annulation incorrect pour '.$role);
    }
    $_SESSION['user_role'] = 'direction';
    cancellationAssert($canCancel->invoke($controller), 'Direction finance historique non reconnue.');
    $_SESSION['user_service'] = 'Ressources humaines';
    cancellationAssert(!$canCancel->invoke($controller), 'Direction hors finance autorisée.');

    $plainId = $makeTransaction($bankId);
    cancellationExpectBlocked(fn()=>$finance->annulerTransaction($plainId,$userId,'   '), 'Motif vide accepté.');
    cancellationAssert($finance->annulerTransaction($plainId,$userId,'Correction de saisie temporaire'), 'Annulation simple refusée.');
    $plain = cancellationRow('financial_transactions',$plainId);
    cancellationAssert($plain['statut']==='annule' && (int)$plain['cancelled_by']===$userId && !empty($plain['cancelled_at']), 'Traçabilité incomplète.');
    cancellationAssert(!$finance->annulerTransaction($plainId,$userId,'Seconde tentative'), 'Double annulation acceptée.');

    $receiptTransactionId = $makeTransaction($cashId,'revenu');
    $receiptId = cancellationInsert('treasury_receipts',[
        'receipt_number'=>$tag.'-REC','date_receipt'=>date('Y-m-d'),'payer_name'=>'Test temporaire','objet'=>$tag,
        'amount'=>25,'currency'=>'USD','amount_usd'=>25,'payment_method'=>'especes','account_id'=>$cashId,
        'financial_transaction_id'=>$receiptTransactionId,'status'=>'valide','created_by'=>$userId,
    ]);
    $sessionId = cancellationInsert('treasury_cash_sessions',[
        'session_date'=>date('Y-m-d'),'cash_account_id'=>$cashId,'currency'=>'USD','opening_balance'=>0,
        'cash_in'=>25,'cash_out'=>0,'theoretical_balance'=>25,'status'=>'ouverte','opened_by'=>$userId,
    ]);
    cancellationAssert($finance->annulerTransaction($receiptTransactionId,$userId,'Annulation encaissement temporaire'), 'Annulation encaissement refusée.');
    cancellationAssert(cancellationRow('treasury_receipts',$receiptId)['status']==='annule', 'Encaissement non synchronisé.');
    cancellationAssert((float)cancellationRow('treasury_cash_sessions',$sessionId)['theoretical_balance']===0.0, 'Solde de caisse incohérent.');

    $requestId = cancellationInsert('treasury_fund_requests',[
        'request_number'=>$tag.'-REQ','request_date'=>date('Y-m-d'),'beneficiary'=>'Test temporaire','objet'=>$tag,
        'amount'=>25,'currency'=>'USD','amount_usd'=>25,'status'=>'payee','created_by'=>$userId,
    ]);
    $orderId = cancellationInsert('treasury_payment_orders',[
        'order_number'=>$tag.'-OP','request_id'=>$requestId,'order_date'=>date('Y-m-d'),'amount'=>25,'currency'=>'USD',
        'amount_usd'=>25,'payment_method'=>'especes','account_id'=>$cashId,'beneficiary'=>'Test temporaire','status'=>'execute','issued_by'=>$userId,
    ]);
    $paymentTransactionId = $makeTransaction($cashId);
    $paymentId = cancellationInsert('treasury_payments',[
        'payment_order_id'=>$orderId,'payment_date'=>date('Y-m-d'),'amount'=>25,'currency'=>'USD',
        'amount_usd'=>25,'financial_transaction_id'=>$paymentTransactionId,'executed_by'=>$userId,
    ]);
    $treasury->recalculerSessionCaisse($cashId,date('Y-m-d'));
    cancellationAssert((float)cancellationRow('treasury_cash_sessions',$sessionId)['cash_out']===25.0, 'Sortie de test absente.');
    cancellationAssert($finance->annulerTransaction($paymentTransactionId,$userId,'Annulation paiement temporaire'), 'Annulation paiement refusée.');
    cancellationAssert(!empty(cancellationRow('treasury_payments',$paymentId)['cancelled_at']), 'Paiement sans trace d’annulation.');
    cancellationAssert(cancellationRow('treasury_payment_orders',$orderId)['status']==='annule' && cancellationRow('treasury_fund_requests',$requestId)['status']==='annulee', 'Ordre/demande non synchronisés.');
    cancellationAssert((float)cancellationRow('treasury_cash_sessions',$sessionId)['cash_out']===0.0, 'Paiement annulé encore dans le solde.');
    cancellationAssert(count($treasury->mouvementsCaisse($cashId,date('Y-m-d')))===0, 'Opérations annulées encore dans le livre de caisse.');

    $closedTransactionId = $makeTransaction($cashId);
    $pdo->prepare("UPDATE treasury_cash_sessions SET status='cloturee' WHERE id=?")->execute([$sessionId]);
    cancellationExpectBlocked(fn()=>$finance->annulerTransaction($closedTransactionId,$userId,'Jour clôturé temporaire'), 'Journée clôturée modifiable.');
    cancellationAssert(cancellationRow('financial_transactions',$closedTransactionId)['statut']==='valide', 'Transaction clôturée altérée.');

    $matchedId = $makeTransaction($bankId);
    $statementId = cancellationInsert('treasury_bank_statements',[
        'account_id'=>$bankId,'period_year'=>(int)date('Y'),'period_month'=>(int)date('n'),'currency'=>'USD','created_by'=>$userId,
    ]);
    cancellationInsert('treasury_bank_statement_lines',[
        'statement_id'=>$statementId,'operation_date'=>date('Y-m-d'),'label'=>$tag,'debit'=>25,
        'matched_transaction_id'=>$matchedId,'status'=>'rapproche',
    ]);
    cancellationExpectBlocked(fn()=>$finance->annulerTransaction($matchedId,$userId,'Ligne rapprochée temporaire'), 'Transaction rapprochée modifiable.');
    cancellationAssert(cancellationRow('financial_transactions',$matchedId)['statut']==='valide', 'Rapprochement altéré.');
    echo "OK $checks contrôles : droits, motifs, historique, caisse, encaissement, paiement, ordre, demande et protections.\n";
} catch (Throwable $e) {
    fwrite(STDERR,'ÉCHEC : '.$e->getMessage().PHP_EOL);
    $exitCode = 1;
} finally {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    // Explicit ID allowlist, collected exclusively by inserts above.
    foreach (['treasury_bank_statement_lines','treasury_bank_statements','treasury_payments','treasury_payment_orders','treasury_fund_requests','treasury_receipts','treasury_cash_sessions','financial_transactions','financial_accounts'] as $table) {
        $ids = $fixtures[$table] ?? [];
        if (!$ids) { continue; }
        $pdo->prepare('DELETE FROM `'.$table.'` WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')')->execute($ids);
    }
}
exit($exitCode ?? 0);
