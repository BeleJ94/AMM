<?php
/**
 * Contrôleur FinancialTracking - Pilotage financier
 * AMM-ERP
 */
class FinancialTrackingController {

    private FinancialTracking $financeModel;

    public function __construct() {
        $this->financeModel = new FinancialTracking();
    }

    /**
     * Dashboard financier opérationnel.
     */
    public function overview(): void {
        $this->verifierAccesFinance();

        $annee = max(2020, (int)($_GET['annee'] ?? date('Y')));
        $mois  = min(12, max(1, (int)($_GET['mois'] ?? date('n'))));

        $stats = $this->financeModel->statistiquesMois($annee, $mois);
        $statsFactures = $this->financeModel->statistiquesFactures();
        $transactionsRecentes = $this->financeModel->transactionsRecentes(10);
        $budgetsMois = $this->financeModel->listerBudgetsMois($annee, $mois);

        require_once __DIR__ . '/../views/finance/overview.php';
    }

    /**
     * Liste des transactions financières.
     */
    public function transactions(): void {
        $this->verifierAccesFinance();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $filtres = [
            'type_flux' => $_GET['type_flux'] ?? '',
            'statut'    => $_GET['statut'] ?? '',
            'devise'    => $_GET['devise'] ?? '',
            'annee'     => (int)($_GET['annee'] ?? date('Y')),
            'mois'      => (int)($_GET['mois'] ?? date('n')),
            'recherche' => trim($_GET['recherche'] ?? ''),
            'transaction_id' => max(0, (int)($_GET['transaction_id'] ?? 0)),
        ];

        $total = $this->financeModel->compterTransactions($filtres);
        $pagination = paginer($total, 25, $page);
        $transactions = $this->financeModel->listerTransactions($filtres, 25, $pagination['offset']);
        $peutAnnulerTransaction = $this->peutAnnulerTransaction();

        require_once __DIR__ . '/../views/finance/transactions.php';
    }

    /**
     * Ajout d'un flux financier (revenu ou dépense).
     */
    public function ajouter(): void {
        $this->verifierAccesFinance();

        $categories = $this->financeModel->listerCategories();
        $comptes    = $this->financeModel->listerComptes();
        $erreurs    = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreurs[] = 'Requête invalide.';
            } else {
                $pieceJointe = null;
                if (!empty($_FILES['piece_jointe']['name'])) {
                    $pieceJointe = traiterUpload($_FILES['piece_jointe'], 'finance');
                    if ($pieceJointe === false) {
                        $erreurs[] = 'Pièce jointe invalide (format/taille).';
                    }
                }

                $donnees = [
                    'date_operation' => $_POST['date_operation'] ?? date('Y-m-d'),
                    'type_flux'      => $_POST['type_flux'] ?? '',
                    'categorie_id'   => (int)($_POST['categorie_id'] ?? 0),
                    'compte_id'      => (int)($_POST['compte_id'] ?? 0),
                    'montant'        => (float)($_POST['montant'] ?? 0),
                    'devise'         => $_POST['devise'] ?? 'USD',
                    'taux_change'    => (float)($_POST['taux_change'] ?? 0),
                    'partenaire'     => trim($_POST['partenaire'] ?? ''),
                    'description'    => trim($_POST['description'] ?? ''),
                    'piece_jointe'   => $pieceJointe,
                    'statut'         => 'brouillon',
                    'created_by'     => (int)($_SESSION['user_id'] ?? 0),
                ];

                if (!in_array($donnees['type_flux'], ['revenu', 'depense'], true)) {
                    $erreurs[] = 'Type de flux invalide.';
                }
                if ($donnees['categorie_id'] <= 0) {
                    $erreurs[] = 'La catégorie est obligatoire.';
                }
                if ($donnees['compte_id'] <= 0) {
                    $erreurs[] = 'Le compte est obligatoire.';
                }
                if ($donnees['montant'] <= 0) {
                    $erreurs[] = 'Le montant doit être supérieur à 0.';
                }
                if ($donnees['devise'] === 'CDF' && $donnees['taux_change'] <= 0) {
                    $erreurs[] = 'Le taux de change est obligatoire pour les montants en CDF.';
                }

                if (empty($erreurs)) {
                    $id = $this->financeModel->creerTransaction($donnees);
                    logAudit('Création transaction financière #' . $id, 'financial_transactions', $id);
                    flashMessage('succes', 'Transaction créée en brouillon avec succès.');
                    redirect('/finance/transactions');
                }
            }
        }

        require_once __DIR__ . '/../views/finance/ajouter.php';
    }

    /**
     * Validation d'une transaction brouillon.
     */
    public function valider(?string $id): void {
        $this->verifierAccesFinance();

        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/finance/transactions');
        }

        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requête invalide.');
            redirect('/finance/transactions');
        }

        $id = (int)$id;
        $ok = $this->financeModel->validerTransaction($id, (int)($_SESSION['user_id'] ?? 0));
        if ($ok) {
            logAudit('Validation transaction financière #' . $id, 'financial_transactions', $id);
            flashMessage('succes', 'Transaction validée.');
        } else {
            flashMessage('erreur', 'Impossible de valider cette transaction.');
        }

        redirect('/finance/transactions');
    }

    /**
     * Annulation d'une transaction.
     */
    public function annuler(?string $id): void {
        $this->verifierAccesFinance();

        if (!$this->peutAnnulerTransaction()) {
            flashMessage('erreur', 'L’annulation d’une transaction est réservée à la Direction financière, à la Direction générale et à l’administrateur.');
            redirect('/finance/transactions');
        }

        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/finance/transactions');
        }

        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requête invalide.');
            redirect('/finance/transactions');
        }

        $id = (int)$id;
        $motif = trim($_POST['motif'] ?? '');

        if ($motif === '') {
            flashMessage('erreur', 'Le motif d\'annulation est obligatoire.');
            redirect('/finance/transactions');
        }

        try {
            $ok = $this->financeModel->annulerTransaction($id, (int)($_SESSION['user_id'] ?? 0), $motif);
        } catch (DomainException $e) {
            flashMessage('erreur', $e->getMessage());
            redirect('/finance/transactions');
        }
        if ($ok) {
            logAudit('Annulation transaction financière #' . $id, 'financial_transactions', $id, $motif);
            flashMessage('succes', 'Transaction annulée. Les opérations liées et les soldes ont été mis à jour ; l’historique est conservé.');
        } else {
            flashMessage('erreur', 'Impossible d\'annuler cette transaction.');
        }

        redirect('/finance/transactions');
    }

    /**
     * Budgets mensuels par catégorie.
     */
    public function budgets(): void {
        $this->verifierAccesFinance();

        $annee = max(2020, (int)($_GET['annee'] ?? $_POST['annee'] ?? date('Y')));
        $mois  = min(12, max(1, (int)($_GET['mois'] ?? $_POST['mois'] ?? date('n'))));
        $categoriesDepense = $this->financeModel->listerCategories('depense');
        $erreurs = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreurs[] = 'Requête invalide.';
            } else {
                $categorieId = (int)($_POST['categorie_id'] ?? 0);
                $montantPrevu = (float)($_POST['montant_prevu'] ?? 0);
                $devise = $_POST['devise'] ?? 'USD';

                if ($categorieId <= 0) {
                    $erreurs[] = 'La catégorie est obligatoire.';
                }
                if ($montantPrevu <= 0) {
                    $erreurs[] = 'Le montant budget doit être > 0.';
                }
                if (!in_array($devise, ['USD', 'CDF'], true)) {
                    $erreurs[] = 'Devise invalide.';
                }

                if (empty($erreurs)) {
                    $this->financeModel->upsertBudget([
                        'annee' => $annee,
                        'mois' => $mois,
                        'categorie_id' => $categorieId,
                        'montant_prevu' => $montantPrevu,
                        'devise' => $devise,
                        'created_by' => (int)($_SESSION['user_id'] ?? 0),
                    ]);
                    logAudit('Mise à jour budget ' . $annee . '-' . sprintf('%02d', $mois), 'budgets');
                    flashMessage('succes', 'Budget enregistré.');
                    redirect('/finance/budgets?annee=' . $annee . '&mois=' . $mois);
                }
            }
        }

        $budgetsMois = $this->financeModel->listerBudgetsMois($annee, $mois);
        $stats = $this->financeModel->statistiquesMois($annee, $mois);

        require_once __DIR__ . '/../views/finance/budgets.php';
    }

    /**
     * Liste des factures clients/fournisseurs.
     */
    public function factures(): void {
        $this->verifierAccesFinance();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $filtres = [
            'type_facture' => $_GET['type_facture'] ?? '',
            'statut'       => $_GET['statut'] ?? '',
            'devise'       => $_GET['devise'] ?? '',
            'annee'        => (int)($_GET['annee'] ?? date('Y')),
            'mois'         => (int)($_GET['mois'] ?? date('n')),
            'recherche'    => trim($_GET['recherche'] ?? ''),
        ];

        $total = $this->financeModel->compterFactures($filtres);
        $pagination = paginer($total, 25, $page);
        $factures = $this->financeModel->listerFactures($filtres, 25, $pagination['offset']);

        require_once __DIR__ . '/../views/finance/factures.php';
    }

    /**
     * Création d'une facture.
     */
    public function ajouterFacture(): void {
        $this->verifierAccesFinance();
        $erreurs = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreurs[] = 'Requête invalide.';
            } else {
                $pieceJointe = null;
                if (!empty($_FILES['piece_jointe']['name'])) {
                    $pieceJointe = traiterUpload($_FILES['piece_jointe'], 'finance');
                    if ($pieceJointe === false) {
                        $erreurs[] = 'Pièce jointe invalide (format/taille).';
                    }
                }

                $donnees = [
                    'type_facture'  => $_POST['type_facture'] ?? '',
                    'partenaire'    => trim($_POST['partenaire'] ?? ''),
                    'objet'         => trim($_POST['objet'] ?? ''),
                    'date_facture'  => $_POST['date_facture'] ?? date('Y-m-d'),
                    'date_echeance' => $_POST['date_echeance'] ?? date('Y-m-d'),
                    'montant_total' => (float)($_POST['montant_total'] ?? 0),
                    'devise'        => $_POST['devise'] ?? 'USD',
                    'description'   => trim($_POST['description'] ?? ''),
                    'piece_jointe'  => $pieceJointe,
                    'created_by'    => (int)($_SESSION['user_id'] ?? 0),
                ];

                if (!in_array($donnees['type_facture'], ['client', 'fournisseur'], true)) {
                    $erreurs[] = 'Type de facture invalide.';
                }
                if ($donnees['partenaire'] === '') {
                    $erreurs[] = 'Le partenaire est obligatoire.';
                }
                if ($donnees['objet'] === '') {
                    $erreurs[] = 'L\'objet est obligatoire.';
                }
                if ($donnees['montant_total'] <= 0) {
                    $erreurs[] = 'Le montant total doit être supérieur à 0.';
                }
                if (!in_array($donnees['devise'], ['USD', 'CDF'], true)) {
                    $erreurs[] = 'Devise invalide.';
                }
                if ($donnees['date_echeance'] < $donnees['date_facture']) {
                    $erreurs[] = 'La date échéance doit être postérieure ou égale à la date facture.';
                }

                if (empty($erreurs)) {
                    $id = $this->financeModel->creerFacture($donnees);
                    logAudit('Création facture #' . $id, 'invoices', $id);
                    flashMessage('succes', 'Facture créée en brouillon.');
                    redirect('/finance/facture-voir/' . $id);
                }
            }
        }

        require_once __DIR__ . '/../views/finance/facture_ajouter.php';
    }

    /**
     * Détail d'une facture.
     */
    public function voirFacture(?string $id): void {
        $this->verifierAccesFinance();
        if (empty($id)) {
            redirect('/finance/factures');
        }

        $facture = $this->financeModel->trouverFactureParId((int)$id);
        if (!$facture) {
            flashMessage('erreur', 'Facture introuvable.');
            redirect('/finance/factures');
        }

        $paiements = $this->financeModel->listerPaiementsFacture((int)$id);
        $relances = $this->financeModel->listerRelancesFacture((int)$id);
        $totalPaye = $this->financeModel->totalPayeFacture((int)$id);
        $reste = (float)$facture['montant_total'] - $totalPaye;

        require_once __DIR__ . '/../views/finance/facture_voir.php';
    }

    /**
     * Validation d'une facture brouillon.
     */
    public function validerFacture(?string $id): void {
        $this->verifierAccesFinance();
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/finance/factures');
        }
        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requête invalide.');
            redirect('/finance/factures');
        }

        $ok = $this->financeModel->validerFacture((int)$id, (int)($_SESSION['user_id'] ?? 0));
        if ($ok) {
            logAudit('Validation facture #' . (int)$id, 'invoices', (int)$id);
            flashMessage('succes', 'Facture validée.');
        } else {
            flashMessage('erreur', 'Impossible de valider cette facture.');
        }
        redirect('/finance/facture-voir/' . (int)$id);
    }

    /**
     * Ajoute un paiement à une facture.
     */
    public function ajouterPaiementFacture(?string $id): void {
        $this->verifierAccesFinance();
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/finance/factures');
        }
        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requête invalide.');
            redirect('/finance/facture-voir/' . (int)$id);
        }

        $pieceJointe = null;
        if (!empty($_FILES['piece_jointe']['name'])) {
            $pieceJointe = traiterUpload($_FILES['piece_jointe'], 'finance');
            if ($pieceJointe === false) {
                flashMessage('erreur', 'Pièce jointe paiement invalide.');
                redirect('/finance/facture-voir/' . (int)$id);
            }
        }

        $donnees = [
            'date_paiement'      => $_POST['date_paiement'] ?? date('Y-m-d'),
            'montant'            => (float)($_POST['montant'] ?? 0),
            'devise'             => $_POST['devise'] ?? 'USD',
            'reference_paiement' => trim($_POST['reference_paiement'] ?? ''),
            'commentaire'        => trim($_POST['commentaire'] ?? ''),
            'piece_jointe'       => $pieceJointe,
            'created_by'         => (int)($_SESSION['user_id'] ?? 0),
        ];

        if ($donnees['montant'] <= 0) {
            flashMessage('erreur', 'Montant paiement invalide.');
            redirect('/finance/facture-voir/' . (int)$id);
        }

        $paymentId = $this->financeModel->ajouterPaiementFacture((int)$id, $donnees);
        if ($paymentId > 0) {
            logAudit('Paiement facture #' . (int)$id . ' (paiement #' . $paymentId . ')', 'invoice_payments', $paymentId);
            flashMessage('succes', 'Paiement enregistré.');
        } else {
            flashMessage('erreur', 'Impossible d\'enregistrer le paiement.');
        }

        redirect('/finance/facture-voir/' . (int)$id);
    }

    /**
     * Enregistre une relance sur facture.
     */
    public function relancerFacture(?string $id): void {
        $this->verifierAccesFinance();
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/finance/factures');
        }
        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requête invalide.');
            redirect('/finance/facture-voir/' . (int)$id);
        }

        $donnees = [
            'reminder_level' => $_POST['reminder_level'] ?? 'R1',
            'reminder_channel' => $_POST['reminder_channel'] ?? 'email',
            'reminder_note' => trim($_POST['reminder_note'] ?? ''),
            'reminded_by' => (int)($_SESSION['user_id'] ?? 0),
        ];

        if (!in_array($donnees['reminder_level'], ['R1', 'R2', 'R3'], true)) {
            flashMessage('erreur', 'Niveau de relance invalide.');
            redirect('/finance/facture-voir/' . (int)$id);
        }

        $relanceId = $this->financeModel->ajouterRelanceFacture((int)$id, $donnees);
        logAudit('Relance facture #' . (int)$id . ' (relance #' . $relanceId . ')', 'invoice_reminders', $relanceId);
        flashMessage('succes', 'Relance enregistrée.');
        redirect('/finance/facture-voir/' . (int)$id);
    }

    /**
     * Contrôle d'accès métier pour le module Finance.
     */
    private function verifierAccesFinance(): void {
        $role = $_SESSION['user_role'] ?? '';
        $service = strtolower((string)($_SESSION['user_service'] ?? ''));

        $autorise = in_array($role, ['admin', 'direction', 'chef_service', 'directeur_financier', 'directeur_general'], true)
            || strpos($service, 'finance') !== false;

        if (!$autorise) {
            flashMessage('erreur', 'Accès refusé au module Financial Tracking.');
            redirect('/dashboard');
        }
    }

    private function peutAnnulerTransaction(): bool {
        $role = $_SESSION['user_role'] ?? '';
        $service = strtolower((string)($_SESSION['user_service'] ?? ''));
        $service = strtr($service, ['è' => 'e', 'é' => 'e', 'ê' => 'e', 'à' => 'a']);
        return in_array($role, ['admin', 'directeur_financier', 'directeur_general'], true)
            || ($role === 'direction' && (strpos($service, 'financ') !== false || strpos($service, 'general') !== false));
    }
}
