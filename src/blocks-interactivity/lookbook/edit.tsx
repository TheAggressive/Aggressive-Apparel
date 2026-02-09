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
} from '@wordpress/block-editor';
import {
  PanelBody,
  Button,
  TextControl,
  Placeholder,
} from '@wordpress/components';
import { useState, type MouseEvent } from '@wordpress/element';
import type { BlockEditProps } from '@wordpress/blocks';

interface Hotspot {
  x: number;
  y: number;
  productId: number;
  productName: string;
}

interface LookbookAttributes {
  mediaId: number;
  mediaUrl: string;
  mediaAlt: string;
  hotspots: Hotspot[];
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<LookbookAttributes>) {
  const { mediaId, mediaUrl, mediaAlt, hotspots } = attributes;
  const [editingIndex, setEditingIndex] = useState<number | null>(null);
  const blockProps = useBlockProps({
    className: 'aggressive-apparel-lookbook',
  });

  /**
   * Handle click on the image to place a new hotspot.
   */
  const handleImageClick = (e: MouseEvent<HTMLDivElement>) => {
    const rect = e.currentTarget.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;

    const newHotspot: Hotspot = {
      x: Math.round(x * 10) / 10,
      y: Math.round(y * 10) / 10,
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
  const updateHotspot = (index: number, changes: Partial<Hotspot>) => {
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
    if (editingIndex === index) {
      setEditingIndex(null);
    }
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

        <PanelBody title={__('Hotspots', 'aggressive-apparel')} initialOpen>
          <p>
            {__(
              'Click on the image to place hotspots. Enter a WooCommerce product ID for each.',
              'aggressive-apparel'
            )}
          </p>
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
                {`#${index + 1} (${hotspot.x}%, ${hotspot.y}%)`}
              </p>
              <TextControl
                label={__('Product ID', 'aggressive-apparel')}
                type='number'
                value={String(hotspot.productId || '')}
                onChange={val =>
                  updateHotspot(index, {
                    productId: parseInt(val, 10) || 0,
                  })
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

      <div {...blockProps}>
        {}
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
              }`}
              style={{
                left: `${hotspot.x}%`,
                top: `${hotspot.y}%`,
              }}
              onClick={e => {
                e.stopPropagation();
                setEditingIndex(editingIndex === index ? null : index);
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
