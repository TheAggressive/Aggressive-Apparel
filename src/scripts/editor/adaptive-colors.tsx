/**
 * Adaptive Colors — Block Editor Extension
 *
 * Discovers color surfaces from each block's supports (+ allowlisted custom
 * attributes) and exposes matching Light/Dark controls via the native
 * WordPress color/gradient UI.
 *
 * @package Aggressive_Apparel
 * @since 1.56.0
 */

import type { ComponentType } from 'react';
import type { BlockEditProps } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import {
  InspectorControls,
  store as blockEditorStore,
} from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getBlockType } from '@wordpress/blocks';
import { useDispatch, useSelect, select } from '@wordpress/data';

import { AdaptiveColorPanelBody } from '../../utils/adaptive-color-controls';
import {
  blockSupportsAdaptiveColors,
  buildAdaptiveStylePayload,
  discoverAdaptiveColorChannels,
  hasAnyAdaptiveColor,
  resolveAdaptiveColors,
  setAdaptiveColorChannel,
  type AdaptiveChannelDefinition,
  type AdaptiveChannelId,
  type BlockAttributesLike,
  type BlockTypeSettingsLike,
} from '../../utils/adaptive-color-channels';
import { normalizeAdaptivePair } from '../../utils/adaptive-color-value';

import './adaptive-colors.css';

/**
 * Marker-class CSS lives in styles/components/adaptive-block-colors.css and
 * ships with main.css (enqueue_block_assets → front end + editor canvas).
 */

interface BlockAttributes extends BlockAttributesLike {
  [key: string]: unknown;
}

interface SaveExtraProps {
  className?: string;
  style?: Record<string, string>;
  [key: string]: string | Record<string, string> | undefined;
}

interface RegisteredBlockType extends BlockTypeSettingsLike {
  name: string;
}

interface BlockListBlockWrapperProps {
  name?: string;
  attributes: BlockAttributes;
  wrapperProps?: {
    className?: string;
    style?: Record<string, string>;
    [key: string]: unknown;
  };
  [key: string]: unknown;
}

/**
 * Filter 1: Register scalable adaptiveColors (+ legacy attrs for migration).
 */
addFilter(
  'blocks.registerBlockType',
  'aggressive-apparel/adaptive-color-attributes',
  (settings: BlockTypeSettingsLike, name: string) => {
    if (!blockSupportsAdaptiveColors(name, settings)) {
      return settings;
    }

    const channels = discoverAdaptiveColorChannels(name, settings);
    const attributes: Record<string, unknown> = {
      ...(settings.attributes as Record<string, unknown>),
      adaptiveColors: {
        type: 'object',
        default: undefined,
      },
    };

    // Keep legacy attributes registered so existing content still deserializes.
    if (channels.some(channel => channel.id === 'background')) {
      attributes.adaptiveBackground = {
        type: 'object',
        default: undefined,
      };
    }
    if (channels.some(channel => channel.id === 'text')) {
      attributes.adaptiveText = {
        type: 'object',
        default: undefined,
      };
    }

    return { ...settings, attributes };
  }
);

/**
 * Adaptive Color sidebar panel — scheme tabs + native color controls.
 */
function AdaptiveColorControls({
  clientId,
  channels,
}: {
  clientId: string;
  channels: AdaptiveChannelDefinition[];
}) {
  const { updateBlockAttributes } = useDispatch(blockEditorStore);
  const attributes = useSelect(
    selectStore =>
      (selectStore(blockEditorStore).getBlockAttributes(clientId) ??
        {}) as BlockAttributes,
    [clientId]
  );
  const resolved = resolveAdaptiveColors(attributes);
  const hasAnyValue = hasAnyAdaptiveColor(attributes);

  return (
    <InspectorControls>
      <PanelBody
        title={__('Adaptive Color', 'aggressive-apparel')}
        initialOpen={hasAnyValue}
        className='aa-adaptive-color-panel-body'
      >
        <AdaptiveColorPanelBody
          panelId={`${clientId}-adaptive-color`}
          settings={channels.map(channel => ({
            id: channel.id,
            label: channel.label,
            group: channel.group,
            rowLabel: channel.rowLabel,
            presentation: channel.presentation,
            value: resolved[channel.id],
            allowGradient:
              channel.application.type === 'css' &&
              channel.application.allowGradient === true,
            hasCoreConflict: channel.hasCoreConflict(attributes),
            onChange: value => {
              // Read latest attrs at commit time to avoid stale multi-channel writes.
              const latest =
                (select(blockEditorStore).getBlockAttributes(
                  clientId
                ) as BlockAttributes | null) ?? attributes;
              updateBlockAttributes(
                clientId,
                setAdaptiveColorChannel(
                  latest,
                  channel.id as AdaptiveChannelId,
                  normalizeAdaptivePair(value)
                )
              );
            },
          }))}
        />
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
      const blockType = getBlockType(props.name) as
        | BlockTypeSettingsLike
        | undefined;
      if (!blockType) {
        return <BlockEdit {...props} />;
      }

      const channels = discoverAdaptiveColorChannels(props.name, blockType);
      if (channels.length === 0) {
        return <BlockEdit {...props} />;
      }

      return (
        <>
          <BlockEdit {...props} />
          {/* Only mount inspector UI for the selected block — avoids N panels. */}
          {props.isSelected && (
            <AdaptiveColorControls
              clientId={props.clientId}
              channels={channels}
            />
          )}
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
 * Filter 3: Add light-dark() styles / classes to saved block HTML.
 */
addFilter(
  'blocks.getSaveContent.extraProps',
  'aggressive-apparel/save-adaptive-color-styles',
  (
    extraProps: SaveExtraProps,
    blockType: RegisteredBlockType,
    attributes: BlockAttributes
  ) => {
    const channels = discoverAdaptiveColorChannels(blockType.name, blockType);
    const payload = buildAdaptiveStylePayload(attributes, channels);

    if (
      Object.keys(payload.style).length === 0 &&
      payload.classNames.length === 0
    ) {
      return extraProps;
    }

    const existingClassName = extraProps.className || '';

    return {
      ...extraProps,
      style: {
        ...(extraProps.style || {}),
        ...payload.style,
      },
      className: [existingClassName, ...payload.classNames]
        .filter(Boolean)
        .join(' '),
    };
  }
);

/**
 * Fast path — skip channel discovery when the block has no adaptive data.
 */
function blockHasAdaptiveAttributes(attributes: BlockAttributes): boolean {
  if (
    attributes.adaptiveColors ||
    attributes.adaptiveBackground ||
    attributes.adaptiveText
  ) {
    return true;
  }
  const overlay = attributes.overlayColor;
  return typeof overlay === 'string' && overlay.includes('light-dark(');
}

/**
 * Filter 4: Apply adaptive styles to block wrapper in the editor preview.
 */
const withAdaptiveColorPreview = createHigherOrderComponent(
  (BlockListBlock: ComponentType<BlockListBlockWrapperProps>) => {
    function EnhancedBlockListBlock(props: BlockListBlockWrapperProps) {
      // Most blocks have no adaptive colors — bail before discovery work.
      if (!blockHasAdaptiveAttributes(props.attributes)) {
        return <BlockListBlock {...props} />;
      }

      const blockName = props.name || '';
      const blockType = blockName
        ? (getBlockType(blockName) as BlockTypeSettingsLike | undefined)
        : undefined;
      const channels = blockType
        ? discoverAdaptiveColorChannels(blockName, blockType)
        : [];
      const payload = buildAdaptiveStylePayload(props.attributes, channels);

      if (
        Object.keys(payload.style).length === 0 &&
        payload.classNames.length === 0
      ) {
        return <BlockListBlock {...props} />;
      }

      const existingClassName = props.wrapperProps?.className || '';

      const wrapperProps = {
        ...(props.wrapperProps || {}),
        style: {
          ...(props.wrapperProps?.style || {}),
          ...payload.style,
        },
        className: [existingClassName, ...payload.classNames]
          .filter(Boolean)
          .join(' '),
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
