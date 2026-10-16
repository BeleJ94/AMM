<?php $pageTitle = 'Calendrier projets — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<?php
$parDate = [];
foreach ($elements as $el) {
    $date = $el['date_event'];
    if (!isset($parDate[$date])) {
        $parDate[$date] = [];
    }
    $parDate[$date][] = $el;
}

$firstDay = sprintf('%04d-%02d-01', $annee, $mois);
$daysInMonth = (int)date('t', strtotime($firstDay));
$startDow = (int)date('N', strtotime($firstDay));
?>

<div class="container-fluid">
    <div class="page-hero">
        <div class="d-flex justify-content-between align-items-center mb-0">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-calendar3 me-2 text-primary"></i>Calendrier Projets</h2>
                <p class="text-muted mb-0">Vue mensuelle / hebdomadaire des taches et jalons.</p>
            </div>
            <a href="<?= APP_URL ?>/projets/liste" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Retour projets
            </a>
        </div>
    </div>

    <div class="card filter-card mb-4">
        <div class="card-body">
            <form method="GET" action="<?= APP_URL ?>/projets/calendrier" class="row g-2">
                <div class="col-md-2"><input type="number" class="form-control form-control-sm" min="2020" max="2100" name="annee" value="<?= (int)$annee ?>"></div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="mois">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m === (int)$mois ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="mode">
                        <option value="mensuel" <?= $mode === 'mensuel' ? 'selected' : '' ?>>Mensuel</option>
                        <option value="hebdo" <?= $mode === 'hebdo' ? 'selected' : '' ?>>Hebdomadaire</option>
                    </select>
                </div>
                <div class="col-md-3"><input type="date" class="form-control form-control-sm" name="semaine" value="<?= e($_GET['semaine'] ?? date('Y-m-d')) ?>" title="Date de reference pour vue hebdo"></div>
                <div class="col-md-2"><button class="btn btn-primary btn-sm w-100" type="submit">Appliquer</button></div>
            </form>
        </div>
    </div>

    <?php if ($mode === 'mensuel'): ?>
        <div class="card section-card mb-4">
            <div class="card-header bg-transparent border-0 fw-semibold">
                <i class="bi bi-calendar-month me-2"></i><?= sprintf('%02d/%04d', $mois, $annee) ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0 align-top">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">Lun</th>
                                <th class="text-center">Mar</th>
                                <th class="text-center">Mer</th>
                                <th class="text-center">Jeu</th>
                                <th class="text-center">Ven</th>
                                <th class="text-center">Sam</th>
                                <th class="text-center">Dim</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $day = 1;
                            $printed = 0;
                            while ($day <= $daysInMonth):
                                echo '<tr>';
                                for ($col = 1; $col <= 7; $col++) {
                                    if ($printed < ($startDow - 1) || $day > $daysInMonth) {
                                        echo '<td style="height:130px;background:#fafbfd"></td>';
                                    } else {
                                        $date = sprintf('%04d-%02d-%02d', $annee, $mois, $day);
                                        $events = $parDate[$date] ?? [];
                                        echo '<td style="height:130px;min-width:150px">';
                                        echo '<div class="fw-semibold mb-1">' . $day . '</div>';
                                        if (empty($events)) {
                                            echo '<div class="small text-muted">-</div>';
                                        } else {
                                            foreach ($events as $ev) {
                                                $isLate = ($ev['statut'] !== 'termine' && $ev['statut'] !== 'atteint' && $ev['date_event'] < date('Y-m-d'));
                                                $badge = $isLate ? 'danger' : ($ev['type'] === 'jalon' ? 'warning text-dark' : 'primary');
                                                echo '<div class="small mb-1">';
                                                echo '<span class="badge bg-' . $badge . ' me-1">' . e($ev['type']) . '</span>';
                                                $titreCourt = (strlen((string)$ev['titre']) > 24) ? substr((string)$ev['titre'], 0, 24) . '...' : (string)$ev['titre'];
                                                echo '<a href="' . APP_URL . '/projets/voir/' . (int)$ev['project_id'] . '" class="text-decoration-none">' . e($titreCourt) . '</a>';
                                                echo '</div>';
                                            }
                                        }
                                        echo '</td>';
                                        $day++;
                                    }
                                    $printed++;
                                }
                                echo '</tr>';
                            endwhile;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card section-card mb-4">
            <div class="card-header bg-transparent border-0 fw-semibold">
                <i class="bi bi-calendar-week me-2"></i>Semaine du <?= formatDate($debutSemaine) ?> au <?= formatDate($finSemaine) ?>
            </div>
            <div class="card-body p-0">
                <table id="tableProjetSemaine" class="table table-sm table-hover align-middle compact mb-0" data-register-table data-register-filter-columns='{"1":"Type","4":"Statut","2":"Projet"}'>
                    <thead class="table-light">
                        <tr><th>Date</th><th>Type</th><th>Projet</th><th>Titre</th><th>Statut</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $rows = 0;
                        foreach ($elements as $ev):
                            if ($ev['date_event'] < $debutSemaine || $ev['date_event'] > $finSemaine) {
                                continue;
                            }
                            $rows++;
                        ?>
                            <tr data-register-state="<?= e($ev['statut']) ?>" data-register-complete="<?= in_array($ev['statut'], ['termine', 'atteint'], true) ? 'true' : 'false' ?>">
                                <td><?= formatDate($ev['date_event']) ?></td>
                                <td><span class="badge bg-secondary"><?= e($ev['type']) ?></span></td>
                                <td><a href="<?= APP_URL ?>/projets/voir/<?= (int)$ev['project_id'] ?>" class="text-decoration-none"><?= e($ev['projet_titre']) ?></a></td>
                                <td><?= e($ev['titre']) ?></td>
                                <td data-register-state="<?= e($ev['statut']) ?>"><?= e($ev['statut']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($rows === 0): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Aucun evenement cette semaine.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
