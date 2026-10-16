<?php
$pageTitle = 'Projet ' . e($projet['titre']) . ' — ' . APP_NAME;

$userId = (int)($_SESSION['user_id'] ?? 0);
$today = date('Y-m-d');
$datePlus7 = date('Y-m-d', strtotime('+7 days'));
$jalonTaskLinks = is_array($jalonTaskLinks ?? null) ? $jalonTaskLinks : [];

$phasesById = [];
$currentPhaseId = 0;
foreach ($phases as $idx => $ph) {
    $pid = (int)$ph['id'];
    $phasesById[$pid] = $ph;

    if ($currentPhaseId === 0 && ($ph['statut'] ?? '') !== 'termine') {
        $currentPhaseId = $pid;
    }
    if ($idx === 0 && $currentPhaseId === 0) {
        $currentPhaseId = $pid;
    }
}

$tachesRetard = 0;
$jalonsRetard = 0;
$echeancesSemaine = 0;
$tachesSansPhase = 0;
$myOpenTasks = 0;
$teamOpenTasks = 0;

$taskCountByPhase = [];
$taskOpenByPhase = [];
foreach ($phases as $ph) {
    $pid = (int)$ph['id'];
    $taskCountByPhase[$pid] = 0;
    $taskOpenByPhase[$pid] = 0;
}

foreach ($taches as $t) {
    $deadline = $t['deadline'] ?? null;
    $phaseId = (int)($t['phase_id'] ?? 0);
    $assignedTo = (int)($t['assigned_to'] ?? 0);
    $isOpen = ($t['statut'] ?? '') !== 'termine';

    if ($isOpen && !empty($deadline) && $deadline < $today) {
        $tachesRetard++;
    }
    if (!empty($deadline) && $deadline >= $today && $deadline <= $datePlus7) {
        $echeancesSemaine++;
    }

    if ($phaseId > 0) {
        if (!isset($taskCountByPhase[$phaseId])) {
            $taskCountByPhase[$phaseId] = 0;
            $taskOpenByPhase[$phaseId] = 0;
        }
        $taskCountByPhase[$phaseId]++;
        if ($isOpen) {
            $taskOpenByPhase[$phaseId]++;
        }
    } else {
        $tachesSansPhase++;
    }

    if ($isOpen && $assignedTo === $userId) {
        $myOpenTasks++;
    }
    if ($isOpen && $assignedTo > 0 && $assignedTo !== $userId) {
        $teamOpenTasks++;
    }
}

$milestoneCountByPhase = [];
foreach ($phases as $ph) {
    $milestoneCountByPhase[(int)$ph['id']] = 0;
}

$nextMilestone = null;
$jalonsSansTachesContrib = 0;
$jalonsMatrice = [];

foreach ($jalons as $j) {
    $butoir = $j['date_butoir'] ?? null;
    $phaseId = (int)($j['phase_id'] ?? 0);
    $jalonStatut = (string)($j['statut'] ?? 'non_demarre');

    if ($jalonStatut !== 'atteint' && !empty($butoir) && $butoir < $today) {
        $jalonsRetard++;
    }
    if (!empty($butoir) && $butoir >= $today && $butoir <= $datePlus7) {
        $echeancesSemaine++;
    }

    if ($phaseId > 0) {
        if (!isset($milestoneCountByPhase[$phaseId])) {
            $milestoneCountByPhase[$phaseId] = 0;
        }
        $milestoneCountByPhase[$phaseId]++;
    }

    if ($jalonStatut !== 'atteint' && !empty($butoir)) {
        if ($nextMilestone === null || $butoir < $nextMilestone['date_butoir']) {
            $nextMilestone = $j;
        }
    }

    $linkMeta = $jalonTaskLinks[(int)$j['id']] ?? null;
    if (is_array($linkMeta)) {
        $contribCount = (int)($linkMeta['total'] ?? 0);
        $pendingCount = (int)($linkMeta['pending'] ?? 0);
    } else {
        $contribCount = 0;
        $pendingCount = 0;
        foreach ($taches as $t) {
            $taskPhaseId = (int)($t['phase_id'] ?? 0);
            $samePhase = ($phaseId > 0 && $taskPhaseId === $phaseId);
            $crossProjectBucket = ($phaseId === 0);

            if (!$samePhase && !$crossProjectBucket) {
                continue;
            }

            $taskDeadline = $t['deadline'] ?? null;
            if (!empty($butoir) && !empty($taskDeadline) && $taskDeadline > $butoir) {
                continue;
            }

            $contribCount++;
            if (($t['statut'] ?? '') !== 'termine') {
                $pendingCount++;
            }
        }
    }

    if ($contribCount === 0) {
        $jalonsSansTachesContrib++;
    }

    $jalonsMatrice[] = [
        'id' => (int)$j['id'],
        'titre' => (string)$j['titre'],
        'phase_nom' => (string)($j['phase_nom'] ?? 'General'),
        'date_butoir' => (string)($j['date_butoir'] ?? ''),
        'statut' => $jalonStatut,
        'contrib' => $contribCount,
        'pending' => $pendingCount,
    ];
}

$statutProjet = (string)($projet['statut'] ?? 'non_demarre');
$classeStatutProjet = 'secondary';
if ($statutProjet === 'en_cours') {
    $classeStatutProjet = 'primary';
} elseif ($statutProjet === 'termine') {
    $classeStatutProjet = 'success';
} elseif ($statutProjet === 'en_retard') {
    $classeStatutProjet = 'danger';
} elseif ($statutProjet === 'suspendu') {
    $classeStatutProjet = 'warning text-dark';
}

$pctTaches = min(100, max(0, (float)$stats['progress_global_pct']));
$pctPhases = min(100, max(0, (float)$stats['progress_phases_pct']));

$projectAlertLevel = 'ok';
$projectAlertText = 'Execution maitrisable sur la periode.';
if ($jalonsRetard > 0 || $tachesRetard > 0) {
    $projectAlertLevel = 'danger';
    $projectAlertText = 'Des retards sont detectes: prioriser les actions de rattrapage.';
} elseif ($nextMilestone && !empty($nextMilestone['date_butoir']) && $nextMilestone['date_butoir'] <= $datePlus7) {
    $projectAlertLevel = 'warning';
    $projectAlertText = 'Un jalon proche arrive dans moins de 7 jours.';
}

$ganttItems = [];

foreach ($phases as $ph) {
    $phaseId = (int)$ph['id'];
    $start = (string)($ph['date_debut'] ?? '');
    $end = (string)($ph['date_fin'] ?? '');

    if ($start === '') {
        $start = (string)($projet['date_debut'] ?? $today);
    }
    if ($end === '') {
        $end = $start;
    }
    if (strtotime($end) < strtotime($start)) {
        $end = $start;
    }

    $ganttItems[] = [
        'id' => 'PH-' . $phaseId,
        'name' => 'Phase: ' . (string)$ph['nom'],
        'start' => $start,
        'end' => $end,
        'progress' => min(100, max(0, (float)($ph['progress_pct'] ?? 0))),
        'dependencies' => '',
        'custom_class' => 'gantt-phase',
        'type' => 'phase',
        'phase_id' => $phaseId,
    ];
}

foreach ($taches as $t) {
    $taskId = (int)$t['id'];
    $taskPhaseId = (int)($t['phase_id'] ?? 0);
    $taskStart = '';

    if ($taskPhaseId > 0 && !empty($phasesById[$taskPhaseId]['date_debut'])) {
        $taskStart = (string)$phasesById[$taskPhaseId]['date_debut'];
    }
    if ($taskStart === '') {
        $taskStart = (string)($projet['date_debut'] ?? $today);
    }

    $taskEnd = (string)($t['deadline'] ?? '');
    if ($taskEnd === '') {
        $taskEnd = $taskStart;
    }
    if (strtotime($taskEnd) < strtotime($taskStart)) {
        $taskEnd = $taskStart;
    }

    $taskProgress = 0;
    if (($t['statut'] ?? '') === 'en_cours') {
        $taskProgress = 50;
    } elseif (($t['statut'] ?? '') === 'termine') {
        $taskProgress = 100;
    }

    $taskClass = 'gantt-task';
    if (($t['statut'] ?? '') === 'termine') {
        $taskClass .= ' gantt-task-done';
    } elseif (($t['statut'] ?? '') === 'en_cours') {
        $taskClass .= ' gantt-task-progress';
    }
    if (!empty($t['deadline']) && ($t['statut'] ?? '') !== 'termine' && $t['deadline'] < $today) {
        $taskClass .= ' gantt-task-late';
    }

    $ganttItems[] = [
        'id' => 'TK-' . $taskId,
        'name' => 'Tache: ' . (string)$t['titre'],
        'start' => $taskStart,
        'end' => $taskEnd,
        'progress' => $taskProgress,
        'dependencies' => $taskPhaseId > 0 ? 'PH-' . $taskPhaseId : '',
        'custom_class' => $taskClass,
        'type' => 'task',
        'phase_id' => $taskPhaseId,
    ];
}

foreach ($jalons as $j) {
    $milestoneId = (int)$j['id'];
    $milestonePhaseId = (int)($j['phase_id'] ?? 0);
    $milestoneDate = (string)($j['date_butoir'] ?? '');
    if ($milestoneDate === '') {
        $milestoneDate = $today;
    }

    $deps = [];
    foreach (($jalonTaskLinks[$milestoneId]['task_ids'] ?? []) as $taskId) {
        $deps[] = 'TK-' . (int)$taskId;
    }

    $msClass = 'gantt-milestone';
    if (($j['statut'] ?? '') === 'atteint') {
        $msClass .= ' gantt-milestone-done';
    } elseif (($j['statut'] ?? '') !== 'atteint' && !empty($j['date_butoir']) && $j['date_butoir'] < $today) {
        $msClass .= ' gantt-milestone-late';
    }

    $ganttItems[] = [
        'id' => 'MS-' . $milestoneId,
        'name' => 'Jalon: ' . (string)$j['titre'],
        'start' => $milestoneDate,
        'end' => $milestoneDate,
        'progress' => ($j['statut'] ?? '') === 'atteint' ? 100 : 0,
        'dependencies' => implode(',', $deps),
        'custom_class' => $msClass,
        'type' => 'milestone',
        'phase_id' => $milestonePhaseId,
    ];
}

$ganttJson = json_encode($ganttItems, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$ganttSyncUrl = APP_URL . '/projets/gantt-update/' . (int)$projet['id'];
$ganttCsrfToken = genererTokenCSRF();
?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.css">

<div class="container-fluid project-workspace">
    <div class="page-hero project-hero">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-0">
            <div class="project-hero-left">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <a href="<?= APP_URL ?>/projets/liste" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
                    <h2 class="fw-bold mb-0"><i class="bi bi-kanban me-2 text-primary"></i><?= e($projet['titre']) ?></h2>
                    <span class="badge bg-<?= $classeStatutProjet ?>"><?= e($statutProjet) ?></span>
                </div>
                <p class="text-muted mb-2"><?= e($projet['description'] ?? 'Aucune description') ?></p>
                <div class="d-flex flex-wrap gap-2 small text-muted">
                    <span><i class="bi bi-person-badge me-1"></i>Responsable: <?= e($projet['responsable_nom'] ?? 'Non defini') ?></span>
                    <span><i class="bi bi-cash-coin me-1"></i><?= formatMontant((float)$projet['budget'], $projet['devise']) ?></span>
                    <span><i class="bi bi-calendar-event me-1"></i><?= formatDate($projet['date_debut']) ?> - <?= formatDate($projet['date_fin']) ?></span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= APP_URL ?>/projets/modifier/<?= (int)$projet['id'] ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-pencil me-1"></i>Modifier
                </a>
                <a href="<?= APP_URL ?>/projets/calendrier" class="btn btn-outline-primary">
                    <i class="bi bi-calendar3 me-1"></i>Calendrier
                </a>
            </div>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <div class="alert alert-<?= $projectAlertLevel === 'danger' ? 'danger' : ($projectAlertLevel === 'warning' ? 'warning' : 'success') ?> border-0 shadow-sm mb-3">
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div>
                <strong><i class="bi bi-activity me-1"></i>Sante projet:</strong> <?= e($projectAlertText) ?>
            </div>
            <?php if ($nextMilestone): ?>
                <div class="small">
                    <span class="fw-semibold">Prochain jalon:</span>
                    <?= e($nextMilestone['titre']) ?>
                    (<?= formatDate($nextMilestone['date_butoir']) ?>)
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="project-phase-rail card section-card mb-3">
        <div class="card-body py-2 px-3">
            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
                <div class="small text-muted fw-semibold">Navigation par phase</div>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-phase-filter="all">Toutes les phases</button>
            </div>
            <div class="project-phase-track">
                <?php foreach ($phases as $ph): ?>
                    <?php
                    $pid = (int)$ph['id'];
                    $isCurrent = ($pid === $currentPhaseId);
                    $phaseOpenTasks = (int)($taskOpenByPhase[$pid] ?? 0);
                    $phaseMilestones = (int)($milestoneCountByPhase[$pid] ?? 0);
                    ?>
                    <button type="button" class="project-phase-pill <?= $isCurrent ? 'is-current' : '' ?>" data-phase-filter="<?= $pid ?>">
                        <span class="phase-order"><?= (int)$ph['ordre_phase'] ?></span>
                        <span class="phase-name"><?= e($ph['nom']) ?></span>
                        <span class="phase-meta"><?= $phaseOpenTasks ?> taches ouvertes · <?= $phaseMilestones ?> jalons</span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="project-sticky-actions card section-card mb-4">
        <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center gap-2 justify-content-between">
            <div class="small text-muted fw-semibold">Actions rapides</div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary btn-sm" href="#tab-taches" data-project-tab="taches"><i class="bi bi-plus-circle me-1"></i>Ajouter une tache</a>
                <a class="btn btn-outline-primary btn-sm" href="#tab-jalons" data-project-tab="jalons"><i class="bi bi-signpost-2 me-1"></i>Ajouter un jalon</a>
                <a class="btn btn-outline-primary btn-sm" href="#tab-phases" data-project-tab="phases"><i class="bi bi-layers me-1"></i>Ajouter une phase</a>
                <a class="btn btn-outline-secondary btn-sm" href="#tab-liens" data-project-tab="liens"><i class="bi bi-link-45deg me-1"></i>Lier un module</a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card kpi-card text-center py-3">
                <div class="kpi-value text-primary"><?= (int)$stats['total_taches'] ?></div>
                <small class="kpi-label">Taches</small>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card kpi-card text-center py-3">
                <div class="kpi-value text-success"><?= (int)$stats['taches_terminees'] ?></div>
                <small class="kpi-label">Terminees</small>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card kpi-card text-center py-3 border-start border-danger border-3">
                <div class="kpi-value text-danger"><?= (int)$stats['taches_en_retard'] ?></div>
                <small class="kpi-label">En retard</small>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card kpi-card text-center py-3">
                <div class="kpi-value text-warning"><?= number_format((float)$stats['progress_global_pct'], 1, ',', ' ') ?>%</div>
                <small class="kpi-label">Avancement</small>
            </div>
        </div>
    </div>

    <?php if ($tachesSansPhase > 0 || $jalonsSansTachesContrib > 0): ?>
        <div class="card section-card mb-4">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <?php if ($tachesSansPhase > 0): ?>
                        <span class="badge bg-warning text-dark">Attention: <?= (int)$tachesSansPhase ?> tache(s) non classee(s) dans une phase</span>
                    <?php endif; ?>
                    <?php if ($jalonsSansTachesContrib > 0): ?>
                        <span class="badge bg-danger">Alerte: <?= (int)$jalonsSansTachesContrib ?> jalon(s) sans taches contributrices</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 align-items-start">
        <div class="col-xl-8">
            <div class="card section-card project-main-panel">
                <div class="card-header bg-transparent border-0 pb-0">
                    <ul class="nav nav-tabs project-tabs" id="projectTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-overview-link" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button" role="tab">Vue d'ensemble</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-taches-link" data-bs-toggle="tab" data-bs-target="#tab-taches" type="button" role="tab">Taches</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-jalons-link" data-bs-toggle="tab" data-bs-target="#tab-jalons" type="button" role="tab">Jalons</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-phases-link" data-bs-toggle="tab" data-bs-target="#tab-phases" type="button" role="tab">Phases</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-planning-link" data-bs-toggle="tab" data-bs-target="#tab-planning" type="button" role="tab">Planning Gantt</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-liens-link" data-bs-toggle="tab" data-bs-target="#tab-liens" type="button" role="tab">Liens modules</button>
                        </li>
                    </ul>
                </div>

                <div class="card-body">
                    <div class="tab-content" id="projectTabsContent">
                        <div class="tab-pane fade show active" id="tab-overview" role="tabpanel" aria-labelledby="tab-overview-link">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="project-progress-card">
                                        <div class="d-flex justify-content-between mb-1">
                                            <h6 class="fw-semibold mb-0">Progression taches</h6>
                                            <span class="small text-muted"><?= number_format($pctTaches, 1, ',', ' ') ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 12px;"><div class="progress-bar bg-primary" style="width: <?= $pctTaches ?>%"></div></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="project-progress-card">
                                        <div class="d-flex justify-content-between mb-1">
                                            <h6 class="fw-semibold mb-0">Progression phases</h6>
                                            <span class="small text-muted"><?= number_format($pctPhases, 1, ',', ' ') ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 12px;"><div class="progress-bar bg-info" style="width: <?= $pctPhases ?>%"></div></div>
                                    </div>
                                </div>
                            </div>

                            <div class="card summary-card mb-3">
                                <div class="card-body py-3">
                                    <div class="row g-3 small">
                                        <div class="col-md-4"><strong>Total jalons:</strong> <?= (int)$stats['total_jalons'] ?></div>
                                        <div class="col-md-4"><strong>Jalons atteints:</strong> <?= (int)$stats['jalons_atteints'] ?></div>
                                        <div class="col-md-4"><strong>Jalons en retard:</strong> <?= (int)$stats['jalons_en_retard'] ?></div>
                                    </div>
                                </div>
                            </div>

                            <h6 class="fw-semibold mb-2">Matrice jalons et taches contributrices</h6>
                            <div class="table-responsive">
                                <table id="tableProjectMilestoneMatrix" class="table table-sm table-hover align-middle compact mb-0" data-register-table data-register-filter-columns='[{"index":5,"label":"Confiance"},{"index":1,"label":"Phase"}]'>
                                    <thead class="table-light">
                                        <tr><th>Jalon</th><th>Phase</th><th>Date butoir</th><th class="text-center">Taches contributrices</th><th class="text-center">Ouvertes</th><th>Confiance</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($jalonsMatrice)): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-3">Aucun jalon.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($jalonsMatrice as $mx): ?>
                                        <?php
                                        $confidence = 'Haute';
                                        $confidenceClass = 'success';
                                        if ($mx['contrib'] === 0) {
                                            $confidence = 'Critique';
                                            $confidenceClass = 'danger';
                                        } elseif ($mx['pending'] > 0) {
                                            $confidence = 'A risque';
                                            $confidenceClass = 'warning text-dark';
                                        }
                                        ?>
                                        <tr>
                                            <td><?= e($mx['titre']) ?></td>
                                            <td><?= e($mx['phase_nom']) ?></td>
                                            <td data-order="<?= e($mx['date_butoir'] ?? '') ?>"><?= formatDate($mx['date_butoir']) ?></td>
                                            <td class="text-center"><?= (int)$mx['contrib'] ?></td>
                                            <td class="text-center"><?= (int)$mx['pending'] ?></td>
                                            <td><span class="badge bg-<?= $confidenceClass ?>"><?= $confidence ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-taches" role="tabpanel" aria-labelledby="tab-taches-link">
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <button class="btn btn-outline-primary btn-sm active" type="button" data-task-scope="all">Toutes</button>
                                <button class="btn btn-outline-primary btn-sm" type="button" data-task-scope="mine">Mes taches</button>
                                <button class="btn btn-outline-primary btn-sm" type="button" data-task-scope="team">Equipe</button>
                                <button class="btn btn-outline-secondary btn-sm" type="button" data-task-scope="late">Retard</button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle compact mb-3" id="tableProjectTasks" data-register-table data-register-filter-columns='[{"index":3,"label":"Statut"},{"index":1,"label":"Agent"}]' data-register-options='{"columnDefs":[{"targets":4,"searchable":false}]}'>
                                    <thead class="table-light">
                                        <tr><th>Tache</th><th>Agent</th><th>Deadline</th><th>Statut</th><th class="text-center">Action</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($taches)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">Aucune tache.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($taches as $t): ?>
                                        <?php
                                        $isLate = ($t['statut'] !== 'termine' && !empty($t['deadline']) && $t['deadline'] < $today);
                                        $badge = 'secondary';
                                        if ($t['statut'] === 'en_cours') {
                                            $badge = 'primary';
                                        }
                                        if ($t['statut'] === 'termine') {
                                            $badge = 'success';
                                        }
                                        $rowPhase = (int)($t['phase_id'] ?? 0);
                                        $assignedTo = (int)($t['assigned_to'] ?? 0);
                                        ?>
                                        <tr class="project-task-row <?= $isLate ? 'table-danger' : '' ?>" data-phase-id="<?= $rowPhase ?>" data-assigned-to="<?= $assignedTo ?>" data-late="<?= $isLate ? '1' : '0' ?>" data-register-state="<?= e($t['statut']) ?>" data-register-complete="<?= $t['statut'] === 'termine' ? 'true' : 'false' ?>">
                                            <td>
                                                <div class="fw-semibold"><?= e($t['titre']) ?></div>
                                                <div class="small text-muted"><?= e($t['phase_nom'] ?? 'General') ?> | Priorite: <?= e($t['priorite']) ?></div>
                                            </td>
                                            <td><?= e($t['assigne_nom'] ?? 'Non assigne') ?></td>
                                            <td data-order="<?= e($t['deadline'] ?? '') ?>"><?= formatDate($t['deadline']) ?></td>
                                            <td data-register-state="<?= e($t['statut']) ?>"><span class="badge bg-<?= $badge ?>"><?= e($t['statut']) ?></span></td>
                                            <td class="text-center">
                                                <form method="POST" action="<?= APP_URL ?>/projets/tache-statut/<?= (int)$t['id'] ?>" class="d-inline-flex gap-1">
                                                    <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                                    <input type="hidden" name="project_id" value="<?= (int)$projet['id'] ?>">
                                                    <select name="statut" class="form-select form-select-sm">
                                                        <option value="a_faire" <?= $t['statut'] === 'a_faire' ? 'selected' : '' ?>>A faire</option>
                                                        <option value="en_cours" <?= $t['statut'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                                                        <option value="termine" <?= $t['statut'] === 'termine' ? 'selected' : '' ?>>Termine</option>
                                                    </select>
                                                    <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-check2"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div id="taskFilterEmpty" class="alert alert-light border small d-none mb-3">Aucune tache ne correspond aux filtres actifs.</div>

                            <form method="POST" action="<?= APP_URL ?>/projets/tache/<?= (int)$projet['id'] ?>" class="row g-2">
                                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                <div class="col-md-5"><input type="text" class="form-control form-control-sm" name="titre" placeholder="Titre tache" required></div>
                                <div class="col-md-3">
                                    <select class="form-select form-select-sm" name="assigned_to">
                                        <option value="">Assigner a</option>
                                        <?php foreach ($users as $u): ?>
                                            <option value="<?= (int)$u['id'] ?>"><?= e($u['nom']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="deadline"></div>
                                <div class="col-md-2"><button class="btn btn-primary btn-sm w-100" type="submit">Ajouter</button></div>
                                <div class="col-md-4">
                                    <select class="form-select form-select-sm" name="phase_id">
                                        <option value="">Phase (optionnel)</option>
                                        <?php foreach ($phases as $ph): ?>
                                            <option value="<?= (int)$ph['id'] ?>"><?= e($ph['nom']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select form-select-sm" name="priorite">
                                        <option value="moyenne">Priorite moyenne</option>
                                        <option value="haute">Priorite haute</option>
                                        <option value="basse">Priorite basse</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select form-select-sm" name="statut">
                                        <option value="a_faire">A faire</option>
                                        <option value="en_cours">En cours</option>
                                        <option value="termine">Termine</option>
                                    </select>
                                </div>
                                <div class="col-12"><input type="text" class="form-control form-control-sm" name="description" placeholder="Description tache"></div>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="tab-jalons" role="tabpanel" aria-labelledby="tab-jalons-link">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle compact mb-3" id="tableProjectMilestones" data-register-table data-register-filter-columns='[{"index":3,"label":"Statut"},{"index":1,"label":"Phase"}]' data-register-options='{"columnDefs":[{"targets":4,"searchable":false}]}'>
                                    <thead class="table-light">
                                        <tr><th>Jalon</th><th>Phase</th><th>Date butoir</th><th>Statut</th><th class="text-center">Action</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($jalons)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">Aucun jalon.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($jalons as $j): ?>
                                        <?php
                                        $statut = $j['statut'];
                                        if ($statut !== 'atteint' && !empty($j['date_butoir']) && $j['date_butoir'] < $today) {
                                            $statut = 'en_retard';
                                        }
                                        $badge = $statut === 'atteint' ? 'success' : ($statut === 'en_retard' ? 'danger' : 'secondary');
                                        $rowPhase = (int)($j['phase_id'] ?? 0);
                                        $jalonTaskSelected = $jalonTaskLinks[(int)$j['id']]['task_ids'] ?? [];
                                        ?>
                                        <tr class="project-milestone-row" data-phase-id="<?= $rowPhase ?>" data-register-state="<?= e($statut) ?>" data-register-complete="<?= $statut === 'atteint' ? 'true' : 'false' ?>">
                                            <td>
                                                <div class="fw-semibold"><?= e($j['titre']) ?></div>
                                                <div class="small text-muted"><?= e($j['description'] ?? '') ?></div>
                                            </td>
                                            <td><?= e($j['phase_nom'] ?? 'General') ?></td>
                                            <td data-order="<?= e($j['date_butoir'] ?? '') ?>"><?= formatDate($j['date_butoir']) ?></td>
                                            <td data-register-state="<?= e($statut) ?>"><span class="badge bg-<?= $badge ?>"><?= e($statut) ?></span></td>
                                            <td class="text-center">
                                                <form method="POST" action="<?= APP_URL ?>/projets/jalon-statut/<?= (int)$j['id'] ?>" class="d-inline-flex gap-1">
                                                    <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                                    <input type="hidden" name="project_id" value="<?= (int)$projet['id'] ?>">
                                                    <select name="statut" class="form-select form-select-sm">
                                                        <option value="non_demarre" <?= $j['statut'] === 'non_demarre' ? 'selected' : '' ?>>Non demarre</option>
                                                        <option value="atteint" <?= $j['statut'] === 'atteint' ? 'selected' : '' ?>>Atteint</option>
                                                        <option value="en_retard" <?= $j['statut'] === 'en_retard' ? 'selected' : '' ?>>En retard</option>
                                                    </select>
                                                    <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-check2"></i></button>
                                                </form>
                                                <button type="button" class="btn btn-outline-secondary btn-sm mt-1" data-milestone-links="<?= (int)$j['id'] ?>" aria-expanded="false"><i class="bi bi-link-45deg me-1"></i>Tâches liées</button>
                                                <template id="projectMilestoneLinks<?= (int)$j['id'] ?>">
                                                <form method="POST" action="<?= APP_URL ?>/projets/jalon-taches/<?= (int)$j['id'] ?>" class="row g-2 align-items-end">
                                                    <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                                    <input type="hidden" name="project_id" value="<?= (int)$projet['id'] ?>">
                                                    <div class="col-md-9">
                                                        <label class="form-label small text-muted mb-1">Taches contributrices du jalon</label>
                                                        <select name="task_ids[]" multiple size="4" class="form-select form-select-sm">
                                                            <?php foreach ($taches as $t): ?>
                                                                <?php
                                                                $taskPhaseId = (int)($t['phase_id'] ?? 0);
                                                                $taskSelectable = ($rowPhase === 0 || $taskPhaseId === 0 || $taskPhaseId === $rowPhase);
                                                                if (!$taskSelectable) {
                                                                    continue;
                                                                }
                                                                $taskId = (int)$t['id'];
                                                                $selected = in_array($taskId, $jalonTaskSelected, true) ? 'selected' : '';
                                                                ?>
                                                                <option value="<?= $taskId ?>" <?= $selected ?>>
                                                                    #<?= $taskId ?> - <?= e($t['titre']) ?> (<?= e($t['statut']) ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <div class="form-text small">Maintiens Ctrl (ou Cmd) pour selection multiple.</div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <button class="btn btn-outline-secondary btn-sm w-100" type="submit">Enregistrer liaisons</button>
                                                    </div>
                                                </form>
                                                </template>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div id="milestoneFilterEmpty" class="alert alert-light border small d-none mb-3">Aucun jalon ne correspond a la phase selectionnee.</div>

                            <form method="POST" action="<?= APP_URL ?>/projets/jalon/<?= (int)$projet['id'] ?>" class="row g-2">
                                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                <div class="col-md-5"><input type="text" class="form-control form-control-sm" name="titre" placeholder="Titre jalon" required></div>
                                <div class="col-md-3">
                                    <select class="form-select form-select-sm" name="phase_id">
                                        <option value="">Phase (optionnel)</option>
                                        <?php foreach ($phases as $ph): ?>
                                            <option value="<?= (int)$ph['id'] ?>"><?= e($ph['nom']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="date_butoir" required></div>
                                <div class="col-md-2"><button class="btn btn-primary btn-sm w-100" type="submit">Ajouter</button></div>
                                <div class="col-12"><input type="text" class="form-control form-control-sm" name="description" placeholder="Description jalon"></div>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="tab-phases" role="tabpanel" aria-labelledby="tab-phases-link">
                            <div class="table-responsive">
                                <table id="tableProjectPhases" class="table table-sm table-hover align-middle compact mb-3" data-register-table data-register-filter-columns='{"3":"Statut"}'>
                                    <thead class="table-light">
                                        <tr><th>#</th><th>Phase</th><th>Periode</th><th>Statut</th><th>Avancement</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($phases)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">Aucune phase.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($phases as $ph): ?>
                                        <tr data-register-state="<?= e($ph['statut']) ?>" data-register-complete="<?= $ph['statut'] === 'termine' ? 'true' : 'false' ?>">
                                            <td><?= (int)$ph['ordre_phase'] ?></td>
                                            <td>
                                                <div class="fw-semibold"><?= e($ph['nom']) ?></div>
                                                <div class="small text-muted"><?= e($ph['description'] ?? '') ?></div>
                                            </td>
                                            <td><?= formatDate($ph['date_debut']) ?> - <?= formatDate($ph['date_fin']) ?></td>
                                            <td data-register-state="<?= e($ph['statut']) ?>"><span class="badge bg-secondary"><?= e($ph['statut']) ?></span></td>
                                            <td><?= number_format((float)$ph['progress_pct'], 0) ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <form method="POST" action="<?= APP_URL ?>/projets/phase/<?= (int)$projet['id'] ?>" class="row g-2">
                                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                <div class="col-md-6"><input type="text" class="form-control form-control-sm" name="nom" placeholder="Nouvelle phase" required></div>
                                <div class="col-md-2"><input type="number" class="form-control form-control-sm" min="1" name="ordre_phase" placeholder="#"></div>
                                <div class="col-md-2"><input type="number" class="form-control form-control-sm" min="0" max="100" step="1" name="progress_pct" placeholder="%"></div>
                                <div class="col-md-2"><button class="btn btn-primary btn-sm w-100" type="submit">Ajouter</button></div>
                                <div class="col-md-6"><input type="date" class="form-control form-control-sm" name="date_debut"></div>
                                <div class="col-md-6"><input type="date" class="form-control form-control-sm" name="date_fin"></div>
                                <div class="col-12"><input type="text" class="form-control form-control-sm" name="description" placeholder="Description phase"></div>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="tab-planning" role="tabpanel" aria-labelledby="tab-planning-link">
                            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <label for="ganttViewMode" class="small text-muted mb-0">Echelle:</label>
                                    <select id="ganttViewMode" class="form-select form-select-sm" style="width: auto;">
                                        <option value="Day">Jour</option>
                                        <option value="Week" selected>Semaine</option>
                                        <option value="Month">Mois</option>
                                    </select>
                                    <div class="form-check form-check-inline ms-2">
                                        <input class="form-check-input" type="checkbox" id="ganttToggleMilestones" checked>
                                        <label class="form-check-label small" for="ganttToggleMilestones">Jalons</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="ganttToggleDone" checked>
                                        <label class="form-check-label small" for="ganttToggleDone">Elements termines</label>
                                    </div>
                                </div>
                                <div class="small text-muted d-flex align-items-center gap-2">
                                    <span>Le filtre phase du rail s'applique aussi au Gantt.</span>
                                    <span id="ganttSyncStatus"></span>
                                </div>
                            </div>

                            <div id="projectGanttContainer" class="project-gantt-shell">
                                <svg id="projectGantt"></svg>
                            </div>
                            <div id="projectGanttEmpty" class="alert alert-light border small d-none mt-3">Aucun element a afficher avec les filtres actuels.</div>
                        </div>

                        <div class="tab-pane fade" id="tab-liens" role="tabpanel" aria-labelledby="tab-liens-link">
                            <?php if (empty($liens)): ?>
                                <div class="text-muted small mb-3">Aucun element associe.</div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush mb-3">
                                    <?php foreach ($liens as $l): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <div>
                                                <span class="badge bg-secondary me-2"><?= e($l['module_type']) ?></span>
                                                <a href="<?= e($l['lien']) ?>" target="_blank" class="text-decoration-none"><?= e($l['label'] ?: ($l['module_type'] . ' #' . $l['entity_id'])) ?></a>
                                            </div>
                                            <small class="text-muted">#<?= (int)$l['entity_id'] ?></small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <form method="POST" action="<?= APP_URL ?>/projets/lien/<?= (int)$projet['id'] ?>" class="row g-2">
                                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                <div class="col-md-4">
                                    <select class="form-select form-select-sm" name="module_type" required>
                                        <option value="">Module</option>
                                        <option value="courrier">Courrier</option>
                                        <option value="contrat">Contrat</option>
                                        <option value="facture">Facture</option>
                                        <option value="document">Document</option>
                                    </select>
                                </div>
                                <div class="col-md-2"><input type="number" class="form-control form-control-sm" name="entity_id" min="1" placeholder="ID" required></div>
                                <div class="col-md-4"><input type="text" class="form-control form-control-sm" name="label" placeholder="Libelle (optionnel)"></div>
                                <div class="col-md-2"><button class="btn btn-primary btn-sm w-100" type="submit">Lier</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="project-side-stack">
                <div class="card section-card mb-3">
                    <div class="card-header bg-transparent border-0 fw-semibold"><i class="bi bi-bullseye me-2"></i>Priorites de la semaine</div>
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small text-muted">Mes taches ouvertes</span>
                            <span class="badge bg-primary"><?= (int)$myOpenTasks ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small text-muted">Taches equipe ouvertes</span>
                            <span class="badge bg-info"><?= (int)$teamOpenTasks ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small text-muted">Taches en retard</span>
                            <span class="badge bg-danger"><?= (int)$tachesRetard ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small text-muted">Jalons en retard</span>
                            <span class="badge bg-danger"><?= (int)$jalonsRetard ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">Echeances 7 jours</span>
                            <span class="badge bg-warning text-dark"><?= (int)$echeancesSemaine ?></span>
                        </div>
                    </div>
                </div>

                <div class="card section-card mb-3">
                    <div class="card-header bg-transparent border-0 fw-semibold"><i class="bi bi-lightning-charge me-2"></i>Prochaine action recommandee</div>
                    <div class="card-body py-3 small">
                        <?php if ($tachesRetard > 0): ?>
                            <p class="mb-2">Traiter d'abord les taches en retard pour reduire le risque de glissement.</p>
                            <a class="btn btn-outline-danger btn-sm" href="#tab-taches" data-project-tab="taches" data-task-scope="late">Voir les retards</a>
                        <?php elseif ($nextMilestone): ?>
                            <p class="mb-2">Securiser le prochain jalon <strong><?= e($nextMilestone['titre']) ?></strong> avant le <?= formatDate($nextMilestone['date_butoir']) ?>.</p>
                            <a class="btn btn-outline-warning btn-sm" href="#tab-jalons" data-project-tab="jalons">Verifier les jalons</a>
                        <?php else: ?>
                            <p class="mb-2">Le projet est stable. Tu peux planifier la prochaine phase.</p>
                            <a class="btn btn-outline-primary btn-sm" href="#tab-phases" data-project-tab="phases">Planifier les phases</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card section-card">
                    <div class="card-header bg-transparent border-0 fw-semibold"><i class="bi bi-activity me-2"></i>Sante du projet</div>
                    <div class="card-body py-3">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small text-muted">Execution taches</span>
                                <span class="small fw-semibold"><?= number_format($pctTaches, 1, ',', ' ') ?>%</span>
                            </div>
                            <div class="progress" style="height: 10px;"><div class="progress-bar bg-primary" style="width: <?= $pctTaches ?>%"></div></div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small text-muted">Execution phases</span>
                                <span class="small fw-semibold"><?= number_format($pctPhases, 1, ',', ' ') ?>%</span>
                            </div>
                            <div class="progress" style="height: 10px;"><div class="progress-bar bg-info" style="width: <?= $pctPhases ?>%"></div></div>
                        </div>

                        <hr class="my-3">
                        <div class="small text-muted d-grid gap-2">
                            <div><strong>Responsable:</strong> <?= e($projet['responsable_nom'] ?? 'Non defini') ?></div>
                            <div><strong>Budget:</strong> <?= formatMontant((float)$projet['budget'], $projet['devise']) ?></div>
                            <div><strong>Debut:</strong> <?= formatDate($projet['date_debut']) ?></div>
                            <div><strong>Fin:</strong> <?= formatDate($projet['date_fin']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const PROJECT_GANTT_ITEMS = <?= $ganttJson ?: '[]' ?>;
const PROJECT_GANTT_SYNC_URL = <?= json_encode($ganttSyncUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const PROJECT_GANTT_CSRF = <?= json_encode($ganttCsrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var currentPhaseFilter = 'all';
    var currentTaskScope = 'all';
    var currentUserId = <?= (int)$userId ?>;
    var gantt = null;
    var ganttSyncStatus = document.getElementById('ganttSyncStatus');

    function setGanttSyncStatus(message, tone) {
        if (!ganttSyncStatus) {
            return;
        }
        ganttSyncStatus.classList.remove('text-muted', 'text-success', 'text-danger', 'text-warning');
        ganttSyncStatus.classList.add('small');
        ganttSyncStatus.classList.add(tone ? ('text-' + tone) : 'text-muted');
        ganttSyncStatus.textContent = message || '';
    }

    function parseGanttEntity(taskId) {
        if (!taskId) {
            return null;
        }
        if (taskId.indexOf('PH-') === 0) {
            return { entity_type: 'phase', entity_id: parseInt(taskId.substring(3), 10) || 0 };
        }
        if (taskId.indexOf('TK-') === 0) {
            return { entity_type: 'task', entity_id: parseInt(taskId.substring(3), 10) || 0 };
        }
        if (taskId.indexOf('MS-') === 0) {
            return { entity_type: 'milestone', entity_id: parseInt(taskId.substring(3), 10) || 0 };
        }
        return null;
    }

    function toIsoDate(value) {
        var d = value instanceof Date ? value : new Date(value);
        if (!(d instanceof Date) || isNaN(d.getTime())) {
            return null;
        }
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }

    async function syncGanttDateChange(task, start, end) {
        var identity = parseGanttEntity(task && task.id ? task.id : '');
        if (!identity || identity.entity_id <= 0) {
            return;
        }

        var startDate = toIsoDate(start || task._start || task.start);
        var endDate = toIsoDate(end || task._end || task.end || start || task._start || task.start);
        if (!startDate || !endDate) {
            setGanttSyncStatus('Date invalide, synchro annulee.', 'danger');
            return;
        }

        setGanttSyncStatus('Sauvegarde...', 'warning');

        var payload = new URLSearchParams();
        payload.set('csrf_token', PROJECT_GANTT_CSRF);
        payload.set('entity_type', identity.entity_type);
        payload.set('entity_id', String(identity.entity_id));
        payload.set('start_date', startDate);
        payload.set('end_date', endDate);

        try {
            var response = await fetch(PROJECT_GANTT_SYNC_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload.toString()
            });

            var data = await response.json().catch(function() { return {}; });
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Echec de synchronisation');
            }

            setGanttSyncStatus('Synchronise', 'success');
        } catch (error) {
            setGanttSyncStatus(error && error.message ? error.message : 'Synchronisation impossible', 'danger');
        }
    }

    function getGanttFilteredItems() {
        var showMilestones = !!document.getElementById('ganttToggleMilestones') && document.getElementById('ganttToggleMilestones').checked;
        var showDone = !!document.getElementById('ganttToggleDone') && document.getElementById('ganttToggleDone').checked;

        return PROJECT_GANTT_ITEMS.filter(function(item) {
            var phaseMatch = currentPhaseFilter === 'all' || String(item.phase_id || 0) === String(currentPhaseFilter);
            if (!phaseMatch) {
                return false;
            }

            if (!showMilestones && item.type === 'milestone') {
                return false;
            }

            if (!showDone) {
                if (item.type === 'task' && item.progress >= 100) {
                    return false;
                }
                if (item.type === 'milestone' && item.progress >= 100) {
                    return false;
                }
            }

            return true;
        }).map(function(item) {
            return {
                id: item.id,
                name: item.name,
                start: item.start,
                end: item.end,
                progress: item.progress,
                dependencies: item.dependencies,
                custom_class: item.custom_class
            };
        });
    }

    function renderGantt() {
        var holder = document.getElementById('projectGanttContainer');
        var empty = document.getElementById('projectGanttEmpty');
        var svg = document.getElementById('projectGantt');
        if (!holder || !svg || typeof Gantt === 'undefined') {
            return;
        }

        var tasks = getGanttFilteredItems();
        if (tasks.length === 0) {
            holder.classList.add('d-none');
            if (empty) {
                empty.classList.remove('d-none');
            }
            return;
        }

        holder.classList.remove('d-none');
        if (empty) {
            empty.classList.add('d-none');
        }

        var modeSelect = document.getElementById('ganttViewMode');
        var viewMode = modeSelect ? modeSelect.value : 'Week';

        gantt = new Gantt('#projectGantt', tasks, {
            view_mode: viewMode,
            language: 'fr',
            date_format: 'YYYY-MM-DD',
            popup_on: 'hover',
            on_date_change: function(task, start, end) {
                syncGanttDateChange(task, start, end);
            },
            custom_popup_html: function(task) {
                return '<div class="px-2 py-1">'
                    + '<div class="fw-semibold small">' + task.name + '</div>'
                    + '<div class="small text-muted">' + task.start + ' -> ' + task.end + '</div>'
                    + '<div class="small">Progression: ' + Math.round(task.progress || 0) + '%</div>'
                    + '</div>';
            }
        });
    }

    function setScopeButtonState(scope) {
        document.querySelectorAll('[data-task-scope]').forEach(function(btn) {
            btn.classList.toggle('active', btn.getAttribute('data-task-scope') === scope);
        });
    }

    function setPhaseButtonState(phaseId) {
        document.querySelectorAll('[data-phase-filter]').forEach(function(btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-phase-filter') === String(phaseId));
        });
    }

    function matchesProjectRow(row, task) {
        if (!row) return false;
        var rowPhase = row.getAttribute('data-phase-id') || '0';
        if (currentPhaseFilter !== 'all' && rowPhase !== String(currentPhaseFilter)) return false;
        if (!task) return true;
        var assignedTo = parseInt(row.getAttribute('data-assigned-to') || '0', 10);
        if (currentTaskScope === 'mine') return assignedTo === currentUserId;
        if (currentTaskScope === 'team') return assignedTo > 0 && assignedTo !== currentUserId;
        if (currentTaskScope === 'late') return row.getAttribute('data-late') === '1';
        return true;
    }

    // Filter the whole register, including rows outside the currently displayed page.
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        var id = settings.nTable.id;
        if (id !== 'tableProjectTasks' && id !== 'tableProjectMilestones') return true;
        var api = new $.fn.dataTable.Api(settings);
        return matchesProjectRow(api.row(dataIndex).node(), id === 'tableProjectTasks');
    });

    $('#tableProjectMilestones').on('click', '[data-milestone-links]', function() {
        if (!$.fn.dataTable.isDataTable('#tableProjectMilestones')) return;
        var api = $('#tableProjectMilestones').DataTable();
        var row = api.row(this.closest('tr'));
        if (row.child.isShown()) {
            row.child.hide();
            this.setAttribute('aria-expanded', 'false');
            this.classList.remove('active');
            return;
        }
        if (!row.child()) {
            var template = row.node().querySelector('template');
            if (!template) return;
            var panel = document.createElement('div');
            panel.id = 'projectMilestoneLinksPanel' + this.getAttribute('data-milestone-links');
            panel.className = 'p-3 bg-light-subtle text-start';
            panel.append(template.content.cloneNode(true));
            this.setAttribute('aria-controls', panel.id);
            row.child(panel);
        }
        row.child.show();
        this.setAttribute('aria-expanded', 'true');
        this.classList.add('active');
    });

    function applyTaskFilters() {
        if ($.fn.dataTable.isDataTable('#tableProjectTasks')) {
            $('#tableProjectTasks').DataTable().draw();
            var message = document.getElementById('taskFilterEmpty');
            if (message) message.classList.add('d-none');
            return;
        }
        var visibleTasks = 0;
        document.querySelectorAll('.project-task-row').forEach(function(row) {
            var rowPhase = row.getAttribute('data-phase-id') || '0';
            var assignedTo = parseInt(row.getAttribute('data-assigned-to') || '0', 10);
            var isLate = row.getAttribute('data-late') === '1';

            var phaseMatch = currentPhaseFilter === 'all' || rowPhase === String(currentPhaseFilter);
            var scopeMatch = true;
            if (currentTaskScope === 'mine') {
                scopeMatch = assignedTo === currentUserId;
            } else if (currentTaskScope === 'team') {
                scopeMatch = assignedTo > 0 && assignedTo !== currentUserId;
            } else if (currentTaskScope === 'late') {
                scopeMatch = isLate;
            }

            var show = phaseMatch && scopeMatch;
            row.classList.toggle('d-none', !show);
            if (show) {
                visibleTasks++;
            }
        });

        var empty = document.getElementById('taskFilterEmpty');
        if (empty) {
            empty.classList.toggle('d-none', visibleTasks > 0);
        }
    }

    function applyMilestoneFilters() {
        if ($.fn.dataTable.isDataTable('#tableProjectMilestones')) {
            $('#tableProjectMilestones').DataTable().draw();
            var message = document.getElementById('milestoneFilterEmpty');
            if (message) message.classList.add('d-none');
            return;
        }
        var visibleMilestones = 0;
        document.querySelectorAll('.project-milestone-row').forEach(function(row) {
            var rowPhase = row.getAttribute('data-phase-id') || '0';
            var phaseMatch = currentPhaseFilter === 'all' || rowPhase === String(currentPhaseFilter);
            row.classList.toggle('d-none', !phaseMatch);
            if (phaseMatch) {
                visibleMilestones++;
            }
        });

        var empty = document.getElementById('milestoneFilterEmpty');
        if (empty) {
            empty.classList.toggle('d-none', visibleMilestones > 0);
        }
    }

    function applyAllFilters() {
        applyTaskFilters();
        applyMilestoneFilters();
        if (document.getElementById('tab-planning') && document.getElementById('tab-planning').classList.contains('active')) {
            renderGantt();
        }
    }

    document.querySelectorAll('[data-phase-filter]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            currentPhaseFilter = btn.getAttribute('data-phase-filter');
            setPhaseButtonState(currentPhaseFilter);
            applyAllFilters();
        });
    });

    var ganttViewMode = document.getElementById('ganttViewMode');
    if (ganttViewMode) {
        ganttViewMode.addEventListener('change', function() {
            renderGantt();
        });
    }

    var ganttToggleMilestones = document.getElementById('ganttToggleMilestones');
    if (ganttToggleMilestones) {
        ganttToggleMilestones.addEventListener('change', function() {
            renderGantt();
        });
    }

    var ganttToggleDone = document.getElementById('ganttToggleDone');
    if (ganttToggleDone) {
        ganttToggleDone.addEventListener('change', function() {
            renderGantt();
        });
    }

    document.querySelectorAll('[data-task-scope]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            currentTaskScope = btn.getAttribute('data-task-scope');
            setScopeButtonState(currentTaskScope);
            applyTaskFilters();
        });
    });

    document.querySelectorAll('[data-project-tab]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var target = link.getAttribute('data-project-tab');
            var tabEl = document.getElementById('tab-' + target + '-link');
            if (!tabEl) {
                return;
            }
            bootstrap.Tab.getOrCreateInstance(tabEl).show();
            history.replaceState(null, '', '#tab-' + target);

            var scope = link.getAttribute('data-task-scope');
            if (scope) {
                currentTaskScope = scope;
                setScopeButtonState(currentTaskScope);
                applyTaskFilters();
            }
        });
    });

    if (window.location.hash && window.location.hash.indexOf('#tab-') === 0) {
        var hashTarget = window.location.hash.replace('#tab-', '');
        var tabEl = document.getElementById('tab-' + hashTarget + '-link');
        if (tabEl) {
            bootstrap.Tab.getOrCreateInstance(tabEl).show();
        }
    }

    setPhaseButtonState('all');
    setScopeButtonState('all');
    applyAllFilters();

    var planningTab = document.getElementById('tab-planning-link');
    if (planningTab) {
        planningTab.addEventListener('shown.bs.tab', function() {
            renderGantt();
        });
    }

    if (window.location.hash === '#tab-planning') {
        renderGantt();
    }
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
