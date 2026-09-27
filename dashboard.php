<?php
/**
 * dashboard.php – KPI summary cards + alert panel + top-used chart
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

session_start();

require_once __DIR__ . '/db.php';
$pdo = getDB();

// ── KPI queries ────────────────────────────────────────────────
$totalItems  = (int)$pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
$lowStock    = (int)$pdo->query('SELECT COUNT(*) FROM items WHERE current_stock > 0 AND current_stock <= min_stock')->fetchColumn();
$outOfStock  = (int)$pdo->query('SELECT COUNT(*) FROM items WHERE current_stock = 0')->fetchColumn();

// Alert items (out of stock OR low stock)
$alertItems = $pdo->query(
    'SELECT id, group_name, name, image, current_stock, min_stock, unit, pack_qty
     FROM items
     WHERE current_stock <= min_stock
     ORDER BY current_stock ASC, group_name, name'
)->fetchAll();

// Top 5 most requisitioned items (by total STOCK_OUT quantity)
$topUsed = $pdo->query(
    'SELECT i.name, i.group_name, i.image, i.unit, i.pack_qty,
            SUM(t.quantity) AS total_out
     FROM transactions t
     JOIN items i ON i.id = t.item_id
     WHERE t.type = \'STOCK_OUT\'
     GROUP BY t.item_id
     ORDER BY total_out DESC
     LIMIT 5'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>แดชบอร์ด – Office Supplies</title>
  <meta name="description" content="ภาพรวมสต็อกวัสดุสำนักงาน: รายการทั้งหมด สต็อกใกล้หมด และสินค้าหมดสต็อก">
  <link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . '/style.css') ?: time() ?>">
  <style>
    /* Fixed proportional columns so the item-name column doesn't stretch
       to fill leftover space and leave a big empty gap before the other
       columns, same convention as items.php / history.php */
    #alertTable, #topUsedTable, #categoryTable { table-layout: fixed; }
    #alertTable th, #alertTable td,
    #topUsedTable th, #topUsedTable td,
    #categoryTable th, #categoryTable td { overflow-wrap: break-word; }
    /* Image column: same thumbnail treatment as items.php / history.php */
    #alertTable .img-cell, #topUsedTable .img-cell, #categoryTable .img-cell { width: auto; }
  </style>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
</head>
<body>

<!-- ── NAVBAR ────────────────────────────────────────────────── -->
<nav class="navbar" role="navigation" aria-label="เมนูหลัก">
  <div class="navbar__brand">
    <span class="navbar__star-wrap">
      <img src="assets/images/icons/icon-star.png" alt="">
    </span>
    Office Supplies
  </div>
  <div class="navbar__menu">
    <div class="navbar__nav">
      <a href="dashboard.php" class="active" aria-current="page">แดชบอร์ด</a>
      <a href="items.php">รายการอุปกรณ์</a>
      <a href="history.php">ประวัติการเบิก</a>
    </div>
    <div class="navbar__right">
      <img src="assets/images/icons/icon-folder.png" alt="" class="navbar__icon--folder">
      <img src="assets/images/icons/icon-sparkles.png" alt="" class="navbar__icon--sparkles">
    </div>
  </div>
</nav>

<!-- ── PAGE ──────────────────────────────────────────────────── -->
<main class="page-wrapper">
  <div class="page-header">
    <h1>แดชบอร์ด</h1>
    <p>ภาพรวมสต็อกวัสดุสำนักงานทั้งหมด – อัปเดตแบบเรียลไทม์</p>
  </div>

  <!-- ── FOLDER TAB CONTAINER ──────────────────────────────── -->
  <div class="folder-tabs-container" id="dashboardFolders">
    
    <!-- Folder Tab Bar -->
    <div class="folder-tab-bar" role="tablist" aria-label="Dashboard tabs">
      <div class="folder-tab active" data-tab="0" role="tab" aria-selected="true" aria-controls="overview-content">
        <span class="folder-tab__label">ภาพรวม</span>
      </div>
      <div class="folder-tab" data-tab="1" role="tab" aria-selected="false" aria-controls="alerts-content">
        <span class="folder-tab__label">แจ้งเตือน</span>
        <span class="folder-tab__badge"><?= count($alertItems) ?></span>
      </div>
      <div class="folder-tab" data-tab="2" role="tab" aria-selected="false" aria-controls="topused-content">
        <span class="folder-tab__label">สินค้าเบิกเยอะ</span>
        <span class="folder-tab__badge"><?= count($topUsed) ?></span>
      </div>
    </div>

    <!-- Folder Content Area -->
    <div class="folder-content-area">

      <!-- TAB 1: Overview -->
      <div class="folder-content active" id="overview-content" role="tabpanel">
        <div class="folder-content__header">
          <div>
            <h2 class="folder-content__title">ภาพรวมสต็อก</h2>
            <p class="folder-content__subtitle">สรุปจำนวนรายการและสถานะคงคลัง</p>
          </div>
        </div>

        <!-- KPI Cards Grid -->
        <div class="cards-grid" role="list" style="margin: 0;">
          <div class="stat-card layered-card" role="listitem" tabindex="0"
               onclick="loadCategoryInTab('all', this)" onkeydown="if(event.key==='Enter')loadCategoryInTab('all',this)">
            <div class="stat-card__badge">→</div>
            <div class="stat-card__value"><?= $totalItems ?> รายการ</div>
            <div class="stat-card__label">อุปกรณ์ทั้งหมด</div>
          </div>

          <div class="stat-card layered-card" role="listitem" tabindex="0"
               onclick="loadCategoryInTab('low', this)" onkeydown="if(event.key==='Enter')loadCategoryInTab('low',this)">
            <div class="stat-card__badge">→</div>
            <div class="stat-card__value"><?= $lowStock ?> รายการ</div>
            <div class="stat-card__label">สต็อกใกล้หมด</div>
          </div>

          <div class="stat-card layered-card" role="listitem" tabindex="0"
               onclick="loadCategoryInTab('out', this)" onkeydown="if(event.key==='Enter')loadCategoryInTab('out',this)">
            <div class="stat-card__badge">→</div>
            <div class="stat-card__value"><?= $outOfStock ?> รายการ</div>
            <div class="stat-card__label">สินค้าหมดสต็อก</div>
          </div>
        </div>

        <!-- Dynamic Result (loaded via AJAX) -->
        <div id="overviewResult" style="margin-top: 20px;"></div>
      </div><!-- /.folder-content overview -->

      <!-- TAB 2: Alerts -->
      <div class="folder-content" id="alerts-content" role="tabpanel">
        <div class="folder-content__header">
          <div>
            <h2 class="folder-content__title">อุปกรณ์ต้องเติมด่วน</h2>
            <p class="folder-content__subtitle"><?= count($alertItems) ?> รายการที่ต้องดำเนินการ</p>
          </div>
        </div>

        <div class="layered-cards">
          <?php if ($alertItems): ?>
            <div class="tbl-wrap">
              <table id="alertTable">
                <thead>
                  <tr>
                    <th style="width:10%;">รูปภาพ</th>
                    <th style="width:36%;">ชื่ออุปกรณ์</th>
                    <th style="width:18%;text-align:center;">คงเหลือ</th>
                    <th style="width:16%;text-align:center;">ขั้นต่ำ</th>
                    <th style="width:20%;text-align:center;">สถานะ</th>
                  </tr>
                </thead>
                <tbody id="alertTableBody"></tbody>
              </table>
            </div>
            <div id="alertPagination" class="pagination"></div>
          <?php else: ?>
            <div class="empty-state">
              <div class="es-icon">✅</div>
              <p>ไม่มีรายการที่ต้องแจ้งเตือน</p>
            </div>
          <?php endif; ?>
        </div>
      </div><!-- /.folder-content alerts -->

      <!-- TAB 3: Top Used -->
      <div class="folder-content" id="topused-content" role="tabpanel">
        <div class="folder-content__header">
          <div>
            <h2 class="folder-content__title">5 อันดับอุปกรณ์เบิกเยอะสุด</h2>
            <p class="folder-content__subtitle">สินค้าที่ถูกเบิกใช้งานมากที่สุด</p>
          </div>
        </div>

        <div class="layered-cards">
          <?php if ($topUsed): ?>
            <div class="tbl-wrap">
              <table id="topUsedTable">
                <thead>
                  <tr>
                    <th style="width:8%;">#</th>
                    <th style="width:12%;">รูปภาพ</th>
                    <th style="width:50%;">รายการ</th>
                    <th style="width:30%;text-align:center;">รวมเบิก</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($topUsed as $rank => $it): ?>
                  <tr>
                    <td style="font-weight:700;color:var(--accent);text-align:center;">
                      <?= $rank + 1 ?>
                    </td>
                    <td class="img-cell">
                      <img src="assets/images/<?= htmlspecialchars($it['image']) ?>"
                           alt="<?= htmlspecialchars($it['name']) ?>"
                           class="item-thumb"
                           onclick="openImageLightbox(this.src, this.alt)"
                           onerror="this.src='assets/images/default.png'">
                    </td>
                    <td>
                      <div style="font-weight:600;"><?= htmlspecialchars($it['name']) ?></div>
                      <?php $packNote = formatPackNote($it['pack_qty'], $it['unit']); ?>
                      <?php if ($packNote !== ''): ?>
                        <small class="pack-note"><?= htmlspecialchars($packNote) ?></small>
                      <?php endif; ?>
                    </td>
                    <td style="font-weight:700;text-align:center;">
                      <?= number_format((int)$it['total_out']) ?>
                      <small style="font-weight:400;color:var(--text-muted);"><?= htmlspecialchars($it['unit']) ?></small>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="empty-state">
              <div class="es-icon">📭</div>
              <p>ยังไม่มีประวัติการเบิก</p>
            </div>
          <?php endif; ?>
        </div>
      </div><!-- /.folder-content topused -->

    </div><!-- /.folder-content-area -->

  </div><!-- /.folder-tabs-container -->

</main>

<!-- ── IMAGE LIGHTBOX (click any item thumbnail to see it enlarged) ─ -->
<div class="lightbox-backdrop" id="imgLightboxBackdrop" onclick="if(event.target===this) closeImageLightbox();">
  <button type="button" class="lightbox-backdrop__close" onclick="closeImageLightbox()" aria-label="ปิด">✕</button>
  <img id="imgLightboxImg" class="lightbox-backdrop__img" src="" alt="">
</div>

<!-- ── Toast container ───────────────────────────────────────── -->
<div id="toastContainer"></div>

<script src="folder-tabs.js"></script>
<script>
// ── Folder Tab Manager ────────────────────────────────────────
class DashboardFolderManager {
  constructor() {
    this.container = document.getElementById('dashboardFolders');
    this.tabs = this.container.querySelectorAll('.folder-tab');
    this.contents = this.container.querySelectorAll('.folder-content');
    this.activeIndex = 0;
    this.isAnimating = false;
    this.init();
  }

  init() {
    this.tabs.forEach((tab, idx) => {
      tab.addEventListener('click', () => this.switchTab(idx));
      tab.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          this.switchTab(idx);
        }
      });
    });

    // Hover effects
    this.tabs.forEach((tab) => {
      tab.addEventListener('mouseenter', () => {
        if (!tab.classList.contains('active')) {
          gsap.to(tab, {
            y: -6,
            duration: 0.25,
            ease: 'power2.out',
          });
        }
      });
      tab.addEventListener('mouseleave', () => {
        if (!tab.classList.contains('active')) {
          gsap.to(tab, {
            y: 0,
            duration: 0.25,
            ease: 'power2.out',
          });
        }
      });
    });
  }

  switchTab(index) {
    if (this.isAnimating || index === this.activeIndex) return;
    this.isAnimating = true;

    const oldTab = this.tabs[this.activeIndex];
    const newTab = this.tabs[index];
    const oldContent = this.contents[this.activeIndex];
    const newContent = this.contents[index];

    const tl = gsap.timeline({
      onComplete: () => { this.isAnimating = false; },
    });

    // Fade out old content
    tl.to(oldContent, {
      opacity: 0,
      scale: 0.97,
      y: -8,
      duration: 0.35,
      ease: 'power2.in',
    }, 0);

    // Update active states
    tl.call(() => {
      oldTab.classList.remove('active');
      newTab.classList.add('active');
      oldContent.classList.remove('active');
      newContent.classList.add('active');
    }, null, 0.2);

    // Fade in new content with spring
    tl.to(newContent, {
      opacity: 1,
      scale: 1,
      y: 0,
      duration: 0.45,
      ease: 'elastic.out(1, 0.55)',
    }, 0.2);

    this.activeIndex = index;
  }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  new DashboardFolderManager();
  console.log('✨ Dashboard Folder Manager Initialized');
});

// ── Card Click Handler ────────────────────────────────────────
let categoryPageData = { items: [], category: '', currentPage: 1 };
const CATEGORY_PAGE_SIZE = 10;

function loadCategoryInTab(category, cardEl) {
  const resultEl = document.getElementById('overviewResult');
  resultEl.innerHTML = '<div class="spinner"></div>';

  fetch('ajax_handler.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=get_category_items&category=' + encodeURIComponent(category)
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) {
      resultEl.innerHTML = '<div class="empty-state"><p>เกิดข้อผิดพลาด: ' + data.error + '</p></div>';
      return;
    }
    if (!data.items || data.items.length === 0) {
      resultEl.innerHTML = '<div class="empty-state"><div class="es-icon">📭</div><p>ไม่พบรายการในหมวดนี้</p></div>';
      return;
    }

    categoryPageData = { items: data.items, category, currentPage: 1 };

    const labels = { all: '📦 อุปกรณ์ทั้งหมด', low: '⚠️ สต็อกใกล้หมด', out: '🚫 หมดสต็อก' };
    resultEl.innerHTML = '<div style="margin-top: 20px;"><h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 12px;">' + labels[category] + '</h3>' +
      '<div class="tbl-wrap"><table id="categoryTable"><thead><tr>' +
      '<th style="width:10%;">รูปภาพ</th><th style="width:42%;">ชื่อรายการ</th><th style="text-align:center;width:18%;">สต็อก</th>' +
      '<th style="text-align:center;width:14%;">หน่วย</th><th style="text-align:center;width:16%;">สถานะ</th>' +
      '</tr></thead><tbody id="categoryTableBody"></tbody></table></div>' +
      '<div id="categoryPagination" class="pagination"></div></div>';

    renderCategoryPage();

    // Smooth scroll into view
    gsap.to(window, { duration: 0.5, scrollTo: { y: resultEl, offsetY: 100 }, ease: 'power2.inOut' });
  })
  .catch(() => {
    resultEl.innerHTML = '<div class="empty-state"><p>ไม่สามารถโหลดข้อมูลได้</p></div>';
  });
}

function renderCategoryPage() {
  const { items, currentPage } = categoryPageData;
  const totalPages = Math.ceil(items.length / CATEGORY_PAGE_SIZE);
  const start = (currentPage - 1) * CATEGORY_PAGE_SIZE;
  const pageItems = items.slice(start, start + CATEGORY_PAGE_SIZE);

  let html = '';
  pageItems.forEach(it => {
    const st = parseInt(it.current_stock);
    let badgeClass, badgeText;
    if (st === 0) { badgeClass = 'badge-out-stock'; badgeText = 'หมดแล้ว'; }
    else if (st <= parseInt(it.min_stock)) { badgeClass = 'badge-low-stock'; badgeText = 'ใกล้หมด'; }
    else { badgeClass = 'badge-in-stock'; badgeText = 'มีสต็อก'; }

    html += `<tr>
      <td class="img-cell">
        <img src="assets/images/${escAttr(it.image)}" alt="${escHtml(it.name)}" class="item-thumb"
             onclick="openImageLightbox(this.src, this.alt)"
             onerror="this.src='assets/images/default.png'">
      </td>
      <td style="font-weight:600;">${escHtml(it.name)}${packNoteHtml(it.pack_qty, it.unit)}</td>
      <td class="stock-num ${st===0?'zero':st<=it.min_stock?'low':''}" style="text-align:center;">${st}</td>
      <td style="text-align:center;">${escHtml(it.unit)}</td>
      <td style="text-align:center;"><span class="badge ${badgeClass}">${badgeText}</span></td>
    </tr>`;
  });
  document.getElementById('categoryTableBody').innerHTML = html;

  renderPagination(document.getElementById('categoryPagination'), totalPages, currentPage, items.length, (p) => {
    categoryPageData.currentPage = p;
    renderCategoryPage();
    document.getElementById('overviewResult').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
}

// ── Alert table (แจ้งเตือน) pagination ──────────────────────────
const ALERT_ITEMS = <?= json_encode($alertItems, JSON_UNESCAPED_UNICODE) ?>;
const ALERT_PAGE_SIZE = 10;
let alertCurrentPage = 1;

function renderAlertPage() {
  if (!ALERT_ITEMS.length) return;
  const totalPages = Math.ceil(ALERT_ITEMS.length / ALERT_PAGE_SIZE);
  const start = (alertCurrentPage - 1) * ALERT_PAGE_SIZE;
  const pageItems = ALERT_ITEMS.slice(start, start + ALERT_PAGE_SIZE);

  let html = '';
  pageItems.forEach(it => {
    const st = parseInt(it.current_stock);
    const min = parseInt(it.min_stock);
    const badgeClass = st === 0 ? 'badge-out-stock' : 'badge-low-stock';
    const badgeText  = st === 0 ? 'หมดแล้ว' : 'ใกล้หมด';
    html += `<tr>
      <td class="img-cell">
        <img src="assets/images/${escAttr(it.image)}" alt="${escHtml(it.name)}" class="item-thumb"
             onclick="openImageLightbox(this.src, this.alt)"
             onerror="this.src='assets/images/default.png'">
      </td>
      <td>
        <div style="font-weight:600;color:var(--dark);">${escHtml(it.name)}</div>
        ${packNoteHtml(it.pack_qty, it.unit)}
      </td>
      <td class="stock-num ${st === 0 ? 'zero' : 'low'}" style="text-align:center;">
        ${st} <small style="font-weight:400;font-size:.78rem;">${escHtml(it.unit)}</small>
      </td>
      <td style="color:var(--text-muted);font-size:.88rem;text-align:center;">${min} ${escHtml(it.unit)}</td>
      <td style="text-align:center;"><span class="badge ${badgeClass}">${badgeText}</span></td>
    </tr>`;
  });
  document.getElementById('alertTableBody').innerHTML = html;

  renderPagination(document.getElementById('alertPagination'), totalPages, alertCurrentPage, ALERT_ITEMS.length, (p) => {
    alertCurrentPage = p;
    renderAlertPage();
    document.getElementById('alertTable').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
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

document.addEventListener('DOMContentLoaded', renderAlertPage);

function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
function escAttr(str) { return escHtml(str); }

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

// ── "1 แพ็ค / N หน่วย" caption shown under an item name ──────────
function packNoteHtml(packQty, unit) {
  const n = parseInt(packQty);
  if (!n || n < 1) return '';
  return `<small class="pack-note">1 แพ็ค / ${n.toLocaleString('th-TH')} ${escHtml(unit)}</small>`;
}

// ── Toast helper ────────────────────────────────────────────────
function showToast(msg, type = 'success') {
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = (type === 'success' ? '✅ ' : '❌ ') + msg;
  document.getElementById('toastContainer').appendChild(el);
  setTimeout(() => {
    el.style.animation = 'toastOut .3s ease forwards';
    setTimeout(() => el.remove(), 300);
  }, 3000);
}
</script>
</body>
</html>