/**
 * Custom Cursor
 *
 * Branded pointer that morphs between states based on what's under the cursor.
 * Runs only on pointer:fine (mouse) devices — CSS hides it on touch.
 *
 * State lifecycle (body[data-aa-cursor]):
 *   default  → small dot
 *   hover    → expanded ring (links, buttons)
 *   product  → large ring + "View" label (product cards)
 *   drag     → large ring + "Drag" label (horizontal-scroll sections)
 */

type CursorState = 'default' | 'hover' | 'product' | 'drag';

const CURSOR_CLASS = 'aa-cursor';
const CURSOR_INNER_CLASS = 'aa-cursor__inner';
const CURSOR_LABEL_CLASS = 'aa-cursor__label';
const STATE_ATTR = 'data-aa-cursor';

// Selectors that trigger each state (checked outermost-first via composedPath)
const PRODUCT_CARD_SELECTOR =
  '.wc-block-product-template article, .products li.product';
const DRAG_SELECTOR = '[data-aa-hscroll]';
const HOVER_SELECTOR =
  'a, button, [role="button"], label[for], input[type="submit"], select';

let raf: number | null = null;
let mouseX = -200;
let mouseY = -200;

function getState(path: EventTarget[]): CursorState {
  for (const el of path) {
    if (!(el instanceof Element)) continue;
    if (el.matches?.(DRAG_SELECTOR)) return 'drag';
    if (el.matches?.(PRODUCT_CARD_SELECTOR)) return 'product';
    if (el.matches?.(HOVER_SELECTOR)) return 'hover';
  }
  return 'default';
}

function init(): void {
  // Skip on touch/stylus devices — CSS handles the hide, but no point running JS
  if (!window.matchMedia('(pointer: fine)').matches) return;

  // Build cursor DOM
  const cursor = document.createElement('div');
  cursor.className = CURSOR_CLASS;
  cursor.setAttribute('aria-hidden', 'true');

  const inner = document.createElement('div');
  inner.className = CURSOR_INNER_CLASS;

  const label = document.createElement('span');
  label.className = CURSOR_LABEL_CLASS;

  cursor.append(inner, label);
  document.body.append(cursor);

  // Initial state
  document.body.setAttribute(STATE_ATTR, 'default');

  // Track mouse position
  document.addEventListener('mousemove', (e: MouseEvent) => {
    mouseX = e.clientX;
    mouseY = e.clientY;

    if (raf !== null) return;
    raf = requestAnimationFrame(() => {
      raf = null;
      cursor.style.transform = `translate(${mouseX}px,${mouseY}px)`;
    });

    // Update state from composed path for efficiency
    const path = e.composedPath();
    const next = getState(path);
    const current = document.body.getAttribute(STATE_ATTR) as CursorState;

    if (next !== current) {
      document.body.setAttribute(STATE_ATTR, next);
      label.textContent =
        next === 'product' ? 'View' : next === 'drag' ? 'Drag' : '';
    }
  });

  // Reset on leave
  document.addEventListener('mouseleave', () => {
    document.body.setAttribute(STATE_ATTR, 'default');
    label.textContent = '';
  });

  // Pressed state
  document.addEventListener('mousedown', () =>
    cursor.classList.add('is-pressed')
  );
  document.addEventListener('mouseup', () =>
    cursor.classList.remove('is-pressed')
  );
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
