<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Connexion — <?= APP_NAME ?></title>
    <link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/assets/images/logo/logo_amm.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --portal-navy: #0b2847;
            --portal-navy-soft: #123b64;
            --portal-blue: #1769e0;
            --portal-ink: #162b43;
            --portal-muted: #6f7f91;
            --portal-border: #dce4ed;
            --portal-surface: #f4f7fa;
            --portal-success: #16834b;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            color: var(--portal-ink);
            background: var(--portal-surface);
            font-family: 'Manrope', Arial, sans-serif;
        }
        .portal-page { min-height: 100vh; display: grid; grid-template-rows: auto 1fr auto; }
        .portal-topbar { min-height: 66px; display: flex; align-items: center; border-bottom: 1px solid rgba(255,255,255,.1); color: #fff; background: var(--portal-navy); }
        .portal-topbar-inner { width: min(1180px, calc(100% - 40px)); margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 20px; }
        .portal-logo { display: block; width: 210px; max-width: 48vw; height: auto; }
        .portal-access-label { display: inline-flex; align-items: center; gap: 7px; color: #c5d7e8; font-size: .7rem; font-weight: 700; letter-spacing: .035em; text-transform: uppercase; }
        .portal-access-dot { width: 7px; height: 7px; border-radius: 50%; background: #4dcc88; box-shadow: 0 0 0 3px rgba(77,204,136,.15); }
        .portal-main { width: min(1180px, calc(100% - 40px)); margin: 0 auto; padding: 42px 0; display: flex; align-items: center; }
        .portal-shell { width: 100%; overflow: hidden; border: 1px solid var(--portal-border); border-radius: 12px; background: #fff; box-shadow: 0 24px 54px rgba(15,39,65,.11); }
        .portal-information { height: 100%; padding: 38px; color: #fff; background: linear-gradient(145deg, var(--portal-navy), var(--portal-navy-soft)); }
        .portal-eyebrow { margin-bottom: 10px; color: #8fbcea; font-size: .68rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
        .portal-title { max-width: 620px; margin: 0 0 12px; color: #fff; font-size: clamp(1.7rem, 2.8vw, 2.45rem); font-weight: 800; line-height: 1.13; letter-spacing: -.035em; }
        .portal-intro { max-width: 650px; margin: 0; color: #c1d2e3; font-size: .88rem; line-height: 1.65; }
        .portal-modules { margin-top: 28px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .portal-module { min-height: 104px; padding: 14px; border: 1px solid rgba(190,215,239,.16); border-radius: 8px; background: rgba(255,255,255,.045); }
        .portal-module-head { display: flex; align-items: center; gap: 8px; margin-bottom: 7px; color: #fff; font-size: .76rem; font-weight: 800; }
        .portal-module-icon { width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; color: #a8cdf2; background: rgba(143,188,234,.12); }
        .portal-module p { margin: 0; color: #aebfd0; font-size: .68rem; line-height: 1.55; }
        .portal-trust { margin-top: 24px; padding-top: 18px; display: flex; flex-wrap: wrap; gap: 14px 20px; border-top: 1px solid rgba(190,215,239,.14); }
        .portal-trust-item { display: inline-flex; align-items: center; gap: 6px; color: #b9cadb; font-size: .67rem; font-weight: 600; }
        .portal-trust-item i { color: #63d396; }
        .portal-login { height: 100%; padding: 42px 38px; display: flex; flex-direction: column; justify-content: center; background: #fff; }
        .portal-login-kicker { margin-bottom: 8px; color: var(--portal-blue); font-size: .68rem; font-weight: 800; letter-spacing: .075em; text-transform: uppercase; }
        .portal-login h1 { margin: 0 0 7px; color: var(--portal-ink); font-size: 1.45rem; font-weight: 800; letter-spacing: -.025em; }
        .portal-login-copy { margin: 0 0 25px; color: var(--portal-muted); font-size: .79rem; line-height: 1.55; }
        .portal-label { margin-bottom: 6px; color: #344a62; font-size: .7rem; font-weight: 800; }
        .portal-input .input-group-text { width: 44px; justify-content: center; border-color: #ccd7e3; color: #65778a; background: #f7f9fb; }
        .portal-input .form-control,.portal-input .btn { min-height: 47px; border-color: #ccd7e3; box-shadow: none; font-size: .83rem; }
        .portal-input .form-control:focus { border-color: #7eabe0; box-shadow: 0 0 0 .2rem rgba(23,105,224,.1); }
        .portal-submit { min-height: 47px; border-radius: 7px; border-color: var(--portal-blue); background: var(--portal-blue); font-size: .79rem; font-weight: 800; }
        .portal-submit:hover,.portal-submit:focus { border-color: #105bbe; background: #105bbe; }
        .portal-form-note { margin-top: 18px; padding-top: 16px; border-top: 1px solid #e8edf2; color: #8290a0; font-size: .65rem; line-height: 1.55; }
        .portal-error { border: 1px solid #efc2c7; border-radius: 7px; color: #922633; background: #fff4f5; font-size: .75rem; }
        .portal-footer { min-height: 54px; display: flex; align-items: center; color: #7b8998; background: #eef2f6; border-top: 1px solid #dde5ed; font-size: .64rem; }
        .portal-footer-inner { width: min(1180px, calc(100% - 40px)); margin: 0 auto; display: flex; justify-content: space-between; gap: 20px; }
        @media(max-width:991.98px){
            .portal-main{padding:24px 0}.portal-information,.portal-login{padding:28px}.portal-modules{margin-top:22px}
        }
        @media(max-width:767.98px){
            .portal-main,.portal-topbar-inner,.portal-footer-inner{width:min(100% - 24px,1180px)}
            .portal-main{padding:18px 0}.portal-shell{border-radius:9px}.portal-information,.portal-login{padding:23px}
            .portal-modules{grid-template-columns:1fr}.portal-module{min-height:auto}.portal-trust{display:none}
            .portal-footer-inner{flex-direction:column;gap:3px;padding:10px 0}
        }
        @media(max-width:420px){.portal-access-label span:last-child{display:none}.portal-logo{width:175px}}
    </style>
</head>
<body>
<div class="portal-page">
    <header class="portal-topbar">
        <div class="portal-topbar-inner">
            <img src="<?= APP_URL ?>/assets/images/logo/logo_amm.jpg" alt="<?= APP_NAME ?>" class="portal-logo">
            <div class="portal-access-label"><span class="portal-access-dot"></span><span>Accès interne réservé</span></div>
        </div>
    </header>

    <main class="portal-main">
        <div class="portal-shell">
            <div class="row g-0">
                <div class="col-lg-7">
                    <section class="portal-information">
                        <div class="portal-eyebrow">Plateforme intégrée AMM</div>
                        <h2 class="portal-title">Pilotez les opérations, les ressources et les décisions depuis un espace unique.</h2>
                        <p class="portal-intro">AMM-ERP centralise la trésorerie, la gestion financière, les courriers, les documents, les contrats et les projets avec des parcours adaptés à chaque profil.</p>

                        <div class="portal-modules">
                            <div class="portal-module">
                                <div class="portal-module-head"><span class="portal-module-icon"><i class="bi bi-bank2"></i></span>Trésorerie et finance</div>
                                <p>Liquidité, encaissements, demandes de fonds, ordres de paiement, comptes, caisse et rapprochements.</p>
                            </div>
                            <div class="portal-module">
                                <div class="portal-module-head"><span class="portal-module-icon"><i class="bi bi-envelope-paper"></i></span>Courriers et documents</div>
                                <p>Réception, affectation, suivi des délais, médiathèque, archivage et historique documentaire.</p>
                            </div>
                            <div class="portal-module">
                                <div class="portal-module-head"><span class="portal-module-icon"><i class="bi bi-kanban"></i></span>Contrats et projets</div>
                                <p>Échéances contractuelles, budgets, responsabilités, avancement et calendrier des activités.</p>
                            </div>
                            <div class="portal-module">
                                <div class="portal-module-head"><span class="portal-module-icon"><i class="bi bi-clipboard-data"></i></span>Pilotage et contrôle</div>
                                <p>Dashboards par rôle, circuits d’approbation, traçabilité, alertes et rapports exécutifs.</p>
                            </div>
                        </div>

                        <div class="portal-trust">
                            <span class="portal-trust-item"><i class="bi bi-person-check"></i>Accès selon le profil</span>
                            <span class="portal-trust-item"><i class="bi bi-diagram-3"></i>Circuits de validation</span>
                            <span class="portal-trust-item"><i class="bi bi-clock-history"></i>Traçabilité des opérations</span>
                            <span class="portal-trust-item"><i class="bi bi-file-earmark-lock"></i>Preuves centralisées</span>
                        </div>
                    </section>
                </div>

                <div class="col-lg-5">
                    <section class="portal-login">
                        <div class="portal-login-kicker">Portail utilisateur</div>
                        <h1>Connexion</h1>
                        <p class="portal-login-copy">Saisissez vos identifiants professionnels. Votre espace de travail sera automatiquement adapté à votre rôle.</p>

                        <?php if (!empty($erreur)): ?>
                            <div class="alert portal-error d-flex align-items-start gap-2 py-2 px-3 mb-3" role="alert">
                                <i class="bi bi-exclamation-circle-fill mt-1"></i><span><?= e($erreur) ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= APP_URL ?>/" autocomplete="on">
                            <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                            <div class="mb-3">
                                <label for="email" class="form-label portal-label">Adresse e-mail</label>
                                <div class="input-group portal-input">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" autocomplete="username"
                                           placeholder="nom@amm.com" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label portal-label">Mot de passe</label>
                                <div class="input-group portal-input">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password"
                                           autocomplete="current-password" placeholder="Votre mot de passe" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Afficher ou masquer le mot de passe">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary portal-submit"><i class="bi bi-box-arrow-in-right me-2"></i>Se connecter</button>
                            </div>
                        </form>

                        <div class="portal-form-note">
                            <i class="bi bi-info-circle me-1"></i>
                            L’accès est réservé aux utilisateurs autorisés. Les connexions et opérations sensibles sont journalisées.
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <footer class="portal-footer">
        <div class="portal-footer-inner">
            <span><?= APP_NAME ?> · Plateforme interne de gestion</span>
            <span>Version <?= APP_VERSION ?> · Lubumbashi, RDC</span>
        </div>
    </footer>
</div>
<script>
document.getElementById('togglePassword').addEventListener('click', function () {
    var input = document.getElementById('password');
    var icon = this.querySelector('i');
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    icon.classList.toggle('bi-eye', !show);
    icon.classList.toggle('bi-eye-slash', show);
    this.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
});
</script>
</body>
</html>
