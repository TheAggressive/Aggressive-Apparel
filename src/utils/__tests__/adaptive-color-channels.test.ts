/**
 * @jest-environment jsdom
 */

import {
  blockSupportsAdaptiveColors,
  buildAdaptiveStylePayload,
  discoverAdaptiveColorChannels,
  pairToCssValue,
  resolveAdaptiveColors,
  setAdaptiveColorChannel,
} from '../adaptive-color-channels';

describe('adaptive-color-channels', () => {
  describe('discoverAdaptiveColorChannels', () => {
    it('enables background + text for color: true shorthand', () => {
      const channels = discoverAdaptiveColorChannels('core/group', {
        supports: { color: true },
      });
      const ids = channels.map(channel => channel.id);
      expect(ids).toEqual(['text', 'background']);
    });

    it('honors explicit color opt-ins and opt-outs', () => {
      const channels = discoverAdaptiveColorChannels('core/paragraph', {
        supports: {
          color: {
            background: false,
            text: true,
            link: true,
            heading: true,
          },
          border: { color: true },
        },
      });
      const ids = channels.map(channel => channel.id);
      expect(ids).toEqual(['text', 'link', 'linkHover', 'heading', 'border']);
    });

    it('groups link + linkHover as a dual-tab Link control', () => {
      const channels = discoverAdaptiveColorChannels('core/paragraph', {
        supports: { color: { link: true } },
      });
      const link = channels.find(channel => channel.id === 'link');
      const linkHover = channels.find(channel => channel.id === 'linkHover');

      expect(link?.group).toBe('link');
      expect(link?.presentation).toBe('dual-tabs');
      expect(linkHover?.group).toBe('link');
      expect(linkHover?.presentation).toBe('dual-tabs');
    });

    it('discovers overlay from allowlisted custom attributes', () => {
      const channels = discoverAdaptiveColorChannels(
        'aggressive-apparel/modal',
        {
          supports: { color: true },
          attributes: {
            overlayColor: { type: 'string' },
          },
        }
      );
      expect(channels.map(channel => channel.id)).toContain('overlay');
    });

    it('returns empty when the block has no color surfaces', () => {
      expect(
        blockSupportsAdaptiveColors('core/spacer', {
          supports: { spacing: true },
        })
      ).toBe(false);
    });
  });

  describe('resolveAdaptiveColors', () => {
    it('merges legacy attributes under the channel map', () => {
      expect(
        resolveAdaptiveColors({
          adaptiveBackground: { light: '#fff', dark: '#000' },
          adaptiveText: { light: '#111' },
          adaptiveColors: {
            link: { light: 'red', dark: 'pink' },
            text: { light: '#222', dark: '#eee' },
          },
        })
      ).toEqual({
        background: { light: '#fff', dark: '#000' },
        text: { light: '#222', dark: '#eee' },
        link: { light: 'red', dark: 'pink' },
      });
    });
  });

  describe('buildAdaptiveStylePayload', () => {
    it('emits CSS vars, marker classes, and wrapper properties', () => {
      const channels = discoverAdaptiveColorChannels('core/group', {
        supports: {
          color: { background: true, text: true, link: true },
          border: true,
        },
      });

      const payload = buildAdaptiveStylePayload(
        {
          adaptiveColors: {
            background: { light: 'white', dark: 'black' },
            text: { light: '#111', dark: '#eee' },
            link: { light: 'blue', dark: 'cyan' },
            border: { light: '#ccc', dark: '#333' },
          },
        },
        channels
      );

      expect(payload.style.background).toBe('light-dark(white, black)');
      expect(payload.style.color).toBe('light-dark(#111, #eee)');
      expect(payload.style.borderColor).toBe('light-dark(#ccc, #333)');
      expect(payload.style['--aa-adaptive-link']).toBe(
        'light-dark(blue, cyan)'
      );
      expect(payload.classNames).toEqual(
        expect.arrayContaining([
          'aa-has-adaptive-bg',
          'aa-has-adaptive-color',
          'aa-has-adaptive-link',
          'aa-has-adaptive-border',
        ])
      );
    });

    it('syncs overlay into the native attribute channel', () => {
      const channels = discoverAdaptiveColorChannels(
        'aggressive-apparel/modal',
        {
          attributes: { overlayColor: { type: 'string' } },
        }
      );

      const payload = buildAdaptiveStylePayload(
        {
          adaptiveColors: {
            overlay: { light: 'rgba(0,0,0,.2)', dark: 'rgba(0,0,0,.6)' },
          },
        },
        channels
      );

      expect(payload.attributeSync.overlayColor).toBe(
        'light-dark(rgba(0,0,0,.2), rgba(0,0,0,.6))'
      );
      expect(payload.classNames).toEqual([]);
    });
  });

  describe('setAdaptiveColorChannel', () => {
    it('writes the map, clears legacy keys, and clears core conflicts', () => {
      const patch = setAdaptiveColorChannel(
        {
          adaptiveBackground: { light: '#fff', dark: '#000' },
          backgroundColor: 'surface',
          style: { color: { background: '#fff', text: '#111' } },
        },
        'background',
        { light: 'oklch(1 0 0)', dark: 'oklch(0 0 0)' }
      );

      expect(patch.adaptiveBackground).toBeUndefined();
      expect(patch.backgroundColor).toBeUndefined();
      expect(patch.adaptiveColors?.background).toEqual({
        light: 'oklch(1 0 0)',
        dark: 'oklch(0 0 0)',
      });
      expect(patch.style?.color?.background).toBeUndefined();
      expect(patch.style?.color?.text).toBe('#111');
    });

    it('syncs overlayColor when setting the overlay channel', () => {
      const patch = setAdaptiveColorChannel({}, 'overlay', {
        light: '#000',
        dark: '#111',
      });

      expect(patch.overlayColor).toBe('light-dark(#000, #111)');
      expect(pairToCssValue(patch.adaptiveColors?.overlay)).toBe(
        'light-dark(#000, #111)'
      );
    });
  });
});
