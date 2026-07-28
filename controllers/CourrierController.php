<?php
/**
 * Contrôleur Courrier - Gestion des courriers et workflow
 * AMM-ERP
 */
class CourrierController {

    private Courrier     $courrierModel;
    private User         $userModel;
    private Notification $notifModel;

    public function __construct() {
        $this->courrierModel = new Courrier();
        $this->userModel     = new User();
        $this->notifModel    = new Notification();
    }

    /**
     * Liste des courriers avec filtres.
     */
    public function liste(): void {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $filtres = [
            'statut'    => $_GET['statut']    ?? '',
            'urgence'   => $_GET['urgence']   ?? '',
            'service'   => $_GET['service']   ?? '',
            'recherche' => $_GET['recherche'] ?? '',
            'date_debut'=> $_GET['date_debut']?? '',
            'date_fin'  => $_GET['date_fin']  ?? '',
        ];

        $total    = $this->courrierModel->compter($filtres);
        $pagination = paginer($total, 20, $page);
        $courriers  = $this->courrierModel->lister($filtres, 20, $pagination['offset']);

        require_once __DIR__ . '/../views/courrier/liste.php';
    }

    /**
     * Formulaire d'ajout d'un courrier.
     */
    public function ajouter(): void {
        $erreurs = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreurs[] = "Requête invalide.";
            } else {
                // Traitement du fichier scan
                $fichierScan = null;
                if (!empty($_FILES['fichier_scan']['name'])) {
                    $fichierScan = traiterUpload($_FILES['fichier_scan'], 'scans');
                    if ($fichierScan === false) {
                        $erreurs[] = "Fichier invalide (type ou taille non autorisé).";
                    }
                }

                $donnees = [
                    'numero_unique'      => genererNumeroCourrier(),
                    'reference_document' => trim($_POST['reference_document'] ?? ''),
                    'expediteur'         => trim($_POST['expediteur'] ?? ''),
                    'type_document'      => trim($_POST['type_document'] ?? ''),
                    'objet'              => trim($_POST['objet'] ?? ''),
                    'urgence'            => $_POST['urgence'] ?? 'basse',
                    'date_reception'     => $_POST['date_reception'] ?? date('Y-m-d'),
                    'deadline'           => $_POST['deadline'] ?? '',
                    'service_destinataire' => trim($_POST['service_destinataire'] ?? ''),
                    'fichier_scan'       => $fichierScan,
                    'recu_physique'      => isset($_POST['recu_physique']) ? 1 : 0,
                ];

                // Validation
                if (empty($donnees['expediteur'])) $erreurs[] = "L'expéditeur est obligatoire.";
                if (empty($donnees['objet']))       $erreurs[] = "L'objet est obligatoire.";
                if (empty($donnees['date_reception'])) $erreurs[] = "La date de réception est obligatoire.";

                if (empty($erreurs)) {
                    $id = $this->courrierModel->creer($donnees);
                    logAudit("Création courrier {$donnees['numero_unique']}", 'courriers', $id);
                    flashMessage('succes', "Courrier {$donnees['numero_unique']} enregistré avec succès.");
                    redirect('/courrier/voir/' . $id);
                }
            }
        }

        require_once __DIR__ . '/../views/courrier/ajouter.php';
    }

    /**
     * Voir le détail d'un courrier.
     */
    public function voir(?string $id): void {
        if (empty($id)) { redirect('/courrier/liste'); }
        $id = (int)$id;

        $courrier    = $this->courrierModel->trouverParId($id);
        if (!$courrier) {
            flashMessage('erreur', "Courrier introuvable.");
            redirect('/courrier/liste');
        }

        $assignations = $this->courrierModel->getAssignations($id);
        $preuves      = $this->courrierModel->getPreuves($id);
        $users        = $this->userModel->listerTous(true);

        require_once __DIR__ . '/../views/courrier/voir.php';
    }

    /**
     * Modifier un courrier.
     */
    public function modifier(?string $id): void {
        if (empty($id)) { redirect('/courrier/liste'); }
        $id = (int)$id;

        $courrier = $this->courrierModel->trouverParId($id);
        if (!$courrier) {
            flashMessage('erreur', "Courrier introuvable.");
            redirect('/courrier/liste');
        }

        $erreurs = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreurs[] = "Requête invalide.";
            } else {
                $donnees = [
                    'reference_document'   => trim($_POST['reference_document'] ?? ''),
                    'expediteur'           => trim($_POST['expediteur'] ?? ''),
                    'type_document'        => trim($_POST['type_document'] ?? ''),
                    'objet'                => trim($_POST['objet'] ?? ''),
                    'urgence'              => $_POST['urgence'] ?? 'basse',
                    'date_reception'       => $_POST['date_reception'] ?? '',
                    'deadline'             => $_POST['deadline'] ?? '',
                    'service_destinataire' => trim($_POST['service_destinataire'] ?? ''),
                    'recu_physique'        => isset($_POST['recu_physique']) ? 1 : 0,
                ];

                if (empty($donnees['expediteur'])) $erreurs[] = "L'expéditeur est obligatoire.";
                if (empty($donnees['objet']))       $erreurs[] = "L'objet est obligatoire.";

                if (empty($erreurs)) {
                    $this->courrierModel->modifier($id, $donnees);
                    logAudit("Modification courrier #$id", 'courriers', $id);
                    flashMessage('succes', "Courrier modifié avec succès.");
                    redirect('/courrier/voir/' . $id);
                }
            }
        }

        require_once __DIR__ . '/../views/courrier/ajouter.php'; // Vue réutilisée
    }

    /**
     * Assigner un courrier à des agents.
     */
    public function assigner(?string $id): void {
        if (empty($id)) { redirect('/courrier/liste'); }
        $id = (int)$id;

        $courrier = $this->courrierModel->trouverParId($id);
        if (!$courrier) {
            flashMessage('erreur', "Courrier introuvable.");
            redirect('/courrier/liste');
        }

        $users = $this->userModel->listerTous(true);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                flashMessage('erreur', "Requête invalide.");
            } else {
                $userIds = $_POST['agents'] ?? [];
                if (empty($userIds)) {
                    flashMessage('erreur', "Sélectionnez au moins un agent.");
                } else {
                    $instructions = trim($_POST['instructions'] ?? '');
                    $duDate       = $_POST['due_date'] ?? '';

                    $this->courrierModel->assigner($id, $userIds, $instructions, $duDate ?: null);

                    // Créer une notification pour chaque agent assigné
                    foreach ($userIds as $userId) {
                        $this->notifModel->creer(
                            (int)$userId,
                            'assignation',
                            "Vous avez été assigné au courrier {$courrier['numero_unique']} : {$courrier['objet']}",
                            APP_URL . '/courrier/voir/' . $id
                        );
                    }

                    logAudit("Assignation courrier #$id à " . implode(',', $userIds), 'courriers', $id);
                    flashMessage('succes', "Courrier assigné avec succès.");
                    redirect('/courrier/voir/' . $id);
                }
            }
        }

        require_once __DIR__ . '/../views/courrier/assigner.php';
    }

    /**
     * Accuser réception d'un courrier (AJAX ou redirect).
     */
    public function accuser(?string $id): void {
        if (empty($id)) { redirect('/courrier/liste'); }
        $id = (int)$id;

        $ok = $this->courrierModel->accuserReception($id, (int)$_SESSION['user_id']);
        logAudit("Accusé réception courrier #$id", 'courriers', $id);

        if ($this->estAjax()) {
            jsonReponse(['success' => $ok, 'message' => $ok ? "Réception accusée." : "Erreur."]);
        } else {
            flashMessage($ok ? 'succes' : 'erreur', $ok ? "Réception accusée." : "Erreur lors de l'accusé.");
            redirect('/courrier/voir/' . $id);
        }
    }

    /**
     * Ajouter une preuve à un courrier.
     */
    public function ajouterPreuve(?string $id): void {
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/courrier/liste'); }
        $id = (int)$id;

        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', "Requête invalide.");
            redirect('/courrier/voir/' . $id);
        }

        $fichier = null;
        if (!empty($_FILES['fichier']['name'])) {
            $fichier = traiterUpload($_FILES['fichier'], 'preuves');
        }

        $donnees = [
            'type_preuve' => $_POST['type_preuve'] ?? 'reponse',
            'fichier'     => $fichier,
            'commentaire' => trim($_POST['commentaire'] ?? ''),
        ];

        $this->courrierModel->ajouterPreuve($id, $donnees);
        logAudit("Ajout preuve courrier #$id", 'preuves', $id);
        flashMessage('succes', "Preuve ajoutée avec succès.");
        redirect('/courrier/voir/' . $id);
    }

    /**
     * Changer le statut d'un courrier (AJAX).
     */
    public function changerStatut(?string $id): void {
        if (empty($id)) { jsonReponse(['success' => false], 400); }
        $id = (int)$id;

        $statut = $_POST['statut'] ?? $_GET['statut'] ?? '';
        $statutsValides = ['Recu','Assigne','Accuse','En_cours','Termine','Clos','Archive','Overdue'];

        if (!in_array($statut, $statutsValides, true)) {
            jsonReponse(['success' => false, 'message' => 'Statut invalide.'], 400);
        }

        $ok = $this->courrierModel->changerStatut($id, $statut);
        logAudit("Changement statut courrier #$id → $statut", 'courriers', $id);

        if ($this->estAjax()) {
            jsonReponse(['success' => $ok, 'new_status' => $statut]);
        } else {
            flashMessage($ok ? 'succes' : 'erreur', "Statut mis à jour.");
            redirect('/courrier/voir/' . $id);
        }
    }

    /**
     * Clôturer un courrier.
     */
    public function cloturer(?string $id): void {
        if (empty($id)) { redirect('/courrier/liste'); }
        $id = (int)$id;

        $resultat = $this->courrierModel->cloturer($id);

        if ($resultat === true) {
            logAudit("Clôture courrier #$id", 'courriers', $id);
            flashMessage('succes', "Courrier clôturé avec succès.");
        } else {
            flashMessage('erreur', $resultat); // Message d'erreur métier
        }

        redirect('/courrier/voir/' . $id);
    }

    /**
     * Escalader un courrier (manuellement).
     */
    public function escalader(?string $id): void {
        if (empty($id)) { redirect('/courrier/liste'); }
        $id = (int)$id;

        $courrier = $this->courrierModel->trouverParId($id);

        // Trouver les chefs de service
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "SELECT id FROM users WHERE role IN ('chef_service','direction') AND actif = 1"
        );
        $stmt->execute();
        $chefs = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($chefs as $chefId) {
            $this->notifModel->creer(
                (int)$chefId,
                'escalation',
                "Escalade : le courrier {$courrier['numero_unique']} nécessite votre attention.",
                APP_URL . '/courrier/voir/' . $id
            );
        }

        logAudit("Escalade courrier #$id", 'courriers', $id);

        if ($this->estAjax()) {
            jsonReponse(['success' => true, 'escalade_niveau' => 'chef_service']);
        } else {
            flashMessage('succes', "Courrier escaladé aux chefs de service.");
            redirect('/courrier/voir/' . $id);
        }
    }

    /**
     * Courriers assignés à l'utilisateur connecté.
     */
    public function mesCourriers(): void {
        $userId    = $_SESSION['user_id'] ?? 0;
        $courriers = $this->courrierModel->mesCourriers($userId);
        $pageTitle = 'Mes courriers — ' . APP_NAME;
        require_once __DIR__ . '/../views/courrier/mes_courriers.php';
    }

    /**
     * Détecte si la requête est AJAX.
     */
    private function estAjax(): bool {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
