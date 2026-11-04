/**
 * Product Specs settings — moving the setup between sites.
 *
 * Export downloads every spec and size guide as one file; Import reads such
 * a file from another site (a local copy, say), shows what it will add and
 * change, and merges it in once confirmed (Product_Specs_Transfer). Nothing
 * only on this site is deleted. Importing reloads the page, so it waits
 * until unsaved changes are saved.
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useState } from '@wordpress/element';
import {
  Button,
  DropZone,
  FormFileUpload,
  Modal,
  Notice,
  Spinner,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import { errorText } from './_utils';
import { exportName } from './_transferName';

type Changes = { added: string[]; changed: string[]; same: number };

type Plan = {
  site: string;
  exported: string;
  specs: Changes;
  guides: Changes;
  missing: string[];
};

type Props = {
  /** The settings REST path. */
  restPath: string;
  /** Whether anything on the page is unsaved. */
  dirty: boolean;
};

function Counts({
  title,
  changes,
  noun,
}: {
  title: string;
  changes: Changes;
  noun: (count: number) => string;
}) {
  const parts = [
    changes.added.length > 0 &&
      sprintf(
        /* translators: %d: number of new items. */
        __('%d new', 'aggressive-apparel'),
        changes.added.length
      ),
    changes.changed.length > 0 &&
      sprintf(
        /* translators: %d: number of updated items. */
        __('%d updated', 'aggressive-apparel'),
        changes.changed.length
      ),
    changes.same > 0 &&
      sprintf(
        /* translators: %d: number of unchanged items. */
        __('%d already the same', 'aggressive-apparel'),
        changes.same
      ),
  ].filter(Boolean);
  const total = changes.added.length + changes.changed.length + changes.same;

  return (
    <div className='aa-ps-transfer__part'>
      <h3 className='aa-ps-eyebrow'>{title}</h3>
      <p>
        <strong>{noun(total)}</strong>
        {parts.length > 0 && ` · ${parts.join(' · ')}`}
      </p>
      {changes.added.length + changes.changed.length > 0 && (
        <ul className='aa-ps-transfer__list'>
          {changes.added.map(name => (
            <li key={`added-${name}`}>
              <span className='aa-ps-badge'>
                {__('New', 'aggressive-apparel')}
              </span>
              {name}
            </li>
          ))}
          {changes.changed.map(name => (
            <li key={`changed-${name}`}>
              <span className='aa-ps-badge'>
                {__('Updated', 'aggressive-apparel')}
              </span>
              {name}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

export default function Transfer({ restPath, dirty }: Props) {
  const [exporting, setExporting] = useState(false);
  const [exportNote, setExportNote] = useState('');
  const [importing, setImporting] = useState(false);
  const [file, setFile] = useState<unknown>(null);
  const [plan, setPlan] = useState<Plan | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [done, setDone] = useState(false);

  // The export note says its piece, then gets out of the way.
  useEffect(() => {
    if (exportNote === '') {
      return;
    }
    const timer = window.setTimeout(() => setExportNote(''), 6000);
    return () => window.clearTimeout(timer);
  }, [exportNote]);

  const download = async () => {
    setExporting(true);
    setExportNote('');
    try {
      const data = await apiFetch({ path: `${restPath}/export` });
      const blob = new Blob([JSON.stringify(data, null, 2)], {
        type: 'application/json',
      });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = exportName(window.location.hostname, new Date());
      link.click();
      URL.revokeObjectURL(link.href);
      setExportNote(
        dirty
          ? __(
              'Exported what’s saved. Your unsaved changes aren’t in the file.',
              'aggressive-apparel'
            )
          : __('Exported.', 'aggressive-apparel')
      );
    } catch (failure) {
      setExportNote(
        errorText(
          failure,
          __('The export failed. Try again.', 'aggressive-apparel')
        )
      );
    } finally {
      setExporting(false);
    }
  };

  const reset = () => {
    setFile(null);
    setPlan(null);
    setError('');
    setDone(false);
  };

  const close = () => {
    setImporting(false);
    reset();
  };

  const read = async (chosen: File | undefined) => {
    if (!chosen) {
      return;
    }
    reset();
    setBusy(true);
    try {
      let parsed: unknown;
      try {
        parsed = JSON.parse(await chosen.text());
      } catch {
        throw new Error(
          __(
            'That file couldn’t be read. Choose the .json file that Export made.',
            'aggressive-apparel'
          )
        );
      }
      setPlan(
        await apiFetch<Plan>({
          path: `${restPath}/import`,
          method: 'POST',
          data: { file: parsed, apply: false },
        })
      );
      setFile(parsed);
    } catch (failure) {
      setError(
        errorText(
          failure,
          __('That file couldn’t be checked.', 'aggressive-apparel')
        )
      );
    } finally {
      setBusy(false);
    }
  };

  const apply = async () => {
    setBusy(true);
    setError('');
    try {
      await apiFetch({
        path: `${restPath}/import`,
        method: 'POST',
        data: { file, apply: true },
      });
      setDone(true);
    } catch (failure) {
      setError(
        errorText(
          failure,
          __('The import failed. Nothing was changed.', 'aggressive-apparel')
        )
      );
    } finally {
      setBusy(false);
    }
  };

  const pending = plan
    ? plan.specs.added.length +
      plan.specs.changed.length +
      plan.guides.added.length +
      plan.guides.changed.length
    : 0;

  return (
    <div className='aa-ps-transfer'>
      {exportNote && (
        <span className='aa-ps-help' role='status'>
          {exportNote}
        </span>
      )}
      <Button
        variant='tertiary'
        size='compact'
        icon='download'
        disabled={exporting}
        onClick={() => void download()}
      >
        {__('Export to file', 'aggressive-apparel')}
      </Button>
      <Button
        variant='tertiary'
        size='compact'
        icon='upload'
        onClick={() => setImporting(true)}
      >
        {__('Import from file', 'aggressive-apparel')}
      </Button>

      {importing && (
        <Modal
          title={__('Import from another site', 'aggressive-apparel')}
          onRequestClose={close}
          className='aa-ps-transfer__modal'
          size='medium'
        >
          {done && (
            <>
              <Notice status='success' isDismissible={false}>
                {__('Imported.', 'aggressive-apparel')}
              </Notice>
              <div className='aa-ps-transfer__actions'>
                <Button
                  variant='primary'
                  onClick={() => window.location.reload()}
                >
                  {__('Reload to see them', 'aggressive-apparel')}
                </Button>
              </div>
            </>
          )}

          {!done && !plan && (
            <div className='aa-ps-transfer__drop'>
              <DropZone
                label={__('Drop the file to check it', 'aggressive-apparel')}
                onFilesDrop={files => void read(files[0])}
              />
              <p>
                {__(
                  'Use a file made with “Export to file” on another site, such as your local copy. You’ll see what it adds and changes before anything is saved.',
                  'aggressive-apparel'
                )}
              </p>
              <FormFileUpload
                accept='application/json,.json'
                onChange={event => void read(event.currentTarget.files?.[0])}
                render={({ openFileDialog }) => (
                  <Button
                    variant='secondary'
                    disabled={busy}
                    onClick={openFileDialog}
                  >
                    {__('Choose a file', 'aggressive-apparel')}
                  </Button>
                )}
              />
              <span className='aa-ps-help'>
                {__('or drop it here', 'aggressive-apparel')}
              </span>
              {busy && <Spinner />}
            </div>
          )}

          {!done && plan && (
            <>
              <p className='aa-ps-help'>
                {sprintf(
                  /* translators: 1: site address, 2: date exported. */
                  __('From %1$s, exported %2$s.', 'aggressive-apparel'),
                  plan.site || __('another site', 'aggressive-apparel'),
                  plan.exported
                    ? new Date(plan.exported).toLocaleString()
                    : __('at an unknown time', 'aggressive-apparel')
                )}
              </p>
              <Counts
                title={__('Specs', 'aggressive-apparel')}
                changes={plan.specs}
                noun={count =>
                  sprintf(
                    /* translators: %d: number of specs. */
                    _n('%d spec', '%d specs', count, 'aggressive-apparel'),
                    count
                  )
                }
              />
              <Counts
                title={__('Size guides', 'aggressive-apparel')}
                changes={plan.guides}
                noun={count =>
                  sprintf(
                    /* translators: %d: number of size guides. */
                    _n(
                      '%d size guide',
                      '%d size guides',
                      count,
                      'aggressive-apparel'
                    ),
                    count
                  )
                }
              />
              {plan.missing.length > 0 && (
                <Notice status='warning' isDismissible={false}>
                  {sprintf(
                    /* translators: %s: category paths. */
                    __(
                      'These categories aren’t on this site, so guides and boundaries skip them: %s. Create them first to include them.',
                      'aggressive-apparel'
                    ),
                    plan.missing.join(', ')
                  )}
                </Notice>
              )}
              {dirty && pending > 0 && (
                <Notice status='warning' isDismissible={false}>
                  {__(
                    'This page has unsaved changes. Save or discard them first: importing reloads the page.',
                    'aggressive-apparel'
                  )}
                </Notice>
              )}
              <p className='aa-ps-help'>
                {pending > 0
                  ? __(
                      'Nothing on this site is deleted. Guides are matched by their Printful product, else by name.',
                      'aggressive-apparel'
                    )
                  : __(
                      'Everything in this file is already on this site.',
                      'aggressive-apparel'
                    )}
              </p>
              <div className='aa-ps-transfer__actions'>
                {pending > 0 && (
                  <Button
                    variant='primary'
                    disabled={busy || dirty}
                    onClick={() => void apply()}
                  >
                    {busy
                      ? __('Importing…', 'aggressive-apparel')
                      : __('Import', 'aggressive-apparel')}
                  </Button>
                )}
                {busy && <Spinner />}
                <Button variant='tertiary' disabled={busy} onClick={reset}>
                  {__('Choose another file', 'aggressive-apparel')}
                </Button>
              </div>
            </>
          )}

          {error && (
            <Notice status='error' isDismissible={false}>
              {error}
            </Notice>
          )}
        </Modal>
      )}
    </div>
  );
}
