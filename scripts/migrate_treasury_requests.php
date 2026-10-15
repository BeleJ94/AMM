<?php
/** Additive migration. Preview: php scripts/migrate_treasury_requests.php
 * Apply locally: php scripts/migrate_treasury_requests.php --apply
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI uniquement.'); }
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();
$apply = in_array('--apply', $argv, true);
$changes = [];
$columns = $pdo->query('SHOW COLUMNS FROM treasury_fund_requests')->fetchAll(PDO::FETCH_UNIQUE);
$definitions = [
    'department' => 'VARCHAR(100) NULL',
    'requester_name' => 'VARCHAR(150) NULL',
    'approved_amount' => 'DECIMAL(15,2) NULL',
    'approved_amount_usd' => 'DECIMAL(15,2) NULL',
    'controlled_by' => 'INT NULL',
    'controlled_at' => 'DATETIME NULL',
    'suspended_level' => 'VARCHAR(40) NULL',
    'previous_status' => 'VARCHAR(40) NULL',
    'reminder_date' => 'DATE NULL',
];
foreach ($definitions as $column => $definition) {
    if (!isset($columns[$column])) { $changes[] = "ALTER TABLE treasury_fund_requests ADD COLUMN `$column` $definition"; }
}
function requestMigrationEnum(PDO $pdo, string $table, string $column, array $newValues, string $default = ''): ?string {
    $type = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetch()['Type'];
    preg_match_all("/'([^']*)'/", $type, $matches);
    $values = array_values(array_unique(array_merge($matches[1], $newValues)));
    if ($values === $matches[1]) { return null; }
    $list = implode(',', array_map([$pdo, 'quote'], $values));
    return "ALTER TABLE `$table` MODIFY `$column` ENUM($list) NOT NULL" . ($default !== '' ? ' DEFAULT ' . $pdo->quote($default) : '');
}
foreach ([
    requestMigrationEnum($pdo, 'treasury_fund_requests', 'status', ['a_controler','en_suspens'], 'brouillon'),
    requestMigrationEnum($pdo, 'treasury_request_approvals', 'approval_level', ['tresorier']),
    requestMigrationEnum($pdo, 'treasury_request_approvals', 'decision', ['annule','en_suspens','repris']),
] as $sql) { if ($sql !== null) { $changes[] = $sql; } }
$approvalColumns = $pdo->query('SHOW COLUMNS FROM treasury_request_approvals')->fetchAll(PDO::FETCH_UNIQUE);
foreach (['approved_amount'=>'DECIMAL(15,2) NULL','approved_amount_usd'=>'DECIMAL(15,2) NULL','reminder_date'=>'DATE NULL'] as $column => $definition) {
    if (!isset($approvalColumns[$column])) { $changes[] = "ALTER TABLE treasury_request_approvals ADD COLUMN `$column` $definition"; }
}
$paymentColumns = $pdo->query('SHOW COLUMNS FROM treasury_payments')->fetchAll(PDO::FETCH_UNIQUE);
foreach (['cancelled_by'=>'INT NULL','cancelled_at'=>'DATETIME NULL','cancellation_reason'=>'TEXT NULL'] as $column => $definition) {
    if (!isset($paymentColumns[$column])) { $changes[] = "ALTER TABLE treasury_payments ADD COLUMN `$column` $definition"; }
}
$indexes = $pdo->query('SHOW INDEX FROM treasury_request_approvals')->fetchAll();
foreach ($indexes as $index) {
    if ($index['Key_name'] === 'uq_treasury_request_level_decision') {
        // Decisions are append-only events: repeated suspension/resumption must remain in history.
        $changes[] = 'ALTER TABLE treasury_request_approvals DROP INDEX uq_treasury_request_level_decision'; break;
    }
}
$changes[] = "CREATE TABLE IF NOT EXISTS treasury_request_items (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    position INT NOT NULL DEFAULT 1,
    description VARCHAR(500) NOT NULL,
    quantity DECIMAL(12,3) NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    line_total DECIMAL(15,2) NOT NULL,
    INDEX idx_request_items (request_id, position),
    CONSTRAINT fk_request_items_request FOREIGN KEY (request_id) REFERENCES treasury_fund_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$changes[] = "CREATE TABLE IF NOT EXISTS treasury_request_reminders (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    approval_level VARCHAR(40) NOT NULL,
    reminder_day DATE NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_request_reminder_day (request_id, approval_level, reminder_day, user_id),
    CONSTRAINT fk_request_reminders_request FOREIGN KEY (request_id) REFERENCES treasury_fund_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_request_reminders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
foreach ($changes as $sql) {
    if ($apply) { $pdo->exec($sql); }
    echo ($apply ? 'OK ' : 'PREVIEW ') . preg_replace('/\s+/', ' ', $sql) . PHP_EOL;
}
echo $apply ? "Migration terminée. Les demandes existantes et leurs validations sont conservées.\n" : "Aucune modification. Ajouter --apply pour appliquer.\n";
