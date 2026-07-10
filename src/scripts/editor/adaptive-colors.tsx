/**
 * Adaptive Colors — Block Editor Extension
 *
 * Adds an Adaptive Color panel with compact Typography / Background rows.
 * Editors can pick theme adaptive colors or set custom light/dark pairs
 * without editing theme.json.
 *
 * @package Aggressive_Apparel
 * @since 1.56.0
 */

import type { ComponentType, ReactElement } from 'react';
import type { BlockEditProps } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls, useSettings } from '@wordpress/block-editor';
import {
  Button,
  ColorIndicator,
  Dropdown,
  Icon,
  PanelBody,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalDropdownContentWrapper as DropdownContentWrapper,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getBlockType } from '@wordpress/blocks';
import { reset as resetIcon } from '@wordpress/icons';
import {
  injectEditorStyle,
  useEditorColorScheme,
} from '../../utils/editor-color-scheme';
import { moon, sun } from '../../utils/editor-color-scheme-icons';
import {
  AdaptiveColorPicker,
  hasAdaptivePairValue,
  normalizeAdaptivePair,
  type AdaptiveColorPair,
  type PaletteColor,
} from '../../utils/adaptive-color-picker';
import {
  ADMIN_CHROME_COLORS,
  ADMIN_HELP_TEXT_STYLE,
  EDITOR_INFO_NOTICE_STYLE,
} from '../../utils/editor-style-tokens';

import './adaptive-colors.css';

/**
 * CSS rules for adaptive color overrides in the editor canvas.
 * Uses !important to beat normal inline styles from WordPress core.
 */
const ADAPTIVE_EDITOR_CSS = `
.aa-has-adaptive-bg {
  --aa-adaptive-bg: inherit;

  background-color: var(--aa-adaptive-bg) !important;
}
.aa-has-adaptive-color {
  --aa-adaptive-color: currentColor;

  color: var(--aa-adaptive-color) !important;
}
`;

/**
 * Inject adaptive color CSS into the editor iframe.
 * Idempotent — no-ops if the style element already exists.
 */
function injectAdaptiveCSS(): void {
  injectEditorStyle('aa-adaptive-colors-css', ADAPTIVE_EDITOR_CSS);
}

interface BlockAttributes {
  adaptiveBackground?: AdaptiveColorPair;
  adaptiveText?: AdaptiveColorPair;
  backgroundColor?: string;
  textColor?: string;
  style?: {
    color?: {
      background?: string;
      text?: string;
      [key: string]: unknown;
    };
    [key: string]: unknown;
  };
  [key: string]: unknown;
}

interface SaveExtraProps {
  className?: string;
  style?: Record<string, string>;
  [key: string]: string | Record<string, string> | undefined;
}

interface RegisteredBlockType {
  name: string;
  attributes?: Record<string, unknown>;
  [key: string]: unknown;
}

interface BlockListBlockWrapperProps {
  attributes: BlockAttributes;
  wrapperProps?: {
    className?: string;
    style?: Record<string, string>;
    [key: string]: unknown;
  };
  [key: string]: unknown;
}

/**
 * Check if a block type supports color settings.
 */
function supportsColor(settings: Record<string, unknown>): {
  background: boolean;
  text: boolean;
} {
  const color = (settings.supports as Record<string, unknown>)?.color as
    | Record<string, unknown>
    | boolean
    | undefined;

  if (!color) return { background: false, text: false };
  if (color === true) return { background: true, text: true };

  return {
    background: color.background !== false,
    text: color.text !== false,
  };
}

/**
 * Whether core Text / Background is set for a channel.
 */
function hasCoreColor(
  attributes: BlockAttributes,
  channel: 'background' | 'text'
): boolean {
  if (channel === 'background') {
    return Boolean(
      attributes.backgroundColor || attributes.style?.color?.background
    );
  }

  return Boolean(attributes.textColor || attributes.style?.color?.text);
}

/**
 * Filter 1: Add adaptive color attributes to all color-supporting blocks.
 */
addFilter(
  'blocks.registerBlockType',
  'aggressive-apparel/adaptive-color-attributes',
  (settings: Record<string, unknown>, _name: string) => {
    const { background, text } = supportsColor(settings);
    if (!background && !text) return settings;

    const attributes = {
      ...(settings.attributes as Record<string, unknown>),
    };

    if (background) {
      attributes.adaptiveBackground = {
        type: 'object',
        default: undefined,
      };
    }

    if (text) {
      attributes.adaptiveText = {
        type: 'object',
        default: undefined,
      };
    }

    return { ...settings, attributes };
  }
);

/**
 * One color indicator with a sun or moon mark (WordPress icon style).
 */
function LabeledSwatch({
  colorValue,
  icon,
  title,
}: {
  colorValue?: string;
  icon: ReactElement;
  title: string;
}) {
  return (
    <span className='aa-adaptive-color-row__swatch' title={title}>
      <ColorIndicator colorValue={colorValue} />
      <span className='aa-adaptive-color-row__swatch-icon' aria-hidden='true'>
        <Icon icon={icon} size={22} />
      </span>
    </span>
  );
}

/**
 * Dual color indicators for light + dark in one compact row.
 *
 * Label on the left (Typography / Background), sun/moon swatches on the right —
 * matches WordPress settings-row rhythm and keeps the property name readable.
 */
function DualColorIndicator({
  value,
  label,
}: {
  value: AdaptiveColorPair | undefined;
  label: string;
}) {
  return (
    <span className='aa-adaptive-color-row__content'>
      <span className='aa-adaptive-color-row__label'>{label}</span>
      <span className='aa-adaptive-color-row__swatches'>
        <LabeledSwatch
          colorValue={value?.light}
          icon={sun}
          title={__('Light', 'aggressive-apparel')}
        />
        <LabeledSwatch
          colorValue={value?.dark}
          icon={moon}
          title={__('Dark', 'aggressive-apparel')}
        />
      </span>
    </span>
  );
}

/**
 * One compact Adaptive Color row for a light/dark pair.
 */
function AdaptiveColorRow({
  label,
  value,
  onChange,
  colors,
}: {
  label: string;
  value: AdaptiveColorPair | undefined;
  onChange: (value: AdaptiveColorPair | undefined) => void;
  colors: PaletteColor[] | undefined;
}) {
  const { colorMode, switchColorMode } = useEditorColorScheme();
  const hasValue = hasAdaptivePairValue(value);

  return (
    <div className='aa-adaptive-color-row'>
      <Dropdown
        className='aa-adaptive-color-row__dropdown'
        popoverProps={{
          placement: 'left-start',
          offset: 36,
          shift: true,
          className: 'aa-adaptive-color-popover',
        }}
        renderToggle={({ isOpen, onToggle }) => (
          <>
            <Button
              __next40pxDefaultSize
              onClick={onToggle}
              aria-expanded={isOpen}
              className={`aa-adaptive-color-row__toggle${
                isOpen ? 'is-open' : ''
              }`}
            >
              <DualColorIndicator value={value} label={label} />
            </Button>
            {hasValue && (
              <Button
                __next40pxDefaultSize
                label={__('Reset', 'aggressive-apparel')}
                className='block-editor-panel-color-gradient-settings__reset'
                size='small'
                icon={resetIcon}
                onClick={() => onChange(undefined)}
              />
            )}
          </>
        )}
        renderContent={() => (
          <DropdownContentWrapper paddingSize='none'>
            <div
              className='block-editor-panel-color-gradient-settings__dropdown-content'
              style={{
                padding: '16px',
                width: '280px',
                maxWidth: '280px',
                boxSizing: 'border-box',
                color: ADMIN_CHROME_COLORS.text,
              }}
            >
              <AdaptiveColorPicker
                label={label}
                mode={colorMode}
                onModeChange={switchColorMode}
                value={value}
                onChange={onChange}
                colors={colors}
              />
            </div>
          </DropdownContentWrapper>
        )}
      />
    </div>
  );
}

/**
 * Adaptive Color sidebar panel.
 */
function AdaptiveColorControls({
  attributes,
  setAttributes,
  supportsBackground,
  supportsText,
}: {
  attributes: BlockAttributes;
  setAttributes: (attrs: Partial<BlockAttributes>) => void;
  supportsBackground: boolean;
  supportsText: boolean;
}) {
  const [colors] = useSettings('color.palette') as [PaletteColor[] | undefined];

  const adaptiveBg = attributes.adaptiveBackground;
  const adaptiveText = attributes.adaptiveText;
  const hasAnyValue =
    hasAdaptivePairValue(adaptiveBg) || hasAdaptivePairValue(adaptiveText);

  const showsConflict =
    (hasAdaptivePairValue(adaptiveBg) &&
      hasCoreColor(attributes, 'background')) ||
    (hasAdaptivePairValue(adaptiveText) && hasCoreColor(attributes, 'text'));

  /**
   * Clear a core color channel so adaptive overrides don't stack silently.
   */
  const withoutCoreColor = (
    channel: 'background' | 'text'
  ): Partial<BlockAttributes> => {
    const style = attributes.style ? { ...attributes.style } : undefined;
    if (style?.color) {
      const color = { ...style.color };
      delete color[channel];
      style.color = Object.keys(color).length > 0 ? color : undefined;
    }

    if (channel === 'background') {
      return { backgroundColor: undefined, style };
    }

    return { textColor: undefined, style };
  };

  const updateBackground = (value: AdaptiveColorPair | undefined) => {
    const next = normalizeAdaptivePair(value);
    // Adaptive overrides win on the front end — clear conflicting core colors.
    if (next) {
      setAttributes({
        adaptiveBackground: next,
        ...withoutCoreColor('background'),
      });
      return;
    }
    setAttributes({ adaptiveBackground: undefined });
  };

  const updateText = (value: AdaptiveColorPair | undefined) => {
    const next = normalizeAdaptivePair(value);
    if (next) {
      setAttributes({
        adaptiveText: next,
        ...withoutCoreColor('text'),
      });
      return;
    }
    setAttributes({ adaptiveText: undefined });
  };

  return (
    <InspectorControls>
      <PanelBody
        title={__('Adaptive Color', 'aggressive-apparel')}
        initialOpen={hasAnyValue}
      >
        <p style={ADMIN_HELP_TEXT_STYLE}>
          {__(
            'Set different colors for light and dark mode on this block.',
            'aggressive-apparel'
          )}
        </p>

        {supportsText && (
          <AdaptiveColorRow
            label={__('Typography', 'aggressive-apparel')}
            value={adaptiveText}
            onChange={updateText}
            colors={colors}
          />
        )}

        {supportsBackground && (
          <AdaptiveColorRow
            label={__('Background', 'aggressive-apparel')}
            value={adaptiveBg}
            onChange={updateBackground}
            colors={colors}
          />
        )}

        {showsConflict && (
          <div style={{ ...EDITOR_INFO_NOTICE_STYLE, marginTop: '8px' }}>
            <p style={{ margin: 0, fontSize: '12px' }}>
              {__(
                'Adaptive Color overrides the standard Text / Background colors for this block.',
                'aggressive-apparel'
              )}
            </p>
          </div>
        )}
      </PanelBody>
    </InspectorControls>
  );
}

/**
 * Filter 2: Add Adaptive Color panel to the block sidebar.
 */
const withAdaptiveColorControls = createHigherOrderComponent(
  (BlockEdit: ComponentType<BlockEditProps<BlockAttributes>>) => {
    function EnhancedBlockEdit(
      props: BlockEditProps<BlockAttributes> & { name: string }
    ) {
      const blockType = getBlockType(props.name);
      const background = !!blockType?.attributes?.adaptiveBackground;
      const text = !!blockType?.attributes?.adaptiveText;

      if (!background && !text) {
        return <BlockEdit {...props} />;
      }

      return (
        <>
          <BlockEdit {...props} />
          <AdaptiveColorControls
            attributes={props.attributes}
            setAttributes={props.setAttributes}
            supportsBackground={background}
            supportsText={text}
          />
        </>
      );
    }
    return EnhancedBlockEdit;
  },
  'withAdaptiveColorControls'
);

addFilter(
  'editor.BlockEdit',
  'aggressive-apparel/adaptive-color-controls',
  withAdaptiveColorControls
);

/**
 * Build a light-dark() CSS style object from adaptive attributes.
 */
function buildAdaptiveStyles(
  attributes: BlockAttributes
): Record<string, string> {
  const style: Record<string, string> = {};
  const { adaptiveBackground, adaptiveText } = attributes;

  if (adaptiveBackground?.light && adaptiveBackground?.dark) {
    style.backgroundColor = `light-dark(${adaptiveBackground.light}, ${adaptiveBackground.dark})`;
  } else if (adaptiveBackground?.light) {
    style.backgroundColor = adaptiveBackground.light;
  } else if (adaptiveBackground?.dark) {
    style.backgroundColor = adaptiveBackground.dark;
  }

  if (adaptiveText?.light && adaptiveText?.dark) {
    style.color = `light-dark(${adaptiveText.light}, ${adaptiveText.dark})`;
  } else if (adaptiveText?.light) {
    style.color = adaptiveText.light;
  } else if (adaptiveText?.dark) {
    style.color = adaptiveText.dark;
  }

  return style;
}

/**
 * Filter 3: Add light-dark() inline styles to saved block HTML.
 */
addFilter(
  'blocks.getSaveContent.extraProps',
  'aggressive-apparel/save-adaptive-color-styles',
  (
    extraProps: SaveExtraProps,
    _blockType: RegisteredBlockType,
    attributes: BlockAttributes
  ) => {
    const adaptiveStyles = buildAdaptiveStyles(attributes);

    if (Object.keys(adaptiveStyles).length === 0) {
      return extraProps;
    }

    return {
      ...extraProps,
      style: {
        ...(extraProps.style || {}),
        ...adaptiveStyles,
      },
    };
  }
);

/**
 * Filter 4: Apply adaptive styles to block wrapper in the editor preview.
 *
 * Uses CSS custom properties + marker classes instead of setting
 * backgroundColor/color directly, because WordPress core merges
 * block inline styles into the same element and would overwrite ours.
 * A stylesheet rule with !important (injected into the iframe) beats
 * normal inline styles.
 */
const withAdaptiveColorPreview = createHigherOrderComponent(
  (BlockListBlock: ComponentType<BlockListBlockWrapperProps>) => {
    function EnhancedBlockListBlock(props: BlockListBlockWrapperProps) {
      const adaptiveStyles = buildAdaptiveStyles(props.attributes);

      if (Object.keys(adaptiveStyles).length === 0) {
        return <BlockListBlock {...props} />;
      }

      // Ensure the editor iframe has our CSS rules.
      injectAdaptiveCSS();

      const cssVars: Record<string, string> = {};
      const classes: string[] = [];

      if (adaptiveStyles.backgroundColor) {
        cssVars['--aa-adaptive-bg'] = adaptiveStyles.backgroundColor;
        classes.push('aa-has-adaptive-bg');
      }

      if (adaptiveStyles.color) {
        cssVars['--aa-adaptive-color'] = adaptiveStyles.color;
        classes.push('aa-has-adaptive-color');
      }

      const existingClassName = props.wrapperProps?.className || '';

      const wrapperProps = {
        ...(props.wrapperProps || {}),
        style: {
          ...(props.wrapperProps?.style || {}),
          ...cssVars,
        },
        className: [existingClassName, ...classes].filter(Boolean).join(' '),
      };

      return <BlockListBlock {...props} wrapperProps={wrapperProps} />;
    }
    return EnhancedBlockListBlock;
  },
  'withAdaptiveColorPreview'
);

addFilter(
  'editor.BlockListBlock',
  'aggressive-apparel/adaptive-color-editor-preview',
  withAdaptiveColorPreview
);
