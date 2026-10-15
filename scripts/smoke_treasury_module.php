<?php
/**
 * Smoke test du module Tresorerie AMM-ERP.
 *
 * Ce script cree des donnees minimales, verifie le flux complet, puis nettoie
 * ses donnees de test. Il doit etre lance en CLI depuis la racine du projet:
 * /Applications/XAMPP/xamppfiles/bin/php scripts/smoke_treasury_module.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/FinancialTracking.php';
require_once __DIR__ . '/../models/Treasury.php';

$pdo = getDB();
$treasury = new Treasury();

$token = 'SMOKE-TREASURY-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
$ids = [
    'accounts' => [],
    'receipts' => [],
    'requests' => [],
    'orders' => [],
    'payments' => [],
    'transactions' => [],
    'cash_sessions' => [],
    'bank_statements' => [],
    'bank_lines' => [],
    'reconciliations' => [],
];

function smokeAssert(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function smokeDeleteIn(PDO $pdo, string $table, string $column, array $ids): void {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (empty($ids)) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$column} IN ({$placeholders})");
    $stmt->execute($ids);
}

function smokeInsertAccount(PDO $pdo, string $name, string $type, string $currency, string $reference): int {
    $stmt = $pdo->prepare(
        "INSERT INTO financial_accounts (nom, type_compte, devise, reference_compte, actif)
         VALUES (?, ?, ?, ?, 1)"
    );
    $stmt->execute([$name, $type, $currency, $reference]);
    return (int)$pdo->lastInsertId();
}

try {
    $userId = (int)($pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);
    smokeAssert($userId > 0, 'Aucun utilisateur disponible pour le smoke test.');

    $categoryId = (int)($pdo->query("SELECT id FROM financial_categories WHERE type_flux = 'depense' AND actif = 1 ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);
    smokeAssert($categoryId > 0, 'Aucune categorie de depense disponible pour le smoke test.');

    $bankAccountId = smokeInsertAccount($pdo, $token . ' Banque USD', 'banque', 'USD', $token . '-BANK');
    $cashAccountId = smokeInsertAccount($pdo, $token . ' Caisse USD', 'caisse', 'USD', $token . '-CASH');
    $ids['accounts'][] = $bankAccountId;
    $ids['accounts'][] = $cashAccountId;

    // 1. Creation encaissement
    $receiptId = $treasury->creerEncaissement([
        'receipt_number' => 'REC-' . $token,
        'date_receipt' => date('Y-m-d'),
        'payer_name' => 'Client smoke treasury',
        'objet' => 'Encaissement smoke test ' . $token,
        'amount' => 150.00,
        'currency' => 'USD',
        'exchange_rate' => null,
        'payment_method' => 'especes',
        'account_id' => $cashAccountId,
        'reference_payment' => $token . '-CASH-IN',
        'notes' => 'Smoke test',
        'created_by' => $userId,
    ]);
    $ids['receipts'][] = $receiptId;
    smokeAssert($receiptId > 0, 'Creation encaissement echouee.');

    // 2. Validation encaissement + 3. creation transaction revenu
    smokeAssert($treasury->validerEncaissement($receiptId, $userId), 'Validation encaissement echouee.');
    $receipt = $treasury->trouverEncaissementParId($receiptId);
    smokeAssert(($receipt['status'] ?? '') === 'valide', 'Encaissement non valide apres validation.');
    smokeAssert((int)($receipt['financial_transaction_id'] ?? 0) > 0, 'Transaction revenu non creee.');
    $ids['transactions'][] = (int)$receipt['financial_transaction_id'];

    // Alimenter aussi le compte bancaire utilisé pour le décaissement : aucun découvert implicite.
    $bankReceiptId = $treasury->creerEncaissement([
        'receipt_number' => 'REC-BANK-' . $token,
        'date_receipt' => date('Y-m-d', strtotime('-1 day')),
        'payer_name' => 'Alimentation banque smoke',
        'objet' => 'Provision isolée du compte bancaire de test',
        'amount' => 500.00, 'currency' => 'USD', 'payment_method' => 'banque',
        'account_id' => $bankAccountId, 'created_by' => $userId,
    ]);
    $ids['receipts'][] = $bankReceiptId;
    smokeAssert($treasury->validerEncaissement($bankReceiptId, $userId), 'Alimentation du compte bancaire échouée.');
    $bankReceipt = $treasury->trouverEncaissementParId($bankReceiptId);
    $ids['transactions'][] = (int)$bankReceipt['financial_transaction_id'];

    // 4. Creation demande de fonds
    $requestId = $treasury->creerDemandeFonds([
        'request_number' => 'DF-' . $token,
        'request_date' => date('Y-m-d'),
        'beneficiary' => 'Fournisseur smoke treasury',
        'objet' => 'Demande smoke test ' . $token,
        'description' => 'Demande generee par smoke test',
        'amount' => 80.00,
        'currency' => 'USD',
        'exchange_rate' => null,
        'category_id' => $categoryId,
        'preferred_account_id' => $bankAccountId,
        'urgency' => 'moyenne',
        'justification_file' => 'smoke-test.pdf',
        'created_by' => $userId,
    ]);
    $ids['requests'][] = $requestId;
    smokeAssert($requestId > 0, 'Creation demande de fonds echouee.');
    smokeAssert($treasury->soumettreDemande($requestId, $userId), 'Soumission demande echouee.');

    // 5. Validation demande (montant sous seuil: validation comptable suffit)
    smokeAssert($treasury->validerDemande($requestId, 'comptable', $userId, 'Validation smoke'), 'Validation demande echouee.');
    $request = $treasury->trouverDemandeParId($requestId);
    smokeAssert(($request['status'] ?? '') === 'validee_direction', 'Demande non prete pour ordre apres validation.');

    // 6. Emission ordre paiement
    $orderId = $treasury->emettreOrdrePaiement($requestId, [
        'order_number' => 'OP-' . $token,
        'order_date' => date('Y-m-d'),
        'payment_method' => 'banque',
        'account_id' => $bankAccountId,
        'payment_reference' => $token . '-PAY-ORDER',
        'instructions' => 'Ordre genere par smoke test',
        'issued_by' => $userId,
    ]);
    $ids['orders'][] = $orderId;
    smokeAssert($orderId > 0, 'Emission ordre de paiement echouee.');

    // 7. Execution paiement + 8. creation transaction depense
    $paymentId = $treasury->executerPaiement($orderId, [
        'payment_date' => date('Y-m-d'),
        'reference_payment' => $token . '-PAYMENT',
        'proof_file' => 'smoke-payment.pdf',
        'notes' => 'Paiement smoke test',
        'executed_by' => $userId,
    ]);
    $ids['payments'][] = $paymentId;
    smokeAssert($paymentId > 0, 'Execution paiement echouee.');

    $paymentStmt = $pdo->prepare("SELECT financial_transaction_id FROM treasury_payments WHERE id = ?");
    $paymentStmt->execute([$paymentId]);
    $expenseTransactionId = (int)($paymentStmt->fetchColumn() ?: 0);
    smokeAssert($expenseTransactionId > 0, 'Transaction depense non creee.');
    $ids['transactions'][] = $expenseTransactionId;

    // 9. Ouverture / cloture caisse
    $cashDate = date('Y-m-d');
    $cashSessionId = $treasury->ouvrirSessionCaisse($cashAccountId, $cashDate, $userId);
    $ids['cash_sessions'][] = $cashSessionId;
    smokeAssert($cashSessionId > 0, 'Ouverture caisse echouee.');
    smokeAssert($treasury->recalculerSessionCaisse($cashAccountId, $cashDate), 'Recalcul caisse echoue.');
    $cashSession = $treasury->trouverSessionCaisse($cashAccountId, $cashDate);
    smokeAssert($cashSession && (float)$cashSession['cash_in'] >= 150.00, 'Mouvement caisse non repris.');
    smokeAssert($treasury->cloturerSessionCaisse($cashSessionId, (float)$cashSession['theoretical_balance'], $userId, 'Cloture smoke'), 'Cloture caisse echouee.');

    // 10. Creation releve bancaire
    $statementId = $treasury->creerReleveBancaire([
        'account_id' => $bankAccountId,
        'period_year' => 2099,
        'period_month' => 12,
        'statement_number' => 'RB-' . $token,
        'opening_balance' => 500.00,
        'closing_balance' => 420.00,
        'currency' => 'USD',
        'statement_file' => 'smoke-statement.pdf',
        'created_by' => $userId,
    ]);
    $ids['bank_statements'][] = $statementId;
    smokeAssert($statementId > 0, 'Creation releve bancaire echouee.');

    $lineId = $treasury->ajouterLigneReleve($statementId, [
        'operation_date' => date('Y-m-d'),
        'label' => 'Paiement fournisseur smoke ' . $token,
        'reference_operation' => $token . '-BANK-LINE',
        'debit' => 80.00,
        'credit' => 0.00,
        'line_comment' => 'Ligne smoke test',
    ]);
    $ids['bank_lines'][] = $lineId;
    smokeAssert($lineId > 0, 'Creation ligne de releve echouee.');

    // 11. Rapprochement d'une ligne
    smokeAssert($treasury->rapprocherLigne($lineId, $expenseTransactionId, $userId), 'Rapprochement ligne bancaire echoue.');
    $lineStmt = $pdo->prepare("SELECT reconciliation_id, status FROM treasury_bank_statement_lines WHERE id = ?");
    $lineStmt->execute([$lineId]);
    $line = $lineStmt->fetch();
    smokeAssert(($line['status'] ?? '') === 'rapproche', 'Ligne bancaire non rapprochee.');
    $ids['reconciliations'][] = (int)$line['reconciliation_id'];

    echo json_encode([
        'status' => 'ok',
        'token' => $token,
        'checks' => [
            'receipt_id' => $receiptId,
            'receipt_transaction_id' => (int)$receipt['financial_transaction_id'],
            'fund_request_id' => $requestId,
            'payment_order_id' => $orderId,
            'payment_id' => $paymentId,
            'expense_transaction_id' => $expenseTransactionId,
            'cash_session_id' => $cashSessionId,
            'bank_statement_id' => $statementId,
            'bank_line_id' => $lineId,
            'reconciliation_id' => (int)$line['reconciliation_id'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'SMOKE_TREASURY_ERROR: ' . $e->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    try {
        smokeDeleteIn($pdo, 'treasury_cash_counts', 'cash_session_id', $ids['cash_sessions']);
        smokeDeleteIn($pdo, 'treasury_bank_statement_lines', 'id', $ids['bank_lines']);
        smokeDeleteIn($pdo, 'treasury_bank_reconciliations', 'id', $ids['reconciliations']);
        smokeDeleteIn($pdo, 'treasury_bank_statements', 'id', $ids['bank_statements']);
        smokeDeleteIn($pdo, 'treasury_cash_sessions', 'id', $ids['cash_sessions']);
        smokeDeleteIn($pdo, 'treasury_payments', 'id', $ids['payments']);
        smokeDeleteIn($pdo, 'treasury_payment_orders', 'id', $ids['orders']);
        smokeDeleteIn($pdo, 'treasury_request_approvals', 'request_id', $ids['requests']);
        smokeDeleteIn($pdo, 'treasury_fund_requests', 'id', $ids['requests']);
        smokeDeleteIn($pdo, 'treasury_receipts', 'id', $ids['receipts']);
        smokeDeleteIn($pdo, 'financial_transactions', 'id', $ids['transactions']);
        smokeDeleteIn($pdo, 'financial_accounts', 'id', $ids['accounts']);
    } catch (Throwable $cleanupError) {
        fwrite(STDERR, 'SMOKE_TREASURY_CLEANUP_ERROR: ' . $cleanupError->getMessage() . PHP_EOL);
        $exitCode = 1;
    }
}

exit($exitCode ?? 0);
