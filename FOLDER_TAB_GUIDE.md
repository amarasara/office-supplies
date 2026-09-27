# 🎯 Folder Tab Style UI - Implementation Guide

## 📊 Dashboard Features

### Layout
```
┌─────────────────────────────────────┐
│ 📊 แดชบอร์ด                          │
│ ภาพรวมสต็อกวัสดุสำนักงาน            │
├─────────────────────────────────────┤
│ [📊 ภาพรวม] [🔔 แจ้งเตือน] [🔥 สินค้าเบิกเยอะ] │
├─────────────────────────────────────┤
│                                       │
│  📊 Summary Cards (KPI)              │
│  - อุปกรณ์ทั้งหมด: XXX               │
│  - สต็อกใกล้หมด: X                   │
│  - หมดแล้ว: X                        │
│                                       │
│  [📦 Card 1] [📦 Card 2] [📦 Card 3]│
│                                       │
└─────────────────────────────────────┘
```

### Tabs in Dashboard
1. **ภาพรวม** (Overview)
   - KPI summary cards
   - Three clickable stat cards
   - Dynamic result table via AJAX

2. **แจ้งเตือน** (Alerts)
   - Items with low/out of stock
   - Sortable table view
   - Stock counts and badges

3. **สินค้าเบิกเยอะ** (Top Used)
   - Top 5 most requisitioned items
   - Usage statistics
   - Rankings

---

## 📜 History Features

### Layout
```
┌────────────────────────────────────────┐
│ 📜 ประวัติการเบิก                       │
├────────────────────────────────────────┤
│ [📦 Item 1] [📦 Item 2] ... [+ 2 อื่นๆ]│
├────────────────────────────────────────┤
│                                         │
│  Item Details:                          │
│  [Image] Item Name                      │
│          Stock: X | Unit: EA            │
│          Status: ✅ / ⚠️ / ❌          │
│                                         │
│  📋 ประวัติการเบิก (5 รายการ)           │
│  ┌─────────────────────────────────┐  │
│  │ 📅 01/09/2026 - 10:30           │  │
│  │ ผู้เบิก: John Doe               │  │  5 ชิ้น
│  │ แผนก: Sales                     │  │  
│  │ หมายเหตุ: For client meeting    │  │
│  └─────────────────────────────────┘  │
│  ┌─────────────────────────────────┐  │
│  │ 📅 31/08/2026 - 14:15           │  │  3 ชิ้น
│  │ ...                              │  │
│  └─────────────────────────────────┘  │
│                                         │
└────────────────────────────────────────┘
```

### Features
- **Tab Navigation**: Click to switch between items
- **Item Details**: Image, stock count, unit, status badge
- **Transaction Cards**: Each transaction shows:
  - Date and time
  - Requester name
  - Department
  - Quantity with unit
  - Notes (optional)
- **Responsive**: Adapts to mobile screens
- **Performance**: Lazy loads transaction history

---

## ✨ Animation Specifications

### Tab Switching
- **Duration**: 0.5 seconds
- **Easing**: elastic.out(1, 0.55)
- **Effect**: Fade + scale + slide

### Hover Effects
- **Tab Hover**: 6px upward lift, 0.25s smooth
- **Card Hover**: Border color change, shadow elevation
- **Ripple**: Expanding circle on click

### Content Transitions
- **Fade Out**: 0.35s power2.in
- **State Change**: Instant (0.2s mark)
- **Fade In**: 0.45s elastic.out

---

## 🎨 Color Scheme

| Element | Color | Usage |
|---------|-------|-------|
| Accent | `#F54F1B` | Primary orange |
| Dark | `#1E223D` | Text & backgrounds |
| Surface | `#FFFFFF` | Cards & tabs |
| Background | `#F5F3F0` | Page background |
| Border | `#E8E4E0` | Lines & dividers |
| Success | `#27A34D` | Stock available |
| Warning | `#D97F2D` | Low stock |
| Danger | `#6C151E` | Out of stock |

---

## 🔧 Technical Stack

### Libraries
- **GSAP 3.12.2**: Animation engine
- **ScrollToPlugin**: Smooth scrolling
- **Vanilla JavaScript**: No dependencies required

### CSS Features
- CSS Grid for responsive layouts
- CSS Custom Properties (Variables)
- Gradient backgrounds
- Box shadows for depth
- Border radius for rounded corners

### Browser Support
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ❌ Internet Explorer 11

---

## 📱 Responsive Breakpoints

### Desktop (1024px+)
- Full folder tab bar with all items
- Side-by-side layouts

### Tablet (768px)
- Reduced padding
- Adjusted font sizes
- Grid: 2 columns

### Mobile (480px)
- Single column layouts
- Compact tab size
- Smaller badges (18px)
- Minimal padding

---

## 🚀 Usage Instructions

### For Developers

#### 1. Add to Page
```html
<!-- Include GSAP -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollToPlugin.min.js"></script>

<!-- Include CSS -->
<link rel="stylesheet" href="style.css">

<!-- Include JS -->
<script src="folder-tabs.js"></script>
```

#### 2. Create Folder Tab Container
```html
<div class="folder-tabs-container" id="myFolder">
  <div class="folder-tab-bar">
    <div class="folder-tab active" data-tab="0">
      <span class="folder-tab__icon">📊</span>
      <span class="folder-tab__label">Tab 1</span>
    </div>
    <!-- More tabs -->
  </div>
  
  <div class="folder-content-area">
    <div class="folder-content active">
      <!-- Content 1 -->
    </div>
    <!-- More content -->
  </div>
</div>
```

#### 3. Initialize Manager
```javascript
class CustomFolderManager {
  constructor() {
    this.tabs = document.querySelectorAll('.folder-tab');
    this.contents = document.querySelectorAll('.folder-content');
    // ... implementation
  }
}

document.addEventListener('DOMContentLoaded', () => {
  new CustomFolderManager();
});
```

---

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| Tabs not animating | Verify GSAP is loaded before folder-tabs.js |
| Content not switching | Check data-tab attributes match indices |
| Slow animation | Reduce animation duration in manager |
| Mobile not responsive | Check viewport meta tag in head |

---

## 📈 Performance Tips

1. **Lazy Load Content**: Load transaction history on tab click
2. **Minimize Repaints**: Use transform & opacity only
3. **Debounce Events**: Prevent rapid tab clicks
4. **Cache DOM Queries**: Store selectors in variables
5. **Use CSS Transforms**: Avoid width/height changes

---

## 📝 File Structure

```
office-supplies/
├── style.css              ← Folder tab CSS (~350 lines)
├── folder-tabs.js         ← Animation engine
├── dashboard.php          ← Dashboard with folder tabs
├── history.php            ← History with folder tabs
├── assets/
│   └── images/
└── [other files]
```

---

**Last Updated**: 2 September 2026
**Version**: 1.0
**Status**: Production Ready ✅
