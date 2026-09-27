<?php
/**
 * db.php  –  PDO singleton connection + shared small view helpers
 * Database: office_supplies | Charset: utf8mb4
 */
declare(strict_types=1);

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $host    = '127.0.0.1';
        $port    = '3306';
        $dbname  = 'office_supplies';
        $user    = 'root';
        $pass    = '';          // Change if your MySQL root has a password
        $dsn     = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            ensureDepartmentSchema($pdo);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

/**
 * ensureDepartmentSchema – departments are "soft deleted" so that deleting
 * a department never touches the transaction history (the history keeps
 * pointing at the department and can still show its name).
 * Adds departments.is_active (1 = shown in the dropdown, 0 = deleted)
 * automatically if the column does not exist yet, so an existing database
 * needs no manual migration. Costs one tiny SHOW COLUMNS per request.
 */
function ensureDepartmentSchema(PDO $pdo): void
{
    $has = $pdo->query("SHOW COLUMNS FROM `departments` LIKE 'is_active'")->fetch();
    if (!$has) {
        $pdo->exec("ALTER TABLE `departments` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1");
    }
}

/**
 * listActiveDepartments – departments that appear in the dropdown
 * (soft-deleted ones are excluded, but history still joins to them).
 */
function listActiveDepartments(PDO $pdo): array
{
    return $pdo->query('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY id')->fetchAll();
}

/**
 * formatPackNote – builds the small "1 แพ็ค / N หน่วย" caption shown
 * under an item's name wherever the item name is displayed.
 * Returns '' when the item has no pack_qty set, so callers can render
 * it unconditionally (empty string = nothing shown).
 *
 * @param int|string|null $packQty  items.pack_qty (nullable)
 * @param string          $unit     items.unit
 */
function formatPackNote($packQty, string $unit): string
{
    $packQty = (int) $packQty;
    if ($packQty < 1) {
        return '';
    }
    return '1 แพ็ค / ' . number_format($packQty) . ' ' . $unit;
}