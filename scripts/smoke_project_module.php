<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Projet.php';

$pdo = getDB();
$projet = new Projet();
$id = 0;

try {
    $userId = (int)($pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0);

    $baseline = [
        'count' => $projet->compter([]),
        'global' => $projet->statistiquesGlobales(),
        'calendar' => count($projet->elementsCalendrier((int)date('Y'), (int)date('n'))),
    ];

    $id = $projet->creer([
        'titre' => 'Smoke Test Projet ' . date('YmdHis'),
        'description' => 'Validation technique automatique',
        'responsable_id' => $userId > 0 ? $userId : null,
        'budget' => 12345.67,
        'devise' => 'USD',
        'date_debut' => date('Y-m-d'),
        'date_fin' => date('Y-m-d', strtotime('+30 days')),
        'statut' => 'en_cours',
        'created_by' => $userId > 0 ? $userId : null,
    ]);

    $phaseId = $projet->ajouterPhase($id, [
        'nom' => 'Phase Smoke',
        'description' => 'Phase de test',
        'ordre_phase' => 0,
        'date_debut' => date('Y-m-d'),
        'date_fin' => date('Y-m-d', strtotime('+10 days')),
        'statut' => 'en_cours',
        'progress_pct' => 25,
    ]);

    $jalonId = $projet->ajouterJalon($id, [
        'phase_id' => $phaseId,
        'titre' => 'Jalon Smoke',
        'description' => 'Jalon test',
        'date_butoir' => date('Y-m-d', strtotime('+5 days')),
        'statut' => 'non_demarre',
        'created_by' => $userId > 0 ? $userId : null,
    ]);

    $taskId = $projet->ajouterTache($id, [
        'phase_id' => $phaseId,
        'assigned_to' => $userId > 0 ? $userId : null,
        'titre' => 'Tache Smoke',
        'description' => 'Tache test',
        'deadline' => date('Y-m-d', strtotime('+7 days')),
        'priorite' => 'moyenne',
        'statut' => 'a_faire',
        'created_by' => $userId > 0 ? $userId : null,
    ]);

    $okJalon = $projet->changerStatutJalon($jalonId, 'atteint');
    $okTask = $projet->changerStatutTache($taskId, 'en_cours');

    $okLink1 = $projet->ajouterLien($id, [
        'module_type' => 'document',
        'entity_id' => 1,
        'label' => 'Doc test',
        'lien' => APP_URL . '/documents/voir/1',
        'created_by' => $userId > 0 ? $userId : null,
    ]);

    $okLink2 = false;
    try {
        $okLink2 = $projet->ajouterLien($id, [
            'module_type' => 'document',
            'entity_id' => 1,
            'label' => 'Doc test duplicate',
            'lien' => APP_URL . '/documents/voir/1',
            'created_by' => $userId > 0 ? $userId : null,
        ]);
    } catch (PDOException $e) {
        // Expected for duplicate unique key in this smoke scenario.
        $okLink2 = false;
    }

    $project = $projet->trouverParId($id);
    $phases = $projet->listerPhases($id);
    $jalons = $projet->listerJalons($id);
    $taches = $projet->listerTaches($id);
    $liens = $projet->listerLiens($id);
    $stats = $projet->statistiquesProjet($id);
    $calendar = $projet->elementsCalendrier((int)date('Y'), (int)date('n'));

    echo json_encode([
        'baseline' => $baseline,
        'created_project_id' => $id,
        'checks' => [
            'project_found' => !empty($project),
            'phase_count' => count($phases),
            'milestone_count' => count($jalons),
            'task_count' => count($taches),
            'link_count' => count($liens),
            'milestone_status_update' => $okJalon,
            'task_status_update' => $okTask,
            'link_insert_first' => $okLink1,
            'link_insert_duplicate_should_fail' => $okLink2,
            'stats_total_tasks' => (int)$stats['total_taches'],
            'stats_progress' => (float)$stats['progress_global_pct'],
            'calendar_entries_this_month' => count($calendar),
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'SMOKE_ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($id > 0) {
        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
    }
}
