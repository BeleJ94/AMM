<?php
/** php scripts/migrate_departments.php [--apply] ; les libellés des dossiers historiques sont conservés. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';
$pdo = getDB(); $apply = in_array('--apply', $argv, true);
$changes = [];
try {
    if (!$pdo->query("SHOW TABLES LIKE 'services'")->fetchColumn()) throw new RuntimeException('La table historique services est absente. Vérifiez la base de l’application.');
    $columns = $pdo->query('SHOW COLUMNS FROM services')->fetchAll(PDO::FETCH_UNIQUE);
    if (!isset($columns['actif'])) $changes[] = 'ALTER TABLE services ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1';
    $duplicates = $pdo->query('SELECT nom FROM services GROUP BY nom HAVING COUNT(*)>1 LIMIT 1')->fetchColumn();
    if ($duplicates !== false) throw new RuntimeException('Des noms de services sont dupliqués. Les distinguer avant la migration ; aucune fusion automatique n’est effectuée.');
    $userColumns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_UNIQUE);
    if (!isset($userColumns['service_id'])) $changes[] = 'ALTER TABLE users ADD COLUMN service_id INT NULL, ADD INDEX idx_users_department (service_id)';
    if ($userColumns['service']['Type'] !== 'varchar(100)') $changes[] = 'ALTER TABLE users MODIFY service VARCHAR(100) NULL';
    $requestColumns = $pdo->query('SHOW COLUMNS FROM treasury_fund_requests')->fetchAll(PDO::FETCH_UNIQUE);
    if (!isset($requestColumns['department'])) throw new RuntimeException('Appliquer d’abord scripts/migrate_treasury_requests.php --apply.');
    if (!isset($requestColumns['department_id'])) $changes[] = 'ALTER TABLE treasury_fund_requests ADD COLUMN department_id INT NULL, ADD INDEX idx_request_department (department_id)';
    $changes[] = "INSERT INTO services (nom,description,actif,created_at) SELECT labels.nom,'Département repris des données existantes',1,NOW() FROM (SELECT DISTINCT TRIM(service) nom FROM users WHERE TRIM(COALESCE(service,''))<>'' UNION SELECT DISTINCT TRIM(department) FROM treasury_fund_requests WHERE department_id IS NULL AND TRIM(COALESCE(department,''))<>'') labels LEFT JOIN services s ON s.nom=labels.nom WHERE s.id IS NULL";
    // L’ancien formulaire modifiait uniquement users.service : préserver ce rattachement effectif.
    $changes[] = "UPDATE users u JOIN services s ON s.nom=TRIM(u.service) SET u.service_id=s.id WHERE TRIM(COALESCE(u.service,''))<>'' AND (u.service_id IS NULL OR u.service_id<>s.id)";
    $changes[] = "UPDATE users u JOIN services s ON s.id=u.service_id SET u.service=s.nom WHERE COALESCE(u.service,'')<>s.nom";
    $changes[] = 'UPDATE treasury_fund_requests r JOIN services s ON s.nom=TRIM(r.department) SET r.department_id=s.id WHERE r.department_id IS NULL';
    $indexes = $pdo->query('SHOW INDEX FROM services')->fetchAll();
    $unique = false;
    foreach ($indexes as $index) if ($index['Column_name'] === 'nom' && (int)$index['Non_unique'] === 0) $unique = true;
    if (!$unique) $changes[] = 'ALTER TABLE services ADD UNIQUE KEY uq_services_nom (nom)';
    foreach ($changes as $sql) { if ($apply) $pdo->exec($sql); echo ($apply ? 'OK ' : 'PREVIEW ') . $sql . PHP_EOL; }
    echo $apply ? "Migration des départements terminée.\n" : "Aucune modification. Ajouter --apply pour exécuter.\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(1); }
