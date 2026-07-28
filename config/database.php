<?php
/**
 * Configuration de la base de données
 * AMM-ERP - Système de gestion documentaire
 */

// Paramètres de connexion
define('DB_HOST', 'localhost');
define('DB_NAME', 'amm_erp');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Paramètres de l'application
define('APP_NAME', 'AMM-ERP');
define('APP_URL', 'http://localhost/AMM');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'Africa/Lubumbashi');

// Répertoires de stockage
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', APP_URL . '/assets/uploads/');
define('BACKUP_DIR', __DIR__ . '/../assets/backups/');
define('LOG_DIR', __DIR__ . '/../logs/');

// Taille maximale des fichiers uploadés (10 Mo)
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

// Types de fichiers autorisés
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'doc', 'xlsx', 'xls']);

// Configuration email (pour les notifications)
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'noreply@amm.cd');
define('MAIL_PASS', '');
define('MAIL_FROM', 'AMM-ERP <noreply@amm.cd>');

/**
 * Connexion PDO à la base de données
 * Retourne une instance PDO partagée (singleton)
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // En production, logger l'erreur sans l'afficher
            error_log("Erreur de connexion BD: " . $e->getMessage());
            die(json_encode(['erreur' => 'Connexion à la base de données impossible.']));
        }
    }

    return $pdo;
}
