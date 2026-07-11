/**
 * Dark Mode Toggle Block Frontend Script
 *
 * Applies light/dark theme via color-scheme + data-theme. Uses the View
 * Transitions API when available so light-dark() colors animate smoothly
 * (CSS color transitions alone do not interpolate light-dark()).
 *
 * Other theme code (favicon override, WCPay appearance) syncs by listening
 * for the `darkModeToggle` CustomEvent on `document`.
 *
 * @package Aggressive_Apparel
 */

import {
  hasStoredColorScheme,
  getStoredColorScheme,
  resolveColorScheme,
  storeColorScheme,
} from '../../utils/color-scheme-storage';

interface DarkModeState {
  isDark: boolean;
  isSystemPreference: boolean;
}

class DarkModeToggle {
  private button: HTMLButtonElement;
  private state: DarkModeState;
  private mediaQuery: MediaQueryList;

  constructor(button: HTMLButtonElement) {
    this.button = button;
    this.mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

    const { scheme, isSystemPreference } = resolveColorScheme(this.mediaQuery);
    this.state = { isDark: scheme === 'dark', isSystemPreference };

    this.button.addEventListener('click', () => this.toggle());
    this.mediaQuery.addEventListener('change', event =>
      this.handleSystemPreferenceChange(event)
    );
    document.addEventListener('darkModeToggle', event =>
      this.handleCustomEvent(event)
    );

    this.updateUI();
    this.applyTheme(false);
  }

  private toggle(): void {
    this.state.isDark = !this.state.isDark;
    this.state.isSystemPreference = false;

    storeColorScheme(this.state.isDark ? 'dark' : 'light');
    this.updateUI();
    this.applyTheme(true);

    document.dispatchEvent(
      new CustomEvent('darkModeToggle', {
        detail: { isDark: this.state.isDark },
      })
    );
  }

  private handleSystemPreferenceChange(event: MediaQueryListEvent): void {
    if (!this.state.isSystemPreference) {
      return;
    }

    this.state.isDark = event.matches;
    this.updateUI();
    this.applyTheme(true);
  }

  private handleCustomEvent(event: Event): void {
    const customEvent = event as CustomEvent<{ isDark: boolean }>;
    if (customEvent.detail?.isDark === this.state.isDark) {
      return;
    }

    this.state.isDark = Boolean(customEvent.detail?.isDark);
    this.state.isSystemPreference = false;
    this.updateUI();
    this.applyTheme(false);
  }

  private updateUI(): void {
    const isDark = this.state.isDark;
    this.button.classList.toggle('is-active', isDark);
    this.button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
    this.button.setAttribute(
      'aria-label',
      this.button.getAttribute(
        isDark ? 'data-label-dark' : 'data-label-light'
      ) || (isDark ? 'Switch to light mode' : 'Switch to dark mode')
    );

    const labelEl = this.button.querySelector<HTMLElement>(
      '.dark-mode-toggle__label'
    );
    if (!labelEl) {
      return;
    }

    const lightText =
      this.button.getAttribute('data-text-label-light') ||
      labelEl.textContent ||
      '';
    const darkText =
      this.button.getAttribute('data-text-label-dark') || lightText;

    labelEl.textContent = isDark ? darkText : lightText;
  }

  /**
   * Apply theme to the document.
   *
   * @param animate - When true, use View Transitions if supported.
   */
  private applyTheme(animate: boolean): void {
    const run = () => {
      const html = document.documentElement;

      // Match Color_Scheme_Bootstrap head script (anti-flash) attributes.
      if (this.state.isDark) {
        html.style.colorScheme = 'dark';
        html.setAttribute('data-theme', 'dark');
        html.classList.add('is-dark-mode');
        html.classList.remove('is-light-mode');
      } else {
        html.style.colorScheme = this.state.isSystemPreference ? '' : 'light';
        html.setAttribute('data-theme', 'light');
        html.classList.add('is-light-mode');
        html.classList.remove('is-dark-mode');
      }
    };

    const canAnimate =
      animate &&
      typeof document.startViewTransition === 'function' &&
      !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (canAnimate) {
      document.startViewTransition(run);
      return;
    }

    run();
  }
}

function initDarkModeToggles(): void {
  // Migrate legacy keys even when no toggle is on the page (favicon / WCPay).
  if (hasStoredColorScheme()) {
    getStoredColorScheme();
  }

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

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initDarkModeToggles);
} else {
  initDarkModeToggles();
}
