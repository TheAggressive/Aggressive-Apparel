/**
 * Source locks for parallax CSS hygiene.
 *
 * @jest-environment jsdom
 */

import fs from 'fs';
import path from 'path';

const STYLE_PATH = path.join(__dirname, '../style.css');

describe('parallax style.css hygiene', () => {
  const css = fs.readFileSync(STYLE_PATH, 'utf8');

  it('keeps the disable-on-mobile gate at 768px for the opt-in class', () => {
    expect(css).toMatch(/@media\s*\(width\s*<=\s*768px\)/);
    expect(css).toMatch(
      /\.aggressive-apparel-parallax--disable-on-mobile\s+\[data-parallax-enabled="true"\]/
    );
  });

  it('does not ship unused container-type or dead layer/fallback chrome', () => {
    expect(css).not.toMatch(/container-type\s*:/);
    expect(css).not.toMatch(/__visual-layer/);
    expect(css).not.toMatch(/__layer--foreground/);
    expect(css).not.toMatch(/__fallback/);
  });

  it('scopes will-change to intersecting scroll-only scenes', () => {
    expect(css).toMatch(
      /\.aggressive-apparel-parallax--intersecting:not\(\.aggressive-apparel-parallax--mouse-interaction\)\s+\[data-parallax-enabled="true"\]/
    );
    expect(css).toMatch(/will-change:\s*transform/);
  });
});
