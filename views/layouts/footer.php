    </main><!-- Fin du contenu principal -->
</div><!-- Fin du flex container -->

<!-- =========================================================
     FOOTER
     ========================================================= -->
<footer class="app-footer text-center small">
    &copy; <?= date('Y') ?> <?= APP_NAME ?> - Tous droits reserves - Lubumbashi, RDC
</footer>

<!-- =========================================================
     SCRIPTS JAVASCRIPT
     ========================================================= -->
<!-- Variable APP_URL pour le JS -->
<script>const APP_URL = '<?= APP_URL ?>';</script>
<!-- JS principal de l'application -->
<script src="<?= APP_URL ?>/assets/js/amm.js"></script>

<script>
// ---------------------------------------------------------------
// CHARGEMENT DES NOTIFICATIONS (via AJAX)
// ---------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function() {
    // Charger les notifications au clic sur l'icône cloche
    const btnNotifs = document.getElementById('btnNotifs');
    if (btnNotifs) {
        btnNotifs.addEventListener('click', chargerNotifications);
    }
});

function chargerNotifications() {
    fetch('<?= APP_URL ?>/ajax/notifications.php')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('contenuNotifs');
            if (!data.length) {
                container.innerHTML = '<div class="text-center p-3 text-muted small">Aucune notification</div>';
                return;
            }
            let html = '';
            data.forEach(n => {
                const lien = n.lien ? n.lien : '#';
                const lu   = n.est_lu == 1 ? 'text-muted' : 'fw-semibold';
                html += `<a href="${lien}" class="dropdown-item py-2 border-bottom ${lu}"
                            onclick="marquerLue(${n.id})">
                           <small class="d-block">${n.message}</small>
                           <small class="text-muted">${n.created_at}</small>
                         </a>`;
            });
            container.innerHTML = html;
        })
        .catch(() => {
            document.getElementById('contenuNotifs').innerHTML =
                '<div class="text-center p-3 text-danger small">Erreur de chargement</div>';
        });
}

function marquerLue(id) {
    fetch('<?= APP_URL ?>/ajax/notification_lue.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    });
}

function marquerToutesLues() {
    fetch('<?= APP_URL ?>/ajax/notification_lue.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'tout=1'
    }).then(() => location.reload());
}
</script>

</body>
</html>
