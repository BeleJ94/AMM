<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">

    <div class="page-hero">
        <div class="d-flex justify-content-between align-items-center mb-0">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-person-check me-2 text-primary"></i>Mes courriers</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard">Accueil</a></li>
                        <li class="breadcrumb-item active">Mes courriers</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <div class="section-card">
        <?php if (empty($courriers)): ?>
            <p class="text-muted text-center py-4">
                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                Aucun courrier ne vous est actuellement assigné.
            </p>
        <?php else: ?>
        <table id="tblMesCourriers" class="table table-hover w-100">
            <thead>
                <tr>
                    <th>Numéro</th>
                    <th>Objet</th>
                    <th>Urgence</th>
                    <th>Statut assignation</th>
                    <th>Échéance</th>
                    <th>Accusé</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courriers as $c): ?>
                <tr>
                    <td>
                        <a href="<?= APP_URL ?>/courrier/voir/<?= $c['id'] ?>" class="fw-semibold text-decoration-none">
                            <?= e($c['numero_unique']) ?>
                        </a>
                    </td>
                    <td><?= e($c['objet']) ?></td>
                    <td><?= badgeUrgence($c['urgence']) ?></td>
                    <td>
                        <?php
                        $sa = $c['statut_assignation'] ?? '';
                        $map = [
                            'en_attente' => ['secondary', 'En attente'],
                            'accuse'     => ['info',      'Accusé'],
                            'en_cours'   => ['warning',   'En cours'],
                            'termine'    => ['success',   'Terminé'],
                        ];
                        $info = $map[$sa] ?? ['light', e($sa)];
                        echo '<span class="badge bg-' . $info[0] . '">' . $info[1] . '</span>';
                        ?>
                    </td>
                    <td>
                        <?php if (!empty($c['due_date'])): ?>
                            <?php $jRestants = (int)ceil((strtotime($c['due_date']) - time()) / 86400); ?>
                            <span class="<?= $jRestants < 0 ? 'text-danger fw-semibold' : ($jRestants <= 3 ? 'text-warning fw-semibold' : '') ?>">
                                <?= date('d/m/Y', strtotime($c['due_date'])) ?>
                                <?php if ($jRestants < 0): ?>
                                    <small>(<?= abs($jRestants) ?>j retard)</small>
                                <?php elseif ($jRestants <= 3): ?>
                                    <small>(J-<?= $jRestants ?>)</small>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($c['acknowledged_at'])): ?>
                            <span class="badge bg-success"><i class="bi bi-check2"></i> <?= date('d/m/Y', strtotime($c['acknowledged_at'])) ?></span>
                        <?php else: ?>
                            <form method="POST" action="<?= APP_URL ?>/courrier/accuser/<?= $c['id'] ?>" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                <button type="submit" class="btn btn-xs btn-outline-primary" style="font-size:.75rem;padding:.15rem .4rem;">
                                    Accuser réception
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= APP_URL ?>/courrier/voir/<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
$(function () {
    $('#tblMesCourriers').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json' },
        pageLength: 25,
        order: [[4, 'asc']]
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
