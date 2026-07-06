import type { CSSProperties } from 'react';

export const EDITOR_COLOR_TOKENS = {
  foreground: 'var(--aa-color-foreground, currentColor)',
  muted: 'var(--aa-color-foreground-muted, currentColor)',
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
