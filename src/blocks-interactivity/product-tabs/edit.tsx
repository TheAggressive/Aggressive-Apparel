/**
 * Product Tabs Block — Editor Component.
 *
 * @package Aggressive_Apparel
 * @since 1.87.0
 */

import { __ } from '@wordpress/i18n';
import {
  useBlockProps,
  InspectorControls,
  useSettings,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
  PanelBody,
  SelectControl,
  ToggleControl,
  FontSizePicker,
} from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  flattenPresetColors,
  fromPresetColorRef,
  toPresetColorRef,
  type PresetColorOrigin,
} from '../../utils/preset-colors';

interface ProductTabsAttributes {
  displayStyle: string;
  hideContentTitles: boolean;
  accordionExclusive: boolean;
  headingFontSize: string;
  headingColor: string;
  accentColor: string;
}

interface FontSize {
  name?: string;
  slug: string;
  size: string;
}

const STYLE_OPTIONS = [
  { value: '', label: __('Use global default', 'aggressive-apparel') },
  { value: 'accordion', label: __('Accordion', 'aggressive-apparel') },
  { value: 'inline', label: __('Inline Sections', 'aggressive-apparel') },
  { value: 'modern-tabs', label: __('Modern Tabs', 'aggressive-apparel') },
  { value: 'scrollspy', label: __('Scrollspy', 'aggressive-apparel') },
];

export default function Edit({
  attributes,
  setAttributes,
  clientId,
}: BlockEditProps<ProductTabsAttributes>) {
  const {
    displayStyle,
    hideContentTitles,
    accordionExclusive,
    headingFontSize,
    headingColor,
    accentColor,
  } = attributes;
  const showAccordionOptions =
    displayStyle === 'accordion' || displayStyle === '';
  const blockProps = useBlockProps({ className: 'aa-product-info' });

  const colorGradientSettings = useMultipleOriginColorsAndGradients();
  const presetColors = flattenPresetColors(
    colorGradientSettings.colors as PresetColorOrigin[] | undefined
  );
  const [fontSizes] = useSettings('typography.fontSizes') as [
    FontSize[] | undefined,
  ];

  const previewAccent = fromPresetColorRef(accentColor, presetColors);

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Display Style', 'aggressive-apparel')}>
          <SelectControl
            label={__('Tab layout', 'aggressive-apparel')}
            value={displayStyle}
            options={STYLE_OPTIONS}
            onChange={(val: string) => setAttributes({ displayStyle: val })}
          />
          <p
            style={{ fontSize: '0.75rem', opacity: 0.7, margin: '0.5rem 0 0' }}
          >
            {__(
              'The global default is set under Products → Product Tabs.',
              'aggressive-apparel'
            )}
          </p>
          <ToggleControl
            label={__('Hide section headings', 'aggressive-apparel')}
            help={__(
              "Removes the title shown above each tab's content. Affects Inline and Scrollspy layouts.",
              'aggressive-apparel'
            )}
            checked={hideContentTitles}
            onChange={(val: boolean) =>
              setAttributes({ hideContentTitles: val })
            }
          />
          {showAccordionOptions && (
            <ToggleControl
              label={__(
                'Only one section open at a time',
                'aggressive-apparel'
              )}
              help={__(
                'Accordion layout: opening a section closes the others. Off by default (sections open independently).',
                'aggressive-apparel'
              )}
              checked={accordionExclusive}
              onChange={(val: boolean) =>
                setAttributes({ accordionExclusive: val })
              }
            />
          )}
        </PanelBody>
        <PanelBody
          title={__('Heading', 'aggressive-apparel')}
          initialOpen={false}
        >
          <FontSizePicker
            __nextHasNoMarginBottom
            fontSizes={fontSizes}
            value={headingFontSize || undefined}
            onChange={(value?: string | number) =>
              setAttributes({
                headingFontSize: value != null ? String(value) : '',
              })
            }
            withReset
          />
        </PanelBody>
      </InspectorControls>
      <InspectorControls group='color'>
        <ColorGradientSettingsDropdown
          panelId={clientId}
          settings={[
            {
              label: __('Heading text', 'aggressive-apparel'),
              colorValue: fromPresetColorRef(headingColor, presetColors),
              onColorChange: (value?: string) =>
                setAttributes({
                  headingColor: toPresetColorRef(value, presetColors),
                }),
            },
            {
              label: __('Accent (active / open)', 'aggressive-apparel'),
              colorValue: previewAccent,
              onColorChange: (value?: string) =>
                setAttributes({
                  accentColor: toPresetColorRef(value, presetColors),
                }),
            },
          ]}
          __experimentalIsRenderedInSidebar
          {...colorGradientSettings}
        />
      </InspectorControls>
      <div {...blockProps}>
        <div
          style={{
            padding: '1.5rem',
            border: '1px dashed var(--aa-color-border-subtle, #ddd)',
            borderRadius: '0.5rem',
            textAlign: 'center',
            color: 'var(--aa-color-foreground-muted, #888)',
          }}
        >
          <span
            style={{
              display: 'inline-block',
              fontSize: headingFontSize || undefined,
              fontWeight: 600,
              letterSpacing: '0.02em',
              textTransform: 'uppercase',
              color:
                fromPresetColorRef(headingColor, presetColors) || undefined,
              borderBottom: `2px solid ${previewAccent || 'var(--aa-color-accent, currentColor)'}`,
              paddingBottom: '0.25rem',
              marginBottom: '0.75rem',
            }}
          >
            {__('Description', 'aggressive-apparel')}
          </span>
          <br />
          <strong>{__('Product Tabs', 'aggressive-apparel')}</strong>
          <br />
          <span style={{ fontSize: '0.8125rem' }}>
            {__('Style: ', 'aggressive-apparel')}
            {STYLE_OPTIONS.find(o => o.value === displayStyle)?.label ||
              __('Use global default', 'aggressive-apparel')}
            {' · '}
            {__('Renders on the frontend', 'aggressive-apparel')}
          </span>
        </div>
      </div>
    </>
  );
}
