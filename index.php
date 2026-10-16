<?php
/**
 * AMM-ERP - Routeur Principal
 * Point d'entrée unique de l'application
 */

// Démarrer la session
session_start();

// Fuseau horaire
date_default_timezone_set('Africa/Lubumbashi');

// Charger la configuration de la base de données
require_once __DIR__ . '/config/database.php';

// Dépendances Composer (moteur PDF Dompdf, etc.)
if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Charger les fonctions utilitaires
require_once __DIR__ . '/includes/functions.php';

// Charger tous les modèles
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Department.php';
require_once __DIR__ . '/models/Courrier.php';
require_once __DIR__ . '/models/Document.php';
require_once __DIR__ . '/models/Contrat.php';
require_once __DIR__ . '/models/Notification.php';
require_once __DIR__ . '/models/FinancialTracking.php';
require_once __DIR__ . '/models/Projet.php';
require_once __DIR__ . '/models/Treasury.php';

// Charger tous les contrôleurs
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/DepartmentController.php';
require_once __DIR__ . '/controllers/CourrierController.php';
require_once __DIR__ . '/controllers/DocumentController.php';
require_once __DIR__ . '/controllers/ContratController.php';
require_once __DIR__ . '/controllers/FinancialTrackingController.php';
require_once __DIR__ . '/controllers/ProjetController.php';
require_once __DIR__ . '/controllers/TreasuryController.php';
require_once __DIR__ . '/controllers/FinancialSettingsController.php';

// ---------------------------------------------------------------
// Récupérer l'URL demandée
// ---------------------------------------------------------------
$url = isset($_GET['url']) ? trim($_GET['url'], '/') : '';
$segments = explode('/', $url);

$controleur = strtolower($segments[0] ?? '');
$action     = strtolower($segments[1] ?? '');
$param      = $segments[2] ?? null;

// ---------------------------------------------------------------
// Routage principal
// ---------------------------------------------------------------
switch ($controleur) {

    // --- Authentification ---
    case '':
    case 'auth':
        $ctrl = new AuthController();
        if ($action === 'logout') {
            $ctrl->logout();
        } else {
            $ctrl->login();
        }
        break;

    // --- Dashboard ---
    case 'dashboard':
        verifierSession();
        $ctrl = new DashboardController();
        switch ($action) {
            case 'reception': $ctrl->reception(); break;
            case 'finance':   $ctrl->finance();   break;
            case 'legal':     $ctrl->legal();     break;
            case 'direction': $ctrl->direction(); break;
            default:          $ctrl->index();     break;
        }
        break;

    // --- Courriers ---
    case 'courrier':
        verifierSession();
        $ctrl = new CourrierController();
        switch ($action) {
            case 'liste':         $ctrl->liste();                                     break;
            case 'mes-courriers': $ctrl->mesCourriers();                            break;
            case 'ajouter':       $ctrl->ajouter();                                 break;
            case 'voir':     $ctrl->voir($param ?? $_GET['id'] ?? null);    break;
            case 'assigner': $ctrl->assigner($param ?? $_GET['id'] ?? null); break;
            case 'accuser':  $ctrl->accuser($param ?? $_GET['id'] ?? null);  break;
            case 'preuve':   $ctrl->ajouterPreuve($param ?? $_GET['id'] ?? null); break;
            case 'statut':   $ctrl->changerStatut($param ?? $_GET['id'] ?? null); break;
            case 'cloturer': $ctrl->cloturer($param ?? $_GET['id'] ?? null); break;
            case 'escalader':$ctrl->escalader($param ?? $_GET['id'] ?? null); break;
            case 'modifier': $ctrl->modifier($param ?? $_GET['id'] ?? null); break;
            default:         $ctrl->liste();                    break;
        }
        break;

    // --- Documents ---
    case 'documents':
        verifierSession();
        $ctrl = new DocumentController();
        switch ($action) {
            case 'liste':     $ctrl->liste();                              break;
            case 'upload':    $ctrl->upload();                             break;
            case 'voir':      $ctrl->voir($param ?? $_GET['id'] ?? null);         break;
            case 'version':   $ctrl->nouvelleVersion($param ?? $_GET['id'] ?? null); break;
            case 'telecharger': $ctrl->telecharger($param ?? $_GET['id'] ?? null); break;
            default:          $ctrl->liste();                              break;
        }
        break;

    // --- Contrats ---
    case 'contrats':
        verifierSession();
        $ctrl = new ContratController();
        switch ($action) {
            case 'liste':    $ctrl->liste();                               break;
            case 'ajouter':  $ctrl->ajouter();                             break;
            case 'voir':     $ctrl->voir($param ?? $_GET['id'] ?? null);          break;
            case 'modifier': $ctrl->modifier($param ?? $_GET['id'] ?? null);      break;
            case 'paiement': $ctrl->ajouterPaiement($param ?? $_GET['id'] ?? null); break;
            case 'alerte':   $ctrl->alertes();                             break;
            default:         $ctrl->liste();                               break;
        }
        break;

    // --- Utilisateurs ---
    case 'users':
        verifierSession();
        verifierRole(['admin']);
        $ctrl = new UserController();
        switch ($action) {
            case 'liste':    $ctrl->liste();                               break;
            case 'ajouter':  $ctrl->ajouter();                             break;
            case 'modifier': $ctrl->modifier($param ?? $_GET['id'] ?? null);      break;
            case 'toggle':   $ctrl->toggleActif($param ?? $_GET['id'] ?? null);   break;
            default:         $ctrl->liste();                               break;
        }
        break;

    // --- Administration des référentiels financiers ---
    case 'admin-departements':
        verifierSession();
        verifierRole(['admin']);
        (new DepartmentController())->index();
        break;

    case 'admin-finance':
        verifierSession();
        verifierRole(['admin']);
        $ctrl = new FinancialSettingsController();
        switch ($action) {
            case 'compte-enregistrer':   $ctrl->enregistrerCompte(); break;
            case 'compte-toggle':        $ctrl->toggleCompte($param ?? $_GET['id'] ?? null); break;
            case 'categorie-enregistrer':$ctrl->enregistrerCategorie(); break;
            case 'categorie-toggle':     $ctrl->toggleCategorie($param ?? $_GET['id'] ?? null); break;
            case 'parametres-enregistrer':$ctrl->enregistrerParametres(); break;
            default:                     $ctrl->index(); break;
        }
        break;

    // --- Financial Tracking ---
    case 'finance':
        verifierSession();
        $ctrl = new FinancialTrackingController();
        switch ($action) {
            case 'overview':     $ctrl->overview(); break;
            case 'transactions': $ctrl->transactions(); break;
            case 'ajouter':      $ctrl->ajouter(); break;
            case 'valider':      $ctrl->valider($param ?? $_GET['id'] ?? null); break;
            case 'annuler':      $ctrl->annuler($param ?? $_GET['id'] ?? null); break;
            case 'budgets':      $ctrl->budgets(); break;
            case 'factures':     $ctrl->factures(); break;
            case 'facture-ajouter': $ctrl->ajouterFacture(); break;
            case 'facture-voir':    $ctrl->voirFacture($param ?? $_GET['id'] ?? null); break;
            case 'facture-valider': $ctrl->validerFacture($param ?? $_GET['id'] ?? null); break;
            case 'facture-paiement': $ctrl->ajouterPaiementFacture($param ?? $_GET['id'] ?? null); break;
            case 'facture-relance': $ctrl->relancerFacture($param ?? $_GET['id'] ?? null); break;
            default:             $ctrl->overview(); break;
        }
        break;

    // --- Tresorerie ---
    case 'treasury':
        verifierSession();
        $ctrl = new TreasuryController();
        switch ($action) {
            case '':
            case 'dashboard':              $ctrl->dashboard(); break;
            case 'rapports':               $ctrl->rapports(); break;
            case 'encaissements':          $ctrl->encaissements(); break;
            case 'encaissement-ajouter':   $ctrl->ajouterEncaissement(); break;
            case 'encaissement-voir':      $ctrl->voirEncaissement($param ?? $_GET['id'] ?? null); break;
            case 'encaissement-pdf':       $ctrl->pdfEncaissement($param ?? $_GET['id'] ?? null); break;
            case 'encaissement-annuler':   $ctrl->annulerEncaissement($param ?? $_GET['id'] ?? null); break;
            case 'demandes':               $ctrl->demandes(); break;
            case 'demandes-kpi-pdf':       $ctrl->pdfDemandesKpi(); break;
            case 'demandes-kpi-excel':     $ctrl->excelDemandesKpi(); break;
            case 'demande-ajouter':        $ctrl->ajouterDemande(); break;
            case 'demande-voir':           $ctrl->voirDemande($param ?? $_GET['id'] ?? null); break;
            case 'demande-pdf':            $ctrl->pdfDemande($param ?? $_GET['id'] ?? null); break;
            case 'demande-valider':        $ctrl->validerDemande($param ?? $_GET['id'] ?? null); break;
            case 'demande-annuler':        $ctrl->annulerDemande($param ?? $_GET['id'] ?? null); break;
            case 'ordres':                 $ctrl->ordres(); break;
            case 'ordres-kpi-pdf':         $ctrl->pdfOrdresKpi(); break;
            case 'ordres-kpi-excel':       $ctrl->excelOrdresKpi(); break;
            case 'ordre-paiement':         $ctrl->ordrePaiement($param ?? $_GET['id'] ?? null); break;
            case 'ordre-annuler':          $ctrl->annulerOrdrePaiement($param ?? $_GET['id'] ?? null); break;
            case 'paiement-executer':      $ctrl->executerPaiement($param ?? $_GET['id'] ?? null); break;
            case 'paiement-bordereau':     $ctrl->pdfBordereauPaiement($param ?? $_GET['id'] ?? null); break;
            case 'comptes':                $ctrl->comptesTresorerie(); break;
            case 'compte':                 $ctrl->voirCompteTresorerie($param ?? $_GET['id'] ?? null); break;
            case 'caisse':                 $ctrl->caisse(); break;
            case 'caisse-pdf':             $ctrl->pdfLivreCaisse(); break;
            case 'caisse-excel':           $ctrl->excelLivreCaisse(); break;
            case 'caisse-cloturer':        $ctrl->cloturerCaisse(); break;
            case 'banque':                 $ctrl->banque(); break;
            case 'releve-ajouter':         $ctrl->ajouterReleve(); break;
            case 'rapprochement':          $ctrl->rapprochement($param ?? $_GET['id'] ?? null); break;
            default:                       $ctrl->dashboard(); break;
        }
        break;

    // --- Projets ---
    case 'projets':
        verifierSession();
        $ctrl = new ProjetController();
        switch ($action) {
            case 'liste':         $ctrl->liste(); break;
            case 'ajouter':       $ctrl->ajouter(); break;
            case 'voir':          $ctrl->voir($param ?? $_GET['id'] ?? null); break;
            case 'gantt':         $ctrl->gantt($param ?? $_GET['id'] ?? null); break;
            case 'modifier':      $ctrl->modifier($param ?? $_GET['id'] ?? null); break;
            case 'calendrier':    $ctrl->calendrier(); break;
            case 'phase':         $ctrl->ajouterPhase($param ?? $_GET['id'] ?? null); break;
            case 'jalon':         $ctrl->ajouterJalon($param ?? $_GET['id'] ?? null); break;
            case 'jalon-statut':  $ctrl->changerStatutJalon($param ?? $_GET['id'] ?? null); break;
            case 'jalon-taches':  $ctrl->lierTachesJalon($param ?? $_GET['id'] ?? null); break;
            case 'tache':         $ctrl->ajouterTache($param ?? $_GET['id'] ?? null); break;
            case 'tache-statut':  $ctrl->changerStatutTache($param ?? $_GET['id'] ?? null); break;
            case 'gantt-update':  $ctrl->mettreAJourPlanningGantt($param ?? $_GET['id'] ?? null); break;
            case 'lien':          $ctrl->ajouterLien($param ?? $_GET['id'] ?? null); break;
            default:              $ctrl->liste(); break;
        }
        break;

    // --- Page 404 ---
    default:
        http_response_code(404);
        require_once __DIR__ . '/views/layouts/header.php';
        echo '<div class="container mt-5 text-center">
                <h1 class="display-4">404</h1>
                <p class="lead">Page introuvable</p>
                <a href="' . APP_URL . '" class="btn btn-primary">Retour à l\'accueil</a>
              </div>';
        require_once __DIR__ . '/views/layouts/footer.php';
        break;
}
