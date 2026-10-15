<?php
/** Focused integration test. Creates isolated fixtures and deletes them in finally. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI uniquement.'); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Treasury.php';
$pdo = getDB();
$treasury = new Treasury();
$token = 'REQUEST-TEST-' . bin2hex(random_bytes(8));
$users = []; $requests = []; $orders = []; $transactions = []; $payments = []; $account = 0; $cdfAccount = 0; $checks = 0;
function requestTestAssert(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
try {
    foreach (['agent','tresorier','comptable_senior','directeur_financier','directeur_general'] as $role) {
        $stmt = $pdo->prepare('INSERT INTO users (nom,email,password,role,service,actif) VALUES (?,?,?,?,?,1)');
        $stmt->execute([$token . ' ' . $role, $token . '-' . $role . '@example.invalid', bin2hex(random_bytes(30)), $role, $token]);
        $users[$role] = (int)$pdo->lastInsertId();
    }
    $stmt = $pdo->prepare("INSERT INTO financial_accounts (nom,type_compte,devise,actif) VALUES (?,'banque','USD',1)");
    $stmt->execute([$token]); $account = (int)$pdo->lastInsertId();
    $normalized = $treasury->normaliserLignesDemande([
        ['description'=>'Matériel','quantity'=>'2','unit_price'=>'37.25','line_total'=>1],
        ['description'=>'Service','quantity'=>'1.5','unit_price'=>'10'],
    ]);
    requestTestAssert($normalized['total'] === 89.5, 'Line totals must be recalculated.');
    foreach ([[], [['description'=>'','quantity'=>1,'unit_price'=>1]], [['description'=>'Erreur','quantity'=>-1,'unit_price'=>10]], [['description'=>'Erreur','quantity'=>1,'unit_price'=>0]]] as $bad) {
        $rejected = false;
        try { $treasury->normaliserLignesDemande($bad); } catch (InvalidArgumentException $e) { $rejected = true; }
        requestTestAssert($rejected, 'Invalid request lines accepted.');
    }
    $amount = round(max(2000, $treasury->seuilValidationDirectionUsd() * 2), 2);
    $fund = function (int $accountId, float $montant, string $currency, ?float $rate = null) use ($pdo, $token, &$transactions): int {
        $category = (int)$pdo->query("SELECT id FROM financial_categories WHERE actif=1 AND type_flux='revenu' ORDER BY id LIMIT 1")->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO financial_transactions (reference_unique,date_operation,type_flux,categorie_id,compte_id,montant,devise,taux_change,montant_usd,statut) VALUES (?,CURDATE(),'revenu',?,?,?,?,?,?,'valide')");
        $stmt->execute([$token . '-FUND-' . count($transactions), $category, $accountId, $montant, $currency, $rate, $currency === 'CDF' ? $montant / $rate : $montant]);
        $transactions[] = (int)$pdo->lastInsertId();
        return (int)$pdo->lastInsertId();
    };
    $funding = $fund($account, round($amount * .75, 2), 'USD');
    $create = function (string $suffix, float $montant = 0, string $currency = 'USD', ?int $creator = null, float $rate = 2850) use ($treasury, $token, $users, $amount, &$requests): int {
        $montant = $montant ?: $amount;
        $id = $treasury->creerDemandeFonds([
            'request_number'=>$token . '-' . $suffix, 'request_date'=>date('Y-m-d'),
            'beneficiary'=>'Fournisseur test', 'objet'=>'Achat de matériel', 'description'=>'Justification du besoin',
            'department'=>$token, 'requester_name'=>'Demandeur test', 'amount'=>1, 'currency'=>$currency,
            'exchange_rate'=>$currency === 'CDF' ? $rate : null,
            'items'=>[['description'=>'Biens demandés','quantity'=>2,'unit_price'=>$montant / 2]],
            'created_by'=>$creator ?? $users['agent'],
        ]);
        $requests[] = $id;
        return $id;
    };
    $id = $create('MAIN');
    requestTestAssert(count($treasury->itemsDemande($id)) === 1, 'Structured lines missing.');
    requestTestAssert((float)$treasury->trouverDemandeParId($id)['amount'] === $amount, 'Posted amount must not override item total.');
    requestTestAssert($treasury->soumettreDemande($id, $users['agent']), 'Department submission failed.');
    requestTestAssert($treasury->trouverDemandeParId($id)['status'] === 'a_controler', 'Treasurer control step missing.');
    requestTestAssert(!$treasury->deciderDemande($id, 'comptable', $users['comptable_senior'], 'valide'), 'Bypassed treasurer control.');
    requestTestAssert($treasury->controlerDemande($id, $users['tresorier'], 'Contrôle effectué'), 'Treasurer control failed.');
    requestTestAssert(!$treasury->controlerDemande($id, $users['tresorier']), 'Duplicate treasurer control accepted.');
    requestTestAssert(!$treasury->deciderDemande($id, 'comptable', $users['agent'], 'valide'), 'Creator approved own request.');
    requestTestAssert(!$treasury->deciderDemande($id, 'comptable', $users['tresorier'], 'valide'), 'Submitting treasurer approved own request.');
    requestTestAssert(!$treasury->deciderDemande($id, 'comptable', $users['comptable_senior'], 'en_suspens', '' ,null,date('Y-m-d')), 'Suspension without reason accepted.');
    requestTestAssert(!$treasury->deciderDemande($id, 'comptable', $users['comptable_senior'], 'en_suspens', 'Attente',null,'2020-02-30'), 'Invalid reminder accepted.');
    requestTestAssert($treasury->deciderDemande($id, 'comptable', $users['comptable_senior'], 'en_suspens', 'Document complémentaire attendu',null,date('Y-m-d')), 'Suspension failed.');
    requestTestAssert(!$treasury->deciderDemande($id, 'direction_financiere', $users['directeur_financier'], 'valide'), 'Held request advanced.');
    $sent = $treasury->envoyerRappelsDemandes($id);
    requestTestAssert($sent >= 2, 'Reminder recipients missing.');
    requestTestAssert($treasury->envoyerRappelsDemandes($id) === 0, 'Daily reminders were duplicated.');
    requestTestAssert(!$treasury->reprendreDemande($id, 'direction_financiere', $users['directeur_financier']), 'Wrong level resumed suspension.');
    requestTestAssert($treasury->reprendreDemande($id, 'comptable', $users['comptable_senior'], 'Pièce reçue'), 'Resume failed.');
    requestTestAssert($treasury->deciderDemande($id, 'comptable', $users['comptable_senior'], 'en_suspens', 'Nouvelle vérification',null,date('Y-m-d')), 'Repeated hold was not recorded.');
    requestTestAssert($treasury->reprendreDemande($id, 'comptable', $users['comptable_senior']), 'Repeated resume failed.');
    $cap = round($amount * .75, 2);
    requestTestAssert(!$treasury->deciderDemande($id, 'comptable', $users['comptable_senior'], 'valide', '', $cap), 'Partial approval without justification accepted.');
    requestTestAssert($treasury->deciderDemande($id, 'comptable', $users['comptable_senior'], 'valide', 'Budget retenu pour les biens prioritaires', $cap), 'Partial approval failed.');
    requestTestAssert(!$treasury->deciderDemande($id, 'comptable', $users['comptable_senior'], 'valide'), 'Duplicate approval accepted.');
    requestTestAssert(!$treasury->deciderDemande($id, 'direction_financiere', $users['directeur_financier'], 'valide', 'Augmentation', $amount), 'Approval cap increased.');
    requestTestAssert($treasury->deciderDemande($id, 'direction_financiere', $users['directeur_financier'], 'valide', 'Accord DAF', $cap), 'DAF approval failed.');
    requestTestAssert($treasury->trouverDemandeParId($id)['status'] === 'validee_comptable', 'DG approval bypassed.');
    requestTestAssert($treasury->deciderDemande($id, 'direction_generale', $users['directeur_general'], 'valide', 'Accord DG', $cap), 'DG approval failed.');
    $request = $treasury->trouverDemandeParId($id);
    requestTestAssert($request['status'] === 'validee_direction' && (float)$request['amount'] === $amount && (float)$request['approved_amount'] === $cap, 'Original and approved amounts not preserved.');
    $orderData = ['order_number'=>$token . '-OP','account_id'=>$account,'payment_method'=>'banque','issued_by'=>$users['directeur_financier']];
    requestTestAssert($treasury->emettreOrdrePaiement($id, $orderData + ['amount'=>$amount]) === 0, 'Order exceeds approved amount.');
    $orderId = $treasury->emettreOrdrePaiement($id, $orderData + ['amount_usd'=>999999]);
    $orders[] = $orderId;
    $order = $treasury->trouverOrdreParId($orderId);
    requestTestAssert($orderId > 0 && (float)$order['amount'] === $cap && (float)$order['amount_usd'] === $cap, 'Order did not use approved cap or trusted posted USD.');
    requestTestAssert($treasury->emettreOrdrePaiement($id, $orderData) === 0, 'Duplicate order accepted.');
    $defaultRate = max(1, (float)$treasury->getSetting('default_exchange_rate_cdf_usd', '2000'));
    $requestRate = $defaultRate + 1000;
    $cdf = $create('CDF', $requestRate * 100, 'CDF', null, $requestRate);
    requestTestAssert((float)$treasury->trouverDemandeParId($cdf)['amount_usd'] === 100.0, 'CDF conversion failed.');
    $treasury->soumettreDemande($cdf,$users['agent']); $treasury->controlerDemande($cdf,$users['tresorier']);
    requestTestAssert($treasury->deciderDemande($cdf,'comptable',$users['comptable_senior'],'valide','Périmètre réduit',$requestRate * 50), 'CDF partial approval failed.');
    requestTestAssert((float)$treasury->trouverDemandeParId($cdf)['approved_amount_usd'] === 50.0, 'CDF approved USD amount incorrect.');
    requestTestAssert($treasury->emettreOrdrePaiement($cdf, ['order_number'=>$token . '-CDF-OP','account_id'=>$account,'payment_method'=>'banque','issued_by'=>$users['directeur_financier']]) === 0, 'CDF request emitted on USD account.');
    $stmt = $pdo->prepare("INSERT INTO financial_accounts (nom,type_compte,devise,actif) VALUES (?,'banque','CDF',1)");
    $stmt->execute([$token . '-CDF']); $cdfAccount = (int)$pdo->lastInsertId();
    $cdfFunding = $fund($cdfAccount, $defaultRate * 50, 'CDF', $defaultRate);
    $cdfData = ['order_number'=>$token . '-CDF-OP','account_id'=>$cdfAccount,'payment_method'=>'banque','issued_by'=>$users['directeur_financier']];
    requestTestAssert($treasury->emettreOrdrePaiement($cdf, $cdfData) === 0, 'CDF native balance was calculated with the default rate instead of the request amount.');
    $pdo->prepare('UPDATE financial_transactions SET montant=?, montant_usd=? WHERE id=?')->execute([$requestRate * 50, 50, $cdfFunding]);
    $cdfOrder = $treasury->emettreOrdrePaiement($cdf, $cdfData); $orders[] = $cdfOrder;
    requestTestAssert($cdfOrder > 0 && (float)$treasury->trouverOrdreParId($cdfOrder)['amount'] === $requestRate * 50, 'CDF native approved amount emission failed.');
    $own = $create('OWN', 80, 'USD', $users['tresorier']);
    $treasury->soumettreDemande($own,$users['tresorier']);
    requestTestAssert($treasury->controlerDemande($own,$users['tresorier']), 'Treasurer cannot control own created request.');
    requestTestAssert($treasury->deciderDemande($own,'comptable',$users['comptable_senior'],'valide'), 'Independent accountant cannot approve treasurer request.');
    requestTestAssert($treasury->emettreOrdrePaiement($own, ['order_number'=>$token . '-OWN-OP','account_id'=>$account,'payment_method'=>'banque','issued_by'=>$users['directeur_financier']]) === 0, 'Pending orders were not reserved against the native balance.');
    $pdo->prepare("UPDATE treasury_payment_orders SET currency='CDF' WHERE id=?")->execute([$orderId]);
    requestTestAssert($treasury->executerPaiement($orderId,['executed_by'=>$users['tresorier']]) === 0, 'Currency mismatch execution accepted.');
    $pdo->prepare("UPDATE treasury_payment_orders SET currency='USD' WHERE id=?")->execute([$orderId]);
    $pdo->prepare('UPDATE financial_transactions SET montant=?, montant_usd=? WHERE id=?')->execute([$cap - 1,$cap - 1,$funding]);
    requestTestAssert($treasury->executerPaiement($orderId,['executed_by'=>$users['tresorier']]) === 0, 'Execution exceeded the current native balance.');
    $pdo->prepare('UPDATE financial_transactions SET montant=?, montant_usd=? WHERE id=?')->execute([$cap,$cap,$funding]);
    $payment = $treasury->executerPaiement($orderId,['executed_by'=>$users['tresorier'],'proof_file'=>'test.pdf']); $payments[] = $payment;
    requestTestAssert($payment > 0, 'Funded approved order execution failed.');
    if ($payment) $transactions[] = (int)$treasury->trouverOrdreParId($orderId)['financial_transaction_id'];
    $cancel = $create('CANCEL');
    $treasury->soumettreDemande($cancel,$users['agent']); $treasury->controlerDemande($cancel,$users['tresorier']);
    requestTestAssert($treasury->deciderDemande($cancel,'comptable',$users['comptable_senior'],'annule','Besoin abandonné'), 'Cancellation failed.');
    requestTestAssert(!$treasury->deciderDemande($cancel,'comptable',$users['comptable_senior'],'valide'), 'Cancelled request resumed.');
    requestTestAssert($treasury->compterDemandes(['department'=>$token]) === count($requests), 'Department scope mismatch.');
    requestTestAssert($treasury->compterDemandes(['created_by'=>$users['agent']]) === count($requests) - 1, 'Creator scope mismatch.');
    requestTestAssert($treasury->compterDemandes(['department'=>$token . '-OTHER']) === 0, 'Department isolation failed.');
    requestTestAssert(count($treasury->listerDemandes(['department'=>$token],50,0)) === count($requests), 'Scoped list mismatch.');
    requestTestAssert(count($treasury->listerDemandes(['department'=>$token,'recherche'=>$token],50,0)) === count($requests), 'Department search mismatch.');
    echo json_encode(['status'=>'ok','checks'=>$checks],JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'REQUEST_TEST_ERROR: ' . $e->getMessage() . PHP_EOL); $exitCode = 1;
} finally {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    foreach ($payments as $paymentId) { $pdo->prepare('DELETE FROM treasury_payments WHERE id=?')->execute([$paymentId]); }
    foreach ($orders as $orderId) { $pdo->prepare('DELETE FROM treasury_payment_orders WHERE id=?')->execute([$orderId]); }
    foreach ($requests as $requestId) {
        $pdo->prepare('DELETE FROM notifications WHERE lien=?')->execute([APP_URL . '/treasury/demande-voir/' . $requestId]);
        $pdo->prepare('DELETE FROM treasury_fund_requests WHERE id=?')->execute([$requestId]);
    }
    foreach ($transactions as $transactionId) { $pdo->prepare('DELETE FROM financial_transactions WHERE id=?')->execute([$transactionId]); }
    if ($account) { $pdo->prepare('DELETE FROM financial_accounts WHERE id=?')->execute([$account]); }
    if ($cdfAccount) { $pdo->prepare('DELETE FROM financial_accounts WHERE id=?')->execute([$cdfAccount]); }
    foreach ($users as $userId) { $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]); }
}
exit($exitCode ?? 0);
