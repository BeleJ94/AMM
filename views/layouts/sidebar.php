<?php
$urlCourante = $_GET['url'] ?? '';
$section = explode('/', $urlCourante)[0];
$userId = $_SESSION['user_id'] ?? 0;
$userRole = $_SESSION['user_role'] ?? '';
$userServiceLabel = trim((string)($_SESSION['user_service'] ?? 'Non affecte'));
$userService = strtolower($userServiceLabel);
$userServiceNormalise = strtr($userService, [
    'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
    'à' => 'a', 'â' => 'a',
    'ù' => 'u', 'û' => 'u',
    'î' => 'i', 'ï' => 'i',
    'ô' => 'o',
    'ç' => 'c',
]);
$accesFinanceModule = in_array($userRole, ['admin', 'direction', 'chef_service', 'comptable_senior', 'directeur_financier', 'directeur_general'], true) || strpos($userService, 'finance') !== false;
$accesTresorerieModule = accesGlobalDemandesFonds();
$sidebarMode = $sidebarMode ?? 'desktop';
$sidebarId = $sidebarMode === 'mobile' ? 'sidebar-mobile' : 'sidebar';
$sidebarClass = $sidebarMode === 'mobile' ? 'app-sidebar app-sidebar-mobile' : 'app-sidebar app-sidebar-desktop';

$_sidebarCourrier = new Courrier();
$_sidebarContrat = new Contrat();

$badgeMesCourriers = count($_sidebarCourrier->mesCourriers($userId));
$badgeNonAssignes = 0;
if (in_array($userRole, ['reception', 'admin', 'chef_service'], true)) {
    $badgeNonAssignes = $_sidebarCourrier->compter(['statut' => 'Recu']);
}
$badgeOverdue = $_sidebarCourrier->compter(['statut' => 'Overdue']);
$badgeAlertes30 = count($_sidebarContrat->expirantDans(30));
$treasuryMenuCounts = ['demandes' => 0, 'ordres' => 0, 'rapprochements' => 0, 'ecarts' => 0];
if ($accesTresorerieModule) {
    try {
        $treasuryMenuCounts = (new Treasury())->compteursActionsMenu();
    } catch (Throwable $e) {
        error_log('Compteurs menu tresorerie indisponibles: ' . $e->getMessage());
    }
}

$_statutFiltre = $_GET['statut'] ?? '';
$_actifTousCourriers = ($urlCourante === 'courrier/liste' && $_statutFiltre === '');
$_actifNonAssignes = ($urlCourante === 'courrier/liste' && $_statutFiltre === 'Recu');
$_actifOverdue = ($urlCourante === 'courrier/liste' && $_statutFiltre === 'Overdue');
$_actifArchive = ($urlCourante === 'courrier/liste' && $_statutFiltre === 'Archive');
$_actifAlertesContrats = ($urlCourante === 'contrats/alerte');
$_actifTousContrats = ($urlCourante === 'contrats/liste' || ($section === 'contrats' && $urlCourante !== 'contrats/alerte'));
$_actifComptesTresorerie = $urlCourante === 'treasury/comptes'
    || strpos($urlCourante, 'treasury/compte/') === 0
    || in_array($urlCourante, ['treasury/caisse','treasury/banque'], true)
    || strpos($urlCourante, 'treasury/rapprochement/') === 0;

$_openNavigation = ($urlCourante === 'dashboard' || $urlCourante === 'courrier/mes-courriers' || $urlCourante === '');
$_openCourriers = ($section === 'courrier');
$_openRessources = ($section === 'documents' || $section === 'contrats');
$_openProjets = ($section === 'projets');
$_openTreasury = ($section === 'treasury');
$_openFinance = ($section === 'finance');
$_openPilotage = (strpos($urlCourante, 'dashboard/') === 0);
$_openAdmin = ($section === 'users' || $section === 'admin-finance');

if (!function_exists('menuItem')) {
    function menuItem(string $href, string $icon, string $label, string $urlActive, string $urlCible, ?int $badge = null, bool $exact = false, bool $forceActive = false): string {
        if ($forceActive) {
            $actif = 'active';
        } elseif ($exact) {
            $actif = ($urlActive === $urlCible) ? 'active' : '';
        } else {
            $actif = ($urlActive === $urlCible || ($urlCible !== '' && strpos($urlActive, $urlCible . '/') === 0)) ? 'active' : '';
        }

        $badgeHtml = '';
        if ($badge !== null && $badge > 0) {
            $count = $badge > 99 ? '99+' : $badge;
            $badgeHtml = "<span class=\"badge app-sidebar-count ms-auto\">{$count}</span>";
        }

        return "<a href=\"{$href}\" class=\"nav-link app-sidebar-link {$actif} mb-1 rounded d-flex align-items-center\">"
            . "<i class=\"bi {$icon} me-2\"></i>"
            . "<span class=\"flex-grow-1\">{$label}</span>"
            . $badgeHtml
            . "</a>";
    }
}

if (!function_exists('menuSectionToggle')) {
    function menuSectionToggle(string $targetId, string $icon, string $label, bool $open = false): string {
        $expanded = $open ? 'true' : 'false';
        $collapsed = $open ? '' : 'collapsed';
        return "<button class=\"app-sidebar-section-toggle {$collapsed}\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#{$targetId}\" aria-expanded=\"{$expanded}\" aria-controls=\"{$targetId}\">"
            . "<span class=\"d-inline-flex align-items-center\"><i class=\"bi {$icon} me-2\"></i>{$label}</span>"
            . "<i class=\"bi bi-chevron-down app-sidebar-chevron\"></i>"
            . "</button>";
    }
}

if (!function_exists('quickActionItem')) {
    function quickActionItem(string $href, string $icon, string $label, string $class = 'btn-outline-light'): string {
        return "<a href=\"{$href}\" class=\"btn {$class} btn-sm app-sidebar-quick-link\"><i class=\"bi {$icon} me-1\"></i>{$label}</a>";
    }
}
?>
<nav id="<?= e($sidebarId) ?>" class="text-white p-3 flex-shrink-0 <?= e($sidebarClass) ?>">
    <div class="app-sidebar-topcard mb-3">
        <div class="app-sidebar-topcard-label">Espace courant</div>
        <div class="app-sidebar-topcard-title"><?= e($_SESSION['user_nom'] ?? 'Utilisateur') ?></div>
        <div class="app-sidebar-topcard-meta">
            <span class="badge app-role-badge"><?= e(libelleRole($userRole ?: 'agent')) ?></span>
            <span class="app-sidebar-topcard-service"><?= e($userServiceLabel) ?></span>
        </div>
    </div>

    <div class="app-sidebar-quick mb-3">
        <div class="app-sidebar-section-title mb-2">Actions rapides</div>
        <div class="d-grid gap-2">
            <?php if ($accesTresorerieModule): ?>
                <?= quickActionItem(APP_URL . '/treasury/encaissement-ajouter', 'bi-box-arrow-in-down', 'Nouvel encaissement') ?>
                <?= quickActionItem(APP_URL . '/treasury/demande-ajouter', 'bi-card-checklist', 'Demande de fonds') ?>
            <?php elseif ($accesFinanceModule): ?>
                <?= quickActionItem(APP_URL . '/finance/facture-ajouter', 'bi-receipt-cutoff', 'Nouvelle facture') ?>
            <?php else: ?>
                <?= quickActionItem(APP_URL . '/treasury/demande-ajouter', 'bi-card-checklist', 'Demande de fonds') ?>
                <?= quickActionItem(APP_URL . '/courrier/ajouter', 'bi-envelope-plus', 'Nouveau courrier') ?>
                <?= quickActionItem(APP_URL . '/projets/ajouter', 'bi-diagram-3', 'Nouveau projet') ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="app-sidebar-stack">
        <?php if (!$accesTresorerieModule): ?>
        <div class="app-sidebar-group mb-2 app-sidebar-group-priority">
            <?= menuItem(APP_URL . '/treasury/demandes', 'bi-card-checklist', $userRole === 'chef_service' ? 'Demandes du département' : 'Mes demandes de fonds', $urlCourante, 'treasury/demandes', null, true) ?>
        </div>
        <?php endif; ?>
        <?php if ($accesTresorerieModule): ?>
        <div class="app-sidebar-group mb-2 app-sidebar-group-priority">
            <?= menuSectionToggle($sidebarId . '-treasury', 'bi-bank2', 'Trésorerie', $_openTreasury) ?>
            <div id="<?= e($sidebarId) ?>-treasury" class="collapse <?= $_openTreasury ? 'show' : '' ?>">
                <div class="app-sidebar-section-body mt-2">
                    <?= menuItem(APP_URL . '/treasury/dashboard', 'bi-grid-1x2', 'Vue d’ensemble', $urlCourante, 'treasury/dashboard', null, true) ?>
                    <?= menuItem(APP_URL . '/treasury/demandes', 'bi-card-checklist', 'Demandes de fonds', $urlCourante, 'treasury/demandes', $treasuryMenuCounts['demandes'], true) ?>
                    <?= menuItem(APP_URL . '/treasury/ordres', 'bi-file-earmark-check', 'Ordres de paiement', $urlCourante, 'treasury/ordres', $treasuryMenuCounts['ordres'], true) ?>
                    <?= menuItem(APP_URL . '/treasury/encaissements', 'bi-box-arrow-in-down', 'Encaissements', $urlCourante, 'treasury/encaissements', null, true) ?>
                    <?= menuItem(APP_URL . '/treasury/comptes', 'bi-wallet2', 'Comptes de trésorerie', $urlCourante, 'treasury/comptes', $treasuryMenuCounts['ecarts'] + $treasuryMenuCounts['rapprochements'], true, $_actifComptesTresorerie) ?>
                    <?= menuItem(APP_URL . '/treasury/rapports', 'bi-file-earmark-bar-graph', 'Rapports CFO', $urlCourante, 'treasury/rapports', null, true) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="app-sidebar-group mb-2">
            <?= menuSectionToggle($sidebarId . '-navigation', 'bi-compass', 'Navigation', $_openNavigation) ?>
            <div id="<?= e($sidebarId) ?>-navigation" class="collapse <?= $_openNavigation ? 'show' : '' ?>">
                <div class="app-sidebar-section-body mt-2">
                    <?= menuItem(APP_URL . '/dashboard', 'bi-house-door', 'Accueil', $urlCourante, 'dashboard', null, true) ?>
                    <?= menuItem(APP_URL . '/courrier/mes-courriers', 'bi-person-check', 'Mes courriers', $urlCourante, 'courrier/mes-courriers', $badgeMesCourriers, true) ?>
                </div>
            </div>
        </div>

        <div class="app-sidebar-group mb-2">
            <?= menuSectionToggle($sidebarId . '-courriers', 'bi-envelope-paper', 'Traitement courrier', $_openCourriers) ?>
            <div id="<?= e($sidebarId) ?>-courriers" class="collapse <?= $_openCourriers ? 'show' : '' ?>">
                <div class="app-sidebar-section-body mt-2">
                    <?= menuItem(APP_URL . '/courrier/liste', 'bi-envelope', 'Tous les courriers', $urlCourante, '', null, false, $_actifTousCourriers) ?>
                    <?php if (in_array($userRole, ['reception', 'admin', 'chef_service'], true)): ?>
                        <?= menuItem(APP_URL . '/courrier/liste?statut=Recu', 'bi-inbox-fill', 'Non assignés', $urlCourante, '', $badgeNonAssignes, false, $_actifNonAssignes) ?>
                    <?php endif; ?>
                    <?= menuItem(APP_URL . '/courrier/liste?statut=Overdue', 'bi-clock-history', 'Overdue', $urlCourante, '', $badgeOverdue, false, $_actifOverdue) ?>
                    <?= menuItem(APP_URL . '/courrier/liste?statut=Archive', 'bi-archive', 'Archives', $urlCourante, '', null, false, $_actifArchive) ?>
                </div>
            </div>
        </div>

        <div class="app-sidebar-group mb-2">
            <?= menuSectionToggle($sidebarId . '-ressources', 'bi-briefcase', 'Ressources métier', $_openRessources) ?>
            <div id="<?= e($sidebarId) ?>-ressources" class="collapse <?= $_openRessources ? 'show' : '' ?>">
                <div class="app-sidebar-section-body mt-2">
                    <?= menuItem(APP_URL . '/documents/liste', 'bi-folder2-open', 'Médiathèque', $urlCourante, 'documents') ?>
                    <?= menuItem(APP_URL . '/contrats/liste', 'bi-file-earmark-text', 'Contrats', $urlCourante, '', null, false, $_actifTousContrats) ?>
                    <?= menuItem(APP_URL . '/contrats/alerte', 'bi-exclamation-triangle', 'Alertes contrats', $urlCourante, '', $badgeAlertes30, false, $_actifAlertesContrats) ?>
                </div>
            </div>
        </div>

        <div class="app-sidebar-group mb-2">
            <?= menuSectionToggle($sidebarId . '-projets', 'bi-diagram-3', 'Projets', $_openProjets) ?>
            <div id="<?= e($sidebarId) ?>-projets" class="collapse <?= $_openProjets ? 'show' : '' ?>">
                <div class="app-sidebar-section-body mt-2">
                    <?= menuItem(APP_URL . '/projets/liste', 'bi-kanban', 'Liste projets', $urlCourante, 'projets/liste', null, true) ?>
                    <?= menuItem(APP_URL . '/projets/calendrier', 'bi-calendar3', 'Calendrier', $urlCourante, 'projets/calendrier', null, true) ?>
                </div>
            </div>
        </div>

        <?php if ($accesFinanceModule): ?>
        <div class="app-sidebar-group mb-2">
            <?= menuSectionToggle($sidebarId . '-finance', 'bi-cash-coin', 'Finance', $_openFinance) ?>
            <div id="<?= e($sidebarId) ?>-finance" class="collapse <?= $_openFinance ? 'show' : '' ?>">
                <div class="app-sidebar-section-body mt-2">
                    <?= menuItem(APP_URL . '/finance/overview', 'bi-graph-up-arrow', 'Vue d\'ensemble', $urlCourante, 'finance/overview', null, true) ?>
                    <?= menuItem(APP_URL . '/finance/transactions', 'bi-cash-stack', 'Transactions', $urlCourante, 'finance/transactions', null, true) ?>
                    <?= menuItem(APP_URL . '/finance/factures', 'bi-receipt', 'Factures', $urlCourante, 'finance/factures', null, true) ?>
                    <?= menuItem(APP_URL . '/finance/budgets', 'bi-piggy-bank', 'Budgets', $urlCourante, 'finance/budgets', null, true) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (in_array($userRole, ['chef_service', 'direction', 'admin'], true)): ?>
        <div class="app-sidebar-group mb-2">
            <?= menuSectionToggle($sidebarId . '-pilotage', 'bi-bar-chart-line', 'Pilotage', $_openPilotage) ?>
            <div id="<?= e($sidebarId) ?>-pilotage" class="collapse <?= $_openPilotage ? 'show' : '' ?>">
                <div class="app-sidebar-section-body mt-2">
                    <?= menuItem(APP_URL . '/dashboard/reception', 'bi-inbox', 'Dashboard Réception', $urlCourante, 'dashboard/reception', null, true) ?>
                    <?= menuItem(APP_URL . '/dashboard/direction', 'bi-bar-chart', 'Dashboard Direction', $urlCourante, 'dashboard/direction', null, true) ?>
                    <?= menuItem(APP_URL . '/dashboard/finance', 'bi-currency-dollar', 'Dashboard Finance', $urlCourante, 'dashboard/finance', null, true) ?>
                    <?= menuItem(APP_URL . '/dashboard/legal', 'bi-shield-check', 'Dashboard Juridique', $urlCourante, 'dashboard/legal', null, true) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($userRole === 'admin'): ?>
        <div class="app-sidebar-group mb-2">
            <?= menuSectionToggle($sidebarId . '-admin', 'bi-sliders', 'Administration', $_openAdmin) ?>
            <div id="<?= e($sidebarId) ?>-admin" class="collapse <?= $_openAdmin ? 'show' : '' ?>">
                <div class="app-sidebar-section-body mt-2">
                    <?= menuItem(APP_URL . '/users/liste', 'bi-people', 'Utilisateurs', $urlCourante, 'users') ?>
                    <?= menuItem(APP_URL . '/admin-finance', 'bi-bank', 'Paramètres financiers', $urlCourante, 'admin-finance') ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="mt-auto pt-3 app-sidebar-footer">
        <small class="d-block text-center"><?= APP_NAME ?> v<?= APP_VERSION ?></small>
        <small class="d-block text-center"><?= date('d/m/Y') ?></small>
    </div>
</nav>
