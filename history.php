<?php
/**
 * history.php – Grouped inventory table (same layout as items.php),
 * with a "รายละเอียด" column whose round arrow button expands an inline
 * per-item history panel (stock in / stock out / info edits) loaded via
 * ajax_handler.php: action=get_item_history.
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

session_start();

require_once __DIR__ . '/db.php';
$pdo = getDB();

// ── Fetch all items grouped (identical query to items.php) ─────
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
  <title>ประวัติการเบิก – Office Supplies</title>
  <meta name="description" content="ประวัติการเบิก เติมสต็อก และแก้ไขข้อมูลของวัสดุสำนักงานแต่ละรายการ">
  <link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . '/style.css') ?: time() ?>">
  <style>
    /* Same "rounded card row" treatment as items.php */
    td.img-cell { border-right: 1.5px solid var(--border); }
    #itemsTable { table-layout: fixed; }
    #itemsTable th, #itemsTable td { overflow-wrap: break-word; }
    #itemsTable td.img-cell {
      background: var(--surface, #fff);
      border-top-left-radius: var(--radius-lg);
      border-bottom-left-radius: var(--radius-lg);
      overflow: hidden;
    }
    #itemsTable tbody tr:not(.history-expand-row) td:last-child {
      background: var(--surface, #fff);
      border-top-right-radius: var(--radius-lg);
      border-bottom-right-radius: var(--radius-lg);
    }
    #itemsTable .row-group-end td { border-bottom: 2px solid var(--border); }
    .col-status { text-align: center; }
    .col-actions { text-align: center; }
    .col-stock, .col-unit { text-align: center; }
    #itemsTable td.col-stock, #itemsTable td.col-unit { text-align: center; }

    /* ── "รายละเอียด" details button — pink icon, turns purple when open ── */
    .btn-expand {
      width: 22px; height: 22px;
      border: none;
      background: transparent;
      padding: 0;
      display: inline-flex; align-items: center; justify-content: center;
      cursor: pointer;
      transition: transform .25s ease;
    }
    .btn-expand:hover { transform: scale(1.08); }
    .btn-expand__icon {
      width: 18px; height: 18px;
      display: block;
      pointer-events: none;
    }

    /* ── Inline expanded history panel row ────────────────────── */
    .history-expand-row td {
      background: #eef0f2;
      padding: 0;
      border-bottom: 1px solid #dde0e3;
    }
    .history-expand-inner { padding: 18px 22px 22px; }
    .txn-list-header {
      font-size: .92rem; font-weight: 700; color: var(--dark);
      margin-bottom: 12px;
    }
    .txn-mini-list { gap: 10px; }

    .txn-card { cursor: default; }
    .txn-card:hover { transform: none; border-color: var(--border); background: var(--surface); }
    .txn-card__top {
      display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
    }
    .txn-card__date { font-weight: 700; color: var(--dark); font-size: .95rem; }
    .txn-card__meta { color: var(--text-muted); font-size: .85rem; margin-top: 4px; line-height: 1.7; }
    .txn-card__qty { font-weight: 800; font-size: 1.15rem; text-align: center; white-space: nowrap; }
    .txn-card__qty.in  { color: #1d8a3d; }
    .txn-card__qty.out { color: var(--out-stock-bg); }
    .txn-card__side { display: flex; flex-direction: column; align-items: center; gap: 6px; }

    /* Info-edit entries get their own badge color (distinct from stock in/out) */
    .badge-edit { background: #e4e8f7; color: #33437a; }
    .txn-card--edit .txn-card__meta { color: var(--dark2); }

    .txn-pagination { margin-top: 10px; }

    /* ── ดูประวัติตามช่วงเวลา ต่อรายการ: แท็บย่อยสัปดาห์/เดือน/ปี ──── */
    .summary-block { margin-top: 22px; }
    .summary-block .txn-list-header { margin-bottom: 10px; }
    .summary-tabs { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
    .summary-tab-btn {
      padding: 6px 16px;
      border-radius: 999px;
      border: 1.5px solid var(--border);
      background: var(--surface, #fff);
      color: var(--dark2);
      font-size: .85rem;
      font-weight: 600;
      cursor: pointer;
      transition: background var(--transition), border-color var(--transition), color var(--transition);
    }
    .summary-tab-btn:hover { border-color: var(--accent); color: var(--accent); }
    .summary-tab-btn.active {
      background: var(--accent);
      border-color: var(--accent);
      color: #fff;
    }
    .period-chip-list { display: flex; flex-wrap: wrap; gap: 8px; }
    .period-chip {
      padding: 5px 12px;
      border-radius: 8px;
      border: 1.5px solid var(--border);
      background: var(--surface, #fff);
      color: var(--dark2);
      font-size: .82rem;
      font-weight: 600;
      cursor: pointer;
      transition: background var(--transition), border-color var(--transition), color var(--transition);
    }
    .period-chip:hover { border-color: var(--accent); }
    .period-chip.active { background: var(--dark2); border-color: var(--dark2); color: #fff; }
    .period-chip__count {
      display: inline-block;
      margin-left: 4px;
      padding: 0 6px;
      border-radius: 999px;
      background: rgba(255,255,255,.25);
      font-size: .74rem;
    }
    .period-chip:not(.active) .period-chip__count { background: var(--bg); }

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
      <a href="items.php">รายการอุปกรณ์</a>
      <a href="history.php" class="active" aria-current="page">ประวัติการเบิก</a>
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
    <h1>ประวัติการเบิก</h1>
    <p>ดูประวัติการเบิก เติมสต็อก และแก้ไขข้อมูลของแต่ละรายการ</p>
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
            <th class="col-actions" style="width:11%;">รายละเอียด</th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <!-- Rendered entirely by JS (renderItemsPage), same as items.php -->
        </tbody>
      </table>

      <!-- Empty state (shown via JS) -->
      <div id="emptyState" class="empty-state" style="display:none;">
        <p>ไม่พบรายการที่ค้นหา</p>
      </div>

      <!-- Pagination -->
      <div id="itemsPagination" class="pagination"></div>
    </div>
  </div><!-- /.panel -->
</main>

<!-- ── IMAGE LIGHTBOX (click any item thumbnail to see it enlarged) ─ -->
<div class="lightbox-backdrop" id="imgLightboxBackdrop" onclick="if(event.target===this) closeImageLightbox();">
  <button type="button" class="lightbox-backdrop__close" onclick="closeImageLightbox()" aria-label="ปิด">✕</button>
  <img id="imgLightboxImg" class="lightbox-backdrop__img" src="" alt="">
</div>

<!-- Toast container -->
<div id="toastContainer"></div>

<!-- ── All items data for JS filter ─────────────────────────── -->
<script>
const ALL_ITEMS = <?= json_encode($allItems, JSON_UNESCAPED_UNICODE) ?>;
</script>

<script>
// ================================================================
// FILTER & SORT & PAGINATION (same convention as items.php)
// ================================================================
const PAGE_SIZE = 25;
let currentPage = 1;

// Per-item expand/collapse + lazily-loaded history state
const expandedIds   = new Set();   // item ids currently expanded
const historyCache  = {};          // itemId -> { item, transactions } | { error: true }

function filterTable() {
  const query = document.getElementById('searchInput').value.trim().toLowerCase();
  const sort  = document.getElementById('sortSelect').value;
  const tbody = document.getElementById('tableBody');
  const emptyState = document.getElementById('emptyState');
  const pagination = document.getElementById('itemsPagination');

  let filtered = ALL_ITEMS.filter(it => {
    const haystack = (it.name + ' ' + it.group_name).toLowerCase();
    return !query || haystack.includes(query);
  });

  filtered.sort((a, b) => {
    if (sort === 'name_asc')    return a.name.localeCompare(b.name, 'th');
    if (sort === 'name_desc')   return b.name.localeCompare(a.name, 'th');
    if (sort === 'stock_asc')   return a.current_stock - b.current_stock;
    if (sort === 'stock_desc')  return b.current_stock - a.current_stock;
    if (sort === 'usage_desc')  return (b.total_out || 0) - (a.total_out || 0);
    if (sort === 'usage_asc')   return (a.total_out || 0) - (b.total_out || 0);
    return 0;
  });

  if (filtered.length === 0) {
    tbody.innerHTML = '';
    emptyState.style.display = 'block';
    pagination.innerHTML = '';
    return;
  }
  emptyState.style.display = 'none';

  const groupedMap = {};
  filtered.forEach(it => {
    if (!groupedMap[it.group_name]) groupedMap[it.group_name] = [];
    groupedMap[it.group_name].push(it);
  });
  const groupEntries = Object.entries(groupedMap);

  // Same group-aware pagination chunking as items.php (counts ITEMS per
  // page, not rendered <tr> rows — expand panels add extra rows but never
  // affect which items land on which page).
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
      const id  = parseInt(it.id, 10);
      const st  = parseInt(it.current_stock, 10);
      const min = parseInt(it.min_stock, 10);
      let badgeClass, badgeText, stockClass;
      if (st === 0)           { badgeClass='badge-out-stock'; badgeText='หมดแล้ว'; stockClass='zero'; }
      else if (st <= min)     { badgeClass='badge-low-stock';  badgeText='ใกล้หมด'; stockClass='low'; }
      else                    { badgeClass='badge-in-stock';   badgeText='มีสต็อก'; stockClass=''; }

      const isLast  = idx === items.length - 1;
      const isFirst = idx === 0;
      const isOpen  = expandedIds.has(id);
      const imgSrc  = 'assets/images/' + escAttr(it.image);

      // The visual bottom-of-group divider goes on whichever <tr> is
      // actually rendered last for this group — the item row itself,
      // unless it's expanded, in which case its expand panel row is last.
      const rowCls = [isFirst ? 'row-group-start' : '', (isLast && !isOpen) ? 'row-group-end' : ''].filter(Boolean).join(' ');

      html += `<tr class="${rowCls}" data-group="${escAttr(grpName)}" data-name="${escAttr(it.name)}" data-stock="${st}" style="cursor:pointer;" onclick="toggleHistory(${id})">`;
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
                 <button type="button" class="btn-expand ${isOpen ? 'open' : ''}"
                         title="ดูประวัติการเบิก / เติม / แก้ไข"
                         aria-label="ดูประวัติการเบิก / เติม / แก้ไข"
                         aria-expanded="${isOpen}"
                         onclick="event.stopPropagation(); toggleHistory(${id})">
                   <img class="btn-expand__icon"
                        src="assets/images/icons/${isOpen ? 'btn-details-purple.png' : 'btn-details-pink.png'}"
                        alt="">
                 </button>
               </td>
             </tr>`;

      if (isOpen) {
        const endCls = isLast ? ' row-group-end' : '';
        html += `<tr class="history-expand-row${endCls}" data-expand-for="${id}">
                   <td colspan="6" class="history-expand-cell">
                     <div class="history-expand-inner">${renderExpandContent(id)}</div>
                   </td>
                 </tr>`;
      }
    });
  });

  tbody.innerHTML = html;
}

// ── Generic pagination control renderer (same as items.php) ────
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
// EXPAND / COLLAPSE HISTORY PANEL
// ================================================================
function toggleHistory(itemId) {
  if (expandedIds.has(itemId)) {
    expandedIds.delete(itemId);
  } else {
    expandedIds.add(itemId);
    if (!historyCache[itemId]) fetchItemHistory(itemId);
  }
  filterTable();
}

function fetchItemHistory(itemId) {
  fetch('ajax_handler.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=get_item_history&item_id=' + encodeURIComponent(itemId)
  })
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        historyCache[itemId] = { error: true };
      } else {
        historyCache[itemId] = { item: data.item, transactions: data.transactions || [] };
      }
      if (expandedIds.has(itemId)) filterTable();
    })
    .catch(() => {
      historyCache[itemId] = { error: true };
      if (expandedIds.has(itemId)) filterTable();
    });
}

function renderExpandContent(itemId) {
  const cache = historyCache[itemId];
  if (!cache) {
    return '<div class="spinner"></div>';
  }
  if (cache.error) {
    return '<div class="empty-state"><p>ไม่สามารถโหลดประวัติได้</p></div>';
  }
  const transactions = cache.transactions;
  if (!transactions.length) {
    return '<div class="empty-state"><p>ยังไม่มีประวัติสำหรับรายการนี้</p></div>';
  }

  const unit = cache.item.unit;

  // ── ดูประวัติตามช่วงเวลา: แท็บย่อยสัปดาห์ / เดือน / ปี ──────────
  let html = renderSummarySection(transactions, unit, itemId);

  return html;
}

function renderTxnCard(t, unit) {
  const dt = formatThaiDateTime(t.created_at);

  if (t.type === 'EDIT') {
    return `<div class="layered-card txn-card txn-card--edit">
      <div class="txn-card__top">
        <div>
          <div class="txn-card__date">${dt}</div>
          <div class="txn-card__meta">${escHtml(t.note || 'แก้ไขข้อมูลรายการ')}</div>
        </div>
        <div class="txn-card__side">
          <span class="badge badge-edit">แก้ไขข้อมูล</span>
        </div>
      </div>
    </div>`;
  }

  const isIn = t.type === 'STOCK_IN';
  const qty  = parseInt(t.quantity, 10);
  return `<div class="layered-card txn-card">
    <div class="txn-card__top">
      <div>
        <div class="txn-card__date">${dt}</div>
        <div class="txn-card__meta">
          ผู้ทำรายการ: ${escHtml(t.requester || '-')}<br>
          ${t.department_name ? 'แผนก: ' + escHtml(t.department_name) + '<br>' : ''}
          ${t.note ? 'หมายเหตุ: ' + escHtml(t.note) : ''}
        </div>
      </div>
      <div class="txn-card__side">
        <span class="badge ${isIn ? 'badge-in-stock' : 'badge-out-stock'}">${isIn ? 'เติมเข้า' : 'เบิกออก'}</span>
        <div class="txn-card__qty ${isIn ? 'in' : 'out'}">${isIn ? '+' : '-'}${qty.toLocaleString('th-TH')} ${escHtml(unit)}</div>
      </div>
    </div>
  </div>`;
}

// ── Utilities ──────────────────────────────────────────────────
function escHtml(str) {
  return String(str ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
function escAttr(str) { return escHtml(str); }

function packNoteHtml(packQty, unit) {
  const n = parseInt(packQty, 10);
  if (!n || n < 1) return '';
  return `<small class="pack-note">1 แพ็ค / ${n.toLocaleString('th-TH')} ${escHtml(unit)}</small>`;
}

// created_at comes as "YYYY-MM-DD HH:MM:SS" from MySQL.
// Only the date is shown in the history list (time is dropped on purpose).
function formatThaiDateTime(mysqlDateTime) {
  if (!mysqlDateTime) return '-';
  const datePart = String(mysqlDateTime).split(' ')[0];
  const [y, m, d] = datePart.split('-');
  return `${d}/${m}/${y}`;
}

// ── Parse a MySQL "YYYY-MM-DD HH:MM:SS" string into a local Date ───
function parseMySQLDateTime(mysqlDateTime) {
  const [datePart, timePart] = String(mysqlDateTime).split(' ');
  const [y, m, d] = datePart.split('-').map(Number);
  let hh = 0, mi = 0, ss = 0;
  if (timePart) {
    const t = timePart.split(':').map(Number);
    hh = t[0] || 0; mi = t[1] || 0; ss = t[2] || 0;
  }
  return new Date(y, (m || 1) - 1, d || 1, hh, mi, ss);
}

// Monday (00:00) of the week containing the given Date
function mondayOf(dateObj) {
  const d = new Date(dateObj);
  const dow = (d.getDay() + 6) % 7; // 0 = Monday
  d.setDate(d.getDate() - dow);
  d.setHours(0, 0, 0, 0);
  return d;
}
function fmtDMY(d) {
  return String(d.getDate()).padStart(2, '0') + '/' +
         String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
}

/**
 * buildPeriodGroups – groups a flat list of transactions (STOCK_IN /
 * STOCK_OUT only) into weekly / monthly / yearly buckets, each bucket
 * keeping the actual transactions that fall in it (most recent bucket
 * first). Used to drive the "ดูประวัติตามช่วงเวลา" tabs, which show the
 * real transaction cards for whichever period is selected.
 */
function buildPeriodGroups(transactions) {
  const weekly = new Map(), monthly = new Map(), yearly = new Map();

  transactions.forEach(t => {
    if (t.type !== 'STOCK_IN' && t.type !== 'STOCK_OUT') return;
    const d = parseMySQLDateTime(t.created_at);

    const mon = mondayOf(d);
    const sun = new Date(mon); sun.setDate(sun.getDate() + 6);
    const wKey = String(mon.getTime());
    if (!weekly.has(wKey)) {
      weekly.set(wKey, { key: wKey, label: fmtDMY(mon) + ' - ' + fmtDMY(sun), sortKey: mon.getTime(), txns: [] });
    }
    weekly.get(wKey).txns.push(t);

    const mKey = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    if (!monthly.has(mKey)) {
      monthly.set(mKey, { key: mKey, label: String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear(), sortKey: mKey, txns: [] });
    }
    monthly.get(mKey).txns.push(t);

    const yKey = String(d.getFullYear());
    if (!yearly.has(yKey)) {
      yearly.set(yKey, { key: yKey, label: yKey, sortKey: yKey, txns: [] });
    }
    yearly.get(yKey).txns.push(t);
  });

  const toSortedArr = (map) => Array.from(map.values()).sort((a, b) => (a.sortKey < b.sortKey ? 1 : -1));
  return { weekly: toSortedArr(weekly), monthly: toSortedArr(monthly), yearly: toSortedArr(yearly) };
}

// itemId -> which sub-tab ('weekly' | 'monthly' | 'yearly') is active
// in that item's "ดูประวัติตามช่วงเวลา" panel.
const summaryTabMap = {};
// itemId -> { weekly: periodKey, monthly: periodKey, yearly: periodKey }
// remembers which specific period chip was picked within each tab.
const summaryPeriodMap = {};

/**
 * renderSummarySection – renders the "ดูประวัติตามช่วงเวลา" block for one
 * item's history panel: สัปดาห์/เดือน/ปี sub-tabs, a row of period chips
 * for whichever tab is active, and the real transaction cards (same
 * card style as the main list above) for the selected period.
 */
function renderSummarySection(transactions, unit, itemId) {
  const groups = buildPeriodGroups(transactions);
  const activeTab = summaryTabMap[itemId] || 'weekly';
  const tabsMeta = [
    { key: 'weekly',  label: 'รายสัปดาห์', periods: groups.weekly },
    { key: 'monthly', label: 'รายเดือน',   periods: groups.monthly },
    { key: 'yearly',  label: 'รายปี',      periods: groups.yearly },
  ];
  const active = tabsMeta.find(t => t.key === activeTab) || tabsMeta[0];

  const tabsHtml = tabsMeta.map(t =>
    `<button type="button" class="summary-tab-btn ${t.key === active.key ? 'active' : ''}"
             onclick="switchSummaryTab(${itemId}, '${t.key}')">${t.label}</button>`
  ).join('');

  if (!active.periods.length) {
    return `<div class="summary-block">
        <div class="txn-list-header">ดูประวัติตามช่วงเวลา</div>
        <div class="summary-tabs">${tabsHtml}</div>
        <div class="empty-state"><p>ยังไม่มีประวัติ</p></div>
      </div>`;
  }

  const savedKey = summaryPeriodMap[itemId] && summaryPeriodMap[itemId][active.key];
  const selectedPeriod = active.periods.find(p => p.key === savedKey) || active.periods[0];

  const chipsHtml = active.periods.map(p => `
    <button type="button" class="period-chip ${p.key === selectedPeriod.key ? 'active' : ''}"
            onclick="selectSummaryPeriod(${itemId}, '${active.key}', '${p.key}')">
      ${escHtml(p.label)} <span class="period-chip__count">${p.txns.length}</span>
    </button>`).join('');

  const cardsHtml = selectedPeriod.txns
    .slice()
    .sort((a, b) => String(b.created_at).localeCompare(String(a.created_at)))
    .map(t => renderTxnCard(t, unit))
    .join('');

  return `<div class="summary-block">
      <div class="txn-list-header">ดูประวัติตามช่วงเวลา</div>
      <div class="summary-tabs">${tabsHtml}</div>
      <div class="period-chip-list">${chipsHtml}</div>
      <div class="layered-cards txn-mini-list" style="margin-top:12px;">${cardsHtml}</div>
    </div>`;
}

function switchSummaryTab(itemId, tab) {
  summaryTabMap[itemId] = tab;
  filterTable();
}

function selectSummaryPeriod(itemId, tabKey, periodKey) {
  if (!summaryPeriodMap[itemId]) summaryPeriodMap[itemId] = {};
  summaryPeriodMap[itemId][tabKey] = periodKey;
  filterTable();
}

function showToast(msg, type = 'success') {
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = escHtml(msg);
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
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') closeImageLightbox();
});

// ── Run filtering/sorting/pagination once on initial page load ─
filterTable();
</script>
</body>
</html>