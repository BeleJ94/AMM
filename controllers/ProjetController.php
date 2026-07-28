<?php
/**
 * Controleur Projet - Planification et suivi execution
 * AMM-ERP
 */
class ProjetController {

    private Projet $projetModel;
    private User $userModel;

    public function __construct() {
        $this->projetModel = new Projet();
        $this->userModel = new User();
    }

    public function liste(): void {
        $this->verifierAccesProjet();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $filtres = [
            'statut' => $_GET['statut'] ?? '',
            'responsable_id' => (int)($_GET['responsable_id'] ?? 0),
            'recherche' => trim($_GET['recherche'] ?? ''),
        ];

        $total = $this->projetModel->compter($filtres);
        $pagination = paginer($total, 20, $page);
        $projets = $this->projetModel->lister($filtres, 20, $pagination['offset']);
        $stats = $this->projetModel->statistiquesGlobales();
        $responsables = $this->userModel->listerTous(true);

        require_once __DIR__ . '/../views/projets/liste.php';
    }

    public function ajouter(): void {
        $this->verifierAccesProjet();
        $erreurs = [];
        $responsables = $this->userModel->listerTous(true);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreurs[] = 'Requete invalide.';
            } else {
                $donnees = [
                    'titre' => trim($_POST['titre'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'responsable_id' => (int)($_POST['responsable_id'] ?? 0),
                    'budget' => (float)($_POST['budget'] ?? 0),
                    'devise' => $_POST['devise'] ?? 'USD',
                    'date_debut' => $_POST['date_debut'] ?? '',
                    'date_fin' => $_POST['date_fin'] ?? '',
                    'statut' => $_POST['statut'] ?? 'non_demarre',
                    'created_by' => (int)($_SESSION['user_id'] ?? 0),
                ];

                if ($donnees['titre'] === '') {
                    $erreurs[] = 'Le titre du projet est obligatoire.';
                }
                if ($donnees['date_debut'] === '' || $donnees['date_fin'] === '') {
                    $erreurs[] = 'Les dates debut et fin sont obligatoires.';
                }
                if ($donnees['date_debut'] !== '' && $donnees['date_fin'] !== '' && $donnees['date_fin'] < $donnees['date_debut']) {
                    $erreurs[] = 'La date de fin doit etre posterieure ou egale a la date de debut.';
                }
                if (!in_array($donnees['devise'], ['USD', 'CDF'], true)) {
                    $erreurs[] = 'Devise invalide.';
                }
                if (!in_array($donnees['statut'], ['non_demarre', 'en_cours', 'termine', 'en_retard', 'suspendu'], true)) {
                    $erreurs[] = 'Statut invalide.';
                }

                if (empty($erreurs)) {
                    $id = $this->projetModel->creer($donnees);
                    logAudit('Creation projet #' . $id . ' - ' . $donnees['titre'], 'projects', $id);
                    flashMessage('succes', 'Projet cree avec succes.');
                    redirect('/projets/voir/' . $id);
                }
            }
        }

        require_once __DIR__ . '/../views/projets/ajouter.php';
    }

    public function modifier(?string $id): void {
        $this->verifierAccesProjet();
        if (empty($id)) {
            redirect('/projets/liste');
        }

        $projectId = (int)$id;
        $projet = $this->projetModel->trouverParId($projectId);
        if (!$projet) {
            flashMessage('erreur', 'Projet introuvable.');
            redirect('/projets/liste');
        }

        $erreurs = [];
        $responsables = $this->userModel->listerTous(true);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreurs[] = 'Requete invalide.';
            } else {
                $donnees = [
                    'titre' => trim($_POST['titre'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'responsable_id' => (int)($_POST['responsable_id'] ?? 0),
                    'budget' => (float)($_POST['budget'] ?? 0),
                    'devise' => $_POST['devise'] ?? 'USD',
                    'date_debut' => $_POST['date_debut'] ?? '',
                    'date_fin' => $_POST['date_fin'] ?? '',
                    'statut' => $_POST['statut'] ?? 'non_demarre',
                ];

                if ($donnees['titre'] === '') {
                    $erreurs[] = 'Le titre du projet est obligatoire.';
                }
                if ($donnees['date_debut'] === '' || $donnees['date_fin'] === '') {
                    $erreurs[] = 'Les dates debut et fin sont obligatoires.';
                }
                if ($donnees['date_debut'] !== '' && $donnees['date_fin'] !== '' && $donnees['date_fin'] < $donnees['date_debut']) {
                    $erreurs[] = 'La date de fin doit etre posterieure ou egale a la date de debut.';
                }

                if (empty($erreurs)) {
                    $this->projetModel->modifier($projectId, $donnees);
                    logAudit('Modification projet #' . $projectId, 'projects', $projectId);
                    flashMessage('succes', 'Projet modifie avec succes.');
                    redirect('/projets/voir/' . $projectId);
                }
            }
        }

        require_once __DIR__ . '/../views/projets/ajouter.php';
    }

    public function voir(?string $id): void {
        $this->verifierAccesProjet();
        if (empty($id)) {
            redirect('/projets/liste');
        }

        $projectId = (int)$id;
        $projet = $this->projetModel->trouverParId($projectId);
        if (!$projet) {
            flashMessage('erreur', 'Projet introuvable.');
            redirect('/projets/liste');
        }

        $phases = $this->projetModel->listerPhases($projectId);
        $jalons = $this->projetModel->listerJalons($projectId);
        $taches = $this->projetModel->listerTaches($projectId);
        $jalonTaskLinks = $this->projetModel->listerRelationsJalonTaches($projectId);
        $liens = $this->projetModel->listerLiens($projectId);
        $stats = $this->projetModel->statistiquesProjet($projectId);
        $users = $this->userModel->listerTous(true);

        require_once __DIR__ . '/../views/projets/voir.php';
    }

    public function gantt(?string $id): void {
        $this->verifierAccesProjet();
        if (empty($id)) {
            redirect('/projets/liste');
        }

        $projectId = (int)$id;
        $projet = $this->projetModel->trouverParId($projectId);
        if (!$projet) {
            flashMessage('erreur', 'Projet introuvable.');
            redirect('/projets/liste');
        }

        $phases = $this->projetModel->listerPhases($projectId);
        $jalons = $this->projetModel->listerJalons($projectId);
        $taches = $this->projetModel->listerTaches($projectId);
        $jalonTaskLinks = $this->projetModel->listerRelationsJalonTaches($projectId);

        require_once __DIR__ . '/../views/projets/gantt.php';
    }

    public function calendrier(): void {
        $this->verifierAccesProjet();

        $annee = max(2020, (int)($_GET['annee'] ?? date('Y')));
        $mois = min(12, max(1, (int)($_GET['mois'] ?? date('n'))));
        $mode = $_GET['mode'] ?? 'mensuel';
        if (!in_array($mode, ['mensuel', 'hebdo'], true)) {
            $mode = 'mensuel';
        }

        $elements = $this->projetModel->elementsCalendrier($annee, $mois);

        $debutSemaine = '';
        $finSemaine = '';
        if ($mode === 'hebdo') {
            $reference = $_GET['semaine'] ?? date('Y-m-d');
            $timestamp = strtotime($reference) ?: time();
            $dayOfWeek = (int)date('N', $timestamp);
            $lundi = strtotime('-' . ($dayOfWeek - 1) . ' days', $timestamp);
            $dimanche = strtotime('+6 days', $lundi);
            $debutSemaine = date('Y-m-d', $lundi);
            $finSemaine = date('Y-m-d', $dimanche);
        }

        require_once __DIR__ . '/../views/projets/calendrier.php';
    }

    public function ajouterPhase(?string $id): void {
        $this->verifierAccesProjet();
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/projets/liste');
        }

        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requete invalide.');
            redirect('/projets/voir/' . (int)$id);
        }

        $projectId = (int)$id;
        $nom = trim($_POST['nom'] ?? '');
        if ($nom === '') {
            flashMessage('erreur', 'Le nom de phase est obligatoire.');
            redirect('/projets/voir/' . $projectId);
        }

        $this->projetModel->ajouterPhase($projectId, [
            'nom' => $nom,
            'description' => trim($_POST['description'] ?? ''),
            'ordre_phase' => (int)($_POST['ordre_phase'] ?? 0),
            'date_debut' => $_POST['date_debut'] ?? null,
            'date_fin' => $_POST['date_fin'] ?? null,
            'statut' => $_POST['statut'] ?? 'non_demarre',
            'progress_pct' => (float)($_POST['progress_pct'] ?? 0),
        ]);

        logAudit('Ajout phase projet #' . $projectId, 'project_phases', $projectId);
        flashMessage('succes', 'Phase ajoutee avec succes.');
        redirect('/projets/voir/' . $projectId);
    }

    public function ajouterJalon(?string $id): void {
        $this->verifierAccesProjet();
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/projets/liste');
        }

        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requete invalide.');
            redirect('/projets/voir/' . (int)$id);
        }

        $projectId = (int)$id;
        $titre = trim($_POST['titre'] ?? '');
        $dateButoir = $_POST['date_butoir'] ?? '';

        if ($titre === '' || $dateButoir === '') {
            flashMessage('erreur', 'Le titre et la date butoir sont obligatoires pour un jalon.');
            redirect('/projets/voir/' . $projectId);
        }

        $this->projetModel->ajouterJalon($projectId, [
            'phase_id' => (int)($_POST['phase_id'] ?? 0),
            'titre' => $titre,
            'description' => trim($_POST['description'] ?? ''),
            'date_butoir' => $dateButoir,
            'statut' => $_POST['statut'] ?? 'non_demarre',
            'created_by' => (int)($_SESSION['user_id'] ?? 0),
        ]);

        logAudit('Ajout jalon projet #' . $projectId, 'project_milestones', $projectId);
        flashMessage('succes', 'Jalon ajoute avec succes.');
        redirect('/projets/voir/' . $projectId);
    }

    public function changerStatutJalon(?string $id): void {
        $this->verifierAccesProjet();
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/projets/liste');
        }

        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requete invalide.');
            redirect('/projets/liste');
        }

        $jalonId = (int)$id;
        $projectId = (int)($_POST['project_id'] ?? 0);
        $statut = $_POST['statut'] ?? '';

        if (!in_array($statut, ['non_demarre', 'atteint', 'en_retard'], true)) {
            flashMessage('erreur', 'Statut jalon invalide.');
            redirect('/projets/voir/' . $projectId);
        }

        $this->projetModel->changerStatutJalon($jalonId, $statut);
        logAudit('Changement statut jalon #' . $jalonId . ' -> ' . $statut, 'project_milestones', $jalonId);
        flashMessage('succes', 'Statut du jalon mis a jour.');
        redirect('/projets/voir/' . $projectId);
    }

    public function ajouterTache(?string $id): void {
        $this->verifierAccesProjet();
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/projets/liste');
        }

        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requete invalide.');
            redirect('/projets/voir/' . (int)$id);
        }

        $projectId = (int)$id;
        $titre = trim($_POST['titre'] ?? '');
        if ($titre === '') {
            flashMessage('erreur', 'Le titre de tache est obligatoire.');
            redirect('/projets/voir/' . $projectId);
        }

        $assignedTo = (int)($_POST['assigned_to'] ?? 0);
        $taskId = $this->projetModel->ajouterTache($projectId, [
            'phase_id' => (int)($_POST['phase_id'] ?? 0),
            'assigned_to' => $assignedTo,
            'titre' => $titre,
            'description' => trim($_POST['description'] ?? ''),
            'deadline' => $_POST['deadline'] ?? null,
            'priorite' => $_POST['priorite'] ?? 'moyenne',
            'statut' => $_POST['statut'] ?? 'a_faire',
            'created_by' => (int)($_SESSION['user_id'] ?? 0),
        ]);

        if ($assignedTo > 0) {
            creerNotification(
                $assignedTo,
                'assignation',
                'Nouvelle tache projet assignee: ' . $titre,
                APP_URL . '/projets/voir/' . $projectId
            );
        }

        logAudit('Ajout tache projet #' . $projectId . ' (tache #' . $taskId . ')', 'project_tasks', $taskId);
        flashMessage('succes', 'Tache ajoutee avec succes.');
        redirect('/projets/voir/' . $projectId);
    }

    public function changerStatutTache(?string $id): void {
        $this->verifierAccesProjet();
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/projets/liste');
        }

        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requete invalide.');
            redirect('/projets/liste');
        }

        $taskId = (int)$id;
        $projectId = (int)($_POST['project_id'] ?? 0);
        $statut = $_POST['statut'] ?? '';

        if (!in_array($statut, ['a_faire', 'en_cours', 'termine'], true)) {
            flashMessage('erreur', 'Statut tache invalide.');
            redirect('/projets/voir/' . $projectId);
        }

        $this->projetModel->changerStatutTache($taskId, $statut);
        logAudit('Changement statut tache #' . $taskId . ' -> ' . $statut, 'project_tasks', $taskId);
        flashMessage('succes', 'Statut de la tache mis a jour.');
        redirect('/projets/voir/' . $projectId);
    }

    public function lierTachesJalon(?string $id): void {
        $this->verifierAccesProjet();
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/projets/liste');
        }

        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requete invalide.');
            redirect('/projets/liste');
        }

        $milestoneId = (int)$id;
        $projectId = (int)($_POST['project_id'] ?? 0);
        $taskIds = $_POST['task_ids'] ?? [];

        if ($projectId <= 0) {
            flashMessage('erreur', 'Projet invalide.');
            redirect('/projets/liste');
        }

        if (!is_array($taskIds)) {
            $taskIds = [];
        }

        $ok = $this->projetModel->definirTachesJalon($projectId, $milestoneId, $taskIds);
        if ($ok) {
            logAudit('Mise a jour liaisons jalon #' . $milestoneId . ' (projet #' . $projectId . ')', 'project_milestone_tasks', $milestoneId);
            flashMessage('succes', 'Liaison taches et jalon enregistree.');
        } else {
            flashMessage('erreur', 'Impossible de mettre a jour les liaisons de ce jalon.');
        }

        redirect('/projets/voir/' . $projectId . '#tab-jalons');
    }

    public function mettreAJourPlanningGantt(?string $id): void {
        $this->verifierAccesProjet();

        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonReponse(['success' => false, 'message' => 'Requete invalide.'], 405);
        }

        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            jsonReponse(['success' => false, 'message' => 'Token CSRF invalide.'], 419);
        }

        $projectId = (int)$id;
        $entityType = trim((string)($_POST['entity_type'] ?? ''));
        $entityId = (int)($_POST['entity_id'] ?? 0);
        $startDate = trim((string)($_POST['start_date'] ?? ''));
        $endDate = trim((string)($_POST['end_date'] ?? ''));

        if (!in_array($entityType, ['phase', 'task', 'milestone'], true) || $entityId <= 0 || $startDate === '' || $endDate === '') {
            jsonReponse(['success' => false, 'message' => 'Parametres invalides.'], 422);
        }

        $projet = $this->projetModel->trouverParId($projectId);
        if (!$projet) {
            jsonReponse(['success' => false, 'message' => 'Projet introuvable.'], 404);
        }

        $ok = $this->projetModel->mettreAJourDatesDepuisGantt($projectId, $entityType, $entityId, $startDate, $endDate);
        if (!$ok) {
            jsonReponse(['success' => false, 'message' => 'Mise a jour impossible.'], 500);
        }

        logAudit('Maj planning Gantt ' . $entityType . ' #' . $entityId . ' du projet #' . $projectId, 'projects', $projectId);
        jsonReponse(['success' => true]);
    }

    public function ajouterLien(?string $id): void {
        $this->verifierAccesProjet();
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/projets/liste');
        }

        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requete invalide.');
            redirect('/projets/voir/' . (int)$id);
        }

        $projectId = (int)$id;
        $moduleType = $_POST['module_type'] ?? '';
        $entityId = (int)($_POST['entity_id'] ?? 0);
        $label = trim($_POST['label'] ?? '');

        if (!in_array($moduleType, ['courrier', 'contrat', 'facture', 'document'], true) || $entityId <= 0) {
            flashMessage('erreur', 'Lien module invalide.');
            redirect('/projets/voir/' . $projectId);
        }

        $lien = $this->buildLienModule($moduleType, $entityId);
        $ok = $this->projetModel->ajouterLien($projectId, [
            'module_type' => $moduleType,
            'entity_id' => $entityId,
            'label' => $label,
            'lien' => $lien,
            'created_by' => (int)($_SESSION['user_id'] ?? 0),
        ]);

        if ($ok) {
            logAudit('Association module ' . $moduleType . ' #' . $entityId . ' au projet #' . $projectId, 'project_links', $projectId);
            flashMessage('succes', 'Lien ajoute au projet.');
        } else {
            flashMessage('erreur', 'Lien deja present ou invalide.');
        }

        redirect('/projets/voir/' . $projectId);
    }

    private function verifierAccesProjet(): void {
        $role = $_SESSION['user_role'] ?? '';
        if (!in_array($role, ['reception', 'agent', 'chef_service', 'direction', 'admin'], true)) {
            flashMessage('erreur', 'Acces refuse au module Projets.');
            redirect('/dashboard');
        }
    }

    private function buildLienModule(string $moduleType, int $entityId): string {
        switch ($moduleType) {
            case 'courrier':
                return APP_URL . '/courrier/voir/' . $entityId;
            case 'contrat':
                return APP_URL . '/contrats/voir/' . $entityId;
            case 'facture':
                return APP_URL . '/finance/facture-voir/' . $entityId;
            case 'document':
                return APP_URL . '/documents/voir/' . $entityId;
            default:
                return APP_URL . '/dashboard';
        }
    }
}
