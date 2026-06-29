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

interface IconPreviewResponse {
  slug: string;
  svg: string;
}

const MIN_SIZE = 16;
const MAX_SIZE = 128;

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<IconBlockAttributes>) {
  const { icon, iconSize, label, isDecorative, textAlign } = attributes;

  const [iconList, setIconList] = useState<IconListItem[]>([]);
  const [iconsLoading, setIconsLoading] = useState(true);
  const [iconsError, setIconsError] = useState('');
  const [previewSvg, setPreviewSvg] = useState('');
  const [previewLoading, setPreviewLoading] = useState(false);

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

  useEffect(() => {
    if (!icon) {
      setPreviewSvg('');
      return;
    }

    let cancelled = false;

    const loadPreview = async () => {
      setPreviewLoading(true);

      try {
        // The mark's shape is size-independent (size is just width/height), so
        // fetch once per icon and let CSS scale it — no refetch while dragging
        // the size slider.
        const response = await apiFetch<IconPreviewResponse>({
          path: `/aggressive-apparel/v1/icons/${encodeURIComponent(icon)}`,
        });

        if (!cancelled) {
          setPreviewSvg(response.svg ?? '');
        }
      } catch {
        if (!cancelled) {
          setPreviewSvg('');
        }
      } finally {
        if (!cancelled) {
          setPreviewLoading(false);
        }
      }
    };

    void loadPreview();

    return () => {
      cancelled = true;
    };
  }, [icon]);

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
            min={MIN_SIZE}
            max={MAX_SIZE}
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
        {previewLoading && !previewSvg ? (
          <Spinner />
        ) : previewSvg ? (
          <span
            className='aggressive-apparel-icon__svg-wrap'
            style={{ width: iconSize, height: iconSize }}
            dangerouslySetInnerHTML={{ __html: previewSvg }}
          />
        ) : (
          <span className='aggressive-apparel-icon-block__placeholder'>
            {__('Select an icon', 'aggressive-apparel')}
          </span>
        )}
      </span>
    </>
  );
}
