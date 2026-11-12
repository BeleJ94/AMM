<?php
/**
 * Réinitialisation des données d'AMM-ERP, en conservant les utilisateurs.
 *
 * CONSERVÉ (toujours) : utilisateurs, rôles, départements (services), référentiels
 *   (types de documents, niveaux d'urgence).
 * CONSERVÉ (par défaut) : paramétrage financier (comptes, catégories, paramètres trésorerie, taux de change).
 *   Option --avec-parametrage-financier : le vide aussi.
 * SUPPRIMÉ : toutes les autres tables (courriers, documents, contrats, projets, finance, trésorerie,
 *   notifications, journal d'audit…), compteurs remis à 1, et fichiers téléversés (assets/uploads).
 *   Option --garder-fichiers : ne touche pas aux fichiers.
 *
 * Aperçu (aucune modification) : php scripts/reinitialiser_donnees.php
 * Exécution                    : php scripts/reinitialiser_donnees.php --apply
 *   -> une sauvegarde complète (mysqldump) est faite d'abord dans assets/backups/ ;
 *      si mysqldump est introuvable, le script s'arrête (forcer avec --sans-sauvegarde).
 *   -> la confirmation « REINITIALISER » doit être saisie au clavier.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI uniquement.'); }
require_once __DIR__ . '/../config/database.php';

$apply = in_array('--apply', $argv, true);
$viderParametrage = in_array('--avec-parametrage-financier', $argv, true);
$garderFichiers = in_array('--garder-fichiers', $argv, true);
$sansSauvegarde = in_array('--sans-sauvegarde', $argv, true);

$conservees = ['users', 'roles', 'services', 'document_types', 'urgency_levels'];
$parametrageFinancier = ['financial_accounts', 'financial_categories', 'treasury_settings', 'exchange_rates'];
if (!$viderParametrage) { $conservees = array_merge($conservees, $parametrageFinancier); }

$pdo = getDB();
$tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")
              ->fetchAll(PDO::FETCH_COLUMN);
$aVider = array_values(array_diff($tables, $conservees));

echo "Base : " . DB_NAME . "\n\nTables conservées :\n";
foreach (array_intersect($tables, $conservees) as $t) {
    printf("  = %-34s %6d ligne(s)\n", $t, (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn());
}
echo "\nTables vidées :\n";
foreach ($aVider as $t) {
    printf("  - %-34s %6d ligne(s)\n", $t, (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn());
}

// Fichiers téléversés (on garde les fichiers de protection du dossier).
$uploadDir = realpath(UPLOAD_DIR);
$fichiers = [];
if (!$garderFichiers && $uploadDir && is_dir($uploadDir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        if ($f->isFile() && !in_array($f->getFilename(), ['.htaccess', 'index.php', 'index.html', '.gitkeep'], true)) { $fichiers[] = $f->getPathname(); }
    }
}
echo "\nFichiers téléversés à supprimer : " . ($garderFichiers ? 'aucun (--garder-fichiers)' : count($fichiers)) . "\n";

if (!$apply) {
    echo "\nAPERÇU UNIQUEMENT. Ajouter --apply pour exécuter.\n";
    exit(0);
}

echo "\nATTENTION : opération irréversible. Tapez REINITIALISER pour confirmer : ";
if (trim((string)fgets(STDIN)) !== 'REINITIALISER') { echo "Annulé.\n"; exit(1); }

// 1. Sauvegarde complète préalable.
if (!$sansSauvegarde) {
    if (!is_dir(BACKUP_DIR)) { @mkdir(BACKUP_DIR, 0775, true); }
    $fichierSauvegarde = rtrim(BACKUP_DIR, '/\\') . '/avant_reinitialisation_' . date('Ymd_His') . '.sql';
    $candidats = ['mysqldump', 'C:/xampp/mysql/bin/mysqldump.exe', '/usr/bin/mysqldump'];
    $ok = false;
    foreach ($candidats as $bin) {
        $cmd = escapeshellarg($bin) . ' --single-transaction --routines -h ' . escapeshellarg(DB_HOST)
             . ' -u ' . escapeshellarg(DB_USER)
             . (defined('DB_PASS') && DB_PASS !== '' ? ' -p' . escapeshellarg(DB_PASS) : '')
             . ' ' . escapeshellarg(DB_NAME) . ' > ' . escapeshellarg($fichierSauvegarde) . ' 2>&1';
        exec($cmd, $sortie, $code);
        if ($code === 0 && is_file($fichierSauvegarde) && filesize($fichierSauvegarde) > 1000) { $ok = true; break; }
    }
    if (!$ok) {
        @unlink($fichierSauvegarde);
        echo "ÉCHEC de la sauvegarde (mysqldump introuvable ou en erreur). Rien n'a été modifié.\n"
           . "Faites une sauvegarde manuelle puis relancez avec --sans-sauvegarde.\n";
        exit(1);
    }
    echo "Sauvegarde : $fichierSauvegarde\n";
}

// 2. Vidage des tables (TRUNCATE remet aussi les compteurs AUTO_INCREMENT à 1).
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
try {
    foreach ($aVider as $t) {
        $pdo->exec("TRUNCATE TABLE `$t`");
        echo "  vidée : $t\n";
    }
} finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// 3. Fichiers téléversés.
$supprimes = 0;
foreach ($fichiers as $f) { if (@unlink($f)) { $supprimes++; } }
if (!$garderFichiers) { echo "Fichiers supprimés : $supprimes / " . count($fichiers) . "\n"; }

echo "\nRéinitialisation terminée. Les utilisateurs et leurs mots de passe sont inchangés.\n";
