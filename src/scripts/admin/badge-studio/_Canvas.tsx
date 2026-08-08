/**
 * Badge studio canvas — product card stage + live badge.
 *
 * The badge itself is server-compiled markup (see `_preview.ts`); the canvas
 * only owns the stage chrome around it.
 *
 * @package Aggressive_Apparel
 */

import type { CompiledBadge } from './_types';

type Props = {
  compiled: CompiledBadge;
  tone: 'light' | 'dark';
  onToneChange: (tone: 'light' | 'dark') => void;
  zoom: number;
  onZoomChange: (zoom: number) => void;
  i18n: Record<string, string>;
};

/**
 * Canvas stage with zoom, tone toggle, and positioned badge.
 *
 * @param props Component props.
 */
export default function Canvas({
  compiled,
  tone,
  onToneChange,
  zoom,
  onZoomChange,
  i18n,
}: Props) {
  return (
    <section
      className='aa-badge-studio__canvas'
      aria-label={i18n.canvas}
      id='aa-badge-studio-preview'
    >
      <div className='aa-badge-studio__canvas-bar'>
        <span className='aa-badge-studio__canvas-title'>{i18n.canvas}</span>
        <div className='aa-badge-studio__canvas-tools'>
          <div className='aa-badge-studio__zoom' aria-label={`${zoom}%`}>
            <button
              type='button'
              className='aa-badge-studio__zoom-btn'
              aria-label={i18n.zoomOut}
              disabled={zoom <= 50}
              onClick={() => onZoomChange(Math.max(50, zoom - 10))}
            >
              –
            </button>
            <span className='aa-badge-studio__zoom-value'>{zoom}%</span>
            <button
              type='button'
              className='aa-badge-studio__zoom-btn'
              aria-label={i18n.zoomIn}
              disabled={zoom >= 150}
              onClick={() => onZoomChange(Math.min(150, zoom + 10))}
            >
              +
            </button>
          </div>
          <div
            className='aa-badge-studio__tone'
            role='group'
            aria-label={i18n.appearance || i18n.canvas}
          >
            <button
              type='button'
              className={
                tone === 'light'
                  ? 'aa-badge-studio__tone-btn is-active'
                  : 'aa-badge-studio__tone-btn'
              }
              aria-pressed={tone === 'light'}
              onClick={() => onToneChange('light')}
            >
              {i18n.light}
            </button>
            <button
              type='button'
              className={
                tone === 'dark'
                  ? 'aa-badge-studio__tone-btn is-active'
                  : 'aa-badge-studio__tone-btn'
              }
              aria-pressed={tone === 'dark'}
              onClick={() => onToneChange('dark')}
            >
              {i18n.dark}
            </button>
          </div>
        </div>
      </div>
      <div
        className={`aa-badge-studio__stage aa-badge-studio__stage--${tone}`}
        style={{ colorScheme: tone }}
        data-theme={tone}
      >
        <div
          className='aa-badge-studio__stage-scale'
          style={{ zoom: zoom / 100 }}
        >
          <div className='aa-badge-studio__card'>
            <div className='aa-badge-studio__card-image'>
              <svg
                className='aa-badge-studio__garment'
                viewBox='0 0 80 96'
                aria-hidden='true'
                focusable='false'
              >
                <path d='M28 16c-2.2 3.8-6.4 6.2-12 7.2L6 28.8l8.4 9.6 6.8-3.2V76c0 5.2 5.6 9.2 18.8 9.2S58 81.2 58 76V35.2l6.8 3.2L73.2 28.8 64 23.2c-5.6-1-9.8-3.4-12-7.2C48.6 12.4 44.6 10 40 10s-8.6 2.4-12 6z' />
              </svg>
              {/*
               * Server-rendered badge span: same compiler, same
               * sanitizers, and the same escaping as the
               * storefront. Authored markup never reaches this
               * without passing Badge_Style_Schema first.
               */}
              <div
                className={`aa-badge-studio__badge-slot aa-badge-studio__badge-slot--${compiled.position}`}
                dangerouslySetInnerHTML={{
                  __html: compiled.html,
                }}
              />
            </div>
            <div className='aa-badge-studio__card-copy' aria-hidden='true'>
              <span className='aa-badge-studio__card-title' />
              <span className='aa-badge-studio__card-price' />
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
