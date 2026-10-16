<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? APP_NAME) ?></title>
    <link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/assets/images/logo/logo_amm.jpg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Merriweather:wght@700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <!-- CSS personnalisé -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/amm.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/register-tables.css">

    <!-- JS commun chargé tôt pour les scripts inline des vues -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?= APP_URL ?>/assets/js/register-tables.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="app-body">

<?php if (!empty($_SESSION['user_id'])): ?>
<!-- =========================================================
     BARRE DE NAVIGATION SUPÉRIEURE
     ========================================================= -->
<nav class="navbar navbar-expand-lg fixed-top app-topbar">
    <div class="container-fluid">
        <button class="btn btn-link app-topbar-icon-btn d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Ouvrir le menu principal">
            <i class="bi bi-list fs-4"></i>
        </button>

        <!-- Logo -->
        <a class="navbar-brand fw-bold d-flex align-items-center" href="<?= APP_URL ?>/dashboard">
            <span class="app-brand-mark">
                <img src="<?= APP_URL ?>/assets/images/logo/logo_amm.jpg" alt="<?= APP_NAME ?>" class="app-brand-logo">
            </span>
            <span><?= APP_NAME ?></span>
        </a>

        <button class="navbar-toggler app-topbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTop">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarTop">
            <!-- Barre de recherche globale -->
            <form class="d-flex ms-lg-4 me-lg-auto app-search-form" action="<?= APP_URL ?>/courrier/liste" method="GET">
                <div class="input-group app-search-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input class="form-control form-control-sm" type="search" name="recherche"
                           placeholder="Rechercher dans AMM-ERP…" aria-label="Recherche globale">
                    <span class="app-search-shortcut d-none d-xl-inline-flex">⌘ K</span>
                </div>
            </form>

            <div class="app-company-context d-none d-xl-flex me-3">
                <span class="app-company-icon"><i class="bi bi-buildings"></i></span>
                <span><small>Société</small><strong>AMM SA · Lubumbashi</strong></span>
            </div>

            <!-- Notifications -->
            <?php
            $nbNotifs = compterNotificationsNonLues();
            ?>
            <div class="dropdown me-3">
                <button class="btn btn-link position-relative app-topbar-icon-btn" data-bs-toggle="dropdown" id="btnNotifs">
                    <i class="bi bi-bell-fill fs-5"></i>
                    <?php if ($nbNotifs > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $nbNotifs > 99 ? '99+' : $nbNotifs ?>
                        </span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow app-notif-dropdown" style="min-width: 320px;" id="dropdownNotifs">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                        <strong>Notifications</strong>
                        <?php if ($nbNotifs > 0): ?>
                            <button class="btn btn-link btn-sm p-0" onclick="marquerToutesLues()">Tout marquer lu</button>
                        <?php endif; ?>
                    </div>
                    <div id="contenuNotifs" style="max-height: 350px; overflow-y: auto;">
                        <div class="text-center p-3 text-muted small">Chargement...</div>
                    </div>
                    <div class="border-top text-center py-2">
                        <a href="#" class="small">Voir toutes les notifications</a>
                    </div>
                </div>
            </div>

            <!-- Densite d'affichage -->
            <button class="btn btn-link app-topbar-icon-btn me-1" id="btnDensityToggle" title="Basculer le mode compact" aria-label="Changer la densité">
                <i class="bi bi-distribute-vertical"></i>
            </button>

            <?php
            $topbarRole = $_SESSION['user_role'] ?? '';
            $topbarService = strtolower((string)($_SESSION['user_service'] ?? ''));
            $topbarFinance = in_array($topbarRole, ['admin','direction','chef_service','tresorier','comptable_senior','directeur_financier','directeur_general','auditeur_interne'], true)
                || strpos($topbarService, 'finance') !== false
                || strpos($topbarService, 'tresorerie') !== false;
            ?>
            <?php if ($topbarFinance): ?>
                <a class="btn btn-link app-topbar-icon-btn me-2" href="<?= APP_URL ?>/treasury/rapports" title="Centre de rapports" aria-label="Centre de rapports">
                    <i class="bi bi-bar-chart-line"></i>
                </a>
            <?php endif; ?>

            <!-- Profil utilisateur -->
            <div class="dropdown">
                <button class="btn btn-link dropdown-toggle app-profile-btn" data-bs-toggle="dropdown">
                    <span class="app-user-avatar"><?= e(strtoupper(substr((string)($_SESSION['user_nom'] ?? 'U'), 0, 1))) ?></span>
                    <span class="app-profile-copy d-none d-md-inline-flex"><strong><?= e($_SESSION['user_nom'] ?? '') ?></strong><small><?= e(libelleRole($_SESSION['user_role'] ?? '')) ?></small></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text text-muted small"><?= e($_SESSION['user_email'] ?? '') ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/auth/logout">
                        <i class="bi bi-box-arrow-right me-2 text-danger"></i>Déconnexion
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="offcanvas offcanvas-start app-sidebar-offcanvas" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title d-flex align-items-center" id="mobileSidebarLabel">
            <span class="app-brand-mark me-2">
                <img src="<?= APP_URL ?>/assets/images/logo/logo_amm.jpg" alt="<?= APP_NAME ?>" class="app-brand-logo">
            </span>
            <?= APP_NAME ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
    </div>
    <div class="offcanvas-body p-0">
        <?php $sidebarMode = 'mobile'; include __DIR__ . '/sidebar.php'; ?>
    </div>
</div>

<div class="d-flex app-layout">
    <!-- Sidebar -->
    <?php $sidebarMode = 'desktop'; include __DIR__ . '/sidebar.php'; ?>

    <!-- Contenu principal -->
    <main class="flex-grow-1 app-main">
<?php endif; ?>
