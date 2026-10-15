<?php
/**
 * Modele Treasury - Gestion de la tresorerie operationnelle
 * AMM-ERP
 */
class Treasury {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    // ---------------------------------------------------------------
    // PARAMETRES & DASHBOARD
    // ---------------------------------------------------------------

    public function getSetting(string $key, string $default = ''): string {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM treasury_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (string)$value : $default;
    }

    public function enregistrerSetting(string $key, string $value, string $description, int $userId): bool {
        $stmt = $this->pdo->prepare(
            "INSERT INTO treasury_settings
             (setting_key, setting_value, description, updated_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                 description = VALUES(description), updated_by = VALUES(updated_by), updated_at = NOW()"
        );
        return $stmt->execute([$key, $value, $description, $userId ?: null]);
    }

    public function plafondCaisseUsd(): float {
        return (float)$this->getSetting('cash_ceiling_usd', '9900');
    }

    public function seuilValidationDirectionUsd(): float {
        return (float)$this->getSetting('direction_approval_threshold_usd', '1000');
    }

    public function statistiquesDashboard(?int $annee = null, ?int $mois = null): array {
        $annee = $annee ?: (int)date('Y');
        $mois = $mois ?: (int)date('n');

        $stats = [
            'encaissements_jour_usd' => 0.0,
            'decaissements_jour_usd' => 0.0,
            'encaissements_mois_usd' => 0.0,
            'decaissements_mois_usd' => 0.0,
            'demandes_en_attente' => 0,
            'ordres_non_executes' => 0,
            'rapprochements_en_attente' => 0,
            'solde_caisse_usd' => 0.0,
            'plafond_caisse_usd' => $this->plafondCaisseUsd(),
            'alerte_plafond_caisse' => false,
            'ecarts_caisse_count' => 0,
        ];

        $today = date('Y-m-d');

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount_usd), 0)
             FROM treasury_receipts
             WHERE status = 'valide' AND date_receipt = ?"
        );
        $stmt->execute([$today]);
        $stats['encaissements_jour_usd'] = (float)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount_usd), 0)
             FROM treasury_payments
             WHERE payment_date = ? AND cancelled_at IS NULL"
        );
        $stmt->execute([$today]);
        $stats['decaissements_jour_usd'] = (float)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount_usd), 0)
             FROM treasury_receipts
             WHERE status = 'valide'
               AND YEAR(date_receipt) = ?
               AND MONTH(date_receipt) = ?"
        );
        $stmt->execute([$annee, $mois]);
        $stats['encaissements_mois_usd'] = (float)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount_usd), 0)
             FROM treasury_payments
             WHERE cancelled_at IS NULL AND YEAR(payment_date) = ?
               AND MONTH(payment_date) = ?"
        );
        $stmt->execute([$annee, $mois]);
        $stats['decaissements_mois_usd'] = (float)$stmt->fetchColumn();

        $stats['demandes_en_attente'] = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM treasury_fund_requests
             WHERE status IN ('a_controler','soumise','validee_comptable','en_suspens')"
        )->fetchColumn();

        $stats['ordres_non_executes'] = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM treasury_payment_orders WHERE status = 'emis'"
        )->fetchColumn();

        $stats['rapprochements_en_attente'] = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM treasury_bank_statements
             WHERE status IN ('brouillon','en_rapprochement')"
        )->fetchColumn();

        $stats['solde_caisse_usd'] = $this->soldeCaisseUsd();
        $stats['alerte_plafond_caisse'] = $stats['solde_caisse_usd'] > $stats['plafond_caisse_usd'];

        $stats['ecarts_caisse_count'] = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM treasury_cash_sessions
             WHERE variance_amount IS NOT NULL AND variance_amount <> 0"
        )->fetchColumn();

        return $stats;
    }

    public function compteursActionsMenu(): array {
        $stmt = $this->pdo->query(
            "SELECT
                (SELECT COUNT(*) FROM treasury_fund_requests
                 WHERE status IN ('a_controler','soumise','validee_comptable','validee_direction','en_suspens')) AS demandes_action,
                (SELECT COUNT(*) FROM treasury_payment_orders WHERE status = 'emis') AS ordres_action,
                (SELECT COUNT(*) FROM treasury_bank_statements
                 WHERE status IN ('brouillon','en_rapprochement')) AS rapprochements_action,
                (SELECT COUNT(*) FROM treasury_cash_sessions
                 WHERE variance_amount IS NOT NULL AND variance_amount <> 0) AS ecarts_caisse"
        );
        $row = $stmt->fetch() ?: [];
        return [
            'demandes' => (int)($row['demandes_action'] ?? 0),
            'ordres' => (int)($row['ordres_action'] ?? 0),
            'rapprochements' => (int)($row['rapprochements_action'] ?? 0),
            'ecarts' => (int)($row['ecarts_caisse'] ?? 0),
        ];
    }

    public function repertoireComptesTresorerie(bool $actifsUniquement = true): array {
        $sql = "SELECT a.*,
                       COALESCE((
                           SELECT SUM(CASE
                               WHEN t.type_flux = 'revenu' THEN t.montant
                               WHEN t.type_flux = 'depense' THEN -t.montant
                               ELSE 0 END)
                           FROM financial_transactions t
                           WHERE t.compte_id = a.id AND t.statut = 'valide'
                       ), 0) AS solde,
                       COALESCE((
                           SELECT SUM(CASE WHEN t.type_flux = 'revenu' THEN t.montant ELSE 0 END)
                           FROM financial_transactions t
                           WHERE t.compte_id = a.id AND t.statut = 'valide'
                             AND YEAR(t.date_operation) = YEAR(CURDATE())
                             AND MONTH(t.date_operation) = MONTH(CURDATE())
                       ), 0) AS entrees_mois,
                       COALESCE((
                           SELECT SUM(CASE WHEN t.type_flux = 'depense' THEN t.montant ELSE 0 END)
                           FROM financial_transactions t
                           WHERE t.compte_id = a.id AND t.statut = 'valide'
                             AND YEAR(t.date_operation) = YEAR(CURDATE())
                             AND MONTH(t.date_operation) = MONTH(CURDATE())
                       ), 0) AS sorties_mois,
                       COALESCE((
                           SELECT SUM(o.amount_usd)
                           FROM treasury_payment_orders o
                           WHERE o.account_id = a.id AND o.status = 'emis'
                       ), 0) AS engagements_usd,
                       (SELECT MAX(t.date_operation)
                        FROM financial_transactions t
                        WHERE t.compte_id = a.id AND t.statut = 'valide') AS derniere_operation,
                       (SELECT s.status FROM treasury_cash_sessions s
                        WHERE s.cash_account_id = a.id AND s.session_date = CURDATE()
                        ORDER BY s.id DESC LIMIT 1) AS cash_status_today,
                       (SELECT bs.status FROM treasury_bank_statements bs
                        WHERE bs.account_id = a.id
                        ORDER BY bs.period_year DESC, bs.period_month DESC, bs.id DESC LIMIT 1) AS bank_status_latest,
                       (SELECT COUNT(*) FROM treasury_bank_statements bs
                        WHERE bs.account_id = a.id
                          AND bs.status IN ('brouillon','en_rapprochement')) AS rapprochements_en_attente
                FROM financial_accounts a";
        if ($actifsUniquement) {
            $sql .= " WHERE a.actif = 1";
        }
        $sql .= " ORDER BY FIELD(a.type_compte, 'caisse','banque','mobile_money','autre'), a.nom ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function mouvementsCompteTresorerie(int $accountId, string $dateDebut, string $dateFin, int $limite = 300): array {
        $limite = max(1, min(1000, $limite));
        $stmt = $this->pdo->prepare(
            "SELECT t.*, c.nom AS category_nom, u.nom AS created_by_nom
             FROM financial_transactions t
             LEFT JOIN financial_categories c ON c.id = t.categorie_id
             LEFT JOIN users u ON u.id = t.created_by
             WHERE t.compte_id = ?
               AND t.statut = 'valide'
               AND t.date_operation BETWEEN ? AND ?
             ORDER BY t.date_operation DESC, t.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $accountId, PDO::PARAM_INT);
        $stmt->bindValue(2, $dateDebut);
        $stmt->bindValue(3, $dateFin);
        $stmt->bindValue(4, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function dernieresOperations(int $limite = 10): array {
        $limite = max(1, min(100, $limite));
        $stmt = $this->pdo->prepare(
            "(SELECT 'encaissement' AS type_operation, receipt_number AS reference_operation,
                    date_receipt AS date_operation, payer_name AS tiers, objet, amount, currency,
                    amount_usd, status
             FROM treasury_receipts)
             UNION ALL
             (SELECT 'decaissement' AS type_operation, po.order_number AS reference_operation,
                    p.payment_date AS date_operation, po.beneficiary AS tiers, r.objet,
                    p.amount, p.currency, p.amount_usd, po.status
             FROM treasury_payments p
             JOIN treasury_payment_orders po ON po.id = p.payment_order_id
             JOIN treasury_fund_requests r ON r.id = po.request_id)
             ORDER BY date_operation DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function soldesBanqueParCompte(): array {
        $stmt = $this->pdo->query(
            "SELECT a.id, a.nom, a.devise, a.reference_compte,
                    COALESCE(SUM(CASE
                        WHEN t.type_flux = 'revenu' THEN t.montant
                        WHEN t.type_flux = 'depense' THEN -t.montant
                        ELSE 0
                    END), 0) AS solde
             FROM financial_accounts a
             LEFT JOIN financial_transactions t
                ON t.compte_id = a.id
               AND t.statut = 'valide'
             WHERE a.actif = 1
               AND a.type_compte = 'banque'
             GROUP BY a.id, a.nom, a.devise, a.reference_compte
             ORDER BY a.nom ASC"
        );
        return $stmt->fetchAll();
    }

    public function rapportTresorerie(string $type, array $filtres = []): array {
        switch ($type) {
            case 'caisse_journalier':
                return $this->rapportCaisseJournalier($filtres);
            case 'encaissements':
                return $this->rapportEncaissements($filtres);
            case 'decaissements':
                return $this->rapportDecaissements($filtres);
            case 'demandes_statut':
                return $this->rapportDemandesParStatut($filtres);
            case 'ordres_paiement':
                return $this->rapportOrdresPaiement($filtres);
            case 'rapprochement_mensuel':
                return $this->rapportRapprochementMensuel($filtres);
            case 'situation_globale':
            default:
                return $this->rapportSituationGlobale();
        }
    }

    // ---------------------------------------------------------------
    // ENCAISSEMENTS / RECUS
    // ---------------------------------------------------------------

    public function compterEncaissements(array $filtres = []): int {
        $sql = "SELECT COUNT(*) FROM treasury_receipts r WHERE 1=1";
        $params = [];
        $this->appliquerFiltresEncaissements($sql, $params, $filtres);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function listerEncaissements(array $filtres = [], int $limite = 20, int $offset = 0): array {
        $sql = "SELECT r.*, a.nom AS account_nom, a.type_compte AS account_type,
                       u.nom AS created_by_nom, uv.nom AS validated_by_nom
                FROM treasury_receipts r
                JOIN financial_accounts a ON a.id = r.account_id
                LEFT JOIN users u ON u.id = r.created_by
                LEFT JOIN users uv ON uv.id = r.validated_by
                WHERE 1=1";
        $params = [];
        $this->appliquerFiltresEncaissements($sql, $params, $filtres);
        $sql .= " ORDER BY r.date_receipt DESC, r.id DESC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function trouverEncaissementParId(int $id) {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, a.nom AS account_nom, a.type_compte, u.nom AS created_by_nom,
                    uv.nom AS validated_by_nom, uc.nom AS cancelled_by_nom
             FROM treasury_receipts r
             JOIN financial_accounts a ON a.id = r.account_id
             LEFT JOIN users u ON u.id = r.created_by
             LEFT JOIN users uv ON uv.id = r.validated_by
             LEFT JOIN users uc ON uc.id = r.cancelled_by
             WHERE r.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function creerEncaissement(array $donnees): int {
        $numero = $donnees['receipt_number'] ?? $this->genererNumeroRecu();
        $amountUsd = $this->convertirVersUsd(
            (float)$donnees['amount'],
            (string)$donnees['currency'],
            isset($donnees['exchange_rate']) ? (float)$donnees['exchange_rate'] : null
        );

        $stmt = $this->pdo->prepare(
            "INSERT INTO treasury_receipts
             (receipt_number, date_receipt, payer_name, objet, amount, currency, exchange_rate,
              amount_usd, payment_method, account_id, document_id, proof_file, reference_payment,
              notes, status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'brouillon', ?, NOW())"
        );

        $stmt->execute([
            $numero,
            $donnees['date_receipt'] ?? date('Y-m-d'),
            $donnees['payer_name'],
            $donnees['objet'],
            $donnees['amount'],
            $donnees['currency'] ?? 'USD',
            !empty($donnees['exchange_rate']) ? $donnees['exchange_rate'] : null,
            $amountUsd,
            $donnees['payment_method'],
            (int)$donnees['account_id'],
            !empty($donnees['document_id']) ? (int)$donnees['document_id'] : null,
            $donnees['proof_file'] ?? null,
            $donnees['reference_payment'] ?? null,
            $donnees['notes'] ?? null,
            $donnees['created_by'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function validerEncaissement(int $id, int $userId): bool {
        $encaissement = $this->trouverEncaissementParId($id);
        if (!$encaissement || $encaissement['status'] !== 'brouillon') {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $transactionId = $this->creerTransactionFinanciere([
                'date_operation' => $encaissement['date_receipt'],
                'type_flux' => 'revenu',
                'categorie_id' => $this->categorieParDefaut('revenu'),
                'compte_id' => (int)$encaissement['account_id'],
                'montant' => (float)$encaissement['amount'],
                'devise' => $encaissement['currency'],
                'taux_change' => $encaissement['exchange_rate'],
                'montant_usd' => (float)$encaissement['amount_usd'],
                'statut' => 'valide',
                'partenaire' => $encaissement['payer_name'],
                'description' => 'Encaissement ' . $encaissement['receipt_number'] . ' - ' . $encaissement['objet'],
                'piece_jointe' => $encaissement['proof_file'],
                'created_by' => $encaissement['created_by'],
                'approved_by' => $userId,
            ]);

            $stmt = $this->pdo->prepare(
                "UPDATE treasury_receipts
                 SET status = 'valide', validated_by = ?, validated_at = NOW(),
                     financial_transaction_id = ?
                 WHERE id = ? AND status = 'brouillon'"
            );
            $stmt->execute([$userId, $transactionId, $id]);

            if ($encaissement['payment_method'] === 'especes') {
                $this->recalculerSessionCaisse((int)$encaissement['account_id'], (string)$encaissement['date_receipt']);
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erreur validerEncaissement: ' . $e->getMessage());
            return false;
        }
    }

    public function genererNumeroRecu(): string {
        return $this->genererNumeroSequence('REC', 'treasury_receipts', 'created_at');
    }

    public function annulerEncaissement(int $id, int $userId, string $motif): bool {
        $motif = trim($motif);
        if ($motif === '') {
            return false;
        }

        $encaissement = $this->trouverEncaissementParId($id);
        if (!$encaissement || $encaissement['status'] === 'annule') {
            return false;
        }

        if ($encaissement['status'] === 'valide' && !empty($encaissement['financial_transaction_id'])) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE treasury_receipts
             SET status = 'annule', cancellation_reason = ?, cancelled_by = ?, cancelled_at = NOW()
             WHERE id = ? AND status = 'brouillon'"
        );
        $stmt->execute([$motif, $userId, $id]);
        return $stmt->rowCount() > 0;
    }

    // ---------------------------------------------------------------
    // DEMANDES DE FONDS
    // ---------------------------------------------------------------

    public function compterDemandes(array $filtres = []): int {
        $sql = "SELECT COUNT(*) FROM treasury_fund_requests r WHERE 1=1";
        $params = [];
        $this->appliquerFiltresDemandes($sql, $params, $filtres);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function listerDemandes(array $filtres = [], int $limite = 20, int $offset = 0): array {
        $sql = "SELECT r.*, c.nom AS category_nom, a.nom AS preferred_account_nom, u.nom AS created_by_nom,
                       EXISTS(
                           SELECT 1 FROM treasury_request_approvals adf
                           WHERE adf.request_id = r.id
                             AND adf.approval_level = 'direction_financiere'
                             AND adf.decision = 'valide'
                       ) AS direction_financiere_validee,
                       EXISTS(
                           SELECT 1 FROM treasury_request_approvals adg
                           WHERE adg.request_id = r.id
                             AND adg.approval_level = 'direction_generale'
                             AND adg.decision = 'valide'
                       ) AS direction_generale_validee,
                       GREATEST(
                           COALESCE(r.submitted_at, r.created_at),
                           COALESCE((SELECT MAX(ap.approved_at) FROM treasury_request_approvals ap WHERE ap.request_id = r.id), '1000-01-01 00:00:00'),
                           COALESCE((SELECT MAX(po.issued_at) FROM treasury_payment_orders po WHERE po.request_id = r.id), '1000-01-01 00:00:00'),
                           COALESCE((
                               SELECT MAX(tp.executed_at)
                               FROM treasury_payment_orders po2
                               JOIN treasury_payments tp ON tp.payment_order_id = po2.id
                               WHERE po2.request_id = r.id
                           ), '1000-01-01 00:00:00')
                       ) AS last_action_at
                FROM treasury_fund_requests r
                LEFT JOIN financial_categories c ON c.id = r.category_id
                LEFT JOIN financial_accounts a ON a.id = r.preferred_account_id
                LEFT JOIN users u ON u.id = r.created_by
                WHERE 1=1";
        $params = [];
        $this->appliquerFiltresDemandes($sql, $params, $filtres);
        $sql .= " ORDER BY r.request_date DESC, r.id DESC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function trouverDemandeParId(int $id) {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, c.nom AS category_nom, a.nom AS preferred_account_nom,
                    u.nom AS created_by_nom, us.nom AS submitted_by_nom
             FROM treasury_fund_requests r
             LEFT JOIN financial_categories c ON c.id = r.category_id
             LEFT JOIN financial_accounts a ON a.id = r.preferred_account_id
             LEFT JOIN users u ON u.id = r.created_by
             LEFT JOIN users us ON us.id = r.submitted_by
             WHERE r.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function validationsDemande(int $requestId): array {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, u.nom AS approved_by_nom
             FROM treasury_request_approvals a
             LEFT JOIN users u ON u.id = a.approved_by
             WHERE a.request_id = ?
             ORDER BY a.approved_at ASC, a.id ASC"
        );
        $stmt->execute([$requestId]);
        return $stmt->fetchAll();
    }

    public function creerDemandeFonds(array $donnees): int {
        $numero = $donnees['request_number'] ?? $this->genererNumeroDemande();
        $lignes = null;
        if (isset($donnees['items'])) {
            $lignes = $this->normaliserLignesDemande($donnees['items']);
            $donnees['amount'] = $lignes['total'];
        }
        $devise = (string)($donnees['currency'] ?? 'USD');
        $montant = (float)($donnees['amount'] ?? 0);
        $taux = isset($donnees['exchange_rate']) ? (float)$donnees['exchange_rate'] : null;
        if (!is_finite($montant) || $montant <= 0 || $montant > 9999999999999.99
            || !in_array($devise, ['USD', 'CDF'], true)
            || ($devise === 'CDF' && (!$taux || !is_finite($taux) || $taux <= 0 || $taux > 99999999.9999))) {
            throw new InvalidArgumentException('Le montant, la devise ou le taux de change est invalide.');
        }
        $amountUsd = $this->convertirVersUsd(
            $montant, $devise, $taux
        );
        if (!is_finite($amountUsd) || $amountUsd > 9999999999999.99) {
            throw new InvalidArgumentException('La contre-valeur USD dépasse la limite autorisée.');
        }
        $this->pdo->beginTransaction();
        try {
        $stmt = $this->pdo->prepare(
            "INSERT INTO treasury_fund_requests
             (request_number, request_date, beneficiary, objet, description, amount, currency,
              exchange_rate, amount_usd, category_id, preferred_account_id, urgency,
              justification_file, document_id, status, created_by, department, requester_name, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'brouillon', ?, ?, ?, NOW())"
        );

        $stmt->execute([
            $numero,
            $donnees['request_date'] ?? date('Y-m-d'),
            $donnees['beneficiary'],
            $donnees['objet'],
            $donnees['description'] ?? null,
            $donnees['amount'],
            $donnees['currency'] ?? 'USD',
            !empty($donnees['exchange_rate']) ? $donnees['exchange_rate'] : null,
            $amountUsd,
            !empty($donnees['category_id']) ? (int)$donnees['category_id'] : null,
            !empty($donnees['preferred_account_id']) ? (int)$donnees['preferred_account_id'] : null,
            $donnees['urgency'] ?? 'moyenne',
            $donnees['justification_file'] ?? null,
            !empty($donnees['document_id']) ? (int)$donnees['document_id'] : null,
            $donnees['created_by'] ?? null,
            trim((string)($donnees['department'] ?? '')) ?: null,
            trim((string)($donnees['requester_name'] ?? '')) ?: null,
        ]);
        $id = (int)$this->pdo->lastInsertId();
        if ($lignes !== null) {
            $stmt = $this->pdo->prepare('INSERT INTO treasury_request_items (request_id, position, description, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($lignes['items'] as $position => $ligne) {
                $stmt->execute([$id, $position + 1, $ligne['description'], $ligne['quantity'], $ligne['unit_price'], $ligne['line_total']]);
            }
        }
        $this->pdo->commit();
        return $id;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
    }

    /** Recalculate every line and the total on the server, independently of posted totals. */
    public function normaliserLignesDemande(array $lignes): array {
        if (count($lignes) < 1 || count($lignes) > 100) {
            throw new InvalidArgumentException('Ajouter entre 1 et 100 lignes de biens ou services.');
        }
        $items = [];
        $total = 0.0;
        foreach (array_values($lignes) as $index => $ligne) {
            if (!is_array($ligne)) { throw new InvalidArgumentException('Ligne de demande invalide.'); }
            if (isset($ligne['description']) && !is_scalar($ligne['description'])) {
                throw new InvalidArgumentException('La désignation de la ligne doit être un texte.');
            }
            $description = trim((string)($ligne['description'] ?? ''));
            $quantite = $ligne['quantity'] ?? null;
            $prix = $ligne['unit_price'] ?? null;
            if ($description === '' || mb_strlen($description) > 500 || !is_numeric($quantite) || !is_numeric($prix)) {
                throw new InvalidArgumentException('Ligne ' . ($index + 1) . ' : renseigner une désignation, une quantité et un prix unitaire valides.');
            }
            $quantite = round((float)$quantite, 3);
            $prix = round((float)$prix, 2);
            if (!is_finite($quantite) || !is_finite($prix) || $quantite <= 0 || $quantite > 999999999
                || $prix < 0 || $prix > 9999999999999.99) {
                throw new InvalidArgumentException('Ligne ' . ($index + 1) . ' : quantité positive et prix positif ou nul requis.');
            }
            $montant = round($quantite * $prix, 2);
            $total = round($total + $montant, 2);
            if (!is_finite($total) || $total > 9999999999999.99) {
                throw new InvalidArgumentException('Le total de la demande dépasse la limite autorisée.');
            }
            $items[] = ['description'=>$description, 'quantity'=>$quantite, 'unit_price'=>$prix, 'line_total'=>$montant];
        }
        if ($total <= 0) { throw new InvalidArgumentException('Le total de la demande doit être supérieur à zéro.'); }
        return ['items'=>$items, 'total'=>$total];
    }

    public function itemsDemande(int $id): array {
        $stmt = $this->pdo->prepare('SELECT * FROM treasury_request_items WHERE request_id = ? ORDER BY position, id');
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public function peutConsulterDemande(array $demande, int $userId, string $role, string $service): bool {
        if ($userId <= 0) { return false; }
        if ($userId === (int)($demande['created_by'] ?? 0)) { return true; }
        if (in_array($role, ['admin','tresorier','comptable_senior','directeur_financier','directeur_general','auditeur_interne'], true)) { return true; }
        $serviceNormalise = $this->normaliserServiceDemande($service);
        if (($role === 'direction' || $role === 'chef_service')
            && preg_match('/finance|comptable|comptabilite|tresorerie|direction generale/', $serviceNormalise)) { return true; }
        return $role === 'chef_service' && trim($service) !== '' && $service === ($demande['department'] ?? null);
    }

    public function soumettreDemande(int $id, int $userId): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE treasury_fund_requests
             SET status = CASE WHEN COALESCE(department, '') = '' THEN 'soumise' ELSE 'a_controler' END,
                 submitted_by = ?, submitted_at = NOW()
             WHERE id = ? AND status = 'brouillon'"
        );
        $stmt->execute([$userId, $id]);
        return $stmt->rowCount() > 0;
    }

    public function validerDemande(int $id, string $niveau, int $userId, string $commentaire = ''): bool {
        return $this->deciderDemande($id, $niveau, $userId, 'valide', $commentaire);
    }

    public function controlerDemande(int $id, int $userId, string $commentaire = ''): bool {
        if ($userId <= 0) { return false; }
        try {
            $this->pdo->beginTransaction();
            $demande = $this->verrouillerDemande($id);
            if (!$demande || $demande['status'] !== 'a_controler') { $this->pdo->rollBack(); return false; }
            $stmt = $this->pdo->prepare("UPDATE treasury_fund_requests SET status='soumise', controlled_by=?, controlled_at=NOW(), submitted_by=?, submitted_at=NOW() WHERE id=?");
            $stmt->execute([$userId, $userId, $id]);
            $this->enregistrerDecisionDemande($id, 'tresorier', $userId, 'valide', $commentaire);
            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            error_log('Erreur controlerDemande: ' . $e->getMessage()); return false;
        }
    }

    public function deciderDemande(int $id, string $niveau, int $userId, string $decision, string $commentaire = '', ?float $approvedAmount = null, ?string $reminderDate = null): bool {
        $commentaire = trim($commentaire);
        if ($userId <= 0 || !in_array($decision, ['annule','en_suspens','valide'], true)
            || !in_array($niveau, ['comptable','direction_financiere','direction_generale'], true)) { return false; }
        if ($decision !== 'valide' && $commentaire === '') { return false; }
        if ($decision === 'en_suspens' && !$this->dateRappelValide($reminderDate)) { return false; }
        try {
            $this->pdo->beginTransaction();
            $demande = $this->verrouillerDemande($id);
            if (!$demande || !$this->niveauValidationPossible($demande, $niveau)
                || !$this->separationDemandeRespectee($demande, $userId)) { $this->pdo->rollBack(); return false; }
            $plafond = (float)($demande['approved_amount'] ?? $demande['amount']);
            if ($decision === 'valide') {
                $montant = round($approvedAmount ?? $plafond, 2);
                if (!is_finite($montant) || $montant <= 0 || $montant > $plafond + 0.00001
                    || ($montant < $plafond && $commentaire === '')) { $this->pdo->rollBack(); return false; }
                $usd = round($this->convertirVersUsd($montant, $demande['currency'], $demande['exchange_rate'] !== null ? (float)$demande['exchange_rate'] : null), 2);
                $this->enregistrerDecisionDemande($id, $niveau, $userId, $decision, $commentaire, $montant, $usd);
                $statut = $this->statutApresValidation($id, $demande, $niveau);
                $stmt = $this->pdo->prepare('UPDATE treasury_fund_requests SET status=?, approved_amount=?, approved_amount_usd=?, suspended_level=NULL, previous_status=NULL, reminder_date=NULL WHERE id=?');
                $stmt->execute([$statut, $montant, $usd, $id]);
            } elseif ($decision === 'en_suspens') {
                $this->enregistrerDecisionDemande($id, $niveau, $userId, $decision, $commentaire, null, null, $reminderDate);
                $stmt = $this->pdo->prepare("UPDATE treasury_fund_requests SET previous_status=status, status='en_suspens', suspended_level=?, reminder_date=? WHERE id=?");
                $stmt->execute([$niveau, $reminderDate, $id]);
            } else {
                $this->enregistrerDecisionDemande($id, $niveau, $userId, $decision, $commentaire);
                $stmt = $this->pdo->prepare("UPDATE treasury_fund_requests SET status='annulee', cancellation_reason=?, cancelled_by=?, cancelled_at=NOW(), suspended_level=NULL, previous_status=NULL, reminder_date=NULL WHERE id=?");
                $stmt->execute([$commentaire, $userId, $id]);
            }
            $this->pdo->commit(); return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            error_log('Erreur deciderDemande: ' . $e->getMessage()); return false;
        }
    }

    public function reprendreDemande(int $id, string $niveau, int $userId, string $commentaire = ''): bool {
        if ($userId <= 0 || !in_array($niveau, ['comptable','direction_financiere','direction_generale'], true)) { return false; }
        try {
            $this->pdo->beginTransaction();
            $demande = $this->verrouillerDemande($id);
            if (!$demande || $demande['status'] !== 'en_suspens' || $demande['suspended_level'] !== $niveau
                || !$this->separationDemandeRespectee($demande, $userId)) { $this->pdo->rollBack(); return false; }
            $demande['status'] = $demande['previous_status'];
            if (!$this->niveauValidationPossible($demande, $niveau)) { $this->pdo->rollBack(); return false; }
            $stmt = $this->pdo->prepare('UPDATE treasury_fund_requests SET status=previous_status, previous_status=NULL, suspended_level=NULL, reminder_date=NULL WHERE id=?');
            $stmt->execute([$id]);
            $this->enregistrerDecisionDemande($id, $niveau, $userId, 'repris', trim($commentaire));
            $this->pdo->commit(); return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            error_log('Erreur reprendreDemande: ' . $e->getMessage()); return false;
        }
    }

    public function rejeterDemande(int $id, string $niveau, int $userId, string $motif): bool {
        $motif = trim($motif);
        if ($userId <= 0 || $motif === '' || !in_array($niveau, ['comptable', 'direction_financiere', 'direction_generale'], true)) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();
            $demande = $this->verrouillerDemande($id);
            if (!$demande || !$this->niveauValidationPossible($demande, $niveau)
                || !$this->separationDemandeRespectee($demande, $userId)) { $this->pdo->rollBack(); return false; }
            $stmt = $this->pdo->prepare(
                "INSERT INTO treasury_request_approvals
                 (request_id, approval_level, decision, approval_comment, approved_by, approved_at)
                 VALUES (?, ?, 'rejete', ?, ?, NOW())"
            );
            $stmt->execute([$id, $niveau, $motif, $userId]);

            $stmt = $this->pdo->prepare(
                "UPDATE treasury_fund_requests
                 SET status = 'rejetee', rejection_reason = ?
                 WHERE id = ? AND status NOT IN ('ordre_emis','payee','annulee')"
            );
            $stmt->execute([$motif, $id]);

            $this->pdo->commit();
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erreur rejeterDemande: ' . $e->getMessage());
            return false;
        }
    }

    public function annulerDemandeFonds(int $id, int $userId, string $motif): bool {
        $motif = trim($motif);
        if ($motif === '') {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE treasury_fund_requests
             SET status = 'annulee', cancellation_reason = ?, cancelled_by = ?, cancelled_at = NOW(),
                 suspended_level = NULL, previous_status = NULL, reminder_date = NULL
             WHERE id = ?
               AND status IN ('brouillon','a_controler','soumise','validee_comptable','validee_direction','rejetee','en_suspens')"
        );
        $stmt->execute([$motif, $userId, $id]);
        return $stmt->rowCount() > 0;
    }

    /** Due reminders are in-app notifications, deduplicated per recipient, level and day. */
    public function envoyerRappelsDemandes(?int $requestId = null): int {
        $stmt = $this->pdo->prepare("SELECT id FROM treasury_fund_requests WHERE status='en_suspens' AND reminder_date IS NOT NULL AND reminder_date <= CURDATE()" . ($requestId !== null ? ' AND id=?' : ''));
        $stmt->execute($requestId !== null ? [$requestId] : []);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) { return 0; }
        $users = $this->pdo->query('SELECT id, role, service FROM users WHERE actif=1')->fetchAll();
        $sent = 0;
        foreach ($ids as $id) {
            try {
                $this->pdo->beginTransaction();
                $demande = $this->verrouillerDemande((int)$id);
                if (!$demande || $demande['status'] !== 'en_suspens' || !$demande['reminder_date'] || $demande['reminder_date'] > date('Y-m-d')) {
                    $this->pdo->rollBack(); continue;
                }
                $niveau = (string)$demande['suspended_level'];
                $destinataires = [];
                foreach ($users as $user) {
                    $userId = (int)$user['id'];
                    if ($userId === (int)$demande['created_by']
                        || ($this->utilisateurValideNiveauDemande($user, $niveau) && $this->separationDemandeRespectee($demande, $userId))) {
                        $destinataires[$userId] = $userId;
                    }
                }
                $insert = $this->pdo->prepare('INSERT IGNORE INTO treasury_request_reminders (request_id, approval_level, reminder_day, user_id) VALUES (?, ?, CURDATE(), ?)');
                $notification = $this->pdo->prepare("INSERT INTO notifications (user_id, type_notif, message, lien, created_at) VALUES (?, 'rappel', ?, ?, NOW())");
                $count = 0;
                foreach ($destinataires as $userId) {
                    $insert->execute([(int)$id, $niveau, $userId]);
                    if ($insert->rowCount() === 0) { continue; }
                    $message = 'Rappel : la demande ' . $demande['request_number'] . ' est en suspens et attend une reprise de traitement.';
                    $notification->execute([$userId, $message, APP_URL . '/treasury/demande-voir/' . (int)$id]);
                    $count++;
                }
                $this->pdo->commit();
                $sent += $count;
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
                error_log('Erreur rappel demande: ' . $e->getMessage());
            }
        }
        return $sent;
    }

    public function genererNumeroDemande(): string {
        return $this->genererNumeroSequence('DF', 'treasury_fund_requests', 'created_at');
    }

    // ---------------------------------------------------------------
    // ORDRES DE PAIEMENT & PAIEMENTS EXECUTES
    // ---------------------------------------------------------------

    public function listerOrdres(array $filtres = [], int $limite = 20, int $offset = 0): array {
        $sql = "SELECT o.*, r.request_number, r.objet,
                       a.nom AS account_nom, a.type_compte AS account_type, a.devise AS account_currency,
                       u.nom AS issued_by_nom,
                       p.id AS payment_id, p.payment_date, p.executed_at,
                       ux.nom AS executed_by_nom
                FROM treasury_payment_orders o
                JOIN treasury_fund_requests r ON r.id = o.request_id
                JOIN financial_accounts a ON a.id = o.account_id
                LEFT JOIN users u ON u.id = o.issued_by
                LEFT JOIN treasury_payments p ON p.payment_order_id = o.id
                LEFT JOIN users ux ON ux.id = p.executed_by
                WHERE 1=1";
        $params = [];

        if (!empty($filtres['status'])) {
            $sql .= " AND o.status = ?";
            $params[] = $filtres['status'];
        }
        if (!empty($filtres['recherche'])) {
            $sql .= " AND (o.order_number LIKE ? OR r.request_number LIKE ? OR o.beneficiary LIKE ? OR r.objet LIKE ?)";
            $term = '%' . $filtres['recherche'] . '%';
            $params = array_merge($params, [$term, $term, $term, $term]);
        }

        $sql .= " ORDER BY o.order_date DESC, o.id DESC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function trouverOrdreParId(int $id) {
        $stmt = $this->pdo->prepare(
            "SELECT o.*, r.request_number, r.objet, r.description, r.category_id,
                    r.status AS request_status,
                    a.nom AS account_nom, a.type_compte AS account_type, a.devise AS account_currency, u.nom AS issued_by_nom,
                    p.id AS payment_id, p.payment_date, p.reference_payment AS executed_reference_payment,
                    p.proof_file AS payment_proof_file, p.financial_transaction_id,
                    p.notes AS payment_notes, p.executed_by, p.executed_at,
                    p.cancelled_at AS payment_cancelled_at, p.cancellation_reason AS payment_cancellation_reason,
                    ux.nom AS executed_by_nom
             FROM treasury_payment_orders o
             JOIN treasury_fund_requests r ON r.id = o.request_id
             JOIN financial_accounts a ON a.id = o.account_id
             LEFT JOIN users u ON u.id = o.issued_by
             LEFT JOIN treasury_payments p ON p.payment_order_id = o.id
             LEFT JOIN users ux ON ux.id = p.executed_by
             WHERE o.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function trouverOrdreParDemande(int $requestId) {
        $stmt = $this->pdo->prepare(
            "SELECT o.*, r.request_number, r.objet, r.description, r.category_id,
                    r.status AS request_status,
                    a.nom AS account_nom, a.type_compte AS account_type, a.devise AS account_currency, u.nom AS issued_by_nom,
                    p.id AS payment_id, p.payment_date, p.reference_payment AS executed_reference_payment,
                    p.proof_file AS payment_proof_file, p.financial_transaction_id,
                    p.notes AS payment_notes, p.executed_by, p.executed_at,
                    p.cancelled_at AS payment_cancelled_at, p.cancellation_reason AS payment_cancellation_reason,
                    ux.nom AS executed_by_nom
             FROM treasury_payment_orders o
             JOIN treasury_fund_requests r ON r.id = o.request_id
             JOIN financial_accounts a ON a.id = o.account_id
             LEFT JOIN users u ON u.id = o.issued_by
             LEFT JOIN treasury_payments p ON p.payment_order_id = o.id
             LEFT JOIN users ux ON ux.id = p.executed_by
             WHERE o.request_id = ?
             LIMIT 1"
        );
        $stmt->execute([$requestId]);
        return $stmt->fetch();
    }

    public function emettreOrdrePaiement(int $requestId, array $donnees): int {
        $demande = $this->trouverDemandeParId($requestId);
        if (!$demande || !$this->demandePretePourOrdre($demande)) {
            return 0;
        }

        if ($this->trouverOrdreParDemande($requestId)) {
            return 0;
        }

        $accountId = (int)($donnees['account_id'] ?? $demande['preferred_account_id']);
        if ($accountId <= 0) {
            return 0;
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "SELECT *
                 FROM treasury_fund_requests
                 WHERE id = ?
                 FOR UPDATE"
            );
            $stmt->execute([$requestId]);
            $demandeVerrouillee = $stmt->fetch();
            if (!$demandeVerrouillee || $demandeVerrouillee['status'] !== 'validee_direction') {
                $this->pdo->rollBack();
                return 0;
            }
            $montantAutorise = (float)($demandeVerrouillee['approved_amount'] ?? $demandeVerrouillee['amount']);
            $montant = round((float)($donnees['amount'] ?? $montantAutorise), 2);
            $devise = (string)($donnees['currency'] ?? $demandeVerrouillee['currency']);
            // The approval is expressed in the request's currency. Never accept a posted USD equivalent.
            if ($devise !== $demandeVerrouillee['currency'] || !is_finite($montant) || $montant <= 0 || $montant > $montantAutorise + 0.00001) {
                $this->pdo->rollBack(); return 0;
            }
            if (!$this->compteCouvrePaiement($accountId, $devise, $montant, (string)($donnees['payment_method'] ?? ''))) {
                $this->pdo->rollBack(); return 0;
            }
            $taux = $demandeVerrouillee['exchange_rate'] !== null ? (float)$demandeVerrouillee['exchange_rate'] : null;
            $montantUsd = round($this->convertirVersUsd($montant, $devise, $taux), 2);

            $stmt = $this->pdo->prepare(
                "SELECT id
                 FROM treasury_payment_orders
                 WHERE request_id = ?
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute([$requestId]);
            if ($stmt->fetchColumn()) {
                $this->pdo->rollBack();
                return 0;
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO treasury_payment_orders
                 (order_number, request_id, order_date, amount, currency, exchange_rate, amount_usd,
                  payment_method, account_id, beneficiary, payment_reference, instructions, document_id,
                  status, issued_by, issued_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'emis', ?, NOW(), NOW())"
            );

            $stmt->execute([
                $donnees['order_number'] ?? $this->genererNumeroOrdrePaiement(),
                $requestId,
                $donnees['order_date'] ?? date('Y-m-d'),
                $montant,
                $devise,
                $taux,
                $montantUsd,
                $donnees['payment_method'],
                $accountId,
                $donnees['beneficiary'] ?? $demande['beneficiary'],
                $donnees['payment_reference'] ?? null,
                $donnees['instructions'] ?? null,
                !empty($donnees['document_id']) ? (int)$donnees['document_id'] : null,
                $donnees['issued_by'] ?? null,
            ]);

            $orderId = (int)$this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare("UPDATE treasury_fund_requests SET status = 'ordre_emis' WHERE id = ?");
            $stmt->execute([$requestId]);

            $this->pdo->commit();
            return $orderId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erreur emettreOrdrePaiement: ' . $e->getMessage());
            return 0;
        }
    }

    public function executerPaiement(int $orderId, array $donnees): int {
        $ordre = $this->trouverOrdreParId($orderId);
        if (!$ordre || $ordre['status'] !== 'emis' || $ordre['request_status'] !== 'ordre_emis' || !empty($ordre['payment_id'])) {
            return 0;
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "SELECT o.*, r.status AS request_status, r.amount AS requested_amount,
                        r.approved_amount AS request_approved_amount, r.currency AS request_currency,
                        r.category_id, r.objet
                 FROM treasury_payment_orders o
                 JOIN treasury_fund_requests r ON r.id = o.request_id
                 WHERE o.id = ?
                 FOR UPDATE"
            );
            $stmt->execute([$orderId]);
            $ordreVerrouille = $stmt->fetch();
            if (!$ordreVerrouille || $ordreVerrouille['status'] !== 'emis' || $ordreVerrouille['request_status'] !== 'ordre_emis') {
                $this->pdo->rollBack();
                return 0;
            }
            $ordre = array_replace($ordre, $ordreVerrouille);
            $plafond = (float)($ordre['request_approved_amount'] ?? $ordre['requested_amount']);
            if ($ordre['currency'] !== $ordre['request_currency'] || (float)$ordre['amount'] <= 0
                || (float)$ordre['amount'] > $plafond + 0.00001
                || !$this->compteCouvrePaiement((int)$ordre['account_id'], $ordre['currency'], (float)$ordre['amount'], $ordre['payment_method'], $orderId)) {
                $this->pdo->rollBack(); return 0;
            }

            $stmt = $this->pdo->prepare(
                "SELECT id
                 FROM treasury_payments
                 WHERE payment_order_id = ?
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute([$orderId]);
            if ($stmt->fetchColumn()) {
                $this->pdo->rollBack();
                return 0;
            }

            $transactionId = $this->creerTransactionFinanciere([
                'date_operation' => $donnees['payment_date'] ?? date('Y-m-d'),
                'type_flux' => 'depense',
                'categorie_id' => !empty($ordre['category_id']) ? (int)$ordre['category_id'] : $this->categorieParDefaut('depense'),
                'compte_id' => (int)$ordre['account_id'],
                'montant' => (float)$ordre['amount'],
                'devise' => $ordre['currency'],
                'taux_change' => $ordre['exchange_rate'],
                'montant_usd' => (float)$ordre['amount_usd'],
                'statut' => 'valide',
                'partenaire' => $ordre['beneficiary'],
                'description' => 'Paiement ' . $ordre['order_number'] . ' - ' . $ordre['objet'],
                'piece_jointe' => $donnees['proof_file'] ?? null,
                'created_by' => $ordre['issued_by'],
                'approved_by' => $donnees['executed_by'] ?? null,
            ]);

            $stmt = $this->pdo->prepare(
                "INSERT INTO treasury_payments
                 (payment_order_id, payment_date, amount, currency, exchange_rate, amount_usd,
                  reference_payment, proof_file, financial_transaction_id, document_id, notes,
                  executed_by, executed_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );
            $stmt->execute([
                $orderId,
                $donnees['payment_date'] ?? date('Y-m-d'),
                $ordre['amount'],
                $ordre['currency'],
                $ordre['exchange_rate'],
                $ordre['amount_usd'],
                $donnees['reference_payment'] ?? null,
                $donnees['proof_file'] ?? null,
                $transactionId,
                !empty($donnees['document_id']) ? (int)$donnees['document_id'] : null,
                $donnees['notes'] ?? null,
                $donnees['executed_by'] ?? null,
            ]);

            $paymentId = (int)$this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare("UPDATE treasury_payment_orders SET status = 'execute' WHERE id = ? AND status = 'emis'");
            $stmt->execute([$orderId]);
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return 0;
            }

            $stmt = $this->pdo->prepare("UPDATE treasury_fund_requests SET status = 'payee' WHERE id = ? AND status = 'ordre_emis'");
            $stmt->execute([(int)$ordre['request_id']]);
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return 0;
            }

            if ($ordre['payment_method'] === 'especes') {
                $this->recalculerSessionCaisse((int)$ordre['account_id'], (string)($donnees['payment_date'] ?? date('Y-m-d')));
            }

            $this->pdo->commit();
            return $paymentId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erreur executerPaiement: ' . $e->getMessage());
            return 0;
        }
    }

    public function genererNumeroOrdrePaiement(): string {
        return $this->genererNumeroSequence('OP', 'treasury_payment_orders', 'created_at');
    }

    public function annulerOrdrePaiement(int $orderId, int $userId, string $motif): bool {
        $motif = trim($motif);
        if ($motif === '') {
            return false;
        }

        $ordre = $this->trouverOrdreParId($orderId);
        if (!$ordre || $ordre['status'] !== 'emis') {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "UPDATE treasury_payment_orders
                 SET status = 'annule', cancellation_reason = ?, cancelled_by = ?, cancelled_at = NOW()
                 WHERE id = ? AND status = 'emis'"
            );
            $stmt->execute([$motif, $userId, $orderId]);

            $stmt = $this->pdo->prepare(
                "UPDATE treasury_fund_requests
                 SET status = 'validee_direction'
                 WHERE id = ? AND status = 'ordre_emis'"
            );
            $stmt->execute([(int)$ordre['request_id']]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erreur annulerOrdrePaiement: ' . $e->getMessage());
            return false;
        }
    }

    // ---------------------------------------------------------------
    // LIVRE DE CAISSE
    // ---------------------------------------------------------------

    public function listerSessionsCaisse(array $filtres = [], int $limite = 31, int $offset = 0): array {
        $sql = "SELECT s.*, a.nom AS cash_account_nom, u.nom AS opened_by_nom,
                       uc.nom AS closed_by_nom, uk.nom AS checked_by_nom
                FROM treasury_cash_sessions s
                JOIN financial_accounts a ON a.id = s.cash_account_id
                LEFT JOIN users u ON u.id = s.opened_by
                LEFT JOIN users uc ON uc.id = s.closed_by
                LEFT JOIN users uk ON uk.id = s.checked_by
                WHERE 1=1";
        $params = [];

        if (!empty($filtres['cash_account_id'])) {
            $sql .= " AND s.cash_account_id = ?";
            $params[] = (int)$filtres['cash_account_id'];
        }
        if (!empty($filtres['status'])) {
            $sql .= " AND s.status = ?";
            $params[] = $filtres['status'];
        }
        if (!empty($filtres['date_debut'])) {
            $sql .= " AND s.session_date >= ?";
            $params[] = $filtres['date_debut'];
        }
        if (!empty($filtres['date_fin'])) {
            $sql .= " AND s.session_date <= ?";
            $params[] = $filtres['date_fin'];
        }

        $sql .= " ORDER BY s.session_date DESC, s.id DESC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function trouverSessionCaisse(int $cashAccountId, string $date) {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM treasury_cash_sessions
             WHERE cash_account_id = ? AND session_date = ?
             LIMIT 1"
        );
        $stmt->execute([$cashAccountId, $date]);
        return $stmt->fetch();
    }

    public function trouverSessionCaisseParId(int $sessionId) {
        $stmt = $this->pdo->prepare(
            "SELECT s.*, a.nom AS cash_account_nom, a.type_compte, u.nom AS opened_by_nom,
                    uc.nom AS closed_by_nom, uk.nom AS checked_by_nom
             FROM treasury_cash_sessions s
             JOIN financial_accounts a ON a.id = s.cash_account_id
             LEFT JOIN users u ON u.id = s.opened_by
             LEFT JOIN users uc ON uc.id = s.closed_by
             LEFT JOIN users uk ON uk.id = s.checked_by
             WHERE s.id = ?
             LIMIT 1"
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetch();
    }

    public function ouvrirSessionCaisse(int $cashAccountId, string $date, int $userId = 0): int {
        $existante = $this->trouverSessionCaisse($cashAccountId, $date);
        if ($existante) {
            return (int)$existante['id'];
        }

        $opening = $this->soldeCaisseCompteAvantDate($cashAccountId, $date);
        $currency = $this->deviseCompte($cashAccountId);

        $stmt = $this->pdo->prepare(
            "INSERT INTO treasury_cash_sessions
             (session_date, cash_account_id, currency, opening_balance, theoretical_balance,
              status, opened_by, created_at)
             VALUES (?, ?, ?, ?, ?, 'ouverte', ?, NOW())"
        );
        $stmt->execute([$date, $cashAccountId, $currency, $opening, $opening, $userId ?: null]);

        return (int)$this->pdo->lastInsertId();
    }

    public function recalculerSessionCaisse(int $cashAccountId, string $date): bool {
        $sessionId = $this->ouvrirSessionCaisse($cashAccountId, $date, (int)($_SESSION['user_id'] ?? 0));
        $session = $this->trouverSessionCaisse($cashAccountId, $date);
        if (!$session || in_array($session['status'], ['verrouillee'], true)) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM treasury_receipts
             WHERE status = 'valide'
               AND payment_method = 'especes'
               AND account_id = ?
               AND date_receipt = ?"
        );
        $stmt->execute([$cashAccountId, $date]);
        $cashIn = (float)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(p.amount), 0)
             FROM treasury_payments p
             JOIN treasury_payment_orders o ON o.id = p.payment_order_id
             WHERE o.payment_method = 'especes'
               AND p.cancelled_at IS NULL
               AND o.account_id = ?
               AND p.payment_date = ?"
        );
        $stmt->execute([$cashAccountId, $date]);
        $cashOut = (float)$stmt->fetchColumn();

        $theoretical = (float)$session['opening_balance'] + $cashIn - $cashOut;
        $variance = $session['physical_balance'] !== null
            ? (float)$session['physical_balance'] - $theoretical
            : null;

        $stmt = $this->pdo->prepare(
            "UPDATE treasury_cash_sessions
             SET cash_in = ?, cash_out = ?, theoretical_balance = ?, variance_amount = ?
             WHERE id = ?"
        );
        return $stmt->execute([$cashIn, $cashOut, $theoretical, $variance, $sessionId]);
    }

    public function mouvementsCaisse(int $cashAccountId, string $date): array {
        return $this->mouvementsCaissePeriode($cashAccountId, $date, $date);
    }

    public function mouvementsCaissePeriode(int $cashAccountId, string $dateDebut, string $dateFin): array {
        $sql = "(SELECT r.id, 'entree' AS sens, 'encaissement' AS type_operation,
                        r.receipt_number AS reference_operation, r.date_receipt AS operation_date,
                        r.payer_name AS partenaire, r.objet AS libelle, r.amount, r.currency,
                        r.amount_usd, r.created_at
                 FROM treasury_receipts r
                 WHERE r.status = 'valide'
                   AND r.payment_method = 'especes'
                   AND r.account_id = ?
                   AND r.date_receipt BETWEEN ? AND ?)
                UNION ALL
                (SELECT p.id, 'sortie' AS sens, 'paiement' AS type_operation,
                        o.order_number AS reference_operation, p.payment_date AS operation_date,
                        o.beneficiary AS partenaire, fr.objet AS libelle, p.amount, p.currency,
                        p.amount_usd, p.created_at
                 FROM treasury_payments p
                 JOIN treasury_payment_orders o ON o.id = p.payment_order_id
                 JOIN treasury_fund_requests fr ON fr.id = o.request_id
                 WHERE o.payment_method = 'especes'
                   AND p.cancelled_at IS NULL
                   AND o.account_id = ?
                   AND p.payment_date BETWEEN ? AND ?)
                ORDER BY operation_date ASC, created_at ASC, id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cashAccountId, $dateDebut, $dateFin, $cashAccountId, $dateDebut, $dateFin]);
        return $stmt->fetchAll();
    }

    public function soldeCaisseAvantDate(int $cashAccountId, string $date): float {
        return $this->soldeCaisseCompteAvantDate($cashAccountId, $date);
    }

    public function cloturerSessionCaisse(int $sessionId, float $physicalBalance, int $userId, string $commentaire = ''): bool {
        $stmt = $this->pdo->prepare("SELECT * FROM treasury_cash_sessions WHERE id = ? LIMIT 1");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();
        if (!$session || !in_array($session['status'], ['ouverte','cloturee'], true)) {
            return false;
        }

        $variance = $physicalBalance - (float)$session['theoretical_balance'];

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "UPDATE treasury_cash_sessions
                 SET physical_balance = ?, variance_amount = ?, status = 'cloturee',
                     close_comment = ?, closed_by = ?, closed_at = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([$physicalBalance, $variance, $commentaire ?: null, $userId, $sessionId]);

            $stmt = $this->pdo->prepare(
                "INSERT INTO treasury_cash_counts
                 (cash_session_id, counted_amount, theoretical_amount, variance_amount,
                  count_comment, counted_by, counted_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $sessionId,
                $physicalBalance,
                $session['theoretical_balance'],
                $variance,
                $commentaire ?: null,
                $userId,
            ]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erreur cloturerSessionCaisse: ' . $e->getMessage());
            return false;
        }
    }

    public function controlerSessionCaisse(int $sessionId, int $userId, string $commentaire = ''): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE treasury_cash_sessions
             SET status = 'controlee', control_comment = ?, checked_by = ?, checked_at = NOW()
             WHERE id = ? AND status = 'cloturee'"
        );
        $stmt->execute([$commentaire ?: null, $userId, $sessionId]);
        return $stmt->rowCount() > 0;
    }

    public function soldeCaisseUsd(): float {
        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(CASE
                        WHEN s.currency = 'USD' THEN s.theoretical_balance
                        WHEN s.currency = 'CDF' THEN s.theoretical_balance / 2000
                        ELSE 0
                    END), 0)
             FROM treasury_cash_sessions s
             JOIN (
                SELECT cash_account_id, MAX(session_date) AS last_date
                FROM treasury_cash_sessions
                GROUP BY cash_account_id
             ) last_s ON last_s.cash_account_id = s.cash_account_id AND last_s.last_date = s.session_date"
        );
        return (float)$stmt->fetchColumn();
    }

    // ---------------------------------------------------------------
    // BANQUE & RAPPROCHEMENT
    // ---------------------------------------------------------------

    public function creerReleveBancaire(array $donnees): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO treasury_bank_statements
             (account_id, period_year, period_month, statement_number, opening_balance,
              closing_balance, currency, statement_file, document_id, status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'brouillon', ?, NOW())"
        );

        $stmt->execute([
            (int)$donnees['account_id'],
            (int)$donnees['period_year'],
            (int)$donnees['period_month'],
            $donnees['statement_number'] ?? null,
            $donnees['opening_balance'] ?? 0,
            $donnees['closing_balance'] ?? 0,
            $donnees['currency'] ?? $this->deviseCompte((int)$donnees['account_id']),
            $donnees['statement_file'] ?? null,
            !empty($donnees['document_id']) ? (int)$donnees['document_id'] : null,
            $donnees['created_by'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function listerRelevesBancaires(array $filtres = [], int $limite = 20, int $offset = 0): array {
        $sql = "SELECT s.*, a.nom AS account_nom, u.nom AS created_by_nom
                FROM treasury_bank_statements s
                JOIN financial_accounts a ON a.id = s.account_id
                LEFT JOIN users u ON u.id = s.created_by
                WHERE 1=1";
        $params = [];

        if (!empty($filtres['account_id'])) {
            $sql .= " AND s.account_id = ?";
            $params[] = (int)$filtres['account_id'];
        }
        if (!empty($filtres['period_year'])) {
            $sql .= " AND s.period_year = ?";
            $params[] = (int)$filtres['period_year'];
        }
        if (!empty($filtres['period_month'])) {
            $sql .= " AND s.period_month = ?";
            $params[] = (int)$filtres['period_month'];
        }
        if (!empty($filtres['status'])) {
            $sql .= " AND s.status = ?";
            $params[] = $filtres['status'];
        }

        $sql .= " ORDER BY s.period_year DESC, s.period_month DESC, s.id DESC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function trouverReleveBancaire(int $id) {
        $stmt = $this->pdo->prepare(
            "SELECT s.*, a.nom AS account_nom
             FROM treasury_bank_statements s
             JOIN financial_accounts a ON a.id = s.account_id
             WHERE s.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function ajouterLigneReleve(int $statementId, array $donnees): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO treasury_bank_statement_lines
             (statement_id, operation_date, label, reference_operation, debit, credit,
              status, line_comment, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'non_rapproche', ?, NOW())"
        );
        $stmt->execute([
            $statementId,
            $donnees['operation_date'],
            $donnees['label'],
            $donnees['reference_operation'] ?? null,
            $donnees['debit'] ?? 0,
            $donnees['credit'] ?? 0,
            $donnees['line_comment'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function lignesReleve(int $statementId): array {
        $stmt = $this->pdo->prepare(
            "SELECT l.*, t.reference_unique AS transaction_reference
             FROM treasury_bank_statement_lines l
             LEFT JOIN financial_transactions t ON t.id = l.matched_transaction_id
             WHERE l.statement_id = ?
             ORDER BY l.operation_date ASC, l.id ASC"
        );
        $stmt->execute([$statementId]);
        return $stmt->fetchAll();
    }

    public function trouverRapprochementParReleve(int $statementId) {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, u.nom AS reconciled_by_nom
             FROM treasury_bank_reconciliations r
             LEFT JOIN users u ON u.id = r.reconciled_by
             WHERE r.statement_id = ?
             LIMIT 1"
        );
        $stmt->execute([$statementId]);
        return $stmt->fetch();
    }

    public function creerOuTrouverRapprochement(int $statementId, int $userId = 0): int {
        $stmt = $this->pdo->prepare("SELECT id FROM treasury_bank_reconciliations WHERE statement_id = ? LIMIT 1");
        $stmt->execute([$statementId]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int)$id;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO treasury_bank_reconciliations
             (statement_id, status, reconciled_by, created_at)
             VALUES (?, 'en_cours', ?, NOW())"
        );
        $stmt->execute([$statementId, $userId ?: null]);

        $this->pdo->prepare("UPDATE treasury_bank_statements SET status = 'en_rapprochement' WHERE id = ?")
            ->execute([$statementId]);

        $newId = (int)$this->pdo->lastInsertId();
        if ($newId > 0) {
            return $newId;
        }

        $stmt = $this->pdo->prepare("SELECT id FROM treasury_bank_reconciliations WHERE statement_id = ? LIMIT 1");
        $stmt->execute([$statementId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    public function rapprocherLigne(int $lineId, int $transactionId, int $userId): bool {
        $stmt = $this->pdo->prepare(
            "SELECT l.*, s.status AS statement_status
             FROM treasury_bank_statement_lines l
             JOIN treasury_bank_statements s ON s.id = l.statement_id
             WHERE l.id = ?
             LIMIT 1"
        );
        $stmt->execute([$lineId]);
        $ligne = $stmt->fetch();
        if (!$ligne || in_array($ligne['statement_status'], ['rapproche','verrouille'], true)) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "SELECT montant
             FROM financial_transactions
             WHERE id = ? AND statut = 'valide'
               AND id NOT IN (
                    SELECT matched_transaction_id
                    FROM treasury_bank_statement_lines
                    WHERE matched_transaction_id IS NOT NULL
                      AND status IN ('rapproche','ecart')
               )
             LIMIT 1"
        );
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();
        if (!$transaction) {
            return false;
        }

        $statementId = (int)$ligne['statement_id'];
        $reconciliationId = $this->creerOuTrouverRapprochement($statementId, $userId);
        $lineAmount = max((float)$ligne['debit'], (float)$ligne['credit']);
        $transactionAmount = (float)$transaction['montant'];
        $status = abs($lineAmount - $transactionAmount) <= 0.01 ? 'rapproche' : 'ecart';

        $stmt = $this->pdo->prepare(
            "UPDATE treasury_bank_statement_lines
             SET reconciliation_id = ?, matched_transaction_id = ?, matched_by = ?,
                 matched_at = NOW(), status = ?
             WHERE id = ? AND status <> 'rapproche'"
        );
        $stmt->execute([$reconciliationId, $transactionId, $userId, $status, $lineId]);

        $this->recalculerRapprochement($reconciliationId);
        return $stmt->rowCount() > 0;
    }

    public function recalculerRapprochement(int $reconciliationId): bool {
        $stmt = $this->pdo->prepare(
            "SELECT statement_id FROM treasury_bank_reconciliations WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$reconciliationId]);
        $statementId = (int)($stmt->fetchColumn() ?: 0);
        if ($statementId <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(l.debit), 0) AS total_debit,
                    COALESCE(SUM(l.credit), 0) AS total_credit,
                    COALESCE(SUM(CASE
                        WHEN l.status IN ('rapproche','ecart') THEN COALESCE(t.montant, GREATEST(l.debit, l.credit))
                        ELSE 0
                    END), 0) AS matched_amount,
                    COALESCE(SUM(CASE
                        WHEN l.status IN ('rapproche','ecart') THEN ABS(GREATEST(l.debit, l.credit) - COALESCE(t.montant, 0))
                        ELSE GREATEST(l.debit, l.credit)
                    END), 0) AS variance_amount
             FROM treasury_bank_statement_lines l
             LEFT JOIN financial_transactions t ON t.id = l.matched_transaction_id
             WHERE l.statement_id = ?"
        );
        $stmt->execute([$statementId]);
        $totaux = $stmt->fetch() ?: ['total_debit' => 0, 'total_credit' => 0, 'matched_amount' => 0];

        $stmt = $this->pdo->prepare(
            "UPDATE treasury_bank_reconciliations
             SET total_debit = ?, total_credit = ?, matched_amount = ?, variance_amount = ?
             WHERE id = ?"
        );
        return $stmt->execute([
            $totaux['total_debit'],
            $totaux['total_credit'],
            $totaux['matched_amount'],
            $totaux['variance_amount'],
            $reconciliationId,
        ]);
    }

    public function validerRapprochement(int $reconciliationId, int $userId, string $commentaire = ''): bool {
        $this->recalculerRapprochement($reconciliationId);

        $stmt = $this->pdo->prepare(
            "UPDATE treasury_bank_reconciliations
             SET status = 'verrouille', reconciliation_comment = ?, reconciled_by = ?, reconciled_at = NOW(),
                 locked_by = ?, locked_at = NOW()
             WHERE id = ? AND status IN ('brouillon','en_cours')"
        );
        $stmt->execute([$commentaire ?: null, $userId, $userId, $reconciliationId]);

        if ($stmt->rowCount() > 0) {
            $stmtStatement = $this->pdo->prepare(
                "UPDATE treasury_bank_statements s
                 JOIN treasury_bank_reconciliations r ON r.statement_id = s.id
                 SET s.status = 'verrouille', s.locked_by = ?, s.locked_at = NOW()
                 WHERE r.id = ?"
            );
            $stmtStatement->execute([$userId, $reconciliationId]);
            return true;
        }

        return false;
    }

    // ---------------------------------------------------------------
    // METHODES PRIVEES
    // ---------------------------------------------------------------

    private function appliquerFiltresEncaissements(string &$sql, array &$params, array $filtres): void {
        if (!empty($filtres['status'])) {
            $sql .= " AND r.status = ?";
            $params[] = $filtres['status'];
        }
        if (!empty($filtres['payment_method'])) {
            $sql .= " AND r.payment_method = ?";
            $params[] = $filtres['payment_method'];
        }
        if (!empty($filtres['account_id'])) {
            $sql .= " AND r.account_id = ?";
            $params[] = (int)$filtres['account_id'];
        }
        if (!empty($filtres['date_debut'])) {
            $sql .= " AND r.date_receipt >= ?";
            $params[] = $filtres['date_debut'];
        }
        if (!empty($filtres['date_fin'])) {
            $sql .= " AND r.date_receipt <= ?";
            $params[] = $filtres['date_fin'];
        }
        if (!empty($filtres['recherche'])) {
            $sql .= " AND (r.receipt_number LIKE ? OR r.payer_name LIKE ? OR r.objet LIKE ? OR r.reference_payment LIKE ?)";
            $term = '%' . $filtres['recherche'] . '%';
            $params = array_merge($params, [$term, $term, $term, $term]);
        }
    }

    private function rapportCaisseJournalier(array $filtres): array {
        $rows = $this->listerSessionsCaisse([
            'cash_account_id' => (int)($filtres['account_id'] ?? 0),
            'status' => $filtres['status'] ?? '',
            'date_debut' => $filtres['date_debut'] ?? date('Y-m-d'),
            'date_fin' => $filtres['date_fin'] ?? date('Y-m-d'),
        ], 500, 0);

        return [
            'titre' => 'Rapport journalier de caisse',
            'colonnes' => ['Date', 'Compte', 'Devise', 'Solde ouverture', 'Entrees', 'Sorties', 'Solde theorique', 'Montant compte', 'Ecart', 'Statut'],
            'lignes' => array_map(static function ($row) {
                return [
                    $row['session_date'],
                    $row['cash_account_nom'],
                    $row['currency'],
                    $row['opening_balance'],
                    $row['cash_in'],
                    $row['cash_out'],
                    $row['theoretical_balance'],
                    $row['physical_balance'],
                    $row['variance_amount'],
                    $row['status'],
                ];
            }, $rows),
        ];
    }

    private function rapportEncaissements(array $filtres): array {
        $rows = $this->listerEncaissements([
            'status' => $filtres['status'] ?? '',
            'payment_method' => $filtres['payment_method'] ?? '',
            'account_id' => (int)($filtres['account_id'] ?? 0),
            'date_debut' => $filtres['date_debut'] ?? '',
            'date_fin' => $filtres['date_fin'] ?? '',
            'recherche' => $filtres['recherche'] ?? '',
        ], 1000, 0);

        return [
            'titre' => 'Liste des encaissements',
            'colonnes' => ['Date', 'Recu', 'Payeur', 'Objet', 'Mode', 'Compte', 'Montant', 'Devise', 'Montant USD', 'Statut'],
            'lignes' => array_map(static function ($row) {
                return [
                    $row['date_receipt'],
                    $row['receipt_number'],
                    $row['payer_name'],
                    $row['objet'],
                    $row['payment_method'],
                    $row['account_nom'],
                    $row['amount'],
                    $row['currency'],
                    $row['amount_usd'],
                    $row['status'],
                ];
            }, $rows),
        ];
    }

    private function rapportDecaissements(array $filtres): array {
        $sql = "SELECT p.payment_date, po.order_number, po.beneficiary, fr.objet,
                       po.payment_method, a.nom AS account_nom, p.amount, p.currency,
                       p.amount_usd, p.reference_payment, po.status
                FROM treasury_payments p
                JOIN treasury_payment_orders po ON po.id = p.payment_order_id
                JOIN treasury_fund_requests fr ON fr.id = po.request_id
                JOIN financial_accounts a ON a.id = po.account_id
                WHERE p.cancelled_at IS NULL";
        $params = [];

        if (!empty($filtres['account_id'])) {
            $sql .= " AND po.account_id = ?";
            $params[] = (int)$filtres['account_id'];
        }
        if (!empty($filtres['payment_method'])) {
            $sql .= " AND po.payment_method = ?";
            $params[] = $filtres['payment_method'];
        }
        if (!empty($filtres['date_debut'])) {
            $sql .= " AND p.payment_date >= ?";
            $params[] = $filtres['date_debut'];
        }
        if (!empty($filtres['date_fin'])) {
            $sql .= " AND p.payment_date <= ?";
            $params[] = $filtres['date_fin'];
        }
        if (!empty($filtres['recherche'])) {
            $sql .= " AND (po.order_number LIKE ? OR po.beneficiary LIKE ? OR fr.objet LIKE ?)";
            $term = '%' . $filtres['recherche'] . '%';
            $params = array_merge($params, [$term, $term, $term]);
        }

        $sql .= " ORDER BY p.payment_date DESC, p.id DESC LIMIT 1000";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return [
            'titre' => 'Liste des decaissements',
            'colonnes' => ['Date', 'Ordre', 'Beneficiaire', 'Objet', 'Mode', 'Compte', 'Montant', 'Devise', 'Montant USD', 'Reference', 'Statut'],
            'lignes' => array_map(static function ($row) {
                return [
                    $row['payment_date'],
                    $row['order_number'],
                    $row['beneficiary'],
                    $row['objet'],
                    $row['payment_method'],
                    $row['account_nom'],
                    $row['amount'],
                    $row['currency'],
                    $row['amount_usd'],
                    $row['reference_payment'],
                    $row['status'],
                ];
            }, $rows),
        ];
    }

    private function rapportDemandesParStatut(array $filtres): array {
        $sql = "SELECT status, COUNT(*) AS total, COALESCE(SUM(amount_usd), 0) AS total_usd
                FROM treasury_fund_requests
                WHERE 1=1";
        $params = [];

        if (!empty($filtres['date_debut'])) {
            $sql .= " AND request_date >= ?";
            $params[] = $filtres['date_debut'];
        }
        if (!empty($filtres['date_fin'])) {
            $sql .= " AND request_date <= ?";
            $params[] = $filtres['date_fin'];
        }

        $sql .= " GROUP BY status ORDER BY FIELD(status, 'brouillon','a_controler','soumise','en_suspens','validee_comptable','validee_direction','rejetee','ordre_emis','payee','annulee')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return [
            'titre' => 'Demandes de fonds par statut',
            'colonnes' => ['Statut', 'Nombre', 'Montant total USD'],
            'lignes' => array_map(static fn($row) => [$row['status'], $row['total'], $row['total_usd']], $rows),
        ];
    }

    private function rapportOrdresPaiement(array $filtres): array {
        $rows = $this->listerOrdres([
            'status' => $filtres['status'] ?? '',
            'recherche' => $filtres['recherche'] ?? '',
        ], 1000, 0);

        return [
            'titre' => 'Ordres de paiement',
            'colonnes' => ['Date', 'Ordre', 'Demande', 'Beneficiaire', 'Objet', 'Mode', 'Compte', 'Montant', 'Devise', 'Montant USD', 'Statut'],
            'lignes' => array_map(static function ($row) {
                return [
                    $row['order_date'],
                    $row['order_number'],
                    $row['request_number'],
                    $row['beneficiary'],
                    $row['objet'],
                    $row['payment_method'],
                    $row['account_nom'],
                    $row['amount'],
                    $row['currency'],
                    $row['amount_usd'],
                    $row['status'],
                ];
            }, $rows),
        ];
    }

    private function rapportRapprochementMensuel(array $filtres): array {
        $sql = "SELECT s.period_year, s.period_month, a.nom AS account_nom, s.statement_number,
                       s.opening_balance, s.closing_balance, s.currency, s.status AS statement_status,
                       r.total_debit, r.total_credit, r.matched_amount, r.variance_amount,
                       r.status AS reconciliation_status
                FROM treasury_bank_statements s
                JOIN financial_accounts a ON a.id = s.account_id
                LEFT JOIN treasury_bank_reconciliations r ON r.statement_id = s.id
                WHERE 1=1";
        $params = [];

        if (!empty($filtres['account_id'])) {
            $sql .= " AND s.account_id = ?";
            $params[] = (int)$filtres['account_id'];
        }
        if (!empty($filtres['annee'])) {
            $sql .= " AND s.period_year = ?";
            $params[] = (int)$filtres['annee'];
        }
        if (!empty($filtres['mois'])) {
            $sql .= " AND s.period_month = ?";
            $params[] = (int)$filtres['mois'];
        }
        if (!empty($filtres['status'])) {
            $sql .= " AND s.status = ?";
            $params[] = $filtres['status'];
        }

        $sql .= " ORDER BY s.period_year DESC, s.period_month DESC, a.nom ASC LIMIT 1000";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return [
            'titre' => 'Rapprochement bancaire mensuel',
            'colonnes' => ['Annee', 'Mois', 'Compte', 'Releve', 'Ouverture', 'Cloture', 'Devise', 'Debit', 'Credit', 'Rapproche', 'Ecart', 'Statut releve', 'Statut rapprochement'],
            'lignes' => array_map(static function ($row) {
                return [
                    $row['period_year'],
                    $row['period_month'],
                    $row['account_nom'],
                    $row['statement_number'],
                    $row['opening_balance'],
                    $row['closing_balance'],
                    $row['currency'],
                    $row['total_debit'],
                    $row['total_credit'],
                    $row['matched_amount'],
                    $row['variance_amount'],
                    $row['statement_status'],
                    $row['reconciliation_status'] ?? 'brouillon',
                ];
            }, $rows),
        ];
    }

    private function rapportSituationGlobale(): array {
        $stats = $this->statistiquesDashboard();
        $lignes = [
            ['Caisse', 'Solde caisse actuel', $stats['solde_caisse_usd'], 'USD', $stats['alerte_plafond_caisse'] ? 'Alerte plafond' : 'OK'],
        ];

        foreach ($this->soldesBanqueParCompte() as $compte) {
            $lignes[] = ['Banque', $compte['nom'], $compte['solde'], $compte['devise'], $compte['reference_compte'] ?? ''];
        }

        $lignes[] = ['Engagements', 'Demandes en attente', $stats['demandes_en_attente'], 'NOMBRE', ''];
        $lignes[] = ['Engagements', 'Ordres non executes', $stats['ordres_non_executes'], 'NOMBRE', ''];
        $lignes[] = ['Controle', 'Rapprochements en attente', $stats['rapprochements_en_attente'], 'NOMBRE', ''];
        $lignes[] = ['Controle', 'Ecarts de caisse', $stats['ecarts_caisse_count'], 'NOMBRE', ''];

        return [
            'titre' => 'Situation de tresorerie globale',
            'colonnes' => ['Categorie', 'Libelle', 'Valeur', 'Unite', 'Observation'],
            'lignes' => $lignes,
        ];
    }

    private function appliquerFiltresDemandes(string &$sql, array &$params, array $filtres): void {
        if (!empty($filtres['status'])) {
            $sql .= " AND r.status = ?";
            $params[] = $filtres['status'];
        }
        if (!empty($filtres['urgency'])) {
            $sql .= " AND r.urgency = ?";
            $params[] = $filtres['urgency'];
        }
        if (!empty($filtres['created_by'])) {
            $sql .= " AND r.created_by = ?";
            $params[] = (int)$filtres['created_by'];
        }
        if (!empty($filtres['department'])) {
            $sql .= " AND r.department = ?";
            $params[] = (string)$filtres['department'];
        }
        if (!empty($filtres['date_debut'])) {
            $sql .= " AND r.request_date >= ?";
            $params[] = $filtres['date_debut'];
        }
        if (!empty($filtres['date_fin'])) {
            $sql .= " AND r.request_date <= ?";
            $params[] = $filtres['date_fin'];
        }
        if (!empty($filtres['recherche'])) {
            $sql .= " AND (r.request_number LIKE ? OR r.beneficiary LIKE ? OR r.objet LIKE ? OR r.description LIKE ? OR r.department LIKE ? OR r.requester_name LIKE ?)";
            $term = '%' . $filtres['recherche'] . '%';
            $params = array_merge($params, [$term, $term, $term, $term, $term, $term]);
        }
    }

    private function verrouillerDemande(int $id) {
        $stmt = $this->pdo->prepare('SELECT * FROM treasury_fund_requests WHERE id=? FOR UPDATE');
        $stmt->execute([$id]); return $stmt->fetch();
    }

    /** Serialize treasury commitments for this account and compare native monetary units. */
    private function compteCouvrePaiement(int $accountId, string $currency, float $amount, string $paymentMethod, int $excludingOrderId = 0): bool {
        $stmt = $this->pdo->prepare('SELECT devise, actif, type_compte FROM financial_accounts WHERE id=? FOR UPDATE');
        $stmt->execute([$accountId]);
        $account = $stmt->fetch();
        $expectedType = ['especes'=>'caisse','banque'=>'banque','mobile_money'=>'mobile_money'][$paymentMethod] ?? null;
        if (!$account || !(int)$account['actif'] || $account['devise'] !== $currency || $expectedType !== $account['type_compte']) { return false; }
        // Lock drafts as well: a concurrent validation must not change the balance between check and posting.
        $stmt = $this->pdo->prepare('SELECT type_flux, montant, devise, statut FROM financial_transactions WHERE compte_id=? FOR UPDATE');
        $stmt->execute([$accountId]);
        $balance = 0.0;
        $mixedCurrency = false;
        foreach ($stmt->fetchAll() as $transaction) {
            if ($transaction['statut'] !== 'valide') { continue; }
            if ($transaction['devise'] !== $currency) { $mixedCurrency = true; }
            if ($transaction['type_flux'] === 'revenu') { $balance += (float)$transaction['montant']; }
            elseif ($transaction['type_flux'] === 'depense') { $balance -= (float)$transaction['montant']; }
        }
        // An account containing historical mixed-currency postings must be reconciled first.
        if ($mixedCurrency) { return false; }
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(amount),0) AS reserved,
                COALESCE(SUM(currency <> ?),0) AS mixed_currency
             FROM treasury_payment_orders WHERE account_id=? AND status='emis' AND id<>?");
        $stmt->execute([$currency, $accountId, $excludingOrderId]);
        $pending = $stmt->fetch();
        return (int)$pending['mixed_currency'] === 0 && $balance - (float)$pending['reserved'] + 0.00001 >= $amount;
    }

    private function enregistrerDecisionDemande(int $id, string $niveau, int $userId, string $decision, string $commentaire, ?float $montant = null, ?float $usd = null, ?string $rappel = null): void {
        $stmt = $this->pdo->prepare('INSERT INTO treasury_request_approvals (request_id, approval_level, decision, approval_comment, approved_by, approved_amount, approved_amount_usd, reminder_date, approved_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$id, $niveau, $decision, $commentaire ?: null, $userId, $montant, $usd, $rappel]);
    }

    private function separationDemandeRespectee(array $demande, int $userId): bool {
        // Legacy requests keep their pre-existing API behavior; new departmental requests enforce separation.
        return empty($demande['department']) || ($userId !== (int)$demande['created_by'] && $userId !== (int)$demande['submitted_by']);
    }

    private function dateRappelValide(?string $date): bool {
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { return false; }
        [$annee, $mois, $jour] = array_map('intval', explode('-', $date));
        return checkdate($mois, $jour, $annee) && $date >= date('Y-m-d');
    }

    private function normaliserServiceDemande(string $service): string {
        return strtr(mb_strtolower(trim($service), 'UTF-8'), ['é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a','ù'=>'u','û'=>'u','î'=>'i','ï'=>'i','ô'=>'o','ç'=>'c']);
    }

    private function utilisateurValideNiveauDemande(array $user, string $niveau): bool {
        $role = (string)$user['role'];
        $service = $this->normaliserServiceDemande((string)$user['service']);
        if ($role === 'admin') { return true; }
        if ($niveau === 'comptable') {
            return $role === 'comptable_senior' || ($role === 'chef_service' && (bool)preg_match('/finance|comptable|comptabilite/', $service));
        }
        if ($niveau === 'direction_financiere') {
            return $role === 'directeur_financier' || ($role === 'direction' && (bool)preg_match('/finance|direction financiere|direction des finances/', $service));
        }
        return $niveau === 'direction_generale' && ($role === 'directeur_general' || ($role === 'direction' && strpos($service, 'generale') !== false));
    }

    private function niveauValidationPossible(array $demande, string $niveau): bool {
        if ($niveau === 'comptable') {
            return $demande['status'] === 'soumise'
                && !$this->validationExiste((int)$demande['id'], 'comptable', 'valide');
        }

        if (in_array($niveau, ['direction_financiere', 'direction_generale'], true)) {
            return $demande['status'] === 'validee_comptable'
                && (float)$demande['amount_usd'] >= $this->seuilValidationDirectionUsd()
                && !$this->validationExiste((int)$demande['id'], $niveau, 'valide');
        }

        return false;
    }

    private function statutApresValidation(int $requestId, array $demande, string $niveau): string {
        if ($niveau === 'comptable') {
            return (float)$demande['amount_usd'] >= $this->seuilValidationDirectionUsd()
                ? 'validee_comptable'
                : 'validee_direction';
        }

        return $this->validationsDirectionCompletes($requestId)
            ? 'validee_direction'
            : 'validee_comptable';
    }

    private function demandePretePourOrdre(array $demande): bool {
        return $demande['status'] === 'validee_direction';
    }

    private function validationExiste(int $requestId, string $niveau, string $decision): bool {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM treasury_request_approvals
             WHERE request_id = ? AND approval_level = ? AND decision = ?"
        );
        $stmt->execute([$requestId, $niveau, $decision]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function validationsDirectionCompletes(int $requestId): bool {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT approval_level)
             FROM treasury_request_approvals
             WHERE request_id = ?
               AND decision = 'valide'
               AND approval_level IN ('direction_financiere','direction_generale')"
        );
        $stmt->execute([$requestId]);
        return (int)$stmt->fetchColumn() >= 2;
    }

    private function creerTransactionFinanciere(array $donnees): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO financial_transactions
             (reference_unique, date_operation, type_flux, categorie_id, compte_id, montant,
              devise, taux_change, montant_usd, statut, partenaire, description, piece_jointe,
              created_by, approved_by, approved_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );

        $stmt->execute([
            $this->genererReferenceTransaction(),
            $donnees['date_operation'],
            $donnees['type_flux'],
            $donnees['categorie_id'],
            $donnees['compte_id'],
            $donnees['montant'],
            $donnees['devise'],
            !empty($donnees['taux_change']) ? $donnees['taux_change'] : null,
            $donnees['montant_usd'],
            $donnees['statut'] ?? 'valide',
            $donnees['partenaire'] ?? null,
            $donnees['description'] ?? null,
            $donnees['piece_jointe'] ?? null,
            $donnees['created_by'] ?? null,
            $donnees['approved_by'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function categorieParDefaut(string $typeFlux): int {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM financial_categories
             WHERE type_flux = ? AND actif = 1
             ORDER BY nom ASC
             LIMIT 1"
        );
        $stmt->execute([$typeFlux]);
        return (int)$stmt->fetchColumn();
    }

    private function deviseCompte(int $accountId): string {
        $stmt = $this->pdo->prepare("SELECT devise FROM financial_accounts WHERE id = ? LIMIT 1");
        $stmt->execute([$accountId]);
        return (string)($stmt->fetchColumn() ?: 'USD');
    }

    private function soldeCaisseCompteAvantDate(int $cashAccountId, string $date): float {
        $stmt = $this->pdo->prepare(
            "SELECT theoretical_balance
             FROM treasury_cash_sessions
             WHERE cash_account_id = ? AND session_date < ?
             ORDER BY session_date DESC
             LIMIT 1"
        );
        $stmt->execute([$cashAccountId, $date]);
        $last = $stmt->fetchColumn();
        return $last !== false ? (float)$last : 0.0;
    }

    private function convertirVersUsd(float $montant, string $devise, ?float $taux): float {
        if ($devise === 'USD') {
            return $montant;
        }

        if ($devise === 'CDF' && $taux !== null && $taux > 0) {
            return $montant / $taux;
        }

        $fallback = (float)$this->getSetting('default_exchange_rate_cdf_usd', '2000');
        return $fallback > 0 ? $montant / $fallback : $montant;
    }

    private function genererNumeroSequence(string $prefix, string $table, string $dateColumn): string {
        $datePart = date('Ym');
        $sql = "SELECT COUNT(*) FROM {$table} WHERE DATE_FORMAT({$dateColumn}, '%Y%m') = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$datePart]);
        $compteur = (int)$stmt->fetchColumn() + 1;
        return sprintf('%s-%s-%04d', $prefix, $datePart, $compteur);
    }

    private function genererReferenceTransaction(): string {
        $datePart = date('Ym');
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM financial_transactions
             WHERE DATE_FORMAT(created_at, '%Y%m') = ?"
        );
        $stmt->execute([$datePart]);
        $compteur = (int)$stmt->fetchColumn() + 1;
        return sprintf('FT-%s-%04d', $datePart, $compteur);
    }
}
