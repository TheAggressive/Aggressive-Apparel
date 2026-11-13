/**
 * Product Specs admin — a product's Size & specs tab.
 *
 * A size guide's spec rows (SpecRow), for one product: each spec that
 * applies, the product's own value, a Shown/Hidden switch, and an edit
 * panel to reword it for this product, with the live shopper preview. Each
 * row says where an empty value comes from (an attribute, the size guide,
 * or the spec's default). Specs the size guide hides are listed with why.
 *
 * Everything is part of the product form (formFields), so saving the
 * product saves it, the way the rest of the product data tabs work. The
 * tab's heading and intro, with its link, are the server's
 * (Product_Specs_Admin::render_product_fields); this draws only text.
 *
 * @package Aggressive_Apparel
 */

import { useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { readWording, worded, type Wording } from './_wording';
import { describeValue } from './_utils';
import GuidePreview from './_GuidePreview';
import ValuePicker from './_ValuePicker';
import { EditPanel, SpecRow, WordingFields } from './_SpecRow';
import {
  formFields,
  panelSpec,
  sourceText,
  type PanelData,
} from './_productPanel';

export default function ProductPanel({ data }: { data: PanelData }) {
  const { items, field } = data;
  const specs = useMemo(() => items.map(panelSpec), [items]);
  const [values, setValues] = useState<Record<string, string>>(() =>
    Object.fromEntries(items.map(item => [item.key, item.own]))
  );
  const [hidden, setHidden] = useState<string[]>(() =>
    items.filter(item => item.hidden).map(item => item.key)
  );
  const [wording, setWording] = useState<Wording>(() =>
    readWording(
      Object.fromEntries(
        items
          .filter(item => Object.keys(item.wording).length > 0)
          .map(item => [item.key, item.wording])
      )
    )
  );
  const [open, setOpen] = useState<string | null>(null);

  const shown = specs
    .filter(spec => !hidden.includes(spec.key))
    .map(spec => worded(spec, wording));

  return (
    <>
      {items.length === 0 && data.hiddenByGuide.length === 0 && (
        <p className='aa-ps-help aa-ps-help--notice'>
          {__(
            'No specs apply to this product’s categories.',
            'aggressive-apparel'
          )}
        </p>
      )}

      <div className='aa-ps-spec-rows'>
        {items.map((item, i) => {
          const spec = specs[i];
          const here = worded(spec, wording);
          const value = values[item.key] ?? '';
          const isHidden = hidden.includes(item.key);
          const notes = [sourceText(item, here, value, isHidden)];
          if (wording[item.key]) {
            notes.push(__('Reworded for this product', 'aggressive-apparel'));
          }
          if (item.locked) {
            notes.push(
              __(
                'Its attribute sets this product’s variations, so change it in the Attributes tab.',
                'aggressive-apparel'
              )
            );
          }
          return (
            <SpecRow
              key={item.key}
              title={here.name}
              notes={notes}
              shown={!isHidden}
              empty={value === '' && item.default === ''}
              switchLabel={sprintf(
                /* translators: %s: spec name. */
                __('Show %s on this product', 'aggressive-apparel'),
                here.name
              )}
              onShow={show =>
                setHidden(
                  show
                    ? hidden.filter(key => key !== item.key)
                    : [...hidden, item.key]
                )
              }
              editing={open === item.key}
              onEdit={() => setOpen(open === item.key ? null : item.key)}
              panel={
                <EditPanel
                  title={sprintf(
                    /* translators: %s: spec name. */
                    __('Edit %s', 'aggressive-apparel'),
                    here.name
                  )}
                  help={__(
                    'Reword it for this product only. Blank fields keep the wording shown greyed out.',
                    'aggressive-apparel'
                  )}
                  onDone={() => setOpen(null)}
                >
                  <WordingFields
                    item={spec}
                    wording={wording}
                    onChange={setWording}
                  />
                </EditPanel>
              }
            >
              {item.locked ? (
                <span className='aa-ps-product__locked'>
                  {describeValue(here, item.own || item.default)}
                </span>
              ) : (
                <ValuePicker
                  item={here}
                  value={value}
                  label={here.name}
                  inherited={describeValue(here, item.default)}
                  existingOnly={!data.canCreate}
                  onChange={next =>
                    setValues({
                      ...values,
                      [item.key]: next,
                    })
                  }
                />
              )}
            </SpecRow>
          );
        })}
      </div>

      {data.hiddenByGuide.length > 0 && (
        <p className='aa-ps-help aa-ps-help--notice'>
          {sprintf(
            /* translators: 1: spec names, 2: size guide name. */
            __(
              'Also for this product’s categories, but hidden by the “%2$s” size guide: %1$s. Show them on that guide to set them here.',
              'aggressive-apparel'
            ),
            data.hiddenByGuide.map(item => item.title).join(', '),
            data.guide
          )}
        </p>
      )}

      {items.length > 0 && <GuidePreview items={shown} values={values} />}

      {formFields(field, items, values, hidden, wording).map(
        ([name, value], i) => (
          <input key={i} type='hidden' name={name} value={value} />
        )
      )}
    </>
  );
}
