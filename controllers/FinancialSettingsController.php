<?php
/** Administration des référentiels financiers et paramètres de trésorerie. */
class FinancialSettingsController {

    private FinancialTracking $financeModel;
    private Treasury $treasuryModel;

    public function __construct() {
        $this->financeModel = new FinancialTracking();
        $this->treasuryModel = new Treasury();
    }

    public function index(): void {
        $comptes = $this->financeModel->listerTousComptes();
        $categories = $this->financeModel->listerToutesCategories();
        $parametres = [
            'cash_ceiling_usd' => $this->treasuryModel->getSetting('cash_ceiling_usd', '9900'),
            'direction_approval_threshold_usd' => $this->treasuryModel->getSetting('direction_approval_threshold_usd', '1000'),
            'default_exchange_rate_cdf_usd' => $this->treasuryModel->getSetting('default_exchange_rate_cdf_usd', '2000'),
        ];
        require __DIR__ . '/../views/admin_finance/index.php';
    }

    public function enregistrerCompte(): void {
        $this->exigerPost('/admin-finance');
        $id = (int)($_POST['id'] ?? 0);
        $donnees = [
            'nom' => trim($_POST['nom'] ?? ''),
            'type_compte' => $_POST['type_compte'] ?? '',
            'devise' => $_POST['devise'] ?? '',
            'reference_compte' => trim($_POST['reference_compte'] ?? ''),
        ];
        $erreurs = [];
        if ($donnees['nom'] === '') $erreurs[] = 'Le nom du compte est obligatoire.';
        if (!in_array($donnees['type_compte'], ['banque','caisse','mobile_money','autre'], true)) $erreurs[] = 'Type de compte invalide.';
        if (!in_array($donnees['devise'], ['USD','CDF'], true)) $erreurs[] = 'Devise invalide.';

        if ($erreurs) {
            flashMessage('erreur', implode(' ', $erreurs));
            redirect('/admin-finance');
        }

        try {
            $recordId = $this->financeModel->enregistrerCompte($donnees, $id > 0 ? $id : null);
            logAudit($id > 0 ? 'Modification compte financier' : 'Creation compte financier', 'financial_accounts', $recordId);
            flashMessage('succes', $id > 0 ? 'Compte financier modifié.' : 'Compte financier créé.');
        } catch (DomainException $e) {
            flashMessage('erreur', $e->getMessage());
        } catch (PDOException $e) {
            flashMessage('erreur', $e->getCode() === '23000' ? 'Un compte porte déjà ce nom.' : 'Impossible d’enregistrer le compte.');
        }
        redirect('/admin-finance');
    }

    public function toggleCompte(?string $id): void {
        $this->exigerPost('/admin-finance');
        $recordId = (int)$id;
        $ok = $recordId > 0 && $this->financeModel->toggleCompteActif($recordId);
        if ($ok) logAudit('Activation/desactivation compte financier', 'financial_accounts', $recordId);
        flashMessage($ok ? 'succes' : 'erreur', $ok ? 'Statut du compte mis à jour.' : 'Compte financier introuvable.');
        redirect('/admin-finance');
    }

    public function enregistrerCategorie(): void {
        $this->exigerPost('/admin-finance');
        $id = (int)($_POST['id'] ?? 0);
        $donnees = [
            'nom' => trim($_POST['nom'] ?? ''),
            'type_flux' => $_POST['type_flux'] ?? '',
        ];
        if ($donnees['nom'] === '' || !in_array($donnees['type_flux'], ['revenu','depense'], true)) {
            flashMessage('erreur', 'Le nom et un type de flux valide sont obligatoires.');
            redirect('/admin-finance');
        }

        try {
            $recordId = $this->financeModel->enregistrerCategorie($donnees, $id > 0 ? $id : null);
            logAudit($id > 0 ? 'Modification categorie financiere' : 'Creation categorie financiere', 'financial_categories', $recordId);
            flashMessage('succes', $id > 0 ? 'Catégorie modifiée.' : 'Catégorie créée.');
        } catch (DomainException $e) {
            flashMessage('erreur', $e->getMessage());
        } catch (PDOException $e) {
            flashMessage('erreur', $e->getCode() === '23000' ? 'Cette catégorie existe déjà pour ce type de flux.' : 'Impossible d’enregistrer la catégorie.');
        }
        redirect('/admin-finance');
    }

    public function toggleCategorie(?string $id): void {
        $this->exigerPost('/admin-finance');
        $recordId = (int)$id;
        $ok = $recordId > 0 && $this->financeModel->toggleCategorieActive($recordId);
        if ($ok) logAudit('Activation/desactivation categorie financiere', 'financial_categories', $recordId);
        flashMessage($ok ? 'succes' : 'erreur', $ok ? 'Statut de la catégorie mis à jour.' : 'Catégorie introuvable.');
        redirect('/admin-finance');
    }

    public function enregistrerParametres(): void {
        $this->exigerPost('/admin-finance');
        $valeurs = [
            'cash_ceiling_usd' => (float)($_POST['cash_ceiling_usd'] ?? 0),
            'direction_approval_threshold_usd' => (float)($_POST['direction_approval_threshold_usd'] ?? 0),
            'default_exchange_rate_cdf_usd' => (float)($_POST['default_exchange_rate_cdf_usd'] ?? 0),
        ];
        foreach ($valeurs as $valeur) {
            if ($valeur <= 0) {
                flashMessage('erreur', 'Tous les paramètres doivent être supérieurs à zéro.');
                redirect('/admin-finance');
            }
        }
        $descriptions = [
            'cash_ceiling_usd' => 'Plafond autorisé de la caisse en USD',
            'direction_approval_threshold_usd' => 'Seuil de double validation Direction financière et Direction générale en USD',
            'default_exchange_rate_cdf_usd' => 'Taux CDF par USD utilisé par défaut',
        ];
        $userId = (int)($_SESSION['user_id'] ?? 0);
        foreach ($valeurs as $cle => $valeur) {
            $this->treasuryModel->enregistrerSetting($cle, (string)$valeur, $descriptions[$cle], $userId);
        }
        logAudit('Modification parametres de tresorerie', 'treasury_settings');
        flashMessage('succes', 'Paramètres de trésorerie enregistrés.');
        redirect('/admin-finance');
    }

    private function exigerPost(string $retour): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect($retour);
        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requête invalide.');
            redirect($retour);
        }
    }
}
