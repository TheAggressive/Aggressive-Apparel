/**
 * Product Specs settings — Size guides tab.
 *
 * The list of guides (one per garment blank) and, when one is open, its
 * editor. The open guide is kept in the URL (?guide=ID or ?guide=new) so a
 * link from a product lands straight on it.
 *
 * @package Aggressive_Apparel
 */

import { useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { Config, GuideSummary, Registry, SpecItem, Usage } from './_types';
import GuideEditor from './_GuideEditor';

type Props = {
  config: Config;
  /** Saved spec items, for the guide's Specs section. */
  items: SpecItem[];
  onRegistry: (registry: Registry) => void;
  guides: GuideSummary[];
  onGuides: (guides: GuideSummary[]) => void;
  onDirty: (dirty: boolean) => void;
};

function setGuideParam(value: number | 'new' | null): void {
  const url = new URL(window.location.href);
  if (value === null) {
    url.searchParams.delete('guide');
  } else {
    url.searchParams.set('guide', String(value));
  }
  window.history.replaceState(null, '', url.toString());
}

function usageText(usage: Usage): string {
  const parts: string[] = [];
  if (usage.global) {
    parts.push(__('Store default', 'aggressive-apparel'));
  }
  parts.push(...usage.categories);
  if (usage.products > 0) {
    parts.push(
      sprintf(
        /* translators: %d: number of products. */
        _n('%d product', '%d products', usage.products, 'aggressive-apparel'),
        usage.products
      )
    );
  }
  return parts.length > 0
    ? parts.join(', ')
    : __('Not used yet', 'aggressive-apparel');
}

export default function GuidesTab({
  config,
  items,
  onRegistry,
  guides,
  onGuides,
  onDirty,
}: Props) {
  const [open, setOpen] = useState<number | 'new' | null>(
    config.guide === 'new' || config.guide > 0 ? config.guide : null
  );
  const [loading, setLoading] = useState(false);

  const show = (value: number | 'new' | null) => {
    setOpen(value);
    setGuideParam(value);
  };

  const refresh = async () => {
    setLoading(true);
    try {
      onGuides((await apiFetch({ path: config.guidesPath })) as GuideSummary[]);
    } finally {
      setLoading(false);
    }
  };

  if (open !== null) {
    return (
      <GuideEditor
        key={String(open)}
        config={config}
        items={items}
        onRegistry={onRegistry}
        guideId={open}
        onDirty={onDirty}
        onOpen={id => {
          show(id);
          void refresh();
        }}
        onCreated={id => {
          setGuideParam(id);
          void refresh();
        }}
        onClose={changed => {
          onDirty(false);
          show(null);
          if (changed || open !== null) {
            void refresh();
          }
        }}
      />
    );
  }

  return (
    <div className='aa-ps-tab aa-ps-guides'>
      <div className='aa-ps-guides__head'>
        <p className='aa-ps-intro'>
          {__(
            'One guide per garment blank: its size chart and notes. Products pick a guide, or get one from their category.',
            'aggressive-apparel'
          )}
        </p>
        <Button variant='primary' onClick={() => show('new')}>
          {__('New size guide', 'aggressive-apparel')}
        </Button>
      </div>

      {loading && <Spinner />}

      {guides.length === 0 ? (
        <div className='aa-ps-empty'>
          <p>
            {__(
              'No size guides yet. Create one from a template in a minute.',
              'aggressive-apparel'
            )}
          </p>
          <Button variant='secondary' onClick={() => show('new')}>
            {__('New size guide', 'aggressive-apparel')}
          </Button>
        </div>
      ) : (
        <ul className='aa-ps-guides__list'>
          {guides.map(guide => (
            <li key={guide.id}>
              <button
                type='button'
                className='aa-ps-guides__item'
                onClick={() => show(guide.id)}
              >
                <span className='aa-ps-guides__name'>
                  {guide.title}
                  {guide.legacy && (
                    <span className='aa-ps-badge'>
                      {__('Old layout', 'aggressive-apparel')}
                    </span>
                  )}
                </span>
                <span className='aa-ps-guides__meta'>
                  {guide.sizes > 0
                    ? sprintf(
                        /* translators: 1: sizes, 2: measurements. */
                        __(
                          '%1$d sizes × %2$d measurements',
                          'aggressive-apparel'
                        ),
                        guide.sizes,
                        guide.columns
                      )
                    : __('No chart yet', 'aggressive-apparel')}
                </span>
                <span className='aa-ps-guides__usage'>
                  {usageText(guide.usage)}
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
