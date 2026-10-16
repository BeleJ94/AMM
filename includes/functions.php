<?php
/**
 * AMM-ERP - Fonctions utilitaires globales
 * Helpers utilisés à travers toute l'application
 */

// ---------------------------------------------------------------
// SÉCURITÉ & SESSION
// ---------------------------------------------------------------

/**
 * Vérifie qu'une session utilisateur est active.
 * Redirige vers la page de login sinon.
 */
function verifierSession(): void {
    if (empty($_SESSION['user_id'])) {
        redirect('/');
    }
    // Actualiser le rattachement, y compris après une modification du référentiel.
    $stmt = getDB()->prepare('SELECT u.service_id, COALESCE(s.nom,u.service) AS service FROM users u LEFT JOIN services s ON s.id=u.service_id WHERE u.id=?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    if ($rattachement = $stmt->fetch()) {
        $_SESSION['user_service_id'] = $rattachement['service_id'] !== null ? (int)$rattachement['service_id'] : null;
        $_SESSION['user_service'] = $rattachement['service'];
    }
}

/**
 * Vérifie que l'utilisateur possède l'un des rôles autorisés.
 * @param array $rolesAutorises Liste des rôles autorisés
 */
function verifierRole(array $rolesAutorises): void {
    if (!in_array($_SESSION['user_role'] ?? '', $rolesAutorises, true)) {
        logAudit('Accès refusé : rôle insuffisant', 'audit_logs', null, json_encode(['roles_requis'=>$rolesAutorises],JSON_UNESCAPED_UNICODE));
        $_SESSION['flash_erreur'] = "Accès refusé : vous n'avez pas les droits nécessaires.";
        redirect('/dashboard');
    }
}

/**
 * Catalogue central des roles disponibles dans l'administration.
 */
function rolesApplication(): array {
    return [
        'reception' => 'Réception',
        'agent' => 'Agent',
        'chef_service' => 'Chef de service',
        'direction' => 'Direction (historique)',
        'tresorier' => 'Trésorier',
        'comptable_senior' => 'Comptable senior',
        'directeur_financier' => 'Directeur financier',
        'directeur_general' => 'Directeur général',
        'auditeur_interne' => 'Auditeur interne',
        'admin' => 'Administrateur',
    ];
}

function libelleRole(string $role): string {
    return rolesApplication()[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

/** Accès au portefeuille complet des demandes ; les autres profils restent dans leur périmètre. */
function accesGlobalDemandesFonds(): bool {
    if (in_array($_SESSION['user_role'] ?? '', ['admin','direction','tresorier','comptable_senior','directeur_financier','directeur_general','auditeur_interne'], true)) {
        return true;
    }
    $service = strtolower((string)($_SESSION['user_service'] ?? ''));
    $service = strtr($service, ['é'=>'e','è'=>'e','ê'=>'e','ô'=>'o']);
    foreach (['finance','tresorerie','comptable','comptabilite'] as $mot) {
        if (strpos($service, $mot) !== false) return true;
    }
    return false;
}

function perimetreDemandesFonds(): array {
    if (accesGlobalDemandesFonds()) return [];
    $service = trim((string)($_SESSION['user_service'] ?? ''));
    if (($_SESSION['user_role'] ?? '') === 'chef_service' && $service !== '') {
        return !empty($_SESSION['user_service_id']) ? ['department_id'=>(int)$_SESSION['user_service_id'], 'department'=>$service] : ['department'=>$service];
    }
    return ['created_by' => (int)($_SESSION['user_id'] ?? 0)];
}

/**
 * Génère un token CSRF et le stocke en session.
 * @return string Le token généré
 */
function genererTokenCSRF(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valide le token CSRF soumis dans un formulaire.
 * @param string $token Le token reçu du formulaire
 * @return bool
 */
function validerTokenCSRF(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ---------------------------------------------------------------
// REDIRECTIONS & MESSAGES FLASH
// ---------------------------------------------------------------

/**
 * Redirige vers une URL relative à APP_URL.
 * @param string $chemin Chemin relatif (ex: '/courrier/liste')
 */
function redirect(string $chemin): void {
    $url = APP_URL . $chemin;
    header("Location: $url");
    exit;
}

/**
 * Définit un message flash à afficher sur la prochaine page.
 * @param string $type    'succes' | 'erreur' | 'info' | 'avertissement'
 * @param string $message Le message à afficher
 */
function flashMessage(string $type, string $message): void {
    $_SESSION["flash_$type"] = $message;
}

/**
 * Affiche et efface les messages flash stockés en session.
 */
function afficherFlash(): void {
    $types = [
        'succes'       => 'success',
        'erreur'       => 'danger',
        'info'         => 'info',
        'avertissement'=> 'warning',
    ];

    foreach ($types as $cle => $classe) {
        if (!empty($_SESSION["flash_$cle"])) {
            $msg = htmlspecialchars($_SESSION["flash_$cle"], ENT_QUOTES, 'UTF-8');
            echo "<div class='alert alert-$classe alert-dismissible fade show' role='alert'>
                    $msg
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                  </div>";
            unset($_SESSION["flash_$cle"]);
        }
    }
}

// ---------------------------------------------------------------
// SÉCURISATION DES SORTIES
// ---------------------------------------------------------------

/**
 * Échappe les caractères spéciaux HTML pour éviter les XSS.
 * @param mixed $valeur La valeur à sécuriser
 * @return string
 */
function e($valeur): string {
    return htmlspecialchars((string)($valeur ?? ''), ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------
// GESTION DES FICHIERS UPLOADÉS
// ---------------------------------------------------------------

/**
 * Traite l'upload d'un fichier et retourne le nom de fichier enregistré.
 * @param array  $fichier      L'entrée $_FILES['champ']
 * @param string $sousRepertoire Sous-répertoire dans assets/uploads/
 * @return string|false Nom du fichier enregistré, false en cas d'échec
 */
function traiterUpload(array $fichier, string $sousRepertoire = '') {
    if ($fichier['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Vérifier la taille
    if ($fichier['size'] > MAX_FILE_SIZE) {
        return false;
    }

    // Vérifier l'extension
    $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS, true)) {
        return false;
    }

    // Vérifier le type MIME réel
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $fichier['tmp_name']);
    finfo_close($finfo);

    $mimesAutorises = [
        'application/pdf', 'image/jpeg', 'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];

    if (!in_array($mime, $mimesAutorises, true)) {
        return false;
    }

    // Générer un nom unique
    $nomFichier = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $baseUpload = rtrim(UPLOAD_DIR, '/\\');
    $sousRepertoireNormalise = trim($sousRepertoire, '/\\');
    $repertoire = $baseUpload . ($sousRepertoireNormalise !== '' ? '/' . $sousRepertoireNormalise : '');

    if (!is_dir($repertoire) && !mkdir($repertoire, 0755, true) && !is_dir($repertoire)) {
        error_log("Impossible de créer le répertoire d'upload: " . $repertoire);
        return false;
    }

    $destination = $repertoire . '/' . $nomFichier;

    if (move_uploaded_file($fichier['tmp_name'], $destination)) {
        return ($sousRepertoireNormalise !== '' ? $sousRepertoireNormalise . '/' : '') . $nomFichier;
    }

    error_log("Echec move_uploaded_file vers: " . $destination);

    return false;
}

// ---------------------------------------------------------------
// JOURNAL D'AUDIT
// ---------------------------------------------------------------

/**
 * Enregistre une action dans le journal d'audit (immuable).
 * @param string   $action    Description de l'action
 * @param string   $table     Nom de la table concernée
 * @param int|null $recordId  ID de l'enregistrement concerné
 * @param string|null $details Détails supplémentaires (JSON)
 */
function logAudit(string $action, string $table = '', ?int $recordId = null, ?string $details = null): void {
    try {
        require_once __DIR__ . '/../models/AuditLog.php';
        $actorId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $decoded = $details !== null ? json_decode($details, true) : null;
        $metadata = [
            '_audit_version'=>1,
            'acteur'=>['nom'=>$actorId ? ($_SESSION['user_nom'] ?? null) : null, 'role'=>$actorId ? ($_SESSION['user_role'] ?? null) : null, 'departement'=>$actorId ? ($_SESSION['user_service'] ?? null) : null],
            'contexte'=>['methode'=>PHP_SAPI === 'cli' ? 'CLI' : ($_SERVER['REQUEST_METHOD'] ?? ''), 'route'=>mb_substr((string)($_GET['url'] ?? basename((string)($_SERVER['SCRIPT_NAME'] ?? ''))),0,250)],
            'evenement'=>AuditLog::nettoyer($decoded ?? $details),
        ];
        $details = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address, details, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $actorId,
            mb_substr($action,0,255),
            $table !== '' ? mb_substr($table,0,50) : null,
            $recordId,
            filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: null,
            $details
        ]);
    } catch (PDOException $e) {
        error_log("Erreur audit_log: " . $e->getMessage());
    }
}

// ---------------------------------------------------------------
// NUMÉROTATION DES COURRIERS
// ---------------------------------------------------------------

/**
 * Génère un numéro unique pour un courrier.
 * Format : AMM-AAAA-XXX (ex: AMM-2026-042)
 * @return string
 */
function genererNumeroCourrier(): string {
    $pdo  = getDB();
    $annee = date('Y');
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM courriers WHERE YEAR(created_at) = ?"
    );
    $stmt->execute([$annee]);
    $compteur = (int)$stmt->fetchColumn() + 1;
    return sprintf('AMM-%s-%03d', $annee, $compteur);
}

// ---------------------------------------------------------------
// FORMATAGE
// ---------------------------------------------------------------

/**
 * Formate une date MySQL (Y-m-d) en format lisible français.
 * @param string|null $date
 * @return string
 */
function formatDate(?string $date): string {
    if (empty($date) || $date === '0000-00-00') return '—';
    return date('d/m/Y', strtotime($date));
}

/**
 * Formate un montant avec séparateur de milliers.
 * @param float  $montant
 * @param string $devise 'USD' | 'CDF'
 * @return string
 */
function formatMontant(float $montant, string $devise = 'USD'): string {
    return number_format($montant, 2, ',', ' ') . ' ' . $devise;
}

/**
 * Retourne la classe Bootstrap correspondant à l'urgence d'un courrier.
 * @param string $urgence
 * @return string
 */
function badgeUrgence(string $urgence): string {
    switch ($urgence) {
        case 'haute':
            return 'danger';
        case 'moyenne':
            return 'warning';
        default:
            return 'secondary';
    }
}

/**
 * Retourne la classe Bootstrap correspondant au statut d'un courrier.
 * @param string $statut
 * @return string
 */
function badgeStatut(string $statut): string {
    switch ($statut) {
        case 'Recu':
            return 'info';
        case 'Assigne':
            return 'primary';
        case 'Accuse':
        case 'En_cours':
            return 'warning';
        case 'Termine':
        case 'Clos':
            return 'success';
        case 'Archive':
            return 'secondary';
        case 'Overdue':
            return 'danger';
        default:
            return 'secondary';
    }
}

/**
 * Retourne la classe Bootstrap pour le statut d'un contrat.
 */
function badgeStatutContrat(string $statut): string {
    switch ($statut) {
        case 'actif':
            return 'success';
        case 'expire':
            return 'danger';
        case 'resilie':
            return 'secondary';
        case 'renouvele':
            return 'info';
        default:
            return 'secondary';
    }
}

// ---------------------------------------------------------------
// NOTIFICATIONS
// ---------------------------------------------------------------

/**
 * Crée une notification in-app pour un utilisateur.
 * @param int    $userId    ID de l'utilisateur destinataire
 * @param string $type      Type de notification
 * @param string $message   Texte du message
 * @param string $lien      URL de redirection
 */
function creerNotification(int $userId, string $type, string $message, string $lien = ''): void {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "INSERT INTO notifications (user_id, type_notif, message, lien, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$userId, $type, $message, $lien]);
    } catch (PDOException $e) {
        error_log("Erreur notification: " . $e->getMessage());
    }
}

/**
 * Compte les notifications non lues pour l'utilisateur courant.
 * @return int
 */
function compterNotificationsNonLues(): int {
    if (empty($_SESSION['user_id'])) return 0;

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND est_lu = 0"
        );
        $stmt->execute([$_SESSION['user_id']]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// ---------------------------------------------------------------
// VÉRIFICATION DES DEADLINES (à appeler via CRON ou à chaque requête)
// ---------------------------------------------------------------

/**
 * Met à jour le statut des courriers dont la deadline est dépassée.
 */
function verifierDeadlines(): void {
    try {
        $pdo = getDB();
        $pdo->exec(
            "UPDATE courriers
             SET statut = 'Overdue'
             WHERE deadline < CURDATE()
               AND statut NOT IN ('Termine','Clos','Archive','Overdue')"
        );
    } catch (PDOException $e) {
        error_log("Erreur verifierDeadlines: " . $e->getMessage());
    }
}

/**
 * Met à jour le statut des contrats expirés.
 */
function verifierContratsExpires(): void {
    try {
        $pdo = getDB();
        $pdo->exec(
            "UPDATE contrats
             SET statut = 'expire'
             WHERE date_fin < CURDATE()
               AND statut = 'actif'"
        );
    } catch (PDOException $e) {
        error_log("Erreur verifierContratsExpires: " . $e->getMessage());
    }
}

/**
 * Detecte taches/jalons projet en retard et cree des notifications automatiques.
 * Appele au login pour limiter la charge tout en gardant un comportement automatique.
 */
function verifierAlertesProjets(): void {
    try {
        $pdo = getDB();

        // Jalons en retard non notifies.
        $stmt = $pdo->query(
            "SELECT m.id, m.project_id, m.titre, p.titre AS projet_titre, p.responsable_id
             FROM project_milestones m
             JOIN projects p ON p.id = m.project_id
             WHERE m.statut <> 'atteint'
               AND m.date_butoir < CURDATE()
               AND m.alert_sent_at IS NULL"
        );
        $jalons = $stmt->fetchAll();

        $updMilestone = $pdo->prepare("UPDATE project_milestones SET statut = 'en_retard', alert_sent_at = NOW() WHERE id = ?");
        $insNotif = $pdo->prepare(
            "INSERT INTO notifications (user_id, type_notif, message, lien, created_at)
             VALUES (?, 'echeance', ?, ?, NOW())"
        );

        foreach ($jalons as $j) {
            $updMilestone->execute([(int)$j['id']]);
            if (!empty($j['responsable_id'])) {
                $insNotif->execute([
                    (int)$j['responsable_id'],
                    "Jalon en retard: {$j['titre']} (projet {$j['projet_titre']})",
                    APP_URL . '/projets/voir/' . (int)$j['project_id']
                ]);
            }
        }

        // Taches en retard non notifiees.
        $stmt = $pdo->query(
            "SELECT t.id, t.project_id, t.assigned_to, t.titre, p.titre AS projet_titre
             FROM project_tasks t
             JOIN projects p ON p.id = t.project_id
             WHERE t.statut <> 'termine'
               AND t.deadline IS NOT NULL
               AND t.deadline < CURDATE()
               AND t.alert_sent_at IS NULL"
        );
        $taches = $stmt->fetchAll();

        $updTask = $pdo->prepare("UPDATE project_tasks SET alert_sent_at = NOW() WHERE id = ?");
        foreach ($taches as $t) {
            $updTask->execute([(int)$t['id']]);
            if (!empty($t['assigned_to'])) {
                $insNotif->execute([
                    (int)$t['assigned_to'],
                    "Tache en retard: {$t['titre']} (projet {$t['projet_titre']})",
                    APP_URL . '/projets/voir/' . (int)$t['project_id']
                ]);
            }
        }
    } catch (Throwable $e) {
        // Les tables projets peuvent ne pas encore etre migrees.
        error_log('Erreur verifierAlertesProjets: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------
// RÉPONSE JSON (pour les endpoints AJAX)
// ---------------------------------------------------------------

/**
 * Envoie une réponse JSON et termine le script.
 * @param array $data
 * @param int   $code Code HTTP
 */
function jsonReponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------
// PAGINATION SIMPLE
// ---------------------------------------------------------------

/**
 * Calcule les paramètres de pagination.
 * @param int $total       Nombre total d'enregistrements
 * @param int $parPage     Enregistrements par page
 * @param int $pageCourante Page actuelle
 * @return array
 */
function paginer(int $total, int $parPage = 20, int $pageCourante = 1): array {
    $totalPages = (int)ceil($total / $parPage);
    $pageCourante = max(1, min($pageCourante, $totalPages));
    $offset = ($pageCourante - 1) * $parPage;

    return [
        'total'        => $total,
        'par_page'     => $parPage,
        'page_courante'=> $pageCourante,
        'total_pages'  => $totalPages,
        'offset'       => $offset,
    ];
}
