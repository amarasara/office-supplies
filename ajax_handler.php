<?php
/**
 * ajax_handler.php – All AJAX endpoints (JSON responses)
 * Actions: get_category_items, get_item_detail, stock_in, stock_out,
 *          update_item, add_item, delete_item, get_item_history,
 *          get_departments, add_department, delete_department
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

session_start();

require_once __DIR__ . '/db.php';

/**
 * normalizeTxnDate – accepts a user-supplied date string (from an
 * <input type="date">, e.g. "2026-09-03") and returns it as a MySQL
 * DATETIME string (midnight of that day), or null if empty/invalid
 * (caller falls back to the current date/time).
 */
function normalizeTxnDate(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') return null;

    // Plain date, e.g. "2026-09-03" (what <input type="date"> sends)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
        $dt = DateTime::createFromFormat('Y-m-d', $raw);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d') . ' 00:00:00';
        }
        return null;
    }

    // Backward-compat: accept a full date/time too, just in case
    $formats = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d H:i:s');
        }
    }
    return null;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ──────────────────────────────────────────────────────────
    // GET_CATEGORY_ITEMS – for dashboard card click
    // ──────────────────────────────────────────────────────────
    case 'get_category_items':
        $category = $_POST['category'] ?? 'all';
        $pdo = getDB();

        $sql = 'SELECT id, group_name, name, image, unit, pack_qty, current_stock, min_stock FROM items';
        if ($category === 'low') {
            $sql .= ' WHERE current_stock > 0 AND current_stock <= min_stock';
        } elseif ($category === 'out') {
            $sql .= ' WHERE current_stock = 0';
        }
        $sql .= ' ORDER BY group_name, name';

        $items = $pdo->query($sql)->fetchAll();
        echo json_encode(['items' => $items]);
        break;

    // ──────────────────────────────────────────────────────────
    // GET_ITEM_DETAIL – load single item for edit modal
    // ──────────────────────────────────────────────────────────
    case 'get_item_detail':
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId < 1) { echo json_encode(['error' => 'Invalid item ID']); break; }

        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'SELECT id, group_name, name, image, unit, pack_qty, current_stock, min_stock FROM items WHERE id = ?'
        );
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if (!$item) { echo json_encode(['error' => 'ไม่พบรายการ']); break; }
        echo json_encode(['item' => $item]);
        break;

    // ──────────────────────────────────────────────────────────
    // STOCK_IN – add quantity to current stock
    // ──────────────────────────────────────────────────────────
    case 'stock_in':
        $itemId    = (int)($_POST['item_id']   ?? 0);
        $qty       = (int)($_POST['quantity']  ?? 0);
        $requester = trim($_POST['requester']  ?? '');
        $note      = trim($_POST['note']       ?? '');
        $txnDate   = normalizeTxnDate((string)($_POST['txn_date'] ?? ''));

        if ($itemId < 1)   { echo json_encode(['error' => 'Invalid item ID']); break; }
        if ($qty < 1)      { echo json_encode(['error' => 'จำนวนต้องมากกว่า 0']); break; }
        if ($requester === '') { echo json_encode(['error' => 'กรุณาระบุผู้รับผิดชอบ']); break; }
        if (($_POST['txn_date'] ?? '') !== '' && $txnDate === null) {
            echo json_encode(['error' => 'รูปแบบวันที่ไม่ถูกต้อง']); break;
        }

        $pdo = getDB();

        // Fetch item
        $stmt = $pdo->prepare('SELECT id, current_stock, unit, pack_qty, min_stock, name, group_name, image FROM items WHERE id = ?');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item) { echo json_encode(['error' => 'ไม่พบรายการ']); break; }

        $newStock = $item['current_stock'] + $qty;

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE items SET current_stock = ? WHERE id = ?')
                ->execute([$newStock, $itemId]);

            if ($txnDate !== null) {
                $pdo->prepare(
                    'INSERT INTO transactions (item_id, type, quantity, requester, note, created_at) VALUES (?, \'STOCK_IN\', ?, ?, ?, ?)'
                )->execute([$itemId, $qty, $requester, $note, $txnDate]);
            } else {
                $pdo->prepare(
                    'INSERT INTO transactions (item_id, type, quantity, requester, note) VALUES (?, \'STOCK_IN\', ?, ?, ?)'
                )->execute([$itemId, $qty, $requester, $note]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['error' => 'บันทึกไม่สำเร็จ: ' . $e->getMessage()]);
            break;
        }

        echo json_encode([
            'success'    => true,
            'item_id'    => $itemId,
            'new_stock'  => $newStock,
            'unit'       => $item['unit'],
            'pack_qty'   => $item['pack_qty'],
            'min_stock'  => $item['min_stock'],
            'name'       => $item['name'],
            'group_name' => $item['group_name'],
            'image'      => $item['image'],
        ]);
        break;

    // ──────────────────────────────────────────────────────────
    // STOCK_OUT – deduct quantity from current stock
    // ──────────────────────────────────────────────────────────
    case 'stock_out':
        $itemId    = (int)($_POST['item_id']       ?? 0);
        $qty       = (int)($_POST['quantity']      ?? 0);
        $deptId    = (int)($_POST['department_id'] ?? 0) ?: null;
        $requester = trim($_POST['requester']      ?? '');
        $note      = trim($_POST['note']           ?? '');
        $txnDate   = normalizeTxnDate((string)($_POST['txn_date'] ?? ''));

        if ($itemId < 1)   { echo json_encode(['error' => 'Invalid item ID']); break; }
        if ($qty < 1)      { echo json_encode(['error' => 'จำนวนต้องมากกว่า 0']); break; }
        // requester (ชื่อผู้เบิก) and department are both optional – may be left empty
        if (($_POST['txn_date'] ?? '') !== '' && $txnDate === null) {
            echo json_encode(['error' => 'รูปแบบวันที่ไม่ถูกต้อง']); break;
        }

        $pdo = getDB();

        // The department, if one was chosen, must exist and must not have been deleted
        if ($deptId !== null) {
            $stmt = $pdo->prepare('SELECT id FROM departments WHERE id = ? AND is_active = 1');
            $stmt->execute([$deptId]);
            if (!$stmt->fetch()) { echo json_encode(['error' => 'ไม่พบแผนกที่เลือก (อาจถูกลบไปแล้ว)']); break; }
        }

        $stmt = $pdo->prepare('SELECT id, current_stock, unit, pack_qty, min_stock, name, group_name, image FROM items WHERE id = ?');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item) { echo json_encode(['error' => 'ไม่พบรายการ']); break; }

        if ($qty > $item['current_stock']) {
            echo json_encode(['error' => 'สต็อกไม่เพียงพอ (คงเหลือ ' . $item['current_stock'] . ' ' . $item['unit'] . ')']);
            break;
        }

        $newStock = $item['current_stock'] - $qty;

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE items SET current_stock = ? WHERE id = ?')
                ->execute([$newStock, $itemId]);

            if ($txnDate !== null) {
                $pdo->prepare(
                    'INSERT INTO transactions (item_id, type, quantity, department_id, requester, note, created_at) VALUES (?, \'STOCK_OUT\', ?, ?, ?, ?, ?)'
                )->execute([$itemId, $qty, $deptId, $requester, $note, $txnDate]);
            } else {
                $pdo->prepare(
                    'INSERT INTO transactions (item_id, type, quantity, department_id, requester, note) VALUES (?, \'STOCK_OUT\', ?, ?, ?, ?)'
                )->execute([$itemId, $qty, $deptId, $requester, $note]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['error' => 'บันทึกไม่สำเร็จ: ' . $e->getMessage()]);
            break;
        }

        echo json_encode([
            'success'    => true,
            'item_id'    => $itemId,
            'new_stock'  => $newStock,
            'unit'       => $item['unit'],
            'pack_qty'   => $item['pack_qty'],
            'min_stock'  => $item['min_stock'],
            'name'       => $item['name'],
            'group_name' => $item['group_name'],
            'image'      => $item['image'],
        ]);
        break;

    // ──────────────────────────────────────────────────────────
    // UPDATE_ITEM – edit name, group, unit, pack size, image
    // ──────────────────────────────────────────────────────────
    case 'update_item':
        $itemId    = (int)($_POST['item_id']    ?? 0);
        $name      = trim($_POST['name']        ?? '');
        $groupName = trim($_POST['group_name']  ?? '');
        $unit      = trim($_POST['unit']        ?? '');
        $packRaw   = trim($_POST['pack_qty']    ?? '');
        $packQty   = ($packRaw === '') ? null : (int) $packRaw;

        if ($itemId < 1)      { echo json_encode(['error' => 'Invalid item ID']); break; }
        if ($name === '')     { echo json_encode(['error' => 'กรุณาระบุชื่อรายการ']); break; }
        if ($groupName === '') { echo json_encode(['error' => 'กรุณาระบุชื่อกลุ่ม']); break; }
        if ($unit === '')     { echo json_encode(['error' => 'กรุณาระบุหน่วย']); break; }
        if ($packQty !== null && $packQty < 1) { echo json_encode(['error' => 'จำนวนต่อแพ็คต้องมากกว่า 0']); break; }

        $pdo = getDB();

        // Fetch current values (needed to build the "before → after" diff
        // that gets logged as an EDIT entry in the transactions history)
        $stmt = $pdo->prepare('SELECT name, group_name, unit, pack_qty, image FROM items WHERE id = ?');
        $stmt->execute([$itemId]);
        $cur = $stmt->fetch();
        if (!$cur) { echo json_encode(['error' => 'ไม่พบรายการ']); break; }

        $imageFilename = $cur['image'];
        $imageChanged  = false;

        // Handle file upload
        if (!empty($_FILES['image']['name'])) {
            $file      = $_FILES['image'];
            $maxBytes  = 2 * 1024 * 1024; // 2 MB
            $allowed   = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $finfo     = new \finfo(FILEINFO_MIME_TYPE);
            $mime      = $finfo->file($file['tmp_name']);

            if ($file['size'] > $maxBytes) {
                echo json_encode(['error' => 'ไฟล์รูปภาพต้องไม่เกิน 2MB']); break;
            }
            if (!in_array($mime, $allowed, true)) {
                echo json_encode(['error' => 'รองรับเฉพาะ JPG, PNG, WEBP, GIF']); break;
            }

            $ext           = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safeFilename  = 'item_' . $itemId . '_' . time() . '.' . strtolower($ext);
            $uploadDir     = __DIR__ . '/assets/images/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $safeFilename)) {
                echo json_encode(['error' => 'อัปโหลดรูปภาพไม่สำเร็จ']); break;
            }

            // Delete old image if not default
            if ($cur['image'] !== 'default.png' && file_exists($uploadDir . $cur['image'])) {
                @unlink($uploadDir . $cur['image']);
            }
            $imageFilename = $safeFilename;
            $imageChanged  = true;
        }

        $pdo->prepare(
            'UPDATE items SET name = ?, group_name = ?, unit = ?, pack_qty = ?, image = ? WHERE id = ?'
        )->execute([$name, $groupName, $unit, $packQty, $imageFilename, $itemId]);

        // Editing item details (name/group/unit/pack size/image) is not
        // logged to the transactions history — only STOCK_IN / STOCK_OUT
        // show up there.

        echo json_encode([
            'success'    => true,
            'item_id'    => $itemId,
            'name'       => $name,
            'group_name' => $groupName,
            'unit'       => $unit,
            'pack_qty'   => $packQty,
            'image'      => $imageFilename,
        ]);
        break;

    // ──────────────────────────────────────────────────────────
    // ADD_ITEM – create a brand-new item
    // ──────────────────────────────────────────────────────────
    case 'add_item':
        $name         = trim($_POST['name']          ?? '');
        $groupName    = trim($_POST['group_name']    ?? '');
        $unit         = trim($_POST['unit']          ?? '');
        $packRaw      = trim($_POST['pack_qty']       ?? '');
        $packQty      = ($packRaw === '') ? null : (int) $packRaw;
        $currentStock = (int)($_POST['current_stock'] ?? 0);
        $minStock     = (int)($_POST['min_stock']      ?? 5);

        if ($name === '')      { echo json_encode(['error' => 'กรุณาระบุชื่อรายการ']); break; }
        if ($groupName === '') { echo json_encode(['error' => 'กรุณาระบุชื่อกลุ่ม']); break; }
        if ($unit === '')      { echo json_encode(['error' => 'กรุณาระบุหน่วย']); break; }
        if ($packQty !== null && $packQty < 1) { echo json_encode(['error' => 'จำนวนต่อแพ็คต้องมากกว่า 0']); break; }
        if ($currentStock < 0) { echo json_encode(['error' => 'จำนวนสต็อกต้องไม่ติดลบ']); break; }
        if ($minStock < 0)     { echo json_encode(['error' => 'สต็อกขั้นต่ำต้องไม่ติดลบ']); break; }

        $pdo = getDB();

        $pdo->prepare(
            'INSERT INTO items (group_name, name, unit, pack_qty, current_stock, min_stock) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$groupName, $name, $unit, $packQty, $currentStock, $minStock]);

        $newItemId = (int) $pdo->lastInsertId();
        $imageFilename = 'default.png';

        // Handle optional image upload
        if (!empty($_FILES['image']['name'])) {
            $file      = $_FILES['image'];
            $maxBytes  = 2 * 1024 * 1024; // 2 MB
            $allowed   = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $finfo     = new \finfo(FILEINFO_MIME_TYPE);
            $mime      = $finfo->file($file['tmp_name']);

            if ($file['size'] > $maxBytes) {
                echo json_encode(['error' => 'ไฟล์รูปภาพต้องไม่เกิน 2MB']); break;
            }
            if (!in_array($mime, $allowed, true)) {
                echo json_encode(['error' => 'รองรับเฉพาะ JPG, PNG, WEBP, GIF']); break;
            }

            $ext          = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safeFilename = 'item_' . $newItemId . '_' . time() . '.' . strtolower($ext);
            $uploadDir    = __DIR__ . '/assets/images/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $uploadDir . $safeFilename)) {
                $imageFilename = $safeFilename;
                $pdo->prepare('UPDATE items SET image = ? WHERE id = ?')
                    ->execute([$imageFilename, $newItemId]);
            }
        }

        echo json_encode([
            'success'       => true,
            'item_id'       => $newItemId,
            'name'          => $name,
            'group_name'    => $groupName,
            'unit'          => $unit,
            'pack_qty'      => $packQty,
            'image'         => $imageFilename,
            'current_stock' => $currentStock,
            'min_stock'     => $minStock,
        ]);
        break;

    // ──────────────────────────────────────────────────────────
    // DELETE_ITEM – remove an item (and its transaction history)
    // ──────────────────────────────────────────────────────────
    case 'delete_item':
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId < 1) { echo json_encode(['error' => 'Invalid item ID']); break; }

        $pdo = getDB();

        $stmt = $pdo->prepare('SELECT image FROM items WHERE id = ?');
        $stmt->execute([$itemId]);
        $cur = $stmt->fetch();
        if (!$cur) { echo json_encode(['error' => 'ไม่พบรายการ']); break; }

        // transactions for this item are removed automatically
        // (fk_tx_item has ON DELETE CASCADE)
        $pdo->prepare('DELETE FROM items WHERE id = ?')->execute([$itemId]);

        // Clean up the uploaded image file, if any
        if ($cur['image'] !== 'default.png') {
            $imgPath = __DIR__ . '/assets/images/' . $cur['image'];
            if (file_exists($imgPath)) { @unlink($imgPath); }
        }

        echo json_encode(['success' => true, 'item_id' => $itemId]);
        break;

    // ──────────────────────────────────────────────────────────
    // GET_ITEM_HISTORY – item detail + full stock-in/out history
    // (used by history.php)
    // ──────────────────────────────────────────────────────────
    case 'get_item_history':
        $itemId = (int)($_POST['item_id'] ?? $_GET['item_id'] ?? 0);
        if ($itemId < 1) { echo json_encode(['error' => 'Invalid item ID']); break; }

        $pdo = getDB();

        $stmt = $pdo->prepare(
            'SELECT id, group_name, name, image, unit, pack_qty, current_stock, min_stock FROM items WHERE id = ?'
        );
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if (!$item) { echo json_encode(['error' => 'ไม่พบรายการ']); break; }

        // Only STOCK_IN / STOCK_OUT show up in the history — any leftover
        // EDIT rows (from before this was disabled) are filtered out here,
        // so the history page never shows edit history regardless of
        // whether old rows were ever cleaned out of the database.
        $stmt = $pdo->prepare(
            'SELECT t.id, t.type, t.quantity, t.requester, t.note, t.created_at,
                    d.name AS department_name
             FROM transactions t
             LEFT JOIN departments d ON d.id = t.department_id
             WHERE t.item_id = ? AND t.type <> \'EDIT\' AND t.quantity <> 0
             ORDER BY t.created_at DESC, t.id DESC'
        );
        $stmt->execute([$itemId]);
        $transactions = $stmt->fetchAll();

        echo json_encode(['item' => $item, 'transactions' => $transactions]);
        break;

    // ──────────────────────────────────────────────────────────
    // GET_DEPARTMENTS – active departments for the stock-out dropdown
    // ──────────────────────────────────────────────────────────
    case 'get_departments':
        echo json_encode(['departments' => listActiveDepartments(getDB())]);
        break;

    // ──────────────────────────────────────────────────────────
    // ADD_DEPARTMENT – create a department (or bring back a deleted
    // one that has the same name, so history stays linked to it)
    // ──────────────────────────────────────────────────────────
    case 'add_department':
        $name = trim((string)($_POST['name'] ?? ''));
        $name = (string) preg_replace('/\s+/u', ' ', $name);

        if ($name === '')            { echo json_encode(['error' => 'กรุณาระบุชื่อแผนก']); break; }
        if (mb_strlen($name) > 120)  { echo json_encode(['error' => 'ชื่อแผนกต้องไม่เกิน 120 ตัวอักษร']); break; }

        $pdo = getDB();

        // Compare in PHP (case-insensitive) against every department, including deleted ones
        $existing = null;
        foreach ($pdo->query('SELECT id, name, is_active FROM departments')->fetchAll() as $row) {
            if (mb_strtolower(trim($row['name'])) === mb_strtolower($name)) { $existing = $row; break; }
        }

        if ($existing !== null) {
            if ((int)$existing['is_active'] === 1) {
                echo json_encode(['error' => 'มีแผนก "' . $existing['name'] . '" อยู่แล้ว']); break;
            }
            $pdo->prepare('UPDATE departments SET is_active = 1 WHERE id = ?')->execute([(int)$existing['id']]);
            $deptRow = ['id' => (int)$existing['id'], 'name' => $existing['name']];
        } else {
            $pdo->prepare('INSERT INTO departments (name) VALUES (?)')->execute([$name]);
            $deptRow = ['id' => (int)$pdo->lastInsertId(), 'name' => $name];
        }

        echo json_encode([
            'success'     => true,
            'department'  => $deptRow,
            'departments' => listActiveDepartments($pdo),
        ]);
        break;

    // ──────────────────────────────────────────────────────────
    // DELETE_DEPARTMENT – soft delete: hidden from the dropdown, but the
    // row and every transaction pointing at it are left untouched, so
    // the history keeps showing the department name.
    // ──────────────────────────────────────────────────────────
    case 'delete_department':
        $deptId = (int)($_POST['department_id'] ?? 0);
        if ($deptId < 1) { echo json_encode(['error' => 'Invalid department ID']); break; }

        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id, name FROM departments WHERE id = ? AND is_active = 1');
        $stmt->execute([$deptId]);
        $dept = $stmt->fetch();
        if (!$dept) { echo json_encode(['error' => 'ไม่พบแผนก']); break; }

        $pdo->prepare('UPDATE departments SET is_active = 0 WHERE id = ?')->execute([$deptId]);

        echo json_encode([
            'success'       => true,
            'department_id' => $deptId,
            'name'          => $dept['name'],
            'departments'   => listActiveDepartments($pdo),
        ]);
        break;

    // ──────────────────────────────────────────────────────────
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
        break;
}