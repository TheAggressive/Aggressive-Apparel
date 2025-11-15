/**
 * Dark Mode Toggle Block Frontend Script
 *
 * @package Aggressive_Apparel
 */

interface DarkModeState {
  isDark: boolean;
  isSystemPreference: boolean;
}

class DarkModeToggle {
  private button: HTMLButtonElement;
  private storageKey = 'aggressive-apparel-dark-mode';
  private state: DarkModeState;

  constructor(button: HTMLButtonElement) {
    this.button = button;
    this.state = this.getInitialState();

    this.init();
    this.updateUI();
    this.applyTheme();
  }

  private getInitialState(): DarkModeState {
    // Check localStorage first
    const stored = localStorage.getItem(this.storageKey);
    if (stored) {
      return {
        isDark: stored === 'dark',
        isSystemPreference: false,
      };
    }

    // Fall back to system preference
    const prefersDark = window.matchMedia(
      '(prefers-color-scheme: dark)'
    ).matches;
    return {
      isDark: prefersDark,
      isSystemPreference: true,
    };
  }

  private init(): void {
    // Bind event listeners
    this.button.addEventListener('click', this.toggle.bind(this));

    // Listen for system preference changes
    window
      .matchMedia('(prefers-color-scheme: dark)')
      .addEventListener('change', this.handleSystemPreferenceChange.bind(this));

    // Listen for custom events from other instances
    document.addEventListener(
      'darkModeToggle',
      this.handleCustomEvent.bind(this)
    );
  }

  private toggle(): void {
    this.state.isDark = !this.state.isDark;
    this.state.isSystemPreference = false;

    this.saveState();
    this.updateUI();
    this.applyTheme();

    // Dispatch custom event for other instances
    document.dispatchEvent(
      new CustomEvent('darkModeToggle', {
        detail: { isDark: this.state.isDark },
      })
    );
  }

  private handleSystemPreferenceChange(event: MediaQueryListEvent): void {
    // Only update if user hasn't set a manual preference
    if (this.state.isSystemPreference) {
      this.state.isDark = event.matches;
      this.updateUI();
      this.applyTheme();
    }
  }

  private handleCustomEvent(event: CustomEvent): void {
    this.state.isDark = event.detail.isDark;
    this.state.isSystemPreference = false;
    this.updateUI();
    this.applyTheme();
  }

  private updateUI(): void {
    if (this.state.isDark) {
      this.button.classList.add('is-active');
      this.button.setAttribute('aria-pressed', 'true');
      this.button.setAttribute(
        'aria-label',
        this.button.getAttribute('data-label-dark') || 'Switch to light mode'
      );
    } else {
      this.button.classList.remove('is-active');
      this.button.setAttribute('aria-pressed', 'false');
      this.button.setAttribute(
        'aria-label',
        this.button.getAttribute('data-label-light') || 'Switch to dark mode'
      );
    }
  }

  private applyTheme(): void {
    const html = document.documentElement;

    if (this.state.isDark) {
      html.setAttribute('data-theme', 'dark');
      html.classList.add('is-dark-mode');
      html.classList.remove('is-light-mode');
    } else {
      html.setAttribute('data-theme', 'light');
      html.classList.add('is-light-mode');
      html.classList.remove('is-dark-mode');
    }
  }

  private saveState(): void {
    localStorage.setItem(this.storageKey, this.state.isDark ? 'dark' : 'light');
  }

  public destroy(): void {
    this.button.removeEventListener('click', this.toggle.bind(this));
    window
      .matchMedia('(prefers-color-scheme: dark)')
      .removeEventListener(
        'change',
        this.handleSystemPreferenceChange.bind(this)
      );
    document.removeEventListener(
      'darkModeToggle',
      this.handleCustomEvent.bind(this)
    );
  }
}

// Initialize all toggle buttons on the page
function initDarkModeToggles(): void {
  const buttons = document.querySelectorAll<HTMLButtonElement>(
    '.dark-mode-toggle__button'
  );

  buttons.forEach(button => {
    if (!button.hasAttribute('data-initialized')) {
      button.setAttribute('data-initialized', 'true');
      new DarkModeToggle(button);
    }
  });
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initDarkModeToggles);
} else {
  initDarkModeToggles();
}

// Initialize on AJAX content updates (for dynamic content)
if (typeof wp !== 'undefined' && wp.hooks) {
  wp.hooks.addAction('wp_body_open', 'aggressive-apparel', initDarkModeToggles);
}

// Export for potential external use
declare global {
  // eslint-disable-next-line no-unused-vars
  interface Window {
    DarkModeToggle: typeof DarkModeToggle;
    initDarkModeToggles: typeof initDarkModeToggles;
  }
}

window.DarkModeToggle = DarkModeToggle;
window.initDarkModeToggles = initDarkModeToggles;
