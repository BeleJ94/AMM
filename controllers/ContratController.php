<?php
/**
 * Contrôleur Contrat - Gestion des contrats et paiements
 * AMM-ERP
 */
class ContratController {

    private Contrat  $contratModel;
    private Document $documentModel;

    public function __construct() {
        $this->contratModel  = new Contrat();
        $this->documentModel = new Document();
    }

    /**
     * Liste des contrats.
     */
    public function liste(): void {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $filtres = [
            'statut'    => $_GET['statut']    ?? '',
            'devise'    => $_GET['devise']    ?? '',
            'recherche' => $_GET['recherche'] ?? '',
        ];

        $parPage = (int)($_GET['per_page'] ?? 15);
        if (!in_array($parPage, [15, 25, 50, 100], true)) $parPage = 15;
        $total      = $this->contratModel->compter($filtres);
        $pagination = paginer($total, $parPage, $page);
        $contrats   = $this->contratModel->lister($filtres, $parPage, $pagination['offset']);

        require_once __DIR__ . '/../views/contrats/liste.php';
    }

    /**
     * Ajouter un contrat.
     */
    public function ajouter(): void {
        $erreurs  = [];
        $documents= $this->documentModel->lister([], 100, 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreurs[] = "Requête invalide.";
            } else {
                $donnees = [
                    'partenaire'         => trim($_POST['partenaire'] ?? ''),
                    'objet'              => trim($_POST['objet'] ?? ''),
                    'valeur'             => (float)($_POST['valeur'] ?? 0),
                    'devise'             => $_POST['devise'] ?? 'USD',
                    'date_signature'     => $_POST['date_signature'] ?? '',
                    'date_debut'         => $_POST['date_debut'] ?? '',
                    'date_fin'           => $_POST['date_fin'] ?? '',
                    'tacite_reconduction'=> isset($_POST['tacite_reconduction']) ? 1 : 0,
                    'document_id'        => (int)($_POST['document_id'] ?? 0) ?: null,
                ];

                if (empty($donnees['partenaire'])) $erreurs[] = "Le partenaire est obligatoire.";
                if (empty($donnees['objet']))       $erreurs[] = "L'objet est obligatoire.";
                if (empty($donnees['date_debut']))  $erreurs[] = "La date de début est obligatoire.";
                if (empty($donnees['date_fin']))    $erreurs[] = "La date de fin est obligatoire.";
                if ($donnees['date_debut'] && $donnees['date_fin'] && $donnees['date_debut'] > $donnees['date_fin']) {
                    $erreurs[] = "La date de fin doit être postérieure à la date de début.";
                }

                if (empty($erreurs)) {
                    $id = $this->contratModel->creer($donnees);
                    logAudit("Création contrat: {$donnees['partenaire']}", 'contrats', $id);
                    flashMessage('succes', "Contrat créé avec succès.");
                    redirect('/contrats/voir/' . $id);
                }
            }
        }

        require_once __DIR__ . '/../views/contrats/ajouter.php';
    }

    /**
     * Voir un contrat.
     */
    public function voir(?string $id): void {
        if (empty($id)) { redirect('/contrats/liste'); }
        $id = (int)$id;

        $contrat = $this->contratModel->trouverParId($id);
        if (!$contrat) {
            flashMessage('erreur', "Contrat introuvable.");
            redirect('/contrats/liste');
        }

        $paiements   = $this->contratModel->getPaiements($id);
        $totalPaye   = $this->contratModel->totalPaye($id);
        $resteAPayer = $contrat['valeur'] - $totalPaye;
        $documents   = $this->documentModel->lister([], 100, 0);

        require_once __DIR__ . '/../views/contrats/ajouter.php';
    }

    /**
     * Modifier un contrat.
     */
    public function modifier(?string $id): void {
        if (empty($id)) { redirect('/contrats/liste'); }
        $id = (int)$id;

        $contrat  = $this->contratModel->trouverParId($id);
        $documents= $this->documentModel->lister([], 100, 0);
        $erreurs  = [];

        if (!$contrat) {
            flashMessage('erreur', "Contrat introuvable.");
            redirect('/contrats/liste');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreurs[] = "Requête invalide.";
            } else {
                $donnees = [
                    'partenaire'         => trim($_POST['partenaire'] ?? ''),
                    'objet'              => trim($_POST['objet'] ?? ''),
                    'valeur'             => (float)($_POST['valeur'] ?? 0),
                    'devise'             => $_POST['devise'] ?? 'USD',
                    'date_signature'     => $_POST['date_signature'] ?? '',
                    'date_debut'         => $_POST['date_debut'] ?? '',
                    'date_fin'           => $_POST['date_fin'] ?? '',
                    'tacite_reconduction'=> isset($_POST['tacite_reconduction']) ? 1 : 0,
                    'statut'             => $_POST['statut'] ?? 'actif',
                    'document_id'        => (int)($_POST['document_id'] ?? 0) ?: null,
                ];

                if (empty($donnees['partenaire'])) $erreurs[] = "Le partenaire est obligatoire.";
                if (empty($donnees['date_debut']))  $erreurs[] = "La date de début est obligatoire.";
                if (empty($donnees['date_fin']))    $erreurs[] = "La date de fin est obligatoire.";

                if (empty($erreurs)) {
                    $this->contratModel->modifier($id, $donnees);
                    logAudit("Modification contrat #$id", 'contrats', $id);
                    flashMessage('succes', "Contrat modifié avec succès.");
                    redirect('/contrats/voir/' . $id);
                }
            }
        }

        require_once __DIR__ . '/../views/contrats/ajouter.php';
    }

    /**
     * Ajouter un paiement à un contrat.
     */
    public function ajouterPaiement(?string $id): void {
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/contrats/liste'); }
        $id = (int)$id;

        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', "Requête invalide.");
            redirect('/contrats/voir/' . $id);
        }

        $preuveFichier = null;
        if (!empty($_FILES['preuve_fichier']['name'])) {
            $preuveFichier = traiterUpload($_FILES['preuve_fichier'], 'paiements');
        }

        $donnees = [
            'date_paiement' => $_POST['date_paiement'] ?? date('Y-m-d'),
            'montant'       => (float)($_POST['montant'] ?? 0),
            'preuve_fichier'=> $preuveFichier,
            'commentaire'   => trim($_POST['commentaire'] ?? ''),
        ];

        $this->contratModel->ajouterPaiement($id, $donnees);
        logAudit("Paiement ajouté contrat #$id : {$donnees['montant']}", 'paiements', $id);
        flashMessage('succes', "Paiement enregistré avec succès.");
        redirect('/contrats/voir/' . $id);
    }

    /**
     * Page des alertes d'échéances.
     */
    public function alertes(): void {
        $expirant7j  = $this->contratModel->expirantDans(7);
        $expirant15j = $this->contratModel->expirantDans(15);
        $expirant30j = $this->contratModel->expirantDans(30);

        require_once __DIR__ . '/../views/contrats/alerte.php';
    }
}
