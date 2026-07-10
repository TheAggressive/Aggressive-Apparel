import type { CSSProperties } from 'react';

/**
 * Fixed WordPress admin chrome colors for editor UI that lives outside the
 * canvas iframe (sidebars, popovers, More menu). Theme `--aa-*` tokens often
 * fail to resolve there and produced unreadable white-on-white labels.
 *
 * Prefer these over EDITOR_COLOR_TOKENS for inspector chrome text/borders.
 */
export const ADMIN_CHROME_COLORS = {
  text: '#1e1e1e',
  muted: '#757575',
  border: '#ddd',
  borderStrong: '#999',
  surface: '#fff',
  surfaceOpen: '#f0f0f0',
} as const;

export const EDITOR_COLOR_TOKENS = {
  foreground: 'var(--aa-color-foreground, currentColor)',
  // Adaptive foreground-muted is tuned for page body copy; in the block
  // editor sidebar it reads too faint at 10–12px. Mix from foreground so
  // secondary text stays readable in both admin chrome and the canvas.
  muted:
    'color-mix(in srgb, var(--aa-color-foreground, currentColor) 70%, transparent)',
  subtle: 'var(--aa-color-muted-50, currentColor)',
  surface: 'var(--aa-color-surface-elevated, transparent)',
  neutralBg: 'var(--aa-color-neutral-bg, transparent)',
  border: 'var(--aa-color-border-default, currentColor)',
  info: 'var(--aa-color-info, currentColor)',
  infoBg: 'var(--aa-color-info-bg, transparent)',
  infoBorder: 'var(--aa-color-info-border, currentColor)',
  success: 'var(--aa-color-success, currentColor)',
  successBg: 'var(--aa-color-success-bg, transparent)',
  error: 'var(--aa-color-error, currentColor)',
  errorBg: 'var(--aa-color-error-bg, transparent)',
} as const;

/** Help copy for admin chrome (sidebar / popover), not canvas. */
export const ADMIN_HELP_TEXT_STYLE: CSSProperties = {
  fontSize: '12px',
  color: ADMIN_CHROME_COLORS.muted,
  marginTop: 0,
};

export const EDITOR_RADIUS_TOKENS = {
  control: 'var(--aa-radius-control, 4px)',
  card: 'var(--aa-radius-card, 8px)',
} as const;

export const EDITOR_HELP_TEXT_STYLE: CSSProperties = {
  fontSize: '12px',
  color: EDITOR_COLOR_TOKENS.muted,
};

export const EDITOR_META_TEXT_STYLE: CSSProperties = {
  fontSize: '11px',
  color: EDITOR_COLOR_TOKENS.muted,
};

/*
 * Border-only grouping — deliberately NO background. Inspector fieldsets
 * wrap WordPress components whose text color follows the admin scheme;
 * painting a theme-token background behind them produced unreadable
 * dark-on-dark labels whenever the token resolved to a dark value.
 */
export const EDITOR_FIELDSET_STYLE: CSSProperties = {
  padding: '12px',
  border: `1px solid ${EDITOR_COLOR_TOKENS.border}`,
  borderRadius: EDITOR_RADIUS_TOKENS.control,
};

export const EDITOR_INFO_NOTICE_STYLE: CSSProperties = {
  padding: '12px',
  backgroundColor: EDITOR_COLOR_TOKENS.infoBg,
  border: `1px solid ${EDITOR_COLOR_TOKENS.infoBorder}`,
  borderRadius: EDITOR_RADIUS_TOKENS.control,
  color: EDITOR_COLOR_TOKENS.info,
};
