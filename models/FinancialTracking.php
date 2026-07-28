<?php
/**
 * Modèle FinancialTracking - Suivi financier (revenus, dépenses, budgets)
 * AMM-ERP
 */
class FinancialTracking {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Liste des catégories actives, optionnellement filtrées par type.
     */
    public function listerCategories(string $typeFlux = ''): array {
        $sql = "SELECT * FROM financial_categories WHERE actif = 1";
        $params = [];

        if ($typeFlux !== '') {
            $sql .= " AND type_flux = ?";
            $params[] = $typeFlux;
        }

        $sql .= " ORDER BY type_flux ASC, nom ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Liste des comptes actifs.
     */
    public function listerComptes(): array {
        $stmt = $this->pdo->query(
            "SELECT a.*,
                    COALESCE((
                        SELECT SUM(CASE
                            WHEN t.type_flux = 'revenu' THEN t.montant
                            WHEN t.type_flux = 'depense' THEN -t.montant
                            ELSE 0
                        END)
                        FROM financial_transactions t
                        WHERE t.compte_id = a.id AND t.statut = 'valide'
                    ), 0) AS solde_disponible
             FROM financial_accounts a
             WHERE a.actif = 1
             ORDER BY a.nom ASC"
        );
        return $stmt->fetchAll();
    }

    public function soldeCompteDisponible(int $id): float {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE
                        WHEN type_flux = 'revenu' THEN montant
                        WHEN type_flux = 'depense' THEN -montant
                        ELSE 0
                    END), 0)
             FROM financial_transactions
             WHERE compte_id = ? AND statut = 'valide'"
        );
        $stmt->execute([$id]);
        return (float)$stmt->fetchColumn();
    }

    /** Liste tous les comptes, y compris les comptes désactivés (administration). */
    public function listerTousComptes(): array {
        return $this->pdo->query(
            "SELECT * FROM financial_accounts ORDER BY actif DESC, type_compte ASC, nom ASC"
        )->fetchAll();
    }

    public function trouverCompteParId(int $id) {
        $stmt = $this->pdo->prepare("SELECT * FROM financial_accounts WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function enregistrerCompte(array $donnees, ?int $id = null): int {
        if ($id !== null) {
            $stmt = $this->pdo->prepare(
                "UPDATE financial_accounts
                 SET nom = ?, type_compte = ?, devise = ?, reference_compte = ?
                 WHERE id = ?"
            );
            $stmt->execute([
                $donnees['nom'], $donnees['type_compte'], $donnees['devise'],
                $donnees['reference_compte'] ?: null, $id,
            ]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO financial_accounts (nom, type_compte, devise, reference_compte, actif, created_at)
             VALUES (?, ?, ?, ?, 1, NOW())"
        );
        $stmt->execute([
            $donnees['nom'], $donnees['type_compte'], $donnees['devise'],
            $donnees['reference_compte'] ?: null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function toggleCompteActif(int $id): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE financial_accounts SET actif = IF(actif = 1, 0, 1) WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /** Liste toutes les catégories, y compris les catégories désactivées. */
    public function listerToutesCategories(): array {
        return $this->pdo->query(
            "SELECT * FROM financial_categories ORDER BY actif DESC, type_flux ASC, nom ASC"
        )->fetchAll();
    }

    public function trouverCategorieParId(int $id) {
        $stmt = $this->pdo->prepare("SELECT * FROM financial_categories WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function enregistrerCategorie(array $donnees, ?int $id = null): int {
        if ($id !== null) {
            $stmt = $this->pdo->prepare(
                "UPDATE financial_categories SET nom = ?, type_flux = ? WHERE id = ?"
            );
            $stmt->execute([$donnees['nom'], $donnees['type_flux'], $id]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO financial_categories (nom, type_flux, actif, created_at)
             VALUES (?, ?, 1, NOW())"
        );
        $stmt->execute([$donnees['nom'], $donnees['type_flux']]);
        return (int)$this->pdo->lastInsertId();
    }

    public function toggleCategorieActive(int $id): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE financial_categories SET actif = IF(actif = 1, 0, 1) WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Compte les transactions avec filtres.
     */
    public function compterTransactions(array $filtres = []): int {
        $sql = "SELECT COUNT(*)
                FROM financial_transactions t
                JOIN financial_categories c ON t.categorie_id = c.id
                JOIN financial_accounts a ON t.compte_id = a.id
                WHERE 1=1";
        $params = [];

        $this->appliquerFiltresTransaction($sql, $params, $filtres);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Liste paginée des transactions financières.
     */
    public function listerTransactions(array $filtres = [], int $limite = 20, int $offset = 0): array {
        $sql = "SELECT t.*, c.nom AS categorie_nom, c.type_flux AS categorie_type_flux,
                       a.nom AS compte_nom, a.type_compte, u.nom AS created_by_nom,
                       ua.nom AS approved_by_nom
                FROM financial_transactions t
                JOIN financial_categories c ON t.categorie_id = c.id
                JOIN financial_accounts a ON t.compte_id = a.id
                LEFT JOIN users u ON t.created_by = u.id
                LEFT JOIN users ua ON t.approved_by = ua.id
                WHERE 1=1";
        $params = [];

        $this->appliquerFiltresTransaction($sql, $params, $filtres);

        $sql .= " ORDER BY t.date_operation DESC, t.id DESC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Crée une transaction.
     */
    public function creerTransaction(array $donnees): int {
        $reference = $this->genererReferenceTransaction();
        $montantUsd = $this->convertirVersUsd(
            (float)$donnees['montant'],
            $donnees['devise'],
            isset($donnees['taux_change']) ? (float)$donnees['taux_change'] : null
        );

        $stmt = $this->pdo->prepare(
            "INSERT INTO financial_transactions
             (reference_unique, date_operation, type_flux, categorie_id, compte_id, montant,
              devise, taux_change, montant_usd, statut, partenaire, description, piece_jointe,
              created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        $stmt->execute([
            $reference,
            $donnees['date_operation'],
            $donnees['type_flux'],
            $donnees['categorie_id'],
            $donnees['compte_id'],
            $donnees['montant'],
            $donnees['devise'],
            $donnees['taux_change'] ?: null,
            $montantUsd,
            $donnees['statut'] ?? 'brouillon',
            $donnees['partenaire'] ?: null,
            $donnees['description'] ?: null,
            $donnees['piece_jointe'] ?: null,
            $donnees['created_by'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Trouve une transaction par ID.
     */
    public function trouverTransactionParId(int $id) {
        $stmt = $this->pdo->prepare(
            "SELECT t.*, c.nom AS categorie_nom, a.nom AS compte_nom
             FROM financial_transactions t
             JOIN financial_categories c ON t.categorie_id = c.id
             JOIN financial_accounts a ON t.compte_id = a.id
             WHERE t.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Valide une transaction brouillon.
     */
    public function validerTransaction(int $id, int $userId): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE financial_transactions
             SET statut = 'valide', approved_by = ?, approved_at = NOW()
             WHERE id = ? AND statut = 'brouillon'"
        );
        return $stmt->execute([$userId, $id]);
    }

    /**
     * Annule une transaction et consigne un motif.
     */
    public function annulerTransaction(int $id, int $userId, string $motif): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE financial_transactions
             SET statut = 'annule', cancelled_by = ?, cancelled_at = NOW(), cancellation_reason = ?
             WHERE id = ? AND statut <> 'annule'"
        );
        return $stmt->execute([$userId, $motif, $id]);
    }

    /**
     * Enregistre ou met à jour un budget mensuel.
     */
    public function upsertBudget(array $donnees): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO budgets (annee, mois, categorie_id, montant_prevu, devise, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                montant_prevu = VALUES(montant_prevu),
                created_by = VALUES(created_by),
                updated_at = NOW()"
        );

        $stmt->execute([
            $donnees['annee'],
            $donnees['mois'],
            $donnees['categorie_id'],
            $donnees['montant_prevu'],
            $donnees['devise'],
            $donnees['created_by'] ?? null,
        ]);
    }

    /**
     * Budgets pour un mois donné.
     */
    public function listerBudgetsMois(int $annee, int $mois): array {
        $stmt = $this->pdo->prepare(
            "SELECT b.*, c.nom AS categorie_nom, c.type_flux
             FROM budgets b
             JOIN financial_categories c ON b.categorie_id = c.id
             WHERE b.annee = ? AND b.mois = ?
             ORDER BY c.type_flux ASC, c.nom ASC"
        );
        $stmt->execute([$annee, $mois]);
        return $stmt->fetchAll();
    }

    /**
     * Statistiques globales pour le pilotage financier.
     */
    public function statistiquesMois(int $annee, int $mois): array {
        $stats = [
            'revenus_usd' => 0.0,
            'depenses_usd' => 0.0,
            'solde_usd' => 0.0,
            'transactions_valides' => 0,
            'transactions_brouillon' => 0,
            'budget_depense_usd' => 0.0,
            'realise_depense_usd' => 0.0,
            'execution_depense_pct' => 0.0,
        ];

        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN type_flux = 'revenu' THEN montant_usd ELSE 0 END), 0) AS revenus_usd,
                COALESCE(SUM(CASE WHEN type_flux = 'depense' THEN montant_usd ELSE 0 END), 0) AS depenses_usd,
                COUNT(*) AS nb_valides
             FROM financial_transactions
             WHERE statut = 'valide'
               AND YEAR(date_operation) = ?
               AND MONTH(date_operation) = ?"
        );
        $stmt->execute([$annee, $mois]);
        $ligne = $stmt->fetch();

        $stats['revenus_usd'] = (float)($ligne['revenus_usd'] ?? 0);
        $stats['depenses_usd'] = (float)($ligne['depenses_usd'] ?? 0);
        $stats['solde_usd'] = $stats['revenus_usd'] - $stats['depenses_usd'];
        $stats['transactions_valides'] = (int)($ligne['nb_valides'] ?? 0);

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM financial_transactions
             WHERE statut = 'brouillon'
               AND YEAR(date_operation) = ?
               AND MONTH(date_operation) = ?"
        );
        $stmt->execute([$annee, $mois]);
        $stats['transactions_brouillon'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE
                        WHEN b.devise = 'USD' THEN b.montant_prevu
                        WHEN b.devise = 'CDF' THEN (b.montant_prevu / 2000)
                        ELSE 0
                    END), 0)
             FROM budgets b
             JOIN financial_categories c ON b.categorie_id = c.id
             WHERE b.annee = ? AND b.mois = ? AND c.type_flux = 'depense'"
        );
        $stmt->execute([$annee, $mois]);
        $stats['budget_depense_usd'] = (float)$stmt->fetchColumn();

        $stats['realise_depense_usd'] = $stats['depenses_usd'];
        if ($stats['budget_depense_usd'] > 0) {
            $stats['execution_depense_pct'] = ($stats['realise_depense_usd'] / $stats['budget_depense_usd']) * 100;
        }

        return $stats;
    }

    /**
     * Transactions récentes.
     */
    public function transactionsRecentes(int $limite = 8): array {
        $stmt = $this->pdo->prepare(
            "SELECT t.*, c.nom AS categorie_nom, a.nom AS compte_nom
             FROM financial_transactions t
             JOIN financial_categories c ON t.categorie_id = c.id
             JOIN financial_accounts a ON t.compte_id = a.id
             ORDER BY t.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$limite]);
        return $stmt->fetchAll();
    }

    /**
     * Compte les factures selon filtres.
     */
    public function compterFactures(array $filtres = []): int {
        $sql = "SELECT COUNT(*) FROM invoices i WHERE 1=1";
        $params = [];

        $this->appliquerFiltresFacture($sql, $params, $filtres);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Liste paginée des factures.
     */
    public function listerFactures(array $filtres = [], int $limite = 20, int $offset = 0): array {
        $sql = "SELECT i.*, u.nom AS created_by_nom
                FROM invoices i
                LEFT JOIN users u ON i.created_by = u.id
                WHERE 1=1";
        $params = [];

        $this->appliquerFiltresFacture($sql, $params, $filtres);

        $sql .= " ORDER BY i.date_echeance ASC, i.id DESC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Crée une facture.
     */
    public function creerFacture(array $donnees): int {
        $numero = $this->genererNumeroFacture();
        $stmt = $this->pdo->prepare(
            "INSERT INTO invoices
             (numero_facture, type_facture, partenaire, objet, date_facture, date_echeance,
              montant_total, montant_paye, devise, statut, description, piece_jointe, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, 'brouillon', ?, ?, ?, NOW())"
        );

        $stmt->execute([
            $numero,
            $donnees['type_facture'],
            $donnees['partenaire'],
            $donnees['objet'],
            $donnees['date_facture'],
            $donnees['date_echeance'],
            $donnees['montant_total'],
            $donnees['devise'],
            $donnees['description'] ?: null,
            $donnees['piece_jointe'] ?: null,
            $donnees['created_by'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Trouve une facture avec métriques de paiement.
     */
    public function trouverFactureParId(int $id) {
        $stmt = $this->pdo->prepare(
            "SELECT i.*, u.nom AS created_by_nom
             FROM invoices i
             LEFT JOIN users u ON i.created_by = u.id
             WHERE i.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Paiements d'une facture.
     */
    public function listerPaiementsFacture(int $invoiceId): array {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.nom AS created_by_nom
             FROM invoice_payments p
             LEFT JOIN users u ON p.created_by = u.id
             WHERE p.invoice_id = ?
             ORDER BY p.date_paiement DESC, p.id DESC"
        );
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll();
    }

    /**
     * Historique des relances d'une facture.
     */
    public function listerRelancesFacture(int $invoiceId): array {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, u.nom AS reminded_by_nom
             FROM invoice_reminders r
             LEFT JOIN users u ON r.reminded_by = u.id
             WHERE r.invoice_id = ?
             ORDER BY r.reminded_at DESC, r.id DESC"
        );
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll();
    }

    /**
     * Valide une facture brouillon.
     */
    public function validerFacture(int $id, int $userId): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE invoices
             SET statut = 'validee', approved_by = ?, approved_at = NOW()
             WHERE id = ? AND statut = 'brouillon'"
        );
        return $stmt->execute([$userId, $id]);
    }

    /**
     * Ajoute un paiement et met à jour le statut de la facture.
     */
    public function ajouterPaiementFacture(int $invoiceId, array $donnees): int {
        $facture = $this->trouverFactureParId($invoiceId);
        if (!$facture) {
            return 0;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO invoice_payments
             (invoice_id, date_paiement, montant, devise, reference_paiement, commentaire, piece_jointe, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $invoiceId,
            $donnees['date_paiement'],
            $donnees['montant'],
            $donnees['devise'],
            $donnees['reference_paiement'] ?: null,
            $donnees['commentaire'] ?: null,
            $donnees['piece_jointe'] ?: null,
            $donnees['created_by'] ?? null,
        ]);

        $totalPaye = $this->totalPayeFacture($invoiceId);
        $this->mettreAJourEtatFacture($invoiceId, (float)$facture['montant_total'], $totalPaye, (string)$facture['statut'], (string)$facture['date_echeance']);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Enregistre une relance sur une facture.
     */
    public function ajouterRelanceFacture(int $invoiceId, array $donnees): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO invoice_reminders
             (invoice_id, reminder_level, reminder_channel, reminder_note, reminded_by, reminded_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $invoiceId,
            $donnees['reminder_level'] ?? 'R1',
            $donnees['reminder_channel'] ?? 'email',
            $donnees['reminder_note'] ?? null,
            $donnees['reminded_by'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Total payé d'une facture.
     */
    public function totalPayeFacture(int $invoiceId): float {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(montant), 0) FROM invoice_payments WHERE invoice_id = ?"
        );
        $stmt->execute([$invoiceId]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Met à jour les factures validées en retard.
     */
    public function marquerFacturesEnRetard(): void {
        $this->pdo->exec(
            "UPDATE invoices
             SET statut = 'en_retard'
             WHERE statut IN ('validee', 'partiellement_payee')
               AND date_echeance < CURDATE()"
        );
    }

    /**
     * Résumé facturation pour dashboard.
     */
    public function statistiquesFactures(): array {
        $this->marquerFacturesEnRetard();

        $stats = [
            'encours_client_usd' => 0.0,
            'encours_fournisseur_usd' => 0.0,
            'retard_count' => 0,
            'factures_ouvertes' => 0,
        ];

        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(CASE
                        WHEN type_facture = 'client' AND devise = 'USD' THEN (montant_total - montant_paye)
                        WHEN type_facture = 'client' AND devise = 'CDF' THEN (montant_total - montant_paye) / 2000
                        ELSE 0
                    END), 0)
             FROM invoices
             WHERE statut IN ('validee','partiellement_payee','en_retard')"
        );
        $stats['encours_client_usd'] = (float)$stmt->fetchColumn();

        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(CASE
                        WHEN type_facture = 'fournisseur' AND devise = 'USD' THEN (montant_total - montant_paye)
                        WHEN type_facture = 'fournisseur' AND devise = 'CDF' THEN (montant_total - montant_paye) / 2000
                        ELSE 0
                    END), 0)
             FROM invoices
             WHERE statut IN ('validee','partiellement_payee','en_retard')"
        );
        $stats['encours_fournisseur_usd'] = (float)$stmt->fetchColumn();

        $stats['retard_count'] = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM invoices WHERE statut = 'en_retard'"
        )->fetchColumn();

        $stats['factures_ouvertes'] = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM invoices WHERE statut IN ('validee','partiellement_payee','en_retard')"
        )->fetchColumn();

        return $stats;
    }

    /**
     * Injecte les filtres SQL pour les transactions.
     */
    private function appliquerFiltresTransaction(string &$sql, array &$params, array $filtres): void {
        if (!empty($filtres['type_flux'])) {
            $sql .= " AND t.type_flux = ?";
            $params[] = $filtres['type_flux'];
        }
        if (!empty($filtres['statut'])) {
            $sql .= " AND t.statut = ?";
            $params[] = $filtres['statut'];
        }
        if (!empty($filtres['devise'])) {
            $sql .= " AND t.devise = ?";
            $params[] = $filtres['devise'];
        }
        if (!empty($filtres['annee'])) {
            $sql .= " AND YEAR(t.date_operation) = ?";
            $params[] = (int)$filtres['annee'];
        }
        if (!empty($filtres['mois'])) {
            $sql .= " AND MONTH(t.date_operation) = ?";
            $params[] = (int)$filtres['mois'];
        }
        if (!empty($filtres['recherche'])) {
            $sql .= " AND (
                        t.reference_unique LIKE ?
                        OR t.partenaire LIKE ?
                        OR t.description LIKE ?
                        OR c.nom LIKE ?
                        OR a.nom LIKE ?
                     )";
            $term = '%' . $filtres['recherche'] . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
    }

    /**
     * Injecte les filtres SQL des factures.
     */
    private function appliquerFiltresFacture(string &$sql, array &$params, array $filtres): void {
        if (!empty($filtres['type_facture'])) {
            $sql .= " AND i.type_facture = ?";
            $params[] = $filtres['type_facture'];
        }
        if (!empty($filtres['statut'])) {
            $sql .= " AND i.statut = ?";
            $params[] = $filtres['statut'];
        }
        if (!empty($filtres['devise'])) {
            $sql .= " AND i.devise = ?";
            $params[] = $filtres['devise'];
        }
        if (!empty($filtres['annee'])) {
            $sql .= " AND YEAR(i.date_facture) = ?";
            $params[] = (int)$filtres['annee'];
        }
        if (!empty($filtres['mois'])) {
            $sql .= " AND MONTH(i.date_facture) = ?";
            $params[] = (int)$filtres['mois'];
        }
        if (!empty($filtres['recherche'])) {
            $sql .= " AND (
                        i.numero_facture LIKE ?
                        OR i.partenaire LIKE ?
                        OR i.objet LIKE ?
                        OR i.description LIKE ?
                     )";
            $term = '%' . $filtres['recherche'] . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
    }

    /**
     * Génère une référence unique de transaction.
     */
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

    /**
     * Génère un numéro de facture unique.
     */
    private function genererNumeroFacture(): string {
        $datePart = date('Ym');
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM invoices
             WHERE DATE_FORMAT(created_at, '%Y%m') = ?"
        );
        $stmt->execute([$datePart]);
        $compteur = (int)$stmt->fetchColumn() + 1;
        return sprintf('FAC-%s-%04d', $datePart, $compteur);
    }

    /**
     * Recalcule montant payé et statut métier d'une facture.
     */
    private function mettreAJourEtatFacture(int $invoiceId, float $montantTotal, float $totalPaye, string $statutActuel, string $dateEcheance): void {
        $nouveauStatut = $statutActuel;

        if ($statutActuel !== 'annulee') {
            if ($totalPaye <= 0.0) {
                $nouveauStatut = (strtotime($dateEcheance) < strtotime(date('Y-m-d'))) ? 'en_retard' : 'validee';
            } elseif ($totalPaye < $montantTotal) {
                $nouveauStatut = (strtotime($dateEcheance) < strtotime(date('Y-m-d'))) ? 'en_retard' : 'partiellement_payee';
            } else {
                $nouveauStatut = 'payee';
            }
        }

        $stmt = $this->pdo->prepare(
            "UPDATE invoices SET montant_paye = ?, statut = ? WHERE id = ?"
        );
        $stmt->execute([$totalPaye, $nouveauStatut, $invoiceId]);
    }

    /**
     * Convertit un montant dans une base USD simplifiée.
     */
    private function convertirVersUsd(float $montant, string $devise, ?float $taux): float {
        if ($devise === 'USD') {
            return $montant;
        }

        if ($devise === 'CDF' && $taux !== null && $taux > 0) {
            return $montant / $taux;
        }

        // Fallback de sécurité métier quand le taux n'est pas fourni.
        return $montant / 2000;
    }
}
