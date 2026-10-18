/**
 * Product Specs settings — one size guide.
 *
 * Everything about a garment blank in one form: its name, size chart, notes
 * and measuring diagram, specs, and where it applies. One save writes it all
 * (Size_Guide_Rest), including the specs the guide owns, which the server
 * merges into the spec registry (Size_Guide_Spec_Items).
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useState } from '@wordpress/element';
import {
  Button,
  CheckboxControl,
  Notice,
  Spinner,
  TextControl,
  TextareaControl,
  ToggleControl,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import type {
  Config,
  DraftItem,
  GuideDetail,
  Registry,
  SpecItem,
} from './_types';
import { toDraft, toStored } from './_utils';
import { templates } from './_chart';
import ChartEditor from './_ChartEditor';
import ChartPreview from './_ChartPreview';
import GuideSpecs from './_GuideSpecs';
import ApplyToProducts from './_ApplyToProducts';

type Attachment = {
  id: number;
  url: string;
  sizes?: { medium?: { url: string } };
};

type MediaFrame = {
  on: (event: string, callback: () => void) => void;
  open: () => void;
  state: () => {
    get: (key: string) => { first: () => { toJSON: () => unknown } };
  };
};

type SaveError = { message?: string; data?: { errors?: string[] } };

/** Error text plus any per-spec problems the server listed. */
function errorText(failure: unknown, fallback: string): string {
  const { message, data } = failure as SaveError;
  return [message ?? fallback, ...(data?.errors ?? [])].join(' ');
}

type Props = {
  config: Config;
  /** Saved spec items (the Specs tab's last save). */
  items: SpecItem[];
  guideId: number | 'new';
  /** A save changed the spec registry. */
  onRegistry: (registry: Registry) => void;
  onClose: (changed: boolean) => void;
  onOpen: (id: number) => void;
  onCreated: (id: number) => void;
  onDirty: (dirty: boolean) => void;
};

function blank(): GuideDetail {
  return {
    id: 0,
    title: '',
    chart: templates()[0].chart,
    notes: '',
    diagram: null,
    specs: {},
    hidden: [],
    categories: [],
    isDefault: false,
    usage: { global: false, categories: [], products: 0 },
    legacyHtml: '',
    legacyEditUrl: '',
  };
}

export default function GuideEditor({
  config,
  items,
  guideId,
  onRegistry,
  onClose,
  onOpen,
  onCreated,
  onDirty,
}: Props) {
  const [guide, setGuide] = useState<GuideDetail | null>(
    guideId === 'new' ? blank() : null
  );
  const [dirty, setDirtyState] = useState(guideId === 'new');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  // A converted guide had content the chart can't hold (shown as a notice).
  const [partial, setPartial] = useState(false);
  const [saved, setSaved] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);
  // A new guide starts dirty (it can be saved as-is), but leaving it
  // untouched discards nothing worth asking about.
  const [touched, setTouched] = useState(false);
  const [confirmLeave, setConfirmLeave] = useState(false);
  // The guide's own specs, edited here and saved with the guide.
  const [own, setOwn] = useState<DraftItem[]>([]);
  const [ownDirty, setOwnDirty] = useState(false);
  const savedId = guide?.id ?? 0;

  // Follow the saved registry (after this or another save) unless the
  // guide's own specs have unsaved edits.
  useEffect(() => {
    if (!ownDirty) {
      setOwn(
        savedId > 0
          ? items.filter(item => item.guides.includes(savedId)).map(toDraft)
          : []
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- ownDirty only gates.
  }, [items, savedId]);

  const setDirty = (value: boolean) => {
    setDirtyState(value);
    onDirty(value);
  };

  useEffect(() => {
    if (guideId === 'new') {
      onDirty(true);
      return;
    }
    apiFetch({ path: `${config.guidesPath}/${guideId}` })
      .then(result => setGuide(result as GuideDetail))
      .catch((failure: { message?: string }) =>
        setError(
          failure.message ??
            __('The size guide could not be loaded.', 'aggressive-apparel')
        )
      );
    // eslint-disable-next-line react-hooks/exhaustive-deps -- load once per guide.
  }, [guideId]);

  // Whatever this editor left unsaved goes with it.
  // eslint-disable-next-line react-hooks/exhaustive-deps -- unmount only.
  useEffect(() => () => onDirty(false), []);

  if (!guide) {
    return error ? (
      <Notice status='error' isDismissible={false}>
        {error}
      </Notice>
    ) : (
      <Spinner />
    );
  }

  const markDirty = () => {
    setDirty(true);
    setTouched(true);
    setConfirmLeave(false);
    setSaved(false);
  };

  // Functional, so a value move and a spec edit in one change both land.
  const update = (patch: Partial<GuideDetail>) => {
    setGuide(current => current && { ...current, ...patch });
    markDirty();
  };

  const updateOwn = (next: DraftItem[]) => {
    setOwn(next);
    setOwnDirty(true);
    markDirty();
  };

  /** Save the guide; true when it saved. */
  const save = async (): Promise<boolean> => {
    setSaving(true);
    setError('');
    try {
      const result = (await apiFetch({
        path: guide.id ? `${config.guidesPath}/${guide.id}` : config.guidesPath,
        method: 'POST',
        data: {
          title: guide.title,
          chart: guide.chart,
          notes: guide.notes,
          diagram: guide.diagram?.id ?? 0,
          specs: guide.specs,
          hidden: guide.hidden,
          ...(ownDirty && { specItems: own.map(toStored) }),
          categories: guide.categories,
          isDefault: guide.isDefault,
        },
      })) as GuideDetail & { registry?: Registry };
      const { registry, ...detail } = result;
      setGuide(detail);
      setOwnDirty(false);
      if (registry) {
        onRegistry(registry);
      }
      setDirty(false);
      setSaved(true);
      if (!guide.id) {
        // Stay in this editor (keeping the saved notice); just record the ID.
        onCreated(result.id);
      }
      return true;
    } catch (failure) {
      setError(
        errorText(
          failure,
          __('Saving failed. Try again.', 'aggressive-apparel')
        )
      );
      return false;
    } finally {
      setSaving(false);
    }
  };

  // Unsaved edits are saved first, so the copy is what's on screen.
  const duplicate = async () => {
    if (dirty && !(await save())) {
      return;
    }
    setSaving(true);
    setError('');
    try {
      const copy = (await apiFetch({
        path: `${config.guidesPath}/${guide.id}/duplicate`,
        method: 'POST',
      })) as GuideDetail & { registry?: Registry };
      if (copy.registry) {
        onRegistry(copy.registry);
      }
      onOpen(copy.id);
    } catch (failure) {
      setError(
        (failure as { message?: string }).message ??
          __('The guide could not be duplicated.', 'aggressive-apparel')
      );
    } finally {
      setSaving(false);
    }
  };

  const remove = async () => {
    setSaving(true);
    setError('');
    try {
      await apiFetch({
        path: `${config.guidesPath}/${guide.id}`,
        method: 'DELETE',
      });
      setDirty(false);
      onClose(true);
    } catch (failure) {
      setConfirmDelete(false);
      setError(
        (failure as { message?: string }).message ??
          __('The guide could not be deleted.', 'aggressive-apparel')
      );
    } finally {
      setSaving(false);
    }
  };

  const pickDiagram = () => {
    const media = (
      window as unknown as { wp?: { media?: (options: object) => MediaFrame } }
    ).wp?.media;
    if (!media) {
      return;
    }
    const frame = media({
      title: __('Measuring diagram', 'aggressive-apparel'),
      library: { type: 'image' },
      multiple: false,
      button: { text: __('Use this image', 'aggressive-apparel') },
    });
    frame.on('select', () => {
      const image = frame
        .state()
        .get('selection')
        .first()
        .toJSON() as Attachment;
      update({
        diagram: { id: image.id, url: image.sizes?.medium?.url ?? image.url },
      });
    });
    frame.open();
  };

  const convertLegacy = async () => {
    setError('');
    try {
      const imported = (await apiFetch({
        path: `${config.guidesPath}/${guide.id}/convert`,
        method: 'POST',
      })) as Pick<GuideDetail, 'chart' | 'notes' | 'diagram'> & {
        lossless: boolean;
      };
      update({
        chart: imported.chart,
        notes: guide.notes || imported.notes,
        diagram: guide.diagram ?? imported.diagram,
        legacyHtml: '',
      });
      setPartial(!imported.lossless);
    } catch (failure) {
      setError(
        (failure as { message?: string }).message ??
          __('The guide could not be converted.', 'aggressive-apparel')
      );
    }
  };

  const startChart = () =>
    update({ chart: templates()[0].chart, legacyHtml: '' });

  const toggleCategory = (id: number, checked: boolean) =>
    update({
      categories: checked
        ? [...guide.categories, id]
        : guide.categories.filter(category => category !== id),
    });

  return (
    <div className='aa-ps-guide'>
      <div className='aa-ps-guide__top'>
        {confirmLeave ? (
          <div className='aa-ps-guide__leave'>
            <span className='aa-ps-help'>
              {__('Discard your unsaved changes?', 'aggressive-apparel')}
            </span>
            <Button
              variant='secondary'
              isDestructive
              onClick={() => {
                setDirty(false);
                onClose(false);
              }}
            >
              {__('Discard', 'aggressive-apparel')}
            </Button>
            <Button variant='tertiary' onClick={() => setConfirmLeave(false)}>
              {__('Keep editing', 'aggressive-apparel')}
            </Button>
          </div>
        ) : (
          <Button
            variant='link'
            onClick={() =>
              dirty && (guide.id > 0 || touched)
                ? setConfirmLeave(true)
                : onClose(false)
            }
          >
            {__('← All size guides', 'aggressive-apparel')}
          </Button>
        )}
        {guide.id > 0 && (
          <span className='aa-ps-help'>
            {sprintf(
              /* translators: %d: number of products. */
              _n(
                'Used by %d product directly',
                'Used by %d products directly',
                guide.usage.products,
                'aggressive-apparel'
              ),
              guide.usage.products
            )}
          </span>
        )}
      </div>

      <TextControl
        className='aa-ps-guide__name'
        label={__('Name', 'aggressive-apparel')}
        value={guide.title}
        placeholder={__('Jersey Tee 4.2 oz', 'aggressive-apparel')}
        onChange={title => update({ title })}
        help={__(
          'Name it after the garment blank, so it is easy to pick on a product.',
          'aggressive-apparel'
        )}
        __next40pxDefaultSize
      />

      {guide.legacyHtml && (
        <div className='aa-ps-legacy'>
          <p>
            <strong>
              {__(
                'This guide uses the old free-form layout.',
                'aggressive-apparel'
              )}
            </strong>{' '}
            {__(
              'Convert it to an editable chart: the table becomes the grid and the other text becomes notes. Nothing changes on the store until you save.',
              'aggressive-apparel'
            )}
          </p>
          <div className='aa-ps-legacy__actions'>
            {guide.legacyHtml.includes('<table') ? (
              <Button variant='primary' onClick={convertLegacy}>
                {__('Convert to chart', 'aggressive-apparel')}
              </Button>
            ) : (
              <Button variant='primary' onClick={startChart}>
                {__('Start a chart', 'aggressive-apparel')}
              </Button>
            )}
            {guide.legacyEditUrl && (
              <Button variant='tertiary' href={guide.legacyEditUrl}>
                {__('Edit the old layout instead', 'aggressive-apparel')}
              </Button>
            )}
          </div>
          <div
            className='aa-ps-legacy__preview'
            // Server-rendered with wp_kses_post (Size_Guide_Rest::guide).
            dangerouslySetInnerHTML={{ __html: guide.legacyHtml }}
          />
        </div>
      )}

      {!guide.legacyHtml && (
        <section className='aa-ps-guide__section'>
          <div className='aa-ps-guide__section-head'>
            <h2>{__('Size chart', 'aggressive-apparel')}</h2>
            {guide.id === 0 && (
              <div
                className='aa-ps-templates'
                role='group'
                aria-label={__('Start from', 'aggressive-apparel')}
              >
                <span className='aa-ps-help'>
                  {__('Start from:', 'aggressive-apparel')}
                </span>
                {templates().map(template => (
                  <Button
                    key={template.name}
                    variant='secondary'
                    size='compact'
                    onClick={() => update({ chart: template.chart })}
                  >
                    {template.name}
                  </Button>
                ))}
              </div>
            )}
          </div>
          {partial && (
            <Notice status='warning' onRemove={() => setPartial(false)}>
              {__(
                'Some of the old content couldn’t be carried over (extra images, embeds, or other tables). Check the chart and notes before saving; the old content stays on the guide until you do.',
                'aggressive-apparel'
              )}
            </Notice>
          )}
          <div className='aa-ps-guide__chart'>
            <ChartEditor
              chart={guide.chart}
              limits={config.limits}
              onChange={chart => update({ chart })}
            />
            <div className='aa-ps-guide__preview'>
              <p className='aa-ps-preview__label'>
                {__('What shoppers see', 'aggressive-apparel')}
              </p>
              <ChartPreview chart={guide.chart} />
            </div>
          </div>
        </section>
      )}

      <section className='aa-ps-guide__section aa-ps-guide__notes'>
        <h2>{__('Notes and diagram', 'aggressive-apparel')}</h2>
        <TextareaControl
          label={__('Notes', 'aggressive-apparel')}
          value={guide.notes}
          rows={4}
          placeholder={__(
            'How to measure, or fit notes like “Model is 6’1” wearing M”.',
            'aggressive-apparel'
          )}
          onChange={notes => update({ notes })}
          help={__(
            'Shown under the chart. Links become clickable.',
            'aggressive-apparel'
          )}
        />
        <div className='aa-ps-diagram'>
          {guide.diagram ? (
            <img src={guide.diagram.url} alt='' />
          ) : (
            <span className='aa-ps-diagram__empty' aria-hidden='true' />
          )}
          <div className='aa-ps-diagram__actions'>
            <Button variant='secondary' onClick={pickDiagram}>
              {guide.diagram
                ? __('Replace diagram', 'aggressive-apparel')
                : __('Add a measuring diagram', 'aggressive-apparel')}
            </Button>
            {guide.diagram && (
              <Button
                variant='tertiary'
                isDestructive
                onClick={() => update({ diagram: null })}
              >
                {__('Remove', 'aggressive-apparel')}
              </Button>
            )}
          </div>
        </div>
      </section>

      <GuideSpecs
        config={config}
        guideId={guide.id}
        items={items}
        own={own}
        values={guide.specs}
        // Mirrors Size_Chart::sanitize (a row needs a size) and
        // Size_Guide::guide_content (old free-form content also counts).
        usable={
          guide.legacyHtml !== '' ||
          guide.chart.rows.some(row => row.size.trim() !== '')
        }
        hidden={guide.hidden}
        onValues={specs => update({ specs })}
        onHidden={hidden => update({ hidden })}
        onOwn={updateOwn}
      />
      {guide.id > 0 && (
        <ApplyToProducts
          guidesPath={config.guidesPath}
          guideId={guide.id}
          dirty={dirty}
        />
      )}

      <section className='aa-ps-guide__section'>
        <h2>{__('Where it applies', 'aggressive-apparel')}</h2>
        <p className='aa-ps-help'>
          {__(
            'Products in these categories use this guide unless they pick another on their Size & specs tab.',
            'aggressive-apparel'
          )}
        </p>
        <div className='aa-ps-categories'>
          {config.categories.map(category => (
            <CheckboxControl
              key={category.id}
              label={category.name}
              checked={guide.categories.includes(category.id)}
              onChange={checked => toggleCategory(category.id, checked)}
            />
          ))}
        </div>
        <ToggleControl
          label={__('Store-wide default', 'aggressive-apparel')}
          help={__(
            'Used for products with no guide of their own or from their category.',
            'aggressive-apparel'
          )}
          checked={guide.isDefault}
          onChange={isDefault => update({ isDefault })}
        />
      </section>

      {error && (
        <Notice status='error' isDismissible={false}>
          {error}
        </Notice>
      )}
      {saved && (
        <Notice status='success' onRemove={() => setSaved(false)}>
          {__('Size guide saved.', 'aggressive-apparel')}
        </Notice>
      )}

      <div className='aa-ps-save aa-ps-guide__footer'>
        <Button
          variant='primary'
          disabled={!dirty || saving}
          onClick={() => void save()}
        >
          {saving
            ? __('Saving…', 'aggressive-apparel')
            : __('Save size guide', 'aggressive-apparel')}
        </Button>
        {saving && <Spinner />}
        {guide.id > 0 && (
          <>
            <Button variant='secondary' disabled={saving} onClick={duplicate}>
              {dirty
                ? __('Save and duplicate', 'aggressive-apparel')
                : __('Duplicate', 'aggressive-apparel')}
            </Button>
            {confirmDelete ? (
              <>
                <span className='aa-ps-help'>
                  {__(
                    'Move to trash? Products using it fall back to their category or the default.',
                    'aggressive-apparel'
                  )}
                </span>
                <Button
                  variant='secondary'
                  isDestructive
                  disabled={saving}
                  onClick={remove}
                >
                  {__('Move to trash', 'aggressive-apparel')}
                </Button>
                <Button
                  variant='tertiary'
                  onClick={() => setConfirmDelete(false)}
                >
                  {__('Cancel', 'aggressive-apparel')}
                </Button>
              </>
            ) : (
              <Button
                variant='tertiary'
                isDestructive
                onClick={() => setConfirmDelete(true)}
              >
                {__('Delete', 'aggressive-apparel')}
              </Button>
            )}
          </>
        )}
        {dirty && !saving && (
          <span className='aa-ps-help'>
            {__('Unsaved changes', 'aggressive-apparel')}
          </span>
        )}
      </div>
    </div>
  );
}
