/**
 * Aggressive Icon Block — Edit
 *
 * @package Aggressive_Apparel
 */

import apiFetch from '@wordpress/api-fetch';
import {
  AlignmentControl,
  BlockControls,
  InspectorControls,
  useBlockProps,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  ComboboxControl,
  Notice,
  PanelBody,
  RangeControl,
  Spinner,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
  ICON_SIZE_BLOCK_MAX,
  ICON_SIZE_BLOCK_MIN,
} from '../../utils/icon-constants';
import { IconEditorPreview } from '../../utils/icon-editor-preview';

export interface IconBlockAttributes {
  icon: string;
  iconSize: number;
  label: string;
  isDecorative: boolean;
  textAlign?: string;
}

interface IconListItem {
  slug: string;
  svg: string;
}

interface IconListResponse {
  icons: IconListItem[];
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<IconBlockAttributes>) {
  const { icon, iconSize, label, isDecorative, textAlign } = attributes;

  const [iconList, setIconList] = useState<IconListItem[]>([]);
  const [iconsLoading, setIconsLoading] = useState(true);
  const [iconsError, setIconsError] = useState('');

  useEffect(() => {
    let cancelled = false;

    const loadIcons = async () => {
      setIconsLoading(true);
      setIconsError('');

      try {
        const response = await apiFetch<IconListResponse>({
          path: '/aggressive-apparel/v1/icons',
        });

        if (cancelled) {
          return;
        }

        setIconList(response.icons ?? []);
      } catch {
        if (!cancelled) {
          setIconsError(
            __('Could not load icon library.', 'aggressive-apparel')
          );
        }
      } finally {
        if (!cancelled) {
          setIconsLoading(false);
        }
      }
    };

    void loadIcons();

    return () => {
      cancelled = true;
    };
  }, []);

  const iconOptions = useMemo(
    () =>
      iconList.map(({ slug, svg }) => ({
        label: slug,
        value: slug,
        svg,
      })),
    [iconList]
  );

  const blockProps = useBlockProps({
    className: `aggressive-apparel-icon${textAlign ? ` has-text-align-${textAlign}` : ''}`,
  });

  const showLabelNotice = !isDecorative && !label.trim();

  return (
    <>
      <BlockControls>
        <AlignmentControl
          onChange={value => setAttributes({ textAlign: value ?? '' })}
          value={textAlign || undefined}
        />
      </BlockControls>

      <InspectorControls>
        <PanelBody title={__('Icon', 'aggressive-apparel')} initialOpen>
          {iconsError && (
            <Notice status='error' isDismissible={false}>
              {iconsError}
            </Notice>
          )}
          {iconsLoading ? (
            <Spinner />
          ) : (
            <ComboboxControl
              __next40pxDefaultSize
              __nextHasNoMarginBottom
              label={__('Icon', 'aggressive-apparel')}
              value={icon}
              options={iconOptions}
              onChange={value => setAttributes({ icon: value ?? icon })}
              help={__(
                'Search by slug. Brand and UI icons share the same library.',
                'aggressive-apparel'
              )}
              __experimentalRenderItem={({ item }) => (
                <span className='aggressive-apparel-icon-option'>
                  <span
                    className='aggressive-apparel-icon-option__icon'
                    aria-hidden='true'
                    dangerouslySetInnerHTML={{ __html: String(item.svg ?? '') }}
                  />
                  <span className='aggressive-apparel-icon-option__label'>
                    {item.label}
                  </span>
                </span>
              )}
            />
          )}
          <RangeControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label={__('Size', 'aggressive-apparel')}
            value={iconSize}
            onChange={value => setAttributes({ iconSize: value ?? iconSize })}
            min={ICON_SIZE_BLOCK_MIN}
            max={ICON_SIZE_BLOCK_MAX}
            step={1}
            help={sprintf(
              /* translators: %d: icon size in pixels. */
              __('%d pixels', 'aggressive-apparel'),
              iconSize
            )}
          />
        </PanelBody>

        <PanelBody
          title={__('Accessibility', 'aggressive-apparel')}
          initialOpen={false}
        >
          <ToggleControl
            __nextHasNoMarginBottom
            label={__('Decorative icon', 'aggressive-apparel')}
            help={__(
              'Turn off when the icon conveys meaning not already in nearby text.',
              'aggressive-apparel'
            )}
            checked={isDecorative}
            onChange={value => setAttributes({ isDecorative: value })}
          />
          {!isDecorative && (
            <TextControl
              __next40pxDefaultSize
              __nextHasNoMarginBottom
              label={__('Accessible label', 'aggressive-apparel')}
              value={label}
              onChange={value => setAttributes({ label: value })}
              help={__(
                'Required when the icon is meaningful. Hidden from sighted users when a visible label already describes it.',
                'aggressive-apparel'
              )}
            />
          )}
          {showLabelNotice && (
            <Notice status='warning' isDismissible={false}>
              {__(
                'Add an accessible label, or mark the icon as decorative.',
                'aggressive-apparel'
              )}
            </Notice>
          )}
        </PanelBody>
      </InspectorControls>

      <span {...blockProps}>
        <IconEditorPreview
          slug={icon}
          size={iconSize}
          empty='placeholder'
          showLoadingSpinner
        />
      </span>
    </>
  );
}
