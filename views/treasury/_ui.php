<?php
if (!function_exists('treasuryUiLabel')) {
    function treasuryUiLabel(string $value): string {
        $labels = [
            'brouillon' => 'Brouillon',
            'soumise' => 'Soumise',
            'validee_comptable' => 'Validée comptable',
            'validee_direction' => 'Validée direction',
            'rejetee' => 'Rejetée',
            'ordre_emis' => 'Ordre émis',
            'payee' => 'Payée',
            'annulee' => 'Annulée',
            'annule' => 'Annulé',
            'valide' => 'Validé',
            'emis' => 'Émis',
            'execute' => 'Exécuté',
            'ouverte' => 'Ouverte',
            'cloturee' => 'Clôturée',
            'controlee' => 'Contrôlée',
            'verrouillee' => 'Verrouillée',
            'en_rapprochement' => 'En rapprochement',
            'rapproche' => 'Rapproché',
            'verrouille' => 'Verrouillé',
            'non_rapproche' => 'Non rapproché',
            'ecart' => 'Écart',
            'en_cours' => 'En cours',
            'basse' => 'Basse',
            'moyenne' => 'Moyenne',
            'haute' => 'Haute',
            'especes' => 'Espèces',
            'banque' => 'Banque',
            'mobile_money' => 'Mobile money',
            'entree' => 'Entrée',
            'sortie' => 'Sortie',
            'comptable' => 'Comptable senior',
            'direction_financiere' => 'Direction financière',
            'direction_generale' => 'Direction générale',
            'rejete' => 'Rejeté',
            'encaissement' => 'Encaissement',
            'paiement' => 'Paiement',
            'actif' => 'Actif',
            'inactif' => 'Inactif',
            'non_ouverte' => 'Journée non ouverte',
            'sans_releve' => 'Aucun relevé',
            'solde_insuffisant' => 'Solde insuffisant',
        ];

        return $labels[$value] ?? ucfirst(str_replace('_', ' ', $value));
    }
}

if (!function_exists('treasuryUiStatusBadge')) {
    function treasuryUiStatusBadge(string $status): string {
        $classes = [
            'brouillon' => 'secondary',
            'soumise' => 'warning text-dark',
            'validee_comptable' => 'info text-dark',
            'validee_direction' => 'primary',
            'ordre_emis' => 'dark',
            'payee' => 'success',
            'valide' => 'success',
            'execute' => 'success',
            'rapproche' => 'success',
            'controlee' => 'primary',
            'ouverte' => 'success',
            'cloturee' => 'warning text-dark',
            'en_rapprochement' => 'warning text-dark',
            'en_cours' => 'warning text-dark',
            'emis' => 'warning text-dark',
            'rejetee' => 'danger',
            'annulee' => 'danger',
            'annule' => 'danger',
            'ecart' => 'danger',
            'verrouille' => 'dark',
            'verrouillee' => 'dark',
            'non_rapproche' => 'secondary',
        ];

        return $classes[$status] ?? 'secondary';
    }
}

if (!function_exists('treasuryUiEmptyState')) {
    function treasuryUiEmptyState(string $icon, string $title, string $text = '', string $actionHtml = ''): void {
        ?>
        <div class="text-center py-5 px-3 text-muted">
            <i class="bi <?= e($icon) ?> fs-2 d-block mb-2"></i>
            <div class="fw-semibold text-body mb-1"><?= e($title) ?></div>
            <?php if ($text !== ''): ?>
                <div class="small mb-3"><?= e($text) ?></div>
            <?php endif; ?>
            <?= $actionHtml ?>
        </div>
        <?php
    }
}
?>
