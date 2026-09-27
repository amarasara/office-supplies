<?php
/**
 * items.php – Grouped inventory table with search, sort, edit modal
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

session_start();

require_once __DIR__ . '/db.php';
$pdo = getDB();

// ── Fetch departments for modal ────────────────────────────────
$departments = listActiveDepartments($pdo);   // deleted (soft-deleted) departments are excluded

// ── Fetch all items grouped ────────────────────────────────────
//    We'll let JS handle live search/sort; PHP serves initial data
$allItems = $pdo->query(
    'SELECT id, group_name, name, image, unit, pack_qty, current_stock, min_stock
     FROM items
     ORDER BY group_name, name'
)->fetchAll();

// ── Total requisitioned (STOCK_OUT) quantity per item, used by the
//    "เรียงบ่อยเบิก" sort options in the toolbar dropdown ─────────
$usageMap = [];
foreach ($pdo->query(
    "SELECT item_id, SUM(quantity) AS total_out
     FROM transactions
     WHERE type = 'STOCK_OUT'
     GROUP BY item_id"
)->fetchAll() as $row) {
    $usageMap[(int) $row['item_id']] = (int) $row['total_out'];
}
foreach ($allItems as &$item) {
    $item['total_out'] = $usageMap[(int) $item['id']] ?? 0;
}
unset($item);

// Build group structure (used for grouping rows by category, not image display)
$groups = [];
foreach ($allItems as $item) {
    $groups[$item['group_name']][] = $item;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>รายการอุปกรณ์ – Office Supplies</title>
  <meta name="description" content="รายการวัสดุสำนักงานทั้งหมด พร้อมสถานะสต็อกและการจัดการ">
  <link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . '/style.css') ?: time() ?>">
  <style>
    td.img-cell { border-right: 1.5px solid var(--border); }
    #itemsTable { table-layout: fixed; }

    #itemsTable th, #itemsTable td { overflow-wrap: break-word; }
    /* Rounded "card" corners — every row rounds its own corners independently */
    #itemsTable td.img-cell {
      background: var(--surface, #fff);
      border-top-left-radius: var(--radius-lg);
      border-bottom-left-radius: var(--radius-lg);
      overflow: hidden;
    }
    #itemsTable tbody tr td:last-child {
      background: var(--surface, #fff);
      border-top-right-radius: var(--radius-lg);
      border-bottom-right-radius: var(--radius-lg);
    }
    /* Full-width divider between different item groups (image column
       through the action buttons), so a category change is easy to spot. */
    #itemsTable .row-group-end td { border-bottom: 2px solid var(--border); }
    /* Department manager dialog (add / delete departments) */
    .dept-modal { max-width: 460px; }
    .dept-add { display: flex; gap: 10px; margin-bottom: 16px; }
    .dept-add input[type="text"] {
      flex: 1; min-width: 0;
      padding: 10px 14px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      font-family: 'Sarabun', sans-serif; font-size: .93rem;
    }
    .dept-add input[type="text"]:focus {
      outline: none; border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(245,79,27,.18);
    }
    .dept-add .btn { flex: 0 0 auto; white-space: nowrap; }
    .dept-list {
      max-height: 300px; overflow-y: auto;
      border: 1.5px solid var(--border); border-radius: var(--radius-md);
      margin-bottom: 10px;
    }
    .dept-item {
      display: flex; align-items: center; justify-content: space-between; gap: 10px;
      padding: 9px 14px; border-bottom: 1px solid var(--border);
    }
    .dept-item:last-child { border-bottom: none; }
    .dept-item__name { font-weight: 600; color: var(--dark); overflow-wrap: anywhere; }
    .dept-item__del {
      flex: 0 0 auto; padding: 5px 12px;
      background: #fff; color: #cc2d3a;
      border: 1.5px solid #cc2d3a; border-radius: var(--radius-sm);
      font-family: 'Sarabun', sans-serif; font-size: .82rem; font-weight: 600;
      cursor: pointer;
      transition: background var(--transition), color var(--transition);
    }
    .dept-item__del:hover { background: #cc2d3a; color: #fff; }
    .dept-empty { padding: 18px; text-align: center; color: var(--text-muted); }

    /* File input styling */
    input[type="file"] {
      font-family: 'Sarabun', sans-serif;
      font-size: .85rem; padding: 6px;
      border: 1.5px dashed var(--border);
      border-radius: var(--radius-sm);
      width: 100%; cursor: pointer;
      background: #f8f6f0;
    }
    input[type="file"]:hover { border-color: var(--accent); }
    /* Status column: badge centered under "สถานะ" header */
    .col-status {
      text-align: center;
    }
    /* Actions column: edit/delete buttons, no header label */
    .col-actions {
      text-align: center;
    }
    .col-stock, .col-unit { text-align: center; }
    #itemsTable td.col-stock, #itemsTable td.col-unit { text-align: center; }
    .col-actions .action-buttons {
      display: flex; align-items: center; justify-content: center; gap: 0;
    }
    .btn-delete:hover { background: var(--out-stock-bg) !important; }

    /* Image lightbox — click a thumbnail to see it full-screen; click
       anywhere outside the picture (the dark overlay) to close it.
       Defined here (not just in style.css) so it always works even if
       style.css on the server is an older copy. */
    .item-thumb { cursor: zoom-in; }
    .lightbox-backdrop {
      display: none;
      position: fixed; inset: 0; z-index: 99999;
      background: rgba(10, 10, 15, .92);
      align-items: center; justify-content: center;
      padding: 20px;
    }
    .lightbox-backdrop.open { display: flex; }
    .lightbox-backdrop__img {
      width: 96vw;
      height: 92vh;
      object-fit: contain;
      border-radius: 8px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, .6);
    }
    .lightbox-backdrop__close {
      position: fixed; top: 18px; right: 22px;
      width: 44px; height: 44px; border-radius: 50%;
      border: none; background: rgba(255, 255, 255, .18); color: #fff;
      font-size: 1.5rem; line-height: 1; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
    }
    .lightbox-backdrop__close:hover { background: rgba(255, 255, 255, .32); }
  </style>
</head>
<body>

<!-- ── NAVBAR ──────────────────────────────────────────────── -->
<nav class="navbar" role="navigation" aria-label="เมนูหลัก">
  <div class="navbar__brand">
    <span class="navbar__star-wrap">
      <img src="assets/images/icons/icon-star.png" alt="">
    </span>
    Office Supplies
  </div>
  <div class="navbar__menu">
    <div class="navbar__nav">
      <a href="dashboard.php">แดชบอร์ด</a>
      <a href="items.php" class="active" aria-current="page">รายการอุปกรณ์</a>
      <a href="history.php">ประวัติการเบิก</a>
    </div>
    <div class="navbar__right">
      <img src="assets/images/icons/icon-folder.png" alt="" class="navbar__icon--folder">
      <img src="assets/images/icons/icon-sparkles.png" alt="" class="navbar__icon--sparkles">
    </div>
  </div>
</nav>

<!-- ── PAGE ──────────────────────────────────────────────── -->
<main class="page-wrapper">
  <div class="page-header">
    <h1>รายการอุปกรณ์</h1>
    <p>จัดการสต็อก เติม และเบิกวัสดุสำนักงาน</p>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <div class="search-wrap">
      <span class="search-icon"><img src="assets/images/icons/search-icon.png" alt="" class="search-icon__img"></span>
      <input type="text" id="searchInput" placeholder="ค้นหาชื่อหรือกลุ่มรายการ..."
             aria-label="ค้นหาอุปกรณ์" oninput="currentPage = 1; filterTable();">
    </div>
    <div class="select-wrap">
      <select id="sortSelect" aria-label="เรียงลำดับ" onchange="currentPage = 1; filterTable();">
        <option value="name_asc">เรียงชื่อ ก → ฮ</option>
        <option value="name_desc">เรียงชื่อ ฮ → ก</option>
        <option value="stock_asc">สต็อกน้อย → มาก</option>
        <option value="stock_desc">สต็อกมาก → น้อย</option>
        <option value="usage_desc" selected>เบิกบ่อยมาก → น้อย</option>
        <option value="usage_asc">เบิกบ่อยน้อย → มาก</option>
      </select>
    </div>
    <button type="button" class="btn btn-primary" onclick="openAddModal()"><img src="assets/images/icons/icon-add.png" alt="" class="icon-badge icon-badge--toolbar"> เพิ่มรายการใหม่</button>
  </div>

  <!-- Inventory Table -->
  <div class="panel">
    <div class="tbl-wrap">
      <table id="itemsTable">
        <thead>
          <tr>
            <th style="width:8%;">รูปภาพ</th>
            <th style="width:46%;">รายการ</th>
            <th class="col-stock" style="width:10%;">สต็อก</th>
            <th class="col-unit" style="width:9%;">หน่วย</th>
            <th class="col-status" style="width:16%;">สถานะ</th>
            <th class="col-actions" style="width:11%;"></th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <?php
          // ── Render grouped rows, one image per ITEM (not per group) ──
          foreach ($groups as $groupName => $items):
            $count = count($items);
            $first = true;
            $lastIdx = $count - 1;
            foreach ($items as $idx => $item):
              $st  = (int)$item['current_stock'];
              $min = (int)$item['min_stock'];
              if ($st === 0) {
                  $badgeClass = 'badge-out-stock'; $badgeText = 'หมดแล้ว';
                  $stockClass = 'zero';
              } elseif ($st <= $min) {
                  $badgeClass = 'badge-low-stock'; $badgeText = 'ใกล้หมด';
                  $stockClass = 'low';
              } else {
                  $badgeClass = 'badge-in-stock'; $badgeText = 'มีสต็อก';
                  $stockClass = '';
              }
              $imageSrc = 'assets/images/' . htmlspecialchars($item['image']);
              $rowClass  = trim(($first ? 'row-group-start ' : '') . ($idx === $lastIdx ? 'row-group-end' : ''));
          ?>
          <tr class="<?= $rowClass ?>"
              data-group="<?= htmlspecialchars($groupName) ?>"
              data-name="<?= htmlspecialchars($item['name']) ?>"
              data-stock="<?= $st ?>"
              style="cursor:pointer;"
              onclick="openModal(<?= (int)$item['id'] ?>)">
            <td class="img-cell">
              <img src="<?= $imageSrc ?>"
                   alt="<?= htmlspecialchars($item['name']) ?>"
                   class="item-thumb"
                   onclick="event.stopPropagation(); openImageLightbox(this.src, this.alt)"
                   onerror="this.src='assets/images/default.png'">
            </td>
            <td>
              <?= htmlspecialchars($item['name']) ?>
              <?php $packNote = formatPackNote($item['pack_qty'], $item['unit']); ?>
              <?php if ($packNote !== ''): ?>
                <small class="pack-note"><?= htmlspecialchars($packNote) ?></small>
              <?php endif; ?>
            </td>
            <td class="col-stock"><span class="stock-num <?= $stockClass ?>"><?= $st ?></span></td>
            <td class="col-unit"><?= htmlspecialchars($item['unit']) ?></td>
            <td class="col-status">
              <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>
            </td>
            <td class="col-actions">
              <div class="action-buttons">
                <button type="button" class="btn-edit" title="เบิก / เติมสต็อก / แก้ไขข้อมูล"
                        onclick="event.stopPropagation(); openModal(<?= (int)$item['id'] ?>)"><img src="assets/images/icons/pen-icon.png" alt="" class="btn-icon-img"></button>
                <button type="button" class="btn-edit btn-delete" title="ลบรายการ"
                        onclick="event.stopPropagation(); deleteItem(<?= (int)$item['id'] ?>, '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>')"><img src="assets/images/icons/trash-icon.png" alt="" class="btn-icon-img"></button>
              </div>
            </td>
          </tr>
          <?php
              $first = false;
            endforeach;
          ?>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Empty state (shown via JS) -->
      <div id="emptyState" class="empty-state" style="display:none;">
        <div class="es-icon">🔍</div>
        <p>ไม่พบรายการที่ค้นหา</p>
      </div>

      <!-- Pagination -->
      <div id="itemsPagination" class="pagination"></div>
    </div>
  </div><!-- /.panel -->
</main>

<!-- ── EDIT / STOCK MODAL ────────────────────────────────────── -->
<div class="modal-backdrop" id="itemModalBackdrop" onclick="if(event.target===this) closeModal();">
  <div class="item-modal" role="dialog" aria-modal="true" aria-labelledby="modalItemName">

    <button type="button" class="item-modal__close" onclick="closeModal()" aria-label="ยกเลิก / ปิด">✕</button>

    <div class="item-modal__inner">

    <!-- Image box + info box -->
    <div class="item-modal__top">
      <div class="item-modal__imgbox" onclick="document.getElementById('edImage').click()" tabindex="0"
           role="button" aria-label="เปลี่ยนรูปภาพ"
           onkeydown="if(event.key===' '){event.preventDefault();document.getElementById('edImage').click();}else if(event.key==='Enter'){event.preventDefault();document.getElementById('editDetailsForm').requestSubmit();}">
        <img id="edPreview" class="item-modal__img" src="assets/images/default.png" alt="รูปภาพรายการ">
        <div class="item-modal__imgHint">🖼️ รูปภาพ<br><small>คลิกเพื่อเพิ่ม/เปลี่ยนรูป</small></div>
      </div>
      <input type="file" id="edImage" name="image" form="editDetailsForm"
             accept="image/jpeg,image/png,image/webp,image/gif" style="display:none" onchange="previewImage(this)">

      <div class="item-modal__infobox">
        <div class="item-modal__name" id="modalItemName">ชื่อรายการ</div>
        <div class="item-modal__stockline">
          สต็อกปัจจุบัน <b id="modalStockVal">0</b> <span id="modalStockUnit"></span>
        </div>
        <div class="item-modal__statusrow">
          <span class="item-modal__packnote" id="modalPackNote"></span>
          <span id="modalCurrentBadge"></span>
        </div>
      </div>
    </div>

    <!-- Prev / tabs / Next -->
    <div class="item-modal__navrow">
      <button type="button" class="item-modal__navbtn" onclick="navigateModalItem(-1)" aria-label="รายการก่อนหน้า">‹</button>

      <div class="modal-tabs">
        <button type="button" class="modal-tab-btn edit-tab active" onclick="switchModalTab('edit')">แก้ไขข้อมูล</button>
        <button type="button" class="modal-tab-btn stock-in-tab" onclick="switchModalTab('stock-in')">เติมสต็อก</button>
        <button type="button" class="modal-tab-btn stock-out-tab" onclick="switchModalTab('stock-out')">เบิกอุปกรณ์</button>
      </div>

      <button type="button" class="item-modal__navbtn" onclick="navigateModalItem(1)" aria-label="รายการถัดไป">›</button>
    </div>

    <div class="item-modal__body">

      <!-- Tab: Edit Details -->
      <div class="modal-tab-content active" id="tab-edit">
        <form id="editDetailsForm" onsubmit="submitEditDetails(event)" enctype="multipart/form-data">
          <input type="hidden" name="item_id" id="edItemId">
          <div class="form-group">
            <label for="edGroup">ชื่อกลุ่ม</label>
            <input type="text" id="edGroup" name="group_name" required>
          </div>
          <div class="form-group">
            <label for="edName">ชื่อรายการ</label>
            <input type="text" id="edName" name="name" required>
          </div>
          <div class="form-group">
            <label for="edUnit">หน่วย</label>
            <input type="text" id="edUnit" name="unit" required>
          </div>
          <div class="form-group">
            <label for="edPackQty">จำนวนต่อแพ็ค (ถ้ามี)</label>
            <input type="number" id="edPackQty" name="pack_qty" min="1" placeholder="เช่น 12 (ไม่บังคับ)">
            <div class="hint">แสดงเป็น "1 แพ็ค / N หน่วย" ใต้ชื่อรายการ เว้นว่างได้ถ้ารายการนี้ไม่มีแพ็ค</div>
          </div>
          <button type="submit" class="btn item-modal__submit" style="width:100%;">💾 บันทึกการแก้ไข</button>
        </form>
      </div>

      <!-- Tab: Stock In -->
      <div class="modal-tab-content" id="tab-stock-in">
        <form id="stockInForm" onsubmit="submitStockIn(event)">
          <input type="hidden" name="item_id" id="siItemId">
          <div class="form-group">
            <label for="siQty">จำนวนที่เติม</label>
            <div class="qty-slider-row">
              <input type="number" id="siQtyNum" class="qty-slider-num" min="1" value="1" oninput="onSiQtyNumInput()">
              <input type="range" id="siQty" name="quantity" min="1" max="1000" value="1" oninput="onSiQtySliderInput()">
              <span class="qty-slider-max" id="siMaxLabel">1000</span>
            </div>
          </div>
          <div class="item-modal__formgrid">
            <div class="qf-col">
              <div class="qf-row">
                <label for="siRequester">ผู้เติม</label>
                <input type="text" id="siRequester" name="requester" required placeholder="ชื่อผู้รับผิดชอบ">
              </div>
              <div class="qf-row">
                <label for="siDate">วันที่เติม</label>
                <input type="date" id="siDate" name="txn_date" required>
              </div>
            </div>
            <div class="qf-note-col">
              <label for="siNote">หมายเหตุ:</label>
              <textarea id="siNote" name="note" rows="4" placeholder="หมายเหตุเพิ่มเติม"></textarea>
            </div>
          </div>
          <button type="submit" class="btn item-modal__submit" style="width:100%;">💾 บันทึกการเติมสต็อก</button>
        </form>
      </div>

      <!-- Tab: Stock Out -->
      <div class="modal-tab-content" id="tab-stock-out">
        <form id="stockOutForm" onsubmit="submitStockOut(event)">
          <input type="hidden" name="item_id" id="soItemId">
          <div class="form-group">
            <label>จำนวนการเบิก</label>
            <div class="qty-slider-row">
              <input type="number" id="soQtyNum" class="qty-slider-num" min="1" max="1" value="1" oninput="onQtyNumInput()">
              <input type="range" id="soQty" name="quantity" min="1" max="1" value="1" oninput="onQtySliderInput()">
              <span class="qty-slider-max" id="soMaxLabel">0</span>
            </div>
          </div>
          <div class="item-modal__formgrid">
            <div class="qf-col">
              <div class="qf-row">
                <label for="soRequester">ชื่อผู้เบิก</label>
                <input type="text" id="soRequester" name="requester" placeholder="ชื่อผู้เบิก (ไม่บังคับ)">
              </div>
              <div class="qf-row">
                <label for="soDept">แผนก <small style="font-weight:400; color: var(--text-muted);">(ไม่บังคับ)</small></label>
                <select id="soDept" name="department_id" onchange="onDeptChange(this)">
                  <!-- options are built by renderDeptSelect() (see DEPARTMENTS in the script) -->
                  <option value="">-- เลือกแผนก --</option>
                </select>
              </div>
              <div class="qf-row">
                <label for="soDate">วันที่เบิก</label>
                <input type="date" id="soDate" name="txn_date" required>
              </div>
            </div>
            <div class="qf-note-col">
              <label for="soNote">หมายเหตุ:</label>
              <textarea id="soNote" name="note" rows="4" placeholder="หมายเหตุเพิ่มเติม"></textarea>
            </div>
          </div>
          <button type="submit" class="btn item-modal__submit" style="width:100%;">💾 บันทึกการเบิกอุปกรณ์</button>
        </form>
      </div>

    </div><!-- /.item-modal__body -->
    </div><!-- /.item-modal__inner -->
  </div><!-- /.item-modal -->
</div><!-- /.modal-backdrop -->

<!-- ── DELETE CONFIRM DIALOG (retro) ───────────────────────────── -->
<div class="retro-modal-backdrop" id="deleteConfirmBackdrop" onclick="if(event.target===this) cancelDeleteConfirm();">
  <div class="retro-modal" role="alertdialog" aria-modal="true" aria-labelledby="deleteConfirmTitle">
    <div class="retro-modal__titlebar">
      <span class="retro-modal__title" id="deleteConfirmTitle">DELETE</span>
      <button type="button" class="retro-modal__close" onclick="cancelDeleteConfirm()" aria-label="ยกเลิก">✕</button>
    </div>
    <div class="retro-modal__body">
      <p class="retro-modal__text" id="deleteConfirmText"></p>
      <button type="button" class="retro-modal__yes" id="deleteConfirmYesBtn" onclick="confirmDeleteYes()">YES</button>
    </div>
  </div>
</div>

<!-- ── ADD ITEM MODAL ──────────────────────────────────────────── -->
<div class="modal-backdrop" id="addModalBackdrop" onclick="if(event.target===this) closeAddModal();">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="addModalTitle">
    <div class="modal__header">
      <div class="modal__title" id="addModalTitle">
        <img src="assets/images/icons/icon-add.png" alt="" class="icon-badge icon-badge--modal">
        <span>เพิ่มรายการใหม่</span>
      </div>
      <button type="button" class="modal__close" onclick="closeAddModal()" aria-label="ปิด">✕</button>
    </div>

    <div class="modal__body">
      <form id="addItemForm" onsubmit="submitAddItem(event)" enctype="multipart/form-data">
        <div class="form-group">
          <label>รูปภาพ (ถ้ามี)</label>
          <div class="img-preview-wrap">
            <img id="addPreview" class="img-preview" src="assets/images/default.png" alt="preview">
            <input type="file" id="addImage" name="image" accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewAddImage(this)">
          </div>
          <div class="hint">รองรับ JPG, PNG, WEBP, GIF ขนาดไม่เกิน 2MB (ไม่เลือกไฟล์ = ใช้รูป default)</div>
        </div>
        <div class="form-group">
          <label for="addGroup">ชื่อกลุ่ม</label>
          <input type="text" id="addGroup" name="group_name" required placeholder="เช่น ปากกา, กระดาษ A4" list="groupNameList">
          <datalist id="groupNameList"></datalist>
        </div>
        <div class="form-group">
          <label for="addName">ชื่อรายการ</label>
          <input type="text" id="addName" name="name" required placeholder="ชื่อเต็มของรายการ">
        </div>
        <div class="form-group">
          <label for="addUnit">หน่วย</label>
          <input type="text" id="addUnit" name="unit" required placeholder="เช่น ด้าม, ชิ้น, รีม">
        </div>
        <div class="form-group">
          <label for="addPackQty">จำนวนต่อแพ็ค (ถ้ามี)</label>
          <input type="number" id="addPackQty" name="pack_qty" min="1" placeholder="เช่น 12 (ไม่บังคับ)">
          <div class="hint">แสดงเป็น "1 แพ็ค / N หน่วย" ใต้ชื่อรายการ เว้นว่างได้ถ้ารายการนี้ไม่มีแพ็ค</div>
        </div>
        <div class="form-group">
          <label for="addStock">จำนวนสต็อกเริ่มต้น</label>
          <input type="number" id="addStock" name="current_stock" min="0" value="0" required>
        </div>
        <div class="form-group">
          <label for="addMinStock">สต็อกขั้นต่ำ (สำหรับแจ้งเตือนใกล้หมด)</label>
          <input type="number" id="addMinStock" name="min_stock" min="0" value="5" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">💾 เพิ่มรายการ</button>
      </form>
    </div>
  </div>
</div>

<!-- ── DEPARTMENT MANAGER MODAL (add / delete departments) ──────── -->
<div class="modal-backdrop" id="deptModalBackdrop" onclick="if(event.target===this) closeDeptModal();">
  <div class="modal dept-modal" role="dialog" aria-modal="true" aria-labelledby="deptModalTitle">
    <div class="modal__header">
      <div class="modal__title" id="deptModalTitle"><span>🏢 จัดการแผนก</span></div>
      <button type="button" class="modal__close" onclick="closeDeptModal()" aria-label="ปิด">✕</button>
    </div>
    <div class="modal__body">
      <form id="deptAddForm" class="dept-add" onsubmit="submitAddDept(event)">
        <input type="text" id="deptNewName" maxlength="120" placeholder="ชื่อแผนกใหม่" autocomplete="off" required>
        <button type="submit" class="btn btn-primary">➕ เพิ่ม</button>
      </form>
      <div class="dept-list" id="deptList"></div>
      <div class="form-group" style="margin-bottom:0;">
        <div class="hint">การลบแผนกจะไม่ลบประวัติการเบิกที่เคยบันทึกไว้</div>
      </div>
    </div>
  </div>
</div>

<!-- ── IMAGE LIGHTBOX (click any item thumbnail to see it enlarged) ─ -->
<div class="lightbox-backdrop" id="imgLightboxBackdrop" onclick="if(event.target===this) closeImageLightbox();">
  <button type="button" class="lightbox-backdrop__close" onclick="closeImageLightbox()" aria-label="ปิด">✕</button>
  <img id="imgLightboxImg" class="lightbox-backdrop__img" src="" alt="">
</div>

<!-- Toast container -->
<div id="toastContainer"></div>

<!-- ── All items data for JS filter ─────────────────────────── -->
<script>
// Embed PHP data for client-side filtering
const ALL_ITEMS = <?= json_encode($allItems, JSON_UNESCAPED_UNICODE) ?>;
// Active departments (dropdown in the stock-out tab)
let DEPARTMENTS = <?= json_encode($departments, JSON_UNESCAPED_UNICODE) ?>;
</script>

<script>
// ================================================================
// FILTER & SORT & PAGINATION
// ================================================================
const PAGE_SIZE = 25;
let currentPage = 1;
let CURRENT_VIEW_IDS = ALL_ITEMS.map(it => it.id); // updated by filterTable(); used by modal prev/next arrows

function filterTable() {
  const query = document.getElementById('searchInput').value.trim().toLowerCase();
  const sort  = document.getElementById('sortSelect').value;
  const tbody = document.getElementById('tableBody');
  const emptyState = document.getElementById('emptyState');
  const pagination = document.getElementById('itemsPagination');

  // Filter
  let filtered = ALL_ITEMS.filter(it => {
    const haystack = (it.name + ' ' + it.group_name).toLowerCase();
    return !query || haystack.includes(query);
  });

  // Sort
  filtered.sort((a, b) => {
    if (sort === 'name_asc')    return a.name.localeCompare(b.name, 'th');
    if (sort === 'name_desc')   return b.name.localeCompare(a.name, 'th');
    if (sort === 'stock_asc')   return a.current_stock - b.current_stock;
    if (sort === 'stock_desc')  return b.current_stock - a.current_stock;
    if (sort === 'usage_desc')  return (b.total_out || 0) - (a.total_out || 0);
    if (sort === 'usage_asc')   return (a.total_out || 0) - (b.total_out || 0);
    return 0;
  });

  CURRENT_VIEW_IDS = filtered.map(it => it.id);

  if (filtered.length === 0) {
    tbody.innerHTML = '';
    emptyState.style.display = 'block';
    pagination.innerHTML = '';
    return;
  }
  emptyState.style.display = 'none';

  // Re-group (preserves the same group order as `filtered`)
  const groupedMap = {};
  filtered.forEach(it => {
    if (!groupedMap[it.group_name]) groupedMap[it.group_name] = [];
    groupedMap[it.group_name].push(it);
  });
  const groupEntries = Object.entries(groupedMap);

  // Split groups into pages of up to PAGE_SIZE items each. A small group is
  // kept whole on one page when it fits; a group larger than PAGE_SIZE (or
  // one that would overflow the current page) is chunked across as many
  // pages as needed, so pagination still works even when every item shares
  // the same group_name (e.g. all 75 items under "สำนักงาน").
  const pages = [];
  let pageEntries = [];
  let pageCount = 0;
  groupEntries.forEach(([grpName, items]) => {
    let remaining = items;
    while (remaining.length > 0) {
      if (pageCount > 0 && pageCount >= PAGE_SIZE) {
        pages.push(pageEntries);
        pageEntries = [];
        pageCount = 0;
      }
      const spaceLeft = PAGE_SIZE - pageCount;
      const take = pageCount === 0
        ? Math.min(remaining.length, PAGE_SIZE)
        : Math.min(remaining.length, spaceLeft);
      const chunk = remaining.slice(0, take);
      remaining = remaining.slice(take);
      pageEntries.push([grpName, chunk]);
      pageCount += chunk.length;
      if (remaining.length > 0) {
        pages.push(pageEntries);
        pageEntries = [];
        pageCount = 0;
      }
    }
  });
  if (pageEntries.length) pages.push(pageEntries);

  const totalPages = pages.length;
  if (currentPage > totalPages) currentPage = totalPages;
  if (currentPage < 1) currentPage = 1;

  renderItemsPage(pages[currentPage - 1]);
  renderPagination(pagination, totalPages, currentPage, filtered.length, (p) => {
    currentPage = p;
    filterTable();
    document.querySelector('.panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
}

function renderItemsPage(groupEntries) {
  const tbody = document.getElementById('tableBody');
  let html = '';
  groupEntries.forEach(([grpName, items]) => {
    items.forEach((it, idx) => {
      const st  = parseInt(it.current_stock);
      const min = parseInt(it.min_stock);
      let badgeClass, badgeText, stockClass;
      if (st === 0)           { badgeClass='badge-out-stock'; badgeText='หมดแล้ว'; stockClass='zero'; }
      else if (st <= min)     { badgeClass='badge-low-stock';  badgeText='ใกล้หมด'; stockClass='low'; }
      else                    { badgeClass='badge-in-stock';   badgeText='มีสต็อก'; stockClass=''; }

      const isLast  = idx === items.length - 1;
      const isFirst = idx === 0;
      const imgSrc  = 'assets/images/' + escAttr(it.image);

      const rowCls = [isFirst ? 'row-group-start' : '', isLast ? 'row-group-end' : ''].filter(Boolean).join(' ');
      html += `<tr class="${rowCls}" data-group="${escAttr(grpName)}" data-name="${escAttr(it.name)}" data-stock="${st}" style="cursor:pointer;" onclick="openModal(${parseInt(it.id)})">`;
      html += `<td class="img-cell">
          <img src="${imgSrc}" alt="${escHtml(it.name)}" class="item-thumb"
               onclick="event.stopPropagation(); openImageLightbox(this.src, this.alt)"
               onerror="this.src='assets/images/default.png'">
        </td>`;
      html += `<td>${escHtml(it.name)}${packNoteHtml(it.pack_qty, it.unit)}</td>
               <td class="col-stock"><span class="stock-num ${stockClass}">${st}</span></td>
               <td class="col-unit">${escHtml(it.unit)}</td>
               <td class="col-status">
                 <span class="badge ${badgeClass}">${badgeText}</span>
               </td>
               <td class="col-actions">
                 <div class="action-buttons">
                   <button type="button" class="btn-edit" title="เบิก / เติมสต็อก / แก้ไขข้อมูล" onclick="event.stopPropagation(); openModal(${parseInt(it.id)})"><img src="assets/images/icons/pen-icon.png" alt="" class="btn-icon-img"></button>
                   <button type="button" class="btn-edit btn-delete" title="ลบรายการ" onclick="event.stopPropagation(); deleteItem(${parseInt(it.id)}, '${escAttr(it.name).replace(/'/g, "\\'")}')"><img src="assets/images/icons/trash-icon.png" alt="" class="btn-icon-img"></button>
                 </div>
               </td>
             </tr>`;
    });
  });
  tbody.innerHTML = html;
}

// ── Generic pagination control renderer ────────────────────────
function renderPagination(container, totalPages, current, totalCount, onPageClick) {
  if (totalPages <= 1) { container.innerHTML = ''; return; }

  const makeBtn = (label, page, opts = {}) => {
    const { disabled = false, active = false, ellipsis = false } = opts;
    const cls = ['page-btn', active ? 'active' : '', ellipsis ? 'ellipsis' : ''].filter(Boolean).join(' ');
    return `<button type="button" class="${cls}" data-page="${page}" ${disabled ? 'disabled' : ''}>${label}</button>`;
  };

  let html = `<div class="pagination__info">หน้า ${current} จาก ${totalPages} • ทั้งหมด ${totalCount.toLocaleString('th-TH')} รายการ</div>`;
  html += makeBtn('‹ ก่อนหน้า', current - 1, { disabled: current === 1 });

  const pageNums = new Set([1, totalPages, current, current - 1, current + 1]);
  let prevShown = 0;
  for (let p = 1; p <= totalPages; p++) {
    if (!pageNums.has(p)) continue;
    if (p - prevShown > 1) html += makeBtn('…', 0, { ellipsis: true });
    html += makeBtn(String(p), p, { active: p === current });
    prevShown = p;
  }

  html += makeBtn('ถัดไป ›', current + 1, { disabled: current === totalPages });
  container.innerHTML = html;

  container.querySelectorAll('.page-btn[data-page]:not(.ellipsis):not(:disabled)').forEach(btn => {
    btn.addEventListener('click', () => onPageClick(parseInt(btn.dataset.page, 10)));
  });
}



// ================================================================
// MODAL (stock in / stock out / edit details)
// ================================================================
let currentItemId = null;
let currentStock   = 0;

function todayDateStr() {
  // Returns "YYYY-MM-DD" in local time, suitable for <input type="date">
  const d = new Date();
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0, 10);
}

function openModal(itemId, tabToShow) {
  currentItemId = itemId;

  const fd = new FormData();
  fd.append('action', 'get_item_detail');
  fd.append('item_id', itemId);

  fetch('ajax_handler.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.error || !data.item) {
        showToast(data.error || 'ไม่พบรายการ', 'error');
        return;
      }
      populateModal(data.item);
      document.getElementById('itemModalBackdrop').classList.add('open');
      switchModalTab(tabToShow || 'stock-out');
    })
    .catch(() => showToast('เกิดข้อผิดพลาด', 'error'));
}

function populateModal(item) {
  currentStock = parseInt(item.current_stock);
  const min = parseInt(item.min_stock);

  // Header / info box
  document.getElementById('modalItemName').textContent = item.name;
  document.getElementById('modalStockVal').textContent = item.current_stock;
  document.getElementById('modalStockUnit').textContent = item.unit;
  document.getElementById('modalPackNote').innerHTML =
    packNoteHtml(item.pack_qty, item.unit) || '<span class="no-pack">ไม่ระบุแพ็ค</span>';
  updateModalBadge(currentStock, min);

  // Reset forms first
  document.getElementById('stockInForm').reset();
  document.getElementById('stockOutForm').reset();
  document.getElementById('editDetailsForm').reset();

  // Stock In tab — date left blank on purpose (not tied to "today"; user
  // picks any date, including a backdated one, from the calendar picker)
  document.getElementById('siItemId').value = item.id;
  document.getElementById('siDate').value = '';
  document.getElementById('siQty').max = 1000;
  document.getElementById('siMaxLabel').textContent = '1000';

  // Stock Out tab — quantity slider bounded by current stock
  document.getElementById('soItemId').value = item.id;
  const soMax = Math.max(currentStock, 1);
  document.getElementById('soQty').max = soMax;
  document.getElementById('soQtyNum').max = soMax;
  document.getElementById('soQty').value = 1;
  document.getElementById('soQtyNum').value = 1;
  document.getElementById('soMaxLabel').textContent = currentStock;
  const soDisabled = currentStock < 1;
  document.getElementById('soQty').disabled = soDisabled;
  document.getElementById('soQtyNum').disabled = soDisabled;
  document.getElementById('soDate').value = '';

  // Edit Details tab
  document.getElementById('edItemId').value = item.id;
  document.getElementById('edGroup').value = item.group_name;
  document.getElementById('edName').value = item.name;
  document.getElementById('edUnit').value = item.unit;
  document.getElementById('edPackQty').value = item.pack_qty ?? '';
  document.getElementById('edPreview').src = 'assets/images/' + item.image;
}

// ── Quantity slider (เบิกอุปกรณ์) ── keep the number box and the range
// slider in sync; the right-hand number (current stock) is just a label.
function onQtyNumInput() {
  const num = document.getElementById('soQtyNum');
  const slider = document.getElementById('soQty');
  const max = parseInt(slider.max) || 1;
  let v = parseInt(num.value);
  if (isNaN(v)) v = 1;
  v = Math.min(Math.max(v, 1), max);
  num.value = v;
  slider.value = v;
}
function onQtySliderInput() {
  document.getElementById('soQtyNum').value = document.getElementById('soQty').value;
}

// ── Quantity slider (เติมสต็อก) ── same idea, but there's no natural
// ceiling for restocking, so the slider's max auto-extends if the typed
// number goes past it (the right-hand label just tracks that ceiling).
function onSiQtyNumInput() {
  const num = document.getElementById('siQtyNum');
  const slider = document.getElementById('siQty');
  let v = parseInt(num.value);
  if (isNaN(v)) v = 1;
  if (v < 1) v = 1;
  if (v > parseInt(slider.max)) {
    slider.max = v;
    document.getElementById('siMaxLabel').textContent = v;
  }
  num.value = v;
  slider.value = v;
}
function onSiQtySliderInput() {
  document.getElementById('siQtyNum').value = document.getElementById('siQty').value;
}

// ── Prev / next arrows: step through the currently filtered/sorted list,
// wrapping around at either end, while staying on the same tab.
function navigateModalItem(dir) {
  if (!CURRENT_VIEW_IDS.length) return;
  let idx = CURRENT_VIEW_IDS.findIndex(id => parseInt(id) === parseInt(currentItemId));
  if (idx === -1) idx = 0;
  let newIdx = (idx + dir + CURRENT_VIEW_IDS.length) % CURRENT_VIEW_IDS.length;

  const activeBtn = document.querySelector('.modal-tab-btn.active');
  let tab = 'stock-out';
  if (activeBtn) {
    if (activeBtn.classList.contains('edit-tab')) tab = 'edit';
    else if (activeBtn.classList.contains('stock-in-tab')) tab = 'stock-in';
    else if (activeBtn.classList.contains('stock-out-tab')) tab = 'stock-out';
  }
  openModal(CURRENT_VIEW_IDS[newIdx], tab);
}

function closeModal() {
  document.getElementById('itemModalBackdrop').classList.remove('open');
}

function switchModalTab(tab) {
  const tabMap = {
    'stock-in':  { btn: '.stock-in-tab',  content: 'tab-stock-in'  },
    'stock-out': { btn: '.stock-out-tab', content: 'tab-stock-out' },
    'edit':      { btn: '.edit-tab',      content: 'tab-edit'      },
  };
  const target = tabMap[tab];
  if (!target) return;

  document.querySelectorAll('.modal-tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.modal-tab-content').forEach(c => c.classList.remove('active'));

  document.querySelector(target.btn).classList.add('active');
  document.getElementById(target.content).classList.add('active');
}

// ================================================================
// ADD ITEM MODAL
// ================================================================
function refreshGroupNameList() {
  const uniqueGroups = [...new Set(ALL_ITEMS.map(it => it.group_name))].sort((a, b) => a.localeCompare(b, 'th'));
  const datalist = document.getElementById('groupNameList');
  datalist.innerHTML = uniqueGroups.map(g => `<option value="${escAttr(g)}"></option>`).join('');
}

function openAddModal() {
  document.getElementById('addItemForm').reset();
  document.getElementById('addPreview').src = 'assets/images/default.png';
  refreshGroupNameList();
  document.getElementById('addModalBackdrop').classList.add('open');
}

function closeAddModal() {
  document.getElementById('addModalBackdrop').classList.remove('open');
}

function previewAddImage(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { document.getElementById('addPreview').src = e.target.result; };
    reader.readAsDataURL(input.files[0]);
  }
}

function submitAddItem(e) {
  e.preventDefault();
  const stock = parseInt(document.getElementById('addStock').value);
  const minStock = parseInt(document.getElementById('addMinStock').value);
  if (isNaN(stock) || stock < 0)    { showToast('กรุณาป้อนจำนวนสต็อกที่ถูกต้อง', 'error'); return; }
  if (isNaN(minStock) || minStock < 0) { showToast('กรุณาป้อนสต็อกขั้นต่ำที่ถูกต้อง', 'error'); return; }

  const fd = new FormData(document.getElementById('addItemForm'));
  fd.append('action', 'add_item');

  fetch('ajax_handler.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('เพิ่มรายการ "' + data.name + '" สำเร็จ');
        ALL_ITEMS.push({
          id: data.item_id,
          group_name: data.group_name,
          name: data.name,
          image: data.image,
          unit: data.unit,
          pack_qty: data.pack_qty,
          current_stock: data.current_stock,
          min_stock: data.min_stock,
        });
        filterTable();
        closeAddModal();
      } else {
        showToast(data.error || 'เกิดข้อผิดพลาด', 'error');
      }
    })
    .catch(() => showToast('เกิดข้อผิดพลาด', 'error'));
}

// ================================================================
// DELETE ITEM
// ================================================================
let _pendingDeleteId = null;
let _pendingDeleteName = null;
let _pendingDeleteKind = 'item';   // 'item' | 'dept' – what the shared confirm dialog is about

function deleteItem(itemId, itemName) {
  _pendingDeleteKind = 'item';
  _pendingDeleteId = itemId;
  _pendingDeleteName = itemName;
  document.getElementById('deleteConfirmText').textContent =
    'ยืนยันการลบรายการ "' + itemName + '" ?\nรายการนี้เมื่อถูกลบ ไม่สามารถกู้คืนได้';
  document.getElementById('deleteConfirmBackdrop').classList.add('open');
}

function cancelDeleteConfirm() {
  _pendingDeleteId = null;
  _pendingDeleteName = null;
  _pendingDeleteKind = 'item';
  document.getElementById('deleteConfirmBackdrop').classList.remove('open');
}

function confirmDeleteYes() {
  if (_pendingDeleteKind === 'dept') {
    document.getElementById('deleteConfirmBackdrop').classList.remove('open');
    performDeleteDept();
    return;
  }
  const itemId = _pendingDeleteId;
  const itemName = _pendingDeleteName;
  document.getElementById('deleteConfirmBackdrop').classList.remove('open');
  if (itemId === null) return;

  const fd = new FormData();
  fd.append('action', 'delete_item');
  fd.append('item_id', itemId);

  fetch('ajax_handler.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('ลบรายการ "' + itemName + '" สำเร็จ');
        const idx = ALL_ITEMS.findIndex(i => parseInt(i.id) === parseInt(data.item_id));
        if (idx !== -1) ALL_ITEMS.splice(idx, 1);
        filterTable();
      } else {
        showToast(data.error || 'เกิดข้อผิดพลาด', 'error');
      }
    })
    .catch(() => showToast('เกิดข้อผิดพลาด', 'error'))
    .finally(() => {
      _pendingDeleteId = null;
      _pendingDeleteName = null;
    });
}

// ================================================================
// DEPARTMENTS – dropdown in the stock-out tab + add/delete dialog
// The LAST option of the dropdown opens the manager dialog.
// Deleting a department only hides it (soft delete) – history stays.
// ================================================================
const DEPT_MANAGE_VALUE = '__manage__';
let _deptPrevValue = '';        // last real selection, restored after the "manage" option is clicked
let _pendingDeptId = null;

// Rebuild the <select>. selectedId (optional) = department to select afterwards;
// otherwise the current selection is kept when it still exists.
function renderDeptSelect(selectedId) {
  const sel  = document.getElementById('soDept');
  const keep = (selectedId !== undefined && selectedId !== null) ? String(selectedId) : sel.value;

  let html = '<option value="">-- เลือกแผนก --</option>';
  DEPARTMENTS.forEach(d => {
    html += `<option value="${parseInt(d.id)}">${escHtml(d.name)}</option>`;
  });
  html += `<option value="${DEPT_MANAGE_VALUE}">⚙️ เพิ่ม / ลบแผนก...</option>`;
  sel.innerHTML = html;

  sel.value = DEPARTMENTS.some(d => String(d.id) === keep) ? keep : '';
  _deptPrevValue = sel.value;
}

function onDeptChange(sel) {
  if (sel.value === DEPT_MANAGE_VALUE) {
    sel.value = _deptPrevValue;          // do not leave "manage" selected
    openDeptModal();
    return;
  }
  _deptPrevValue = sel.value;
}

function renderDeptList() {
  const box = document.getElementById('deptList');
  if (!DEPARTMENTS.length) {
    box.innerHTML = '<div class="dept-empty">ยังไม่มีแผนก</div>';
    return;
  }
  box.innerHTML = DEPARTMENTS.map(d =>
    `<div class="dept-item">
       <span class="dept-item__name">${escHtml(d.name)}</span>
       <button type="button" class="dept-item__del" data-id="${parseInt(d.id)}" data-name="${escAttr(d.name)}">🗑️ ลบ</button>
     </div>`
  ).join('');
}

function openDeptModal() {
  renderDeptList();
  document.getElementById('deptNewName').value = '';
  document.getElementById('deptModalBackdrop').classList.add('open');
  setTimeout(() => document.getElementById('deptNewName').focus(), 50);
}

function closeDeptModal() {
  document.getElementById('deptModalBackdrop').classList.remove('open');
}

function submitAddDept(e) {
  e.preventDefault();
  const input = document.getElementById('deptNewName');
  const name  = input.value.trim();
  if (!name) { showToast('กรุณาระบุชื่อแผนก', 'error'); return; }

  const fd = new FormData();
  fd.append('action', 'add_department');
  fd.append('name', name);

  fetch('ajax_handler.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        DEPARTMENTS = data.departments;
        renderDeptList();
        renderDeptSelect(data.department.id);   // select the new department in the form
        input.value = '';
        input.focus();
        showToast('เพิ่มแผนก "' + data.department.name + '" สำเร็จ');
      } else {
        showToast(data.error || 'เกิดข้อผิดพลาด', 'error');
      }
    })
    .catch(() => showToast('เกิดข้อผิดพลาด', 'error'));
}

function deleteDept(id, name) {
  _pendingDeleteKind = 'dept';
  _pendingDeptId     = id;
  _pendingDeleteName = name;
  document.getElementById('deleteConfirmText').textContent =
    'ยืนยันการลบแผนก "' + name + '" ?\nประวัติการเบิกที่เคยบันทึกไว้จะไม่ถูกลบ';
  document.getElementById('deleteConfirmBackdrop').classList.add('open');
}

function performDeleteDept() {
  const id   = _pendingDeptId;
  const name = _pendingDeleteName;
  _pendingDeptId = null;
  _pendingDeleteName = null;
  _pendingDeleteKind = 'item';
  if (id === null) return;

  const fd = new FormData();
  fd.append('action', 'delete_department');
  fd.append('department_id', id);

  fetch('ajax_handler.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        DEPARTMENTS = data.departments;
        renderDeptList();
        renderDeptSelect();                     // keeps the selection unless it was the deleted one
        showToast('ลบแผนก "' + name + '" สำเร็จ');
      } else {
        showToast(data.error || 'เกิดข้อผิดพลาด', 'error');
      }
    })
    .catch(() => showToast('เกิดข้อผิดพลาด', 'error'));
}

// Delete buttons inside the manager list (event delegation)
document.getElementById('deptList').addEventListener('click', e => {
  const btn = e.target.closest('.dept-item__del');
  if (!btn) return;
  deleteDept(parseInt(btn.dataset.id, 10), btn.dataset.name);
});

// form.reset() puts the dropdown back on the placeholder → forget the remembered value
document.getElementById('stockOutForm').addEventListener('reset', () => { _deptPrevValue = ''; });

// ── Image preview ──────────────────────────────────────────────
function previewImage(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { document.getElementById('edPreview').src = e.target.result; };
    reader.readAsDataURL(input.files[0]);
  }
}

// ── Stock In submit ────────────────────────────────────────────
function submitStockIn(e) {
  e.preventDefault();
  const qty = parseInt(document.getElementById('siQty').value);
  if (!qty || qty < 1) { showToast('กรุณาป้อนจำนวนที่ถูกต้อง', 'error'); return; }

  const fd = new FormData(document.getElementById('stockInForm'));
  fd.append('action', 'stock_in');

  fetch('ajax_handler.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('เติมสต็อกสำเร็จ +' + qty + ' ' + data.unit);
        refreshRow(data.item_id, data.new_stock, data.unit, data.min_stock, data.group_name, data.name, data.image, data.pack_qty);
        // Update modal stock display
        currentStock = data.new_stock;
        document.getElementById('modalStockVal').textContent = data.new_stock;
        const soMax = Math.max(data.new_stock, 1);
        document.getElementById('soQty').max = soMax;
        document.getElementById('soQtyNum').max = soMax;
        document.getElementById('soMaxLabel').textContent = data.new_stock;
        document.getElementById('soQty').disabled = data.new_stock < 1;
        document.getElementById('soQtyNum').disabled = data.new_stock < 1;
        document.getElementById('stockInForm').reset();
        document.getElementById('siDate').value = '';
        document.getElementById('siQty').max = 1000;
        document.getElementById('siMaxLabel').textContent = '1000';
        updateModalBadge(data.new_stock, data.min_stock);
      } else {
        showToast(data.error || 'เกิดข้อผิดพลาด', 'error');
      }
    })
    .catch(() => showToast('เกิดข้อผิดพลาด', 'error'));
}

// ── Stock Out submit ───────────────────────────────────────────
function submitStockOut(e) {
  e.preventDefault();
  const qty = parseInt(document.getElementById('soQty').value);
  if (!qty || qty < 1) { showToast('กรุณาป้อนจำนวนที่ถูกต้อง', 'error'); return; }
  if (qty > currentStock) { showToast('จำนวนที่เบิกเกินสต็อกที่มี', 'error'); return; }

  const fd = new FormData(document.getElementById('stockOutForm'));
  fd.append('action', 'stock_out');

  fetch('ajax_handler.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('เบิกอุปกรณ์สำเร็จ -' + qty + ' ' + data.unit);
        refreshRow(data.item_id, data.new_stock, data.unit, data.min_stock, data.group_name, data.name, data.image, data.pack_qty, qty);
        currentStock = data.new_stock;
        document.getElementById('modalStockVal').textContent = data.new_stock;
        const soMax = Math.max(data.new_stock, 1);
        document.getElementById('soQty').max = soMax;
        document.getElementById('soQtyNum').max = soMax;
        document.getElementById('soMaxLabel').textContent = data.new_stock;
        document.getElementById('soQty').value = 1;
        document.getElementById('soQtyNum').value = 1;
        document.getElementById('soQty').disabled = data.new_stock < 1;
        document.getElementById('soQtyNum').disabled = data.new_stock < 1;
        document.getElementById('stockOutForm').reset();
        document.getElementById('soDate').value = '';
        updateModalBadge(data.new_stock, data.min_stock);
      } else {
        showToast(data.error || 'เกิดข้อผิดพลาด', 'error');
      }
    })
    .catch(() => showToast('เกิดข้อผิดพลาด', 'error'));
}

// ── Edit Details submit ────────────────────────────────────────
function submitEditDetails(e) {
  e.preventDefault();
  const fd = new FormData(document.getElementById('editDetailsForm'));
  fd.append('action', 'update_item');

  fetch('ajax_handler.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('บันทึกการแก้ไขสำเร็จ');
        // Update ALL_ITEMS cache
        const idx = ALL_ITEMS.findIndex(i => i.id == data.item_id);
        if (idx !== -1) {
          ALL_ITEMS[idx].name       = data.name;
          ALL_ITEMS[idx].group_name = data.group_name;
          ALL_ITEMS[idx].unit       = data.unit;
          ALL_ITEMS[idx].pack_qty   = data.pack_qty;
          ALL_ITEMS[idx].image      = data.image;
        }
        filterTable();
        closeModal();
      } else {
        showToast(data.error || 'เกิดข้อผิดพลาด', 'error');
      }
    })
    .catch(() => showToast('เกิดข้อผิดพลาด', 'error'));
}

// ── Refresh a single row after stock change ────────────────────
// qtyOut: when a STOCK_OUT just happened, pass the quantity taken out
// so the "เบิกบ่อย" sort stays accurate without needing a page reload.
function refreshRow(itemId, newStock, unit, minStock, groupName, name, image, packQty, qtyOut = 0) {
  // Update the ALL_ITEMS source array
  const idx = ALL_ITEMS.findIndex(i => parseInt(i.id) === parseInt(itemId));
  if (idx !== -1) {
    ALL_ITEMS[idx].current_stock = newStock;
    ALL_ITEMS[idx].unit          = unit;
    ALL_ITEMS[idx].min_stock     = minStock;
    ALL_ITEMS[idx].name          = name;
    ALL_ITEMS[idx].group_name    = groupName;
    ALL_ITEMS[idx].image         = image;
    if (qtyOut > 0) {
      ALL_ITEMS[idx].total_out = (ALL_ITEMS[idx].total_out || 0) + qtyOut;
    }
    ALL_ITEMS[idx].pack_qty      = packQty;
  }
  filterTable();
}

// ── Update modal badge after stock change ──────────────────────
function updateModalBadge(st, min) {
  let badgeClass, badgeText;
  if (st === 0)       { badgeClass='badge-out-stock'; badgeText='หมดแล้ว'; }
  else if (st <= min) { badgeClass='badge-low-stock';  badgeText='ใกล้หมด'; }
  else                { badgeClass='badge-in-stock';   badgeText='มีสต็อก'; }
  document.getElementById('modalCurrentBadge').innerHTML =
    `<span class="badge ${badgeClass}">${badgeText}</span>`;
}

// ── Utilities ──────────────────────────────────────────────────
function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
function escAttr(str) { return escHtml(str); }

// ── "1 แพ็ค / N หน่วย" caption shown under an item name ──────────
function packNoteHtml(packQty, unit) {
  const n = parseInt(packQty);
  if (!n || n < 1) return '';
  return `<small class="pack-note">1 แพ็ค / ${n.toLocaleString('th-TH')} ${escHtml(unit)}</small>`;
}

function showToast(msg, type = 'success') {
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = (type === 'success' ? '✅ ' : '❌ ') + escHtml(msg);
  document.getElementById('toastContainer').appendChild(el);
  setTimeout(() => {
    el.style.animation = 'toastOut .3s ease forwards';
    setTimeout(() => el.remove(), 300);
  }, 3200);
}

// ── Image lightbox (click any item thumbnail → see it enlarged) ─
function openImageLightbox(src, alt) {
  document.getElementById('imgLightboxImg').src = src;
  document.getElementById('imgLightboxImg').alt = alt || '';
  document.getElementById('imgLightboxBackdrop').classList.add('open');
}
function closeImageLightbox() {
  document.getElementById('imgLightboxBackdrop').classList.remove('open');
}

// ── Enter key = save ────────────────────────────────────────────
// Pressing Enter in any field of an open form submits that form (same
// as clicking its "บันทึก" button), including fields (range sliders,
// number inputs, selects) where the browser wouldn't submit on Enter
// by default. A <textarea> (the "หมายเหตุ" note boxes) is excluded so
// Enter still inserts a normal line break there instead of saving.
document.addEventListener('keydown', function (e) {
  if (e.key !== 'Enter' || e.shiftKey) return;
  const tag = e.target.tagName;
  if (tag === 'TEXTAREA' || tag === 'BUTTON' || tag === 'A') return;
  const form = e.target.form || e.target.closest('form');
  if (!form) return;
  e.preventDefault();
  if (form.requestSubmit) form.requestSubmit();
  else form.dispatchEvent(new Event('submit', { cancelable: true }));
});

// Esc closes the image lightbox
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') closeImageLightbox();
});

// ── Run filtering/sorting/pagination once on initial page load ─
filterTable();
renderDeptSelect();

</script>
</body>
</html>