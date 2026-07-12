/**
 * Tests for split-story attribute → class/style derivation.
 */

import {
  SPLIT_STORY_DEFAULTS,
  clampMediaWidth,
  getSplitStoryClassName,
  getSplitStoryStyle,
  resolveBlockGap,
  type SplitStoryAttributes,
} from '../props';

const attrs = (
  overrides: Partial<SplitStoryAttributes> = {}
): SplitStoryAttributes => ({ ...SPLIT_STORY_DEFAULTS, ...overrides });

describe('clampMediaWidth', () => {
  it('clamps to the 25–75 editorial range and rounds', () => {
    expect(clampMediaWidth(10)).toBe(25);
    expect(clampMediaWidth(90)).toBe(75);
    expect(clampMediaWidth(42.4)).toBe(42);
  });

  it('falls back to the default for non-finite input', () => {
    expect(clampMediaWidth(Number.NaN)).toBe(SPLIT_STORY_DEFAULTS.mediaWidth);
  });
});

describe('getSplitStoryClassName', () => {
  it('builds the default class list', () => {
    expect(getSplitStoryClassName(attrs())).toBe(
      'aa-split-story aa-split-story--media-left aa-split-story--viewport aa-split-story--sticky aa-split-story--stack-media-first'
    );
  });

  it('reflects each option', () => {
    const cls = getSplitStoryClassName(
      attrs({
        mediaPosition: 'right',
        mediaHeight: 'content',
        sticky: false,
        stackOrder: 'content-first',
      })
    );
    expect(cls).toContain('aa-split-story--media-right');
    expect(cls).toContain('aa-split-story--content');
    expect(cls).toContain('aa-split-story--stack-content-first');
    expect(cls).not.toContain('aa-split-story--sticky');
  });
});

describe('getSplitStoryStyle', () => {
  it('always sets the clamped media width', () => {
    expect(getSplitStoryStyle(attrs({ mediaWidth: 40 }))).toEqual({
      '--aa-split-media-width': '40%',
    });
    expect(getSplitStoryStyle(attrs({ mediaWidth: 200 }))).toEqual({
      '--aa-split-media-width': '75%',
    });
  });

  it('adds the sticky offset only when sticky and > 0', () => {
    expect(getSplitStoryStyle(attrs({ sticky: true, stickyTop: 2 }))).toEqual({
      '--aa-split-media-width': '50%',
      '--aa-split-sticky-top': '2rem',
    });
    expect(
      getSplitStoryStyle(attrs({ sticky: true, stickyTop: 0 }))
    ).not.toHaveProperty('--aa-split-sticky-top');
    expect(
      getSplitStoryStyle(attrs({ sticky: false, stickyTop: 5 }))
    ).not.toHaveProperty('--aa-split-sticky-top');
  });

  it('wires the native (axial) blockGap into --aa-split-gap', () => {
    // { top: vertical, left: horizontal } → `gap: <row> <column>`.
    expect(
      getSplitStoryStyle(
        attrs({
          style: { spacing: { blockGap: { top: '1.5rem', left: '3rem' } } },
        })
      )['--aa-split-gap']
    ).toBe('1.5rem 3rem');
    // A single value applies to both axes.
    expect(
      getSplitStoryStyle(attrs({ style: { spacing: { blockGap: '2rem' } } }))[
        '--aa-split-gap'
      ]
    ).toBe('2rem');
    expect(getSplitStoryStyle(attrs())).not.toHaveProperty('--aa-split-gap');
  });
});

describe('resolveBlockGap', () => {
  it('returns undefined when unset', () => {
    expect(resolveBlockGap(undefined)).toBeUndefined();
  });

  it('passes raw lengths through and collapses equal axes', () => {
    expect(resolveBlockGap('2rem')).toBe('2rem');
    expect(resolveBlockGap({ top: '1rem', left: '1rem' })).toBe('1rem');
  });

  it('builds a row/column pair from an axial object', () => {
    expect(resolveBlockGap({ top: '1rem', left: '2rem' })).toBe('1rem 2rem');
    expect(resolveBlockGap({ left: '2rem' })).toBe('0 2rem');
  });

  it('resolves spacing-preset references', () => {
    expect(resolveBlockGap('var:preset|spacing|40')).toBe(
      'var(--wp--preset--spacing--40)'
    );
    expect(
      resolveBlockGap({ top: 'var:preset|spacing|30', left: '2rem' })
    ).toBe('var(--wp--preset--spacing--30) 2rem');
  });
});
