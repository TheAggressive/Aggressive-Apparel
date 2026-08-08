/**
 * Badge studio application shell.
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useMemo, useReducer, useState } from '@wordpress/element';
import Canvas from './_Canvas';
import { cssColorToBadgeColor } from './_color';
import { contrastRatio } from './_contrast';
import Inspector from './_Inspector';
import Library, { type LibraryTab } from './_Library';
import { canRedo, canUndo, historyReducer, initHistory } from './_history';
import { useCompiledBadge } from './_preview';
import { readTermName, syncHiddenFields } from './_sync';
import type { BadgeFields, InspectorTab, StudioConfig } from './_types';
import type { BadgeFieldKey } from './_field-keys';

type Props = {
  config: StudioConfig;
};

/**
 * Format contrast status copy.
 *
 * @param fields Field map.
 * @param i18n   Strings.
 */
function contrastMessage(
  fields: BadgeFields,
  i18n: Record<string, string>
): string {
  if ((fields.badge_fill_mode || 'solid') === 'gradient') {
    return '';
  }
  const bg = fields.badge_bg_color || '';
  if (bg.length === 9 || bg === 'transparent') {
    return i18n.contrastAlpha;
  }
  const bgSolid = cssColorToBadgeColor(bg) || (bg.startsWith('#') ? bg : '');
  const textSolid =
    cssColorToBadgeColor(fields.badge_text_color || '#ffffff') || '#ffffff';
  const ratio = contrastRatio(bgSolid, textSolid);
  if (ratio === null) {
    return '';
  }
  const formatted = ratio.toFixed(1);
  const template = ratio >= 4.5 ? i18n.contrastPass : i18n.contrastFail;
  return template.replace('%s', formatted);
}

/**
 * Stable serialize for dirty / history compares.
 *
 * @param fields Field map.
 */
function serializeFields(fields: BadgeFields): string {
  return JSON.stringify(
    (Object.keys(fields) as BadgeFieldKey[])
      .sort()
      .reduce<BadgeFields>((acc, key) => {
        acc[key] = fields[key] ?? '';
        return acc;
      }, {})
  );
}

/**
 * Submit the taxonomy edit/add form.
 */
function submitTaxonomyForm(): void {
  const form =
    document.getElementById('edittag') || document.getElementById('addtag');
  if (form instanceof HTMLFormElement) {
    const submit =
      form.querySelector<HTMLInputElement>('input[type="submit"]') ||
      form.querySelector<HTMLButtonElement>('button[type="submit"]');
    if (submit) {
      submit.click();
      return;
    }
    form.requestSubmit?.();
  }
}

/**
 * Root studio layout.
 *
 * @param props Component props.
 */
export default function App({ config }: Props) {
  const [history, dispatch] = useReducer(
    historyReducer,
    config.fields,
    initHistory
  );
  const fields = history.present;
  const [tone, setTone] = useState<'light' | 'dark'>('light');
  const [tab, setTab] = useState<InspectorTab>('fill');
  const [libraryTab, setLibraryTab] = useState<LibraryTab>('styles');
  const [search, setSearch] = useState('');
  const [zoom, setZoom] = useState(100);
  const [label, setLabel] = useState(
    config.saleSample || config.label || 'Badge'
  );
  const baseline = useMemo(
    () => serializeFields(config.fields),
    [config.fields]
  );
  const dirty = serializeFields(fields) !== baseline;
  const compiled = useCompiledBadge(config, fields, label);

  useEffect(() => {
    syncHiddenFields(fields);
  }, [fields]);

  useEffect(() => {
    if (config.saleSample) {
      setLabel(config.saleSample);
      return;
    }

    const writeName = (value: string) => {
      const targets = [
        document.getElementById('tag-name'),
        document.querySelector('input[name="name"]'),
      ].filter(Boolean) as HTMLInputElement[];
      targets.forEach(input => {
        if (input.value !== value) {
          input.value = value;
        }
      });
    };

    const update = () => {
      const next = readTermName() || config.label || 'Badge';
      setLabel(next);
      // Keep WP's hidden name field in sync so add/edit save still works.
      writeName(next);
    };
    update();

    const inputs = [
      document.getElementById('tag-name'),
      document.querySelector('input[name="name"]'),
    ].filter(Boolean) as HTMLElement[];

    inputs.forEach(input => {
      input.addEventListener('input', update);
      input.addEventListener('change', update);
    });

    return () => {
      inputs.forEach(input => {
        input.removeEventListener('input', update);
        input.removeEventListener('change', update);
      });
    };
  }, [config.label, config.saleSample]);

  const onPatch = (patch: Partial<BadgeFields>) => {
    dispatch({ type: 'patch', patch });
  };

  const undo = () => {
    dispatch({ type: 'undo' });
  };

  const redo = () => {
    dispatch({ type: 'redo' });
  };

  const onNameChange = (value: string) => {
    setLabel(value);
    const inputs = [
      document.getElementById('tag-name'),
      document.querySelector('input[name="name"]'),
    ].filter(Boolean) as HTMLInputElement[];
    inputs.forEach(input => {
      input.value = value;
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
  };

  const isSystem = config.badgeType !== 'custom';
  const isAddScreen = config.screen === 'add';
  const saveLabel = isAddScreen
    ? config.i18n.addNew || config.i18n.update
    : config.i18n.update;
  const status = contrastMessage(fields, config.i18n);
  const undoAvailable = canUndo(history);
  const redoAvailable = canRedo(history);

  return (
    <div className='aa-badge-studio__app'>
      <header className='aa-badge-studio__toolbar'>
        <div className='aa-badge-studio__toolbar-left'>
          <span className='aa-badge-studio__brand' aria-hidden='true'>
            B
          </span>
          <strong className='aa-badge-studio__title'>
            {config.i18n.title}
          </strong>
          <span className='aa-badge-studio__toolbar-rule' />
          {isSystem ? (
            <span className='aa-badge-studio__entity'>{label}</span>
          ) : (
            <input
              className='aa-badge-studio__entity-input'
              type='text'
              value={label}
              aria-label={config.i18n.title}
              onChange={event => onNameChange(event.target.value)}
            />
          )}
          <span
            className={
              dirty
                ? 'aa-badge-studio__save-pill is-dirty'
                : 'aa-badge-studio__save-pill'
            }
          >
            <span className='aa-badge-studio__save-dot' />
            {dirty ? config.i18n.unsaved : config.i18n.saved}
          </span>
        </div>
        <div className='aa-badge-studio__toolbar-right'>
          <button
            type='button'
            className='aa-badge-studio__icon-btn'
            aria-label={config.i18n.undo}
            disabled={!undoAvailable}
            onClick={undo}
          >
            <svg viewBox='0 0 24 24' width='16' height='16' fill='none'>
              <polyline
                points='3 4 3 10 9 10'
                stroke='currentColor'
                strokeWidth='2'
                strokeLinecap='round'
                strokeLinejoin='round'
              />
              <path
                d='M3.51 15a9 9 0 1 0 2.13-9.36L1 10'
                stroke='currentColor'
                strokeWidth='2'
                strokeLinecap='round'
                strokeLinejoin='round'
              />
            </svg>
          </button>
          <button
            type='button'
            className='aa-badge-studio__icon-btn'
            aria-label={config.i18n.redo}
            disabled={!redoAvailable}
            onClick={redo}
          >
            <svg viewBox='0 0 24 24' width='16' height='16' fill='none'>
              <polyline
                points='23 4 23 10 17 10'
                stroke='currentColor'
                strokeWidth='2'
                strokeLinecap='round'
                strokeLinejoin='round'
              />
              <path
                d='M20.49 15a9 9 0 1 1-2.12-9.36L23 10'
                stroke='currentColor'
                strokeWidth='2'
                strokeLinecap='round'
                strokeLinejoin='round'
              />
            </svg>
          </button>
          <span className='aa-badge-studio__toolbar-rule' />
          <button
            type='button'
            className='aa-badge-studio__ghost-btn'
            onClick={() => {
              document
                .getElementById('aa-badge-studio-preview')
                ?.scrollIntoView({
                  behavior: 'smooth',
                  block: 'center',
                });
            }}
          >
            {config.i18n.preview}
          </button>
          <button
            type='button'
            className='aa-badge-studio__primary-btn'
            onClick={submitTaxonomyForm}
          >
            {saveLabel}
          </button>
        </div>
      </header>
      {status ? (
        <p className='aa-badge-studio__status' aria-live='polite'>
          {status}
        </p>
      ) : null}
      {isSystem ? (
        <p className='aa-badge-studio__notice'>{config.i18n.systemLocked}</p>
      ) : null}
      <div className='aa-badge-studio__workspace'>
        <Library
          fields={fields}
          presets={config.presets}
          shapes={config.shapes}
          i18n={config.i18n}
          tab={libraryTab}
          search={search}
          onTabChange={setLibraryTab}
          onSearchChange={setSearch}
          onPatch={onPatch}
        />
        <Canvas
          compiled={compiled}
          tone={tone}
          onToneChange={setTone}
          zoom={zoom}
          onZoomChange={setZoom}
          i18n={config.i18n}
        />
        <Inspector
          fields={fields}
          tab={tab}
          onTabChange={setTab}
          icons={config.icons}
          palette={config.palette || []}
          i18n={config.i18n}
          onPatch={onPatch}
          isSystem={isSystem}
        />
      </div>
    </div>
  );
}
