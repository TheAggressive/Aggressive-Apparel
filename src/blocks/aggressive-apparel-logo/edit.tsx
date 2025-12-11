import {
  InspectorControls,
  useBlockProps,
  PanelColorSettings,
} from '@wordpress/block-editor';
import {
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { Fragment, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import type { BlockEditProps } from '@wordpress/blocks';
import './editor.css';

type Breakpoint = 'mobile' | 'tablet' | 'desktop';

type Attributes = {
  alt: string;
  lightColor: string;
  lightHoverColor: string;
  darkColor: string;
  darkHoverColor: string;
  breakpoint: Breakpoint;
  largeWidth: number;
  largeHeight?: number;
  smallWidth: number;
  smallHeight?: number;
  linkToHome: boolean;
};

const BREAKPOINT_VALUES: Record<Breakpoint, number> = {
  mobile: 480,
  tablet: 768,
  desktop: 1024,
};

// Inline SVG for large screens (full wordmark).
const SVG_LARGE = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 675 72" aria-hidden="true"><g fill="currentColor"><polygon points="513,54 525.6,72 525.6,22.7 513,22.7"/><path d="M657,44.9h-27.4c-11.3,0-14.5-0.8-16.6-2.3c-2.1-1.5-3.7-3.7-4.5-6.6H639l18-13.2h-49.3v-9h48.9l18-13.9h-72.8H594h-8.4c0,0-22.7,41-23.1,42.2C561.6,39.9,539.6,0,539.6,0h-10.8h-5.4h-65.7c-16.5,0-25,7.6-25.6,22.8H432h-18h-40.9c0.7-6,3.9-9,9.7-9H414L432,0h-38.8h-7.4H291h-3h-72v22.8h-36L198,36h6.4v8.9h-46.7V29.9c0-3.1,0.2-5.7,0.7-7.7c0.5-2.1,1.2-3.7,2.4-4.9c1.1-1.2,2.6-2.1,4.5-2.6c1.9-0.5,4.3-0.8,7.1-0.8H198L216,0h-43.9c-4.5,0-8.5,0.7-12,2c-3.5,1.3-6.4,3.2-8.8,5.8c-2.4,2.5-4.2,5.6-5.4,9.2c-0.6,1.8-1.1,3.8-1.4,5.9H108L126,36h6.4v8.9H85.7V29.9c0-3.1,0.2-5.7,0.7-7.7c0.5-2.1,1.2-3.7,2.4-4.9c1.1-1.2,2.6-2.1,4.5-2.6c1.9-0.5,4.3-0.8,7.1-0.8H126L144,0h-43.9c-4.5,0-8.5,0.7-12,2c-3.5,1.3-6.4,3.2-8.8,5.8c-2.4,2.5-4.2,5.6-5.4,9.2c-0.2,0.5-0.4,1.1-0.5,1.7V0H28.1c-4.5,0-8.4,0.7-11.9,2c-3.5,1.3-6.4,3.2-8.8,5.8C5,10.2,3.2,13.3,1.9,17C0.6,20.6,0,24.7,0,29.3V54l13.7,18V36h45.9v36l10-13.2H144h2.1h73.6l10,13.2V13.9h48c-0.4,2.9-1.4,5.1-3,6.7c-1.6,1.5-3.7,2.3-6.5,2.3H234l45,36h36l0,0c0.4,0,0.8,0,1.3,0H360h18h4h24.4h72c17.1,0,25.6-8.3,25.6-24.8V22.8h-54h-4.9c0.7-6,3.9-9,9.7-9H531L562.5,72L594,14.3v15.3c0,4.5,0.6,8.5,1.9,12.1c1.3,3.6,3.2,6.7,5.6,9.2c2.4,2.5,5.4,4.5,8.9,5.8c3.5,1.4,7.4,2.1,11.8,2.1H675L657,44.9z M59.6,23.1H14.6c0.3-1.3,0.7-2.6,1.3-3.7c0.6-1.1,1.6-2.1,3.1-2.9c1.4-0.8,3.4-1.4,5.9-1.9c2.5-0.4,5.8-0.7,10-0.7h24.8V23.1z M270.2,35.8c8.6-0.9,14.6-4.5,17.8-10.6v4.4c0,4.5,0.6,8.5,1.9,12.1c0.7,2.1,1.7,4,2.8,5.7L270.2,35.8z M389.5,44.9H378h-6.8h-54.7c-4.2,0-7.4-0.8-9.5-2.3c-2.1-1.5-3.7-3.7-4.5-6.6H333l18-13.2h-49.3v-9h59.9c-1.1,3.1-1.6,6.8-1.6,10.9V36h54h4.9c-0.4,2.9-1.4,5.1-3,6.6c-1.6,1.5-3.8,2.3-6.6,2.3H389.5z M450,36h40.9c-0.4,2.9-1.4,5.1-3,6.6c-1.6,1.5-3.8,2.3-6.6,2.3h-50.9c0.9-2.6,1.4-5.6,1.6-8.9h0.1H450z"/></g></svg>`;

// Inline SVG for small screens (icon only).
const SVG_SMALL = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 199 175" aria-hidden="true"><g fill="currentColor"><path d="M12.6,75.3c0-12.1,2.4-22,5.1-29.9c6.5-18.9,26.5-33.8,54.7-33.8h106.8L199,0H68.4c-10.8,0-20.5,1.6-29,4.8c-8.5,3.2-15.6,7.9-21.5,14C12.1,24.9,7.7,32.4,4.6,41.3C1.5,50.2,0,60.2,0,71.3V134l44.6,41l-32-43.7V75.3z"/><path d="M167.6,17.1h-5.2h-8.5H73.3c-8.8,0-16.6,1.3-23.4,3.9c-6.9,2.6-12.6,6.4-17.3,11.3c-4.7,5-8.3,11-10.8,18.2C19.2,57.6,18,65.7,18,74.8v48.6l27,35.4V88h90.4v70.8l27-35.4V29.7h5.2v101.7l-32,43.7l44.6-41V17.1h-1.8H167.6z M135.4,62.5H46.7c0.5-2.6,1.4-5.1,2.6-7.4c1.2-2.3,3.2-4.2,6-5.8c2.8-1.6,6.7-2.8,11.6-3.7c4.9-0.9,11.4-1.3,19.6-1.3h48.9V62.5z"/></g></svg>`;

const Edit = ({ attributes, setAttributes }: BlockEditProps<Attributes>) => {
  const {
    alt,
    lightColor,
    lightHoverColor,
    darkColor,
    darkHoverColor,
    breakpoint,
    largeWidth,
    largeHeight,
    smallWidth,
    smallHeight,
    linkToHome,
  } = attributes;

  // Track preview mode - auto-detect system preference.
  const [previewMode, setPreviewMode] = useState<'light' | 'dark'>(() =>
    window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
  );

  // Track screen size based on breakpoint.
  const breakpointPx = BREAKPOINT_VALUES[breakpoint];
  const [isSmallScreen, setIsSmallScreen] = useState(
    () => window.innerWidth <= breakpointPx
  );

  useEffect(() => {
    const query = window.matchMedia(`(max-width: ${breakpointPx}px)`);
    const update = () => setIsSmallScreen(query.matches);
    query.addEventListener('change', update);
    return () => query.removeEventListener('change', update);
  }, [breakpointPx]);

  useEffect(() => {
    const query = window.matchMedia('(prefers-color-scheme: dark)');
    const update = (e: MediaQueryListEvent) =>
      setPreviewMode(e.matches ? 'dark' : 'light');
    query.addEventListener('change', update);
    return () => query.removeEventListener('change', update);
  }, []);

  const blockProps = useBlockProps({
    className: 'aggressive-apparel-logo',
    'data-breakpoint': breakpoint,
  });

  const currentColor = previewMode === 'dark' ? darkColor : lightColor;
  const currentSvg = isSmallScreen ? SVG_SMALL : SVG_LARGE;
  const currentWidth = isSmallScreen ? smallWidth : largeWidth;
  const currentHeight = isSmallScreen ? smallHeight : largeHeight;

  return (
    <Fragment>
      <InspectorControls>
        <PanelBody title={__('Light Mode Colors', 'aggressive-apparel')} initialOpen={true}>
          <PanelColorSettings
            __experimentalIsRenderedInSidebar
            title=""
            colorSettings={[
              {
                value: lightColor,
                onChange: (color: string | undefined) =>
                  setAttributes({ lightColor: color || '#000000' }),
                label: __('Color', 'aggressive-apparel'),
              },
              {
                value: lightHoverColor,
                onChange: (color: string | undefined) =>
                  setAttributes({ lightHoverColor: color || '#333333' }),
                label: __('Hover Color', 'aggressive-apparel'),
              },
            ]}
          />
        </PanelBody>

        <PanelBody title={__('Dark Mode Colors', 'aggressive-apparel')} initialOpen={false}>
          <PanelColorSettings
            __experimentalIsRenderedInSidebar
            title=""
            colorSettings={[
              {
                value: darkColor,
                onChange: (color: string | undefined) =>
                  setAttributes({ darkColor: color || '#ffffff' }),
                label: __('Color', 'aggressive-apparel'),
              },
              {
                value: darkHoverColor,
                onChange: (color: string | undefined) =>
                  setAttributes({ darkHoverColor: color || '#cccccc' }),
                label: __('Hover Color', 'aggressive-apparel'),
              },
            ]}
          />
        </PanelBody>

        <PanelBody title={__('Preview', 'aggressive-apparel')} initialOpen={false}>
          <div className="aggressive-apparel-logo__preview-toggle">
            <span>{__('Mode:', 'aggressive-apparel')}</span>
            <button
              type="button"
              className={`aggressive-apparel-logo__mode-btn ${previewMode === 'light' ? 'is-active' : ''}`}
              onClick={() => setPreviewMode('light')}
            >
              {__('Light', 'aggressive-apparel')}
            </button>
            <button
              type="button"
              className={`aggressive-apparel-logo__mode-btn ${previewMode === 'dark' ? 'is-active' : ''}`}
              onClick={() => setPreviewMode('dark')}
            >
              {__('Dark', 'aggressive-apparel')}
            </button>
          </div>
        </PanelBody>

        <PanelBody title={__('Responsive', 'aggressive-apparel')} initialOpen={false}>
          <SelectControl
            label={__('Breakpoint', 'aggressive-apparel')}
            value={breakpoint}
            options={[
              { label: __('Mobile (480px)', 'aggressive-apparel'), value: 'mobile' },
              { label: __('Tablet (768px)', 'aggressive-apparel'), value: 'tablet' },
              { label: __('Desktop (1024px)', 'aggressive-apparel'), value: 'desktop' },
            ]}
            onChange={(value) => setAttributes({ breakpoint: value as Breakpoint })}
            help={__('Switch to icon below this width.', 'aggressive-apparel')}
          />
        </PanelBody>

        <PanelBody title={__('Size', 'aggressive-apparel')} initialOpen={false}>
          <div className="aggressive-apparel-logo__size-group">
            <h4>{__('Large Logo', 'aggressive-apparel')}</h4>
            <RangeControl
              label={__('Width', 'aggressive-apparel')}
              value={largeWidth}
              onChange={(value) => setAttributes({ largeWidth: value ?? 200 })}
              min={50}
              max={600}
              step={10}
            />
            <RangeControl
              label={__('Height', 'aggressive-apparel')}
              value={largeHeight ?? 0}
              onChange={(value) =>
                setAttributes({ largeHeight: value && value > 0 ? value : undefined })
              }
              min={0}
              max={200}
              step={5}
              help={__('0 = auto', 'aggressive-apparel')}
            />
          </div>

          <div className="aggressive-apparel-logo__size-group">
            <h4>{__('Small Logo', 'aggressive-apparel')}</h4>
            <RangeControl
              label={__('Width', 'aggressive-apparel')}
              value={smallWidth}
              onChange={(value) => setAttributes({ smallWidth: value ?? 48 })}
              min={24}
              max={150}
              step={4}
            />
            <RangeControl
              label={__('Height', 'aggressive-apparel')}
              value={smallHeight ?? 0}
              onChange={(value) =>
                setAttributes({ smallHeight: value && value > 0 ? value : undefined })
              }
              min={0}
              max={150}
              step={4}
              help={__('0 = auto', 'aggressive-apparel')}
            />
          </div>
        </PanelBody>

        <PanelBody title={__('Link & Accessibility', 'aggressive-apparel')} initialOpen={false}>
          <ToggleControl
            label={__('Link to Home', 'aggressive-apparel')}
            checked={linkToHome}
            onChange={(value) => setAttributes({ linkToHome: value })}
          />
          <TextControl
            label={__('Alt Text', 'aggressive-apparel')}
            value={alt}
            onChange={(value) => setAttributes({ alt: value })}
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <div
          className="aggressive-apparel-logo__svg-wrapper"
          style={{
            color: currentColor,
            width: currentWidth ? `${currentWidth}px` : 'auto',
            height: currentHeight ? `${currentHeight}px` : 'auto',
          }}
          dangerouslySetInnerHTML={{ __html: currentSvg }}
        />
      </div>
    </Fragment>
  );
};

export default Edit;
