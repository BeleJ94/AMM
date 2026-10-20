<?php
/** Règles communes aux écritures et aux encaissements. */
class FinancialAccountGuard {
    public static function transaction(PDO $pdo, callable $action) {
        $owner = !$pdo->inTransaction();
        if ($owner) $pdo->beginTransaction();
        try {
            $result = $action();
            if ($owner) $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($owner && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function verifier(PDO $pdo, int $id, string $currency, ?string $method = null): void {
        $stmt = $pdo->prepare('SELECT devise, actif, type_compte FROM financial_accounts WHERE id=? FOR UPDATE');
        $stmt->execute([$id]);
        $account = $stmt->fetch();
        if (!$account || !(int)$account['actif']) throw new DomainException('Sélectionnez un compte actif.');
        if (!in_array($currency, ['USD','CDF'], true) || $account['devise'] !== $currency) {
            throw new DomainException('Vous enregistrez des ' . $currency . '. Sélectionnez un compte dans cette devise.');
        }
        if ($method !== null) {
            $type = ['especes'=>'caisse', 'banque'=>'banque', 'mobile_money'=>'mobile_money'][$method] ?? null;
            if ($type === null || $account['type_compte'] !== $type) throw new DomainException('Le compte ne correspond pas au mode de paiement choisi.');
        }
    }

    public static function verifierModification(PDO $pdo, int $id, string $currency): void {
        $stmt = $pdo->prepare('SELECT devise FROM financial_accounts WHERE id=? FOR UPDATE');
        $stmt->execute([$id]);
        $old = $stmt->fetchColumn();
        if ($old === false) throw new DomainException('Compte introuvable.');
        if ($old === $currency) return;
        foreach (['financial_transactions'=>'compte_id', 'treasury_receipts'=>'account_id', 'treasury_payment_orders'=>'account_id', 'treasury_cash_sessions'=>'cash_account_id', 'treasury_bank_statements'=>'account_id'] as $table=>$column) {
            $stmt = $pdo->prepare("SELECT id FROM $table WHERE $column=? LIMIT 1 FOR UPDATE");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn()) throw new DomainException('Ce compte possède un historique. Sa devise ne peut plus être modifiée. Créez un autre compte pour la nouvelle devise.');
        }
    }
}
