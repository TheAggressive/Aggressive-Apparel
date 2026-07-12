/**
 * Lookbook Block — Editor Component.
 *
 * Allows placing product hotspots on an image in the block editor.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { __ } from '@wordpress/i18n';
import {
  useBlockProps,
  InspectorControls,
  MediaUpload,
  MediaUploadCheck,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
  PanelBody,
  Button,
  ComboboxControl,
  Notice,
  RangeControl,
  TextControl,
  ToggleControl,
  Placeholder,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import type { BlockEditProps } from '@wordpress/blocks';
import type { CSSProperties, MouseEvent, PointerEvent } from 'react';
import type {
  LookbookAttributes,
  LookbookHotspot,
  StoreApiProduct,
} from './types';
import {
  flattenPresetColors,
  fromPresetColorRef,
  toPresetColorRef,
  type PresetColorOrigin,
} from '../../utils/preset-colors';

const MIN_PRODUCT_SEARCH_LENGTH = 2;
const PRODUCT_SEARCH_LIMIT = 20;
const productSearchCache = new Map<string, ProductOption[]>();

interface ProductOption {
  label: string;
  name: string;
  value: string;
}

function normalizeColorValue(value: string): string {
  const match = value.match(/^var:preset\|color\|([a-z0-9_-]+)$/i);

  if (match) {
    return `var(--wp--preset--color--${match[1]})`;
  }

  if (/^[a-z0-9_-]+$/i.test(value)) {
    return `var(--wp--preset--color--${value})`;
  }

  return value;
}

function getLookbookColorStyles({
  hotspotBgColor,
  hotspotTextColor,
  hotspotSize,
  cardBgColor,
  cardTextColor,
  actionBgColor,
  actionIconColor,
}: Pick<
  LookbookAttributes,
  | 'hotspotBgColor'
  | 'hotspotTextColor'
  | 'hotspotSize'
  | 'cardBgColor'
  | 'cardTextColor'
  | 'actionBgColor'
  | 'actionIconColor'
>): CSSProperties {
  return {
    '--aa-lookbook-hotspot-size': `${hotspotSize || 32}px`,
    ...(hotspotBgColor
      ? { '--aa-lookbook-hotspot-bg': normalizeColorValue(hotspotBgColor) }
      : {}),
    ...(hotspotTextColor
      ? { '--aa-lookbook-hotspot-color': normalizeColorValue(hotspotTextColor) }
      : {}),
    ...(cardBgColor
      ? { '--aa-lookbook-card-bg': normalizeColorValue(cardBgColor) }
      : {}),
    ...(cardTextColor
      ? { '--aa-lookbook-card-text': normalizeColorValue(cardTextColor) }
      : {}),
    ...(actionBgColor
      ? { '--aa-lookbook-action-bg': normalizeColorValue(actionBgColor) }
      : {}),
    ...(actionIconColor
      ? { '--aa-lookbook-action-color': normalizeColorValue(actionIconColor) }
      : {}),
  } as CSSProperties;
}

function productToOption(product: StoreApiProduct): ProductOption | null {
  if (!product.id || !product.name) {
    return null;
  }

  return {
    label: product.sku
      ? `${product.name} (#${product.id}, ${product.sku})`
      : `${product.name} (#${product.id})`,
    name: product.name,
    value: String(product.id),
  };
}

export default function Edit({
  attributes,
  setAttributes,
  clientId,
}: BlockEditProps<LookbookAttributes>) {
  const {
    mediaId,
    mediaUrl,
    mediaAlt,
    hotspots,
    hotspotBgColor = '',
    hotspotTextColor = '',
    hotspotSize = 32,
    openOnHover = false,
    cardBgColor = '',
    cardTextColor = '',
    actionBgColor = '',
    actionIconColor = '',
  } = attributes;
  const [editingIndex, setEditingIndex] = useState<number | null>(null);
  const [draggingIndex, setDraggingIndex] = useState<number | null>(null);
  const [productSearch, setProductSearch] = useState('');
  const [productOptions, setProductOptions] = useState<ProductOption[]>([]);
  const [isLoadingProducts, setIsLoadingProducts] = useState(false);
  const [productSearchError, setProductSearchError] = useState('');
  const hasDraggedRef = useRef(false);
  const colorGradientSettings = useMultipleOriginColorsAndGradients();
  const presetColors = flattenPresetColors(
    colorGradientSettings.colors as PresetColorOrigin[] | undefined
  );
  const colorSetting = (
    label: string,
    key:
      | 'hotspotBgColor'
      | 'hotspotTextColor'
      | 'cardBgColor'
      | 'cardTextColor'
      | 'actionBgColor'
      | 'actionIconColor'
  ) => ({
    label,
    colorValue: fromPresetColorRef(attributes[key], presetColors),
    onColorChange: (value?: string) =>
      setAttributes({
        [key]: toPresetColorRef(value, presetColors),
      } as Partial<LookbookAttributes>),
  });
  const blockProps = useBlockProps({
    className: 'aggressive-apparel-lookbook',
    style: getLookbookColorStyles({
      hotspotBgColor,
      hotspotTextColor,
      hotspotSize,
      cardBgColor,
      cardTextColor,
      actionBgColor,
      actionIconColor,
    }),
  });

  useEffect(() => {
    let isMounted = true;

    const timeout = window.setTimeout(() => {
      const search = productSearch.trim();
      const cacheKey = search.toLocaleLowerCase();

      if (search.length < MIN_PRODUCT_SEARCH_LENGTH) {
        setIsLoadingProducts(false);
        setProductSearchError('');
        setProductOptions([]);
        return;
      }

      const cachedOptions = productSearchCache.get(cacheKey);
      if (cachedOptions) {
        setIsLoadingProducts(false);
        setProductSearchError('');
        setProductOptions(cachedOptions);
        return;
      }

      const params = new URLSearchParams({
        per_page: String(PRODUCT_SEARCH_LIMIT),
        orderby: 'title',
        order: 'asc',
      });
      params.set('search', search);

      setIsLoadingProducts(true);
      setProductSearchError('');

      apiFetch<StoreApiProduct[]>({
        path: `/wc/store/v1/products?${params.toString()}`,
      })
        .then(products => {
          if (!isMounted) {
            return;
          }

          const options = products.reduce<ProductOption[]>((items, product) => {
            const option = productToOption(product);

            if (option) {
              items.push(option);
            }

            return items;
          }, []);

          productSearchCache.set(cacheKey, options);
          setProductOptions(options);
        })
        .catch(() => {
          if (isMounted) {
            setProductSearchError(
              __(
                'Products could not be loaded. Try a different search.',
                'aggressive-apparel'
              )
            );
          }
        })
        .finally(() => {
          if (isMounted) {
            setIsLoadingProducts(false);
          }
        });
    }, 250);

    return () => {
      isMounted = false;
      window.clearTimeout(timeout);
    };
  }, [productSearch]);

  const productOptionsById = useMemo(() => {
    const optionsById = new Map<string, ProductOption>();

    productOptions.forEach(option => {
      optionsById.set(option.value, option);
    });

    hotspots.forEach(hotspot => {
      if (!hotspot.productId) {
        return;
      }

      const value = String(hotspot.productId);
      if (optionsById.has(value)) {
        return;
      }

      optionsById.set(value, {
        label: hotspot.productName
          ? `${hotspot.productName} (#${hotspot.productId})`
          : `Product #${hotspot.productId}`,
        name: hotspot.productName,
        value,
      });
    });

    return optionsById;
  }, [hotspots, productOptions]);

  const productSelectOptions = useMemo(
    () => [
      { label: __('Select a product', 'aggressive-apparel'), value: '' },
      ...Array.from(productOptionsById.values()),
    ],
    [productOptionsById]
  );

  const getHotspotPosition = (
    e: MouseEvent<HTMLDivElement> | PointerEvent<HTMLButtonElement>,
    wrapper: HTMLElement
  ) => {
    const rect = wrapper.getBoundingClientRect();

    if (!rect.width || !rect.height) {
      return { x: 50, y: 50 };
    }

    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;

    return {
      x: Math.max(0, Math.min(100, Math.round(x * 10) / 10)),
      y: Math.max(0, Math.min(100, Math.round(y * 10) / 10)),
    };
  };

  /**
   * Handle click on the image to place a new hotspot.
   */
  const handleImageClick = (e: MouseEvent<HTMLDivElement>) => {
    if (hasDraggedRef.current) {
      hasDraggedRef.current = false;
      return;
    }

    const position = getHotspotPosition(e, e.currentTarget);

    const newHotspot: LookbookHotspot = {
      ...position,
      productId: 0,
      productName: '',
    };

    const updated = [...hotspots, newHotspot];
    setAttributes({ hotspots: updated });
    setEditingIndex(updated.length - 1);
  };

  /**
   * Update a specific hotspot.
   */
  const updateHotspot = (index: number, changes: Partial<LookbookHotspot>) => {
    const updated = hotspots.map((h, i) =>
      i === index ? { ...h, ...changes } : h
    );
    setAttributes({ hotspots: updated });
  };

  /**
   * Remove a hotspot.
   */
  const removeHotspot = (index: number) => {
    setAttributes({
      hotspots: hotspots.filter((_, i) => i !== index),
    });
    // Indexes above the removed hotspot shift down by one.
    const adjustIndex = (current: number | null) => {
      if (current === null || current === index) {
        return null;
      }

      return current > index ? current - 1 : current;
    };
    setEditingIndex(adjustIndex);
    setDraggingIndex(adjustIndex);
  };

  const handleHotspotPointerDown = (
    e: PointerEvent<HTMLButtonElement>,
    index: number
  ) => {
    if (e.button !== 0) {
      return;
    }

    e.preventDefault();
    e.stopPropagation();
    hasDraggedRef.current = false;
    setEditingIndex(index);
    setDraggingIndex(index);
    e.currentTarget.setPointerCapture(e.pointerId);
  };

  const handleHotspotPointerMove = (
    e: PointerEvent<HTMLButtonElement>,
    index: number
  ) => {
    if (draggingIndex !== index) {
      return;
    }

    const wrapper = e.currentTarget.closest<HTMLElement>(
      '.aggressive-apparel-lookbook__image-wrapper'
    );

    if (!wrapper) {
      return;
    }

    hasDraggedRef.current = true;
    updateHotspot(index, getHotspotPosition(e, wrapper));
  };

  const handleHotspotPointerUp = (e: PointerEvent<HTMLButtonElement>) => {
    if (e.currentTarget.hasPointerCapture(e.pointerId)) {
      e.currentTarget.releasePointerCapture(e.pointerId);
    }

    setDraggingIndex(null);
  };

  if (!mediaUrl) {
    return (
      <div {...blockProps}>
        <MediaUploadCheck>
          <Placeholder
            label={__('Lookbook / Shop the Look', 'aggressive-apparel')}
            instructions={__(
              'Upload or select an image to create a shoppable lookbook.',
              'aggressive-apparel'
            )}
            icon='format-image'
          >
            <MediaUpload
              onSelect={media =>
                setAttributes({
                  mediaId: media.id,
                  mediaUrl: media.url,
                  mediaAlt: media.alt || '',
                })
              }
              allowedTypes={['image']}
              value={mediaId}
              render={({ open }) => (
                <Button variant='primary' onClick={open}>
                  {__('Select Image', 'aggressive-apparel')}
                </Button>
              )}
            />
          </Placeholder>
        </MediaUploadCheck>
      </div>
    );
  }

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Image', 'aggressive-apparel')}>
          <MediaUploadCheck>
            <MediaUpload
              onSelect={media =>
                setAttributes({
                  mediaId: media.id,
                  mediaUrl: media.url,
                  mediaAlt: media.alt || '',
                })
              }
              allowedTypes={['image']}
              value={mediaId}
              render={({ open }) => (
                <Button variant='secondary' onClick={open}>
                  {__('Replace Image', 'aggressive-apparel')}
                </Button>
              )}
            />
          </MediaUploadCheck>
        </PanelBody>

        <PanelBody title={__('Behavior', 'aggressive-apparel')} initialOpen>
          <RangeControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label={__('Hotspot size', 'aggressive-apparel')}
            value={hotspotSize}
            onChange={value => setAttributes({ hotspotSize: value ?? 32 })}
            min={20}
            max={48}
            step={2}
          />
          <ToggleControl
            __nextHasNoMarginBottom
            label={__('Open product card on hover', 'aggressive-apparel')}
            checked={openOnHover}
            onChange={value => setAttributes({ openOnHover: value })}
          />
        </PanelBody>

        <PanelBody title={__('Hotspots', 'aggressive-apparel')} initialOpen>
          <p>
            {__(
              'Click on the image to place hotspots, then drag dots to reposition them.',
              'aggressive-apparel'
            )}
          </p>
          {productSearchError ? (
            <Notice status='error' isDismissible={false}>
              {productSearchError}
            </Notice>
          ) : null}
          {hotspots.map((hotspot, index) => (
            <div
              key={index}
              style={{
                marginBottom: '1rem',
                padding: '0.5rem',
                border: '1px solid #ddd',
                borderRadius: '4px',
              }}
            >
              <p style={{ margin: '0 0 0.5rem', fontWeight: 600 }}>
                {`#${index + 1}`}
              </p>
              <ComboboxControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__('Product', 'aggressive-apparel')}
                value={hotspot.productId ? String(hotspot.productId) : ''}
                options={productSelectOptions}
                onFilterValueChange={setProductSearch}
                isLoading={isLoadingProducts}
                placeholder={__('Search products', 'aggressive-apparel')}
                onChange={value => {
                  const productId = parseInt(value ?? '', 10) || 0;
                  const selected = productOptionsById.get(String(productId));

                  updateHotspot(index, {
                    productId,
                    productName: productId
                      ? selected?.name || hotspot.productName
                      : '',
                  });
                }}
                help={
                  isLoadingProducts
                    ? __('Loading products...', 'aggressive-apparel')
                    : __(
                        'Type at least 2 characters to search products.',
                        'aggressive-apparel'
                      )
                }
              />
              <TextControl
                label={__(
                  'Product Name (optional label)',
                  'aggressive-apparel'
                )}
                value={hotspot.productName}
                onChange={val =>
                  updateHotspot(index, {
                    productName: val,
                  })
                }
              />
              <RangeControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__('Horizontal position (%)', 'aggressive-apparel')}
                value={hotspot.x}
                onChange={value =>
                  updateHotspot(index, { x: value ?? hotspot.x })
                }
                min={0}
                max={100}
                step={0.5}
              />
              <RangeControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__('Vertical position (%)', 'aggressive-apparel')}
                value={hotspot.y}
                onChange={value =>
                  updateHotspot(index, { y: value ?? hotspot.y })
                }
                min={0}
                max={100}
                step={0.5}
              />
              <Button
                variant='link'
                isDestructive
                onClick={() => removeHotspot(index)}
              >
                {__('Remove Hotspot', 'aggressive-apparel')}
              </Button>
            </div>
          ))}
        </PanelBody>
      </InspectorControls>

      <InspectorControls group='color'>
        <ColorGradientSettingsDropdown
          panelId={clientId}
          settings={[
            colorSetting(
              __('Hotspot background', 'aggressive-apparel'),
              'hotspotBgColor'
            ),
            colorSetting(
              __('Hotspot text', 'aggressive-apparel'),
              'hotspotTextColor'
            ),
            colorSetting(
              __('Popup card background', 'aggressive-apparel'),
              'cardBgColor'
            ),
            colorSetting(
              __('Popup card text', 'aggressive-apparel'),
              'cardTextColor'
            ),
            colorSetting(
              __('Popup chevron background', 'aggressive-apparel'),
              'actionBgColor'
            ),
            colorSetting(
              __('Popup chevron color', 'aggressive-apparel'),
              'actionIconColor'
            ),
          ]}
          __experimentalIsRenderedInSidebar
          {...colorGradientSettings}
        />
      </InspectorControls>

      <div {...blockProps}>
        <div
          className='aggressive-apparel-lookbook__image-wrapper'
          onClick={handleImageClick}
        >
          <img
            src={mediaUrl}
            alt={mediaAlt}
            className='aggressive-apparel-lookbook__image'
          />
          {hotspots.map((hotspot, index) => (
            <button
              key={index}
              type='button'
              className={`aggressive-apparel-lookbook__hotspot${
                editingIndex === index ? 'is-editing' : ''
              }${draggingIndex === index ? 'is-dragging' : ''}`}
              data-aa-hotspot-index={index}
              onPointerDown={e => handleHotspotPointerDown(e, index)}
              onPointerMove={e => handleHotspotPointerMove(e, index)}
              onPointerUp={handleHotspotPointerUp}
              onPointerCancel={handleHotspotPointerUp}
              onClick={e => {
                e.stopPropagation();

                if (hasDraggedRef.current) {
                  hasDraggedRef.current = false;
                  return;
                }

                setEditingIndex(editingIndex === index ? null : index);
              }}
              style={{
                left: `${hotspot.x}%`,
                top: `${hotspot.y}%`,
              }}
              aria-label={`Hotspot ${index + 1}`}
            >
              <span className='aggressive-apparel-lookbook__hotspot-dot'>
                {index + 1}
              </span>
            </button>
          ))}
        </div>
      </div>
    </>
  );
}
