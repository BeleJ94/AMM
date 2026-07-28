<?php
/**
 * Contrôleur Dashboard
 * AMM-ERP
 */
class DashboardController {

    private Courrier  $courrierModel;
    private Contrat   $contratModel;
    private Document  $documentModel;

    public function __construct() {
        $this->courrierModel = new Courrier();
        $this->contratModel  = new Contrat();
        $this->documentModel = new Document();
    }

    /**
     * Dashboard principal - redirige selon le rôle.
     */
    public function index(): void {
        $role = $_SESSION['user_role'] ?? 'agent';
        $service = strtolower((string)($_SESSION['user_service'] ?? ''));
        $service = strtr($service, [
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a',
            'ù'=>'u','û'=>'u','î'=>'i','ï'=>'i','ô'=>'o','ç'=>'c',
        ]);
        $serviceTresorerie = strpos($service, 'finance') !== false
            || strpos($service, 'tresorerie') !== false
            || strpos($service, 'comptable') !== false
            || strpos($service, 'comptabilite') !== false;
        if ($serviceTresorerie && in_array($role, ['direction','chef_service'], true)) {
            redirect('/treasury/dashboard');
        }
        switch ($role) {
            case 'reception': $this->reception(); break;
            case 'direction': $this->direction(); break;
            case 'directeur_general': redirect('/treasury/dashboard'); break;
            case 'directeur_financier': redirect('/treasury/dashboard'); break;
            case 'comptable_senior': redirect('/treasury/dashboard'); break;
            case 'tresorier': redirect('/treasury/dashboard'); break;
            case 'auditeur_interne': redirect('/treasury/dashboard'); break;
            case 'chef_service': $this->direction(); break;
            case 'admin':     redirect('/treasury/dashboard'); break;
            default:          $this->reception(); break;
        }
    }

    /**
     * Dashboard Réception.
     */
    public function reception(): void {
        $statsCourrier  = $this->courrierModel->statistiques();
        $courriersDuJour= $this->courrierModel->lister(['date_debut' => date('Y-m-d'), 'date_fin' => date('Y-m-d')], 10);
        $nonAssignes    = $this->courrierModel->lister(['statut' => 'Recu'], 10);
        $urgents        = $this->courrierModel->lister(['urgence' => 'haute'], 5);
        $courriersRecents = $this->courrierModel->lister([], 80);
        $nonAssignesFull  = $this->courrierModel->lister(['statut' => 'Recu'], 80);
        $urgentsFull      = $this->courrierModel->lister(['urgence' => 'haute'], 80);

        require_once __DIR__ . '/../views/dashboard/reception.php';
    }

    /**
     * Dashboard Finance.
     */
    public function finance(): void {
        $statsContrat   = $this->contratModel->statistiques();
        $contratsActifs = $this->contratModel->lister(['statut' => 'actif'], 10);
        $contratsActifsFull = $this->contratModel->lister(['statut' => 'actif'], 120);
        $expirant30j    = $this->contratModel->expirantDans(30);
        $expirant7j     = $this->contratModel->expirantDans(7);

        require_once __DIR__ . '/../views/dashboard/finance.php';
    }

    /**
     * Dashboard Juridique.
     */
    public function legal(): void {
        $contratsExpirant = $this->contratModel->expirantDans(30);
        $courriersJuridiques = $this->courrierModel->lister(['service' => 'Juridique'], 10);
        $courriersJuridiquesFull = $this->courrierModel->lister(['service' => 'Juridique'], 120);
        $statsDocument    = $this->documentModel->statistiques();
        $documentsExpires = $this->documentModel->expiresList(120);

        require_once __DIR__ . '/../views/dashboard/legal.php';
    }

    /**
     * Dashboard Direction.
     */
    public function direction(): void {
        $statsCourrier  = $this->courrierModel->statistiques();
        $statsContrat   = $this->contratModel->statistiques();
        $statsDocument  = $this->documentModel->statistiques();
        $courriersRecents = $this->courrierModel->lister([], 120);
        $overdue        = $this->courrierModel->lister(['statut' => 'Overdue'], 10);
        $overdueFull    = $this->courrierModel->lister(['statut' => 'Overdue'], 120);
        $urgents        = $this->courrierModel->lister(['urgence' => 'haute'], 10);
        $urgentsFull    = $this->courrierModel->lister(['urgence' => 'haute'], 120);
        $contratsActifs = $this->contratModel->lister(['statut' => 'actif'], 120);
        $expirant30j    = $this->contratModel->expirantDans(30);

        require_once __DIR__ . '/../views/dashboard/direction.php';
    }
}
