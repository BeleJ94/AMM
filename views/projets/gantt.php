<?php
$pageTitle = 'Planning Gantt - ' . e($projet['titre']) . ' - ' . APP_NAME;

$today = date('Y-m-d');
$jalonTaskLinks = is_array($jalonTaskLinks ?? null) ? $jalonTaskLinks : [];

$phasesById = [];
foreach ($phases as $ph) {
    $phasesById[(int)$ph['id']] = $ph;
}

$ganttItems = [];
$phaseFilters = [];

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

    $phaseClass = 'gantt-phase';
    if (($ph['statut'] ?? '') === 'termine') {
        $phaseClass .= ' gantt-task-done';
    }

    $ganttItems[] = [
        'id' => 'PH-' . $phaseId,
        'name' => 'Phase: ' . (string)$ph['nom'],
        'start' => $start,
        'end' => $end,
        'progress' => (float)($ph['progress_pct'] ?? 0),
        'dependencies' => '',
        'custom_class' => $phaseClass,
        'type' => 'phase',
        'phase_id' => $phaseId,
    ];

    $phaseFilters[] = [
        'id' => $phaseId,
        'nom' => (string)$ph['nom'],
    ];
}

foreach ($taches as $t) {
    $taskId = (int)$t['id'];
    $taskPhaseId = (int)($t['phase_id'] ?? 0);
    $taskEnd = (string)($t['deadline'] ?? '');
    if ($taskEnd === '') {
        $taskEnd = (string)($projet['date_fin'] ?? $today);
    }

    $taskStart = $taskEnd;
    if ($taskPhaseId > 0 && isset($phasesById[$taskPhaseId]['date_debut']) && !empty($phasesById[$taskPhaseId]['date_debut'])) {
        $taskStart = (string)$phasesById[$taskPhaseId]['date_debut'];
    } elseif (!empty($projet['date_debut'])) {
        $taskStart = (string)$projet['date_debut'];
    }

    if (strtotime($taskEnd) < strtotime($taskStart)) {
        $taskStart = $taskEnd;
    }

    $taskProgress = 0;
    if (($t['statut'] ?? '') === 'termine') {
        $taskProgress = 100;
    } elseif (($t['statut'] ?? '') === 'en_cours') {
        $taskProgress = 60;
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
$phaseJson = json_encode($phaseFilters, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$ganttSyncUrl = APP_URL . '/projets/gantt-update/' . (int)$projet['id'];
$ganttCsrfToken = genererTokenCSRF();

require_once __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.css">

<div class="container-fluid project-workspace">
    <div class="page-hero project-hero">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-0">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <a href="<?= APP_URL ?>/projets/liste" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
                    <h2 class="fw-bold mb-0"><i class="bi bi-bar-chart-steps me-2 text-primary"></i>Planning Gantt</h2>
                    <span class="badge bg-secondary">Projet #<?= (int)$projet['id'] ?></span>
                </div>
                <div class="small text-muted">
                    <strong><?= e($projet['titre']) ?></strong>
                    - Responsable: <?= e($projet['responsable_nom'] ?? 'Non defini') ?>
                    - Periode: <?= formatDate($projet['date_debut']) ?> a <?= formatDate($projet['date_fin']) ?>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= APP_URL ?>/projets/voir/<?= (int)$projet['id'] ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-kanban me-1"></i>Vue detail
                </a>
                <a href="<?= APP_URL ?>/projets/modifier/<?= (int)$projet['id'] ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-pencil me-1"></i>Modifier projet
                </a>
            </div>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <div class="card section-card">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <label for="ganttViewMode" class="small text-muted mb-0">Echelle:</label>
                    <select id="ganttViewMode" class="form-select form-select-sm" style="width: auto;">
                        <option value="Day">Jour</option>
                        <option value="Week" selected>Semaine</option>
                        <option value="Month">Mois</option>
                    </select>
                    <label for="ganttPhaseFilter" class="small text-muted mb-0 ms-2">Phase:</label>
                    <select id="ganttPhaseFilter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">Toutes</option>
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
                    <span>Glisser-deposer pour mettre a jour les dates.</span>
                    <span id="ganttSyncStatus"></span>
                </div>
            </div>

            <div id="projectGanttContainer" class="project-gantt-shell">
                <svg id="projectGantt"></svg>
            </div>
            <div id="projectGanttEmpty" class="alert alert-light border small d-none mt-3">Aucun element a afficher avec les filtres actuels.</div>
        </div>
    </div>
</div>

<script>
const PROJECT_GANTT_ITEMS = <?= $ganttJson ?: '[]' ?>;
const PROJECT_PHASE_FILTERS = <?= $phaseJson ?: '[]' ?>;
const PROJECT_GANTT_SYNC_URL = <?= json_encode($ganttSyncUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const PROJECT_GANTT_CSRF = <?= json_encode($ganttCsrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var currentPhaseFilter = 'all';
    var ganttSyncStatus = document.getElementById('ganttSyncStatus');
    var phaseFilterSelect = document.getElementById('ganttPhaseFilter');

    function setGanttSyncStatus(message, tone) {
        if (!ganttSyncStatus) {
            return;
        }
        ganttSyncStatus.classList.remove('text-muted', 'text-success', 'text-danger', 'text-warning');
        ganttSyncStatus.classList.add('small');
        ganttSyncStatus.classList.add(tone ? ('text-' + tone) : 'text-muted');
        ganttSyncStatus.textContent = message || '';
    }

    function initPhaseFilterOptions() {
        if (!phaseFilterSelect) {
            return;
        }
        PROJECT_PHASE_FILTERS.forEach(function(item) {
            var option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = item.nom;
            phaseFilterSelect.appendChild(option);
        });
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

            if (!showDone && item.progress >= 100) {
                return false;
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

        new Gantt('#projectGantt', tasks, {
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

    initPhaseFilterOptions();

    if (phaseFilterSelect) {
        phaseFilterSelect.addEventListener('change', function() {
            currentPhaseFilter = phaseFilterSelect.value;
            renderGantt();
        });
    }

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

    renderGantt();
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
