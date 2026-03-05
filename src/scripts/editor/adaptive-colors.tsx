/**
 * Adaptive Colors — Block Editor Extension
 *
 * Adds per-block light/dark color override controls to all blocks
 * that support color settings. When both light and dark values are set,
 * outputs CSS light-dark() inline styles.
 *
 * @package Aggressive_Apparel
 * @since 1.56.0
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls, useSetting } from '@wordpress/block-editor';
import {
  PanelBody,
  ColorPalette,
  BaseControl,
  Button,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';
import { getBlockType } from '@wordpress/blocks';
import {
  useEditorColorScheme,
  ColorModeToggle,
  injectEditorStyle,
} from '../../utils/editor-color-scheme';

/**
 * CSS rules for adaptive color overrides in the editor.
 * Uses !important to beat normal inline styles from WordPress core.
 */
const ADAPTIVE_EDITOR_CSS = `
.aa-has-adaptive-bg { background-color: var(--aa-adaptive-bg) !important; }
.aa-has-adaptive-color { color: var(--aa-adaptive-color) !important; }
`;

/**
 * Inject adaptive color CSS into the editor iframe.
 * Idempotent — no-ops if the style element already exists.
 */
function injectAdaptiveCSS(): void {
  injectEditorStyle('aa-adaptive-colors-css', ADAPTIVE_EDITOR_CSS);
}

interface AdaptiveColor {
  light?: string;
  dark?: string;
}

interface BlockAttributes {
  adaptiveBackground?: AdaptiveColor;
  adaptiveText?: AdaptiveColor;
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
 * Adaptive Colors sidebar panel component.
 */
function AdaptiveColorPanel({
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
  const allColors = useSetting('color.palette') as
    | Array<{ name: string; slug: string; color: string }>
    | undefined;

  // Filter out adaptive (light-dark) palette entries — they shouldn't appear
  // in per-mode pickers since each slot expects a single concrete color value.
  const colors = useMemo(
    () => allColors?.filter(c => !c.color.startsWith('light-dark(')),
    [allColors]
  );

  const adaptiveBg = attributes.adaptiveBackground;
  const adaptiveText = attributes.adaptiveText;

  const hasAnyValue =
    adaptiveBg?.light ||
    adaptiveBg?.dark ||
    adaptiveText?.light ||
    adaptiveText?.dark;

  const { colorMode, switchColorMode } = useEditorColorScheme();

  const updateBg = (mode: 'light' | 'dark', value?: string) => {
    const current = attributes.adaptiveBackground || {};
    const updated = { ...current, [mode]: value || '' };
    if (!updated.light && !updated.dark) {
      setAttributes({ adaptiveBackground: undefined });
    } else {
      setAttributes({ adaptiveBackground: updated });
    }
  };

  const updateText = (mode: 'light' | 'dark', value?: string) => {
    const current = attributes.adaptiveText || {};
    const updated = { ...current, [mode]: value || '' };
    if (!updated.light && !updated.dark) {
      setAttributes({ adaptiveText: undefined });
    } else {
      setAttributes({ adaptiveText: updated });
    }
  };

  const clearAll = () => {
    setAttributes({
      adaptiveBackground: undefined,
      adaptiveText: undefined,
    });
  };

  return (
    <PanelBody
      title={__('Adaptive Colors', 'aggressive-apparel')}
      initialOpen={false}
    >
      <p style={{ fontSize: '12px', color: '#757575', marginTop: 0 }}>
        {__(
          'Set different colors for light and dark mode. Both must be set for adaptive behavior.',
          'aggressive-apparel'
        )}
      </p>

      <ColorModeToggle mode={colorMode} onChange={switchColorMode} />

      {supportsBackground && (
        <BaseControl
          label={__('Background', 'aggressive-apparel')}
          __nextHasNoMarginBottom
        >
          <ColorPalette
            colors={colors}
            value={colorMode === 'light' ? adaptiveBg?.light : adaptiveBg?.dark}
            onChange={value => updateBg(colorMode, value)}
            clearable
          />
        </BaseControl>
      )}

      {supportsText && (
        <BaseControl
          label={__('Text', 'aggressive-apparel')}
          __nextHasNoMarginBottom
        >
          <ColorPalette
            colors={colors}
            value={
              colorMode === 'light' ? adaptiveText?.light : adaptiveText?.dark
            }
            onChange={value => updateText(colorMode, value)}
            clearable
          />
        </BaseControl>
      )}

      {hasAnyValue && (
        <Button variant='tertiary' isDestructive onClick={clearAll}>
          {__('Clear All Adaptive Colors', 'aggressive-apparel')}
        </Button>
      )}
    </PanelBody>
  );
}

/**
 * Filter 2: Add Adaptive Colors panel to block sidebar.
 */
const withAdaptiveColorControls = createHigherOrderComponent(
  (BlockEdit: any) => {
    function EnhancedBlockEdit(props: any) {
      const blockType = getBlockType(props.name);
      const background = !!blockType?.attributes?.adaptiveBackground;
      const text = !!blockType?.attributes?.adaptiveText;

      if (!background && !text) {
        return <BlockEdit {...props} />;
      }

      return (
        <>
          <BlockEdit {...props} />
          <InspectorControls>
            <AdaptiveColorPanel
              attributes={props.attributes}
              setAttributes={props.setAttributes}
              supportsBackground={background}
              supportsText={text}
            />
          </InspectorControls>
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
  (extraProps: any, blockType: any, attributes: BlockAttributes) => {
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
  (BlockListBlock: any) => {
    function EnhancedBlockListBlock(props: any) {
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
