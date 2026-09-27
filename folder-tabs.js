/**
 * folder-tabs.js – Folder Tab Style Animation Engine
 * Uses GSAP for smooth, spring-based animations
 */

class FolderTabManager {
  constructor(containerSelector, options = {}) {
    this.container = document.querySelector(containerSelector);
    if (!this.container) return;

    this.options = {
      tabSelector: '.folder-tab',
      contentSelector: '.folder-content',
      animationDuration: 0.5,
      easing: 'power3.inOut',
      ...options,
    };

    this.tabs = this.container.querySelectorAll(this.options.tabSelector);
    this.contents = this.container.querySelectorAll(this.options.contentSelector);
    this.activeIndex = 0;
    this.isAnimating = false;

    this.init();
  }

  init() {
    // Set initial state
    this.contents.forEach((content, idx) => {
      if (idx === 0) {
        content.classList.add('active');
        gsap.set(content, { opacity: 1, pointerEvents: 'auto' });
      } else {
        gsap.set(content, { opacity: 0, pointerEvents: 'none', y: 10 });
      }
    });

    // Attach click handlers
    this.tabs.forEach((tab, idx) => {
      tab.addEventListener('click', () => this.switchTab(idx));
      tab.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          this.switchTab(idx);
        }
      });
    });

    // Add hover effects to tabs
    this.tabs.forEach((tab) => {
      tab.addEventListener('mouseenter', () => {
        if (!tab.classList.contains('active')) {
          gsap.to(tab, {
            y: -4,
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

    // Timeline animation
    const tl = gsap.timeline({
      onComplete: () => {
        this.isAnimating = false;
      },
    });

    // Animate out old content with scaling + fade
    tl.to(
      oldContent,
      {
        opacity: 0,
        scale: 0.96,
        y: -10,
        duration: this.options.animationDuration * 0.6,
        ease: this.options.easing,
      },
      0
    );

    // Update tab styles
    tl.call(
      () => {
        oldTab.classList.remove('active');
        newTab.classList.add('active');
        oldContent.classList.remove('active');
        newContent.classList.add('active');
      },
      null,
      this.options.animationDuration * 0.35
    );

    // Animate in new content with spring effect
    tl.to(
      newContent,
      {
        opacity: 1,
        scale: 1,
        y: 0,
        duration: this.options.animationDuration,
        ease: 'elastic.out(1, 0.6)',
      },
      this.options.animationDuration * 0.35
    );

    this.activeIndex = index;
  }
}

/**
 * Card Ripple Effect on Click
 */
class RippleEffect {
  static attach(selector) {
    const elements = document.querySelectorAll(selector);
    elements.forEach((el) => {
      el.addEventListener('click', (e) => this.createRipple(e, el));
    });
  }

  static createRipple(event, element) {
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;

    const ripple = document.createElement('span');
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = x + 'px';
    ripple.style.top = y + 'px';
    ripple.classList.add('ripple');

    element.appendChild(ripple);

    gsap.to(ripple, {
      duration: 0.6,
      opacity: 0,
      scale: 2,
      ease: 'power1.out',
      onComplete: () => ripple.remove(),
    });
  }
}

/**
 * Stacked Card Animation
 */
class StackedCards {
  static animate(containerSelector) {
    const container = document.querySelector(containerSelector);
    if (!container) return;

    const cards = container.querySelectorAll('.card');
    cards.forEach((card, idx) => {
      gsap.set(card, {
        y: idx * 8,
        rotate: idx * 1.5,
        zIndex: cards.length - idx,
      });

      card.addEventListener('mouseenter', () => {
        gsap.to(card, {
          y: 0,
          rotate: 0,
          duration: 0.4,
          ease: 'power2.out',
        });

        // Lift other cards slightly
        cards.forEach((c, i) => {
          if (i !== idx) {
            gsap.to(c, {
              y: (i < idx ? -2 : 2) * (idx - i),
              duration: 0.4,
              ease: 'power2.out',
            });
          }
        });
      });

      card.addEventListener('mouseleave', () => {
        cards.forEach((c, i) => {
          gsap.to(c, {
            y: i * 8,
            rotate: i * 1.5,
            duration: 0.4,
            ease: 'power2.out',
          });
        });
      });
    });
  }
}

/**
 * Smooth Scroll Timeline
 */
class TimelineScroll {
  static init(scrollContainerSelector) {
    const container = document.querySelector(scrollContainerSelector);
    if (!container) return;

    // Smooth scroll behavior
    container.addEventListener('wheel', (e) => {
      if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
        e.preventDefault();
        gsap.to(container, {
          scrollLeft: container.scrollLeft + e.deltaY,
          duration: 0.4,
          ease: 'power2.out',
        });
      }
    });
  }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  // GSAP animations will be initialized per page
  console.log('✨ Folder Tab Engine Loaded');
});
