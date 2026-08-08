/**
 * Badge studio style + shape + template library rail.
 *
 * @package Aggressive_Apparel
 */

import {
  BADGE_LOOK_PRESETS,
  BADGE_TEMPLATES,
  lookPresetPatch,
  templatePatch,
} from './_presets';
import type { BadgeFields } from './_types';

export type LibraryTab = 'styles' | 'shapes' | 'templates';

type Props = {
  fields: BadgeFields;
  presets: Record<string, string>;
  shapes: Record<string, string>;
  i18n: Record<string, string>;
  tab: LibraryTab;
  search: string;
  onTabChange: (tab: LibraryTab) => void;
  onSearchChange: (value: string) => void;
  onPatch: (patch: Partial<BadgeFields>) => void;
};

/**
 * Whether field values match an applied library patch.
 *
 * @param fields Current studio fields.
 * @param patch  Full reset+dump patch.
 */
function fieldsMatchPatch(
  fields: BadgeFields,
  patch: Record<string, string>
): boolean {
  return Object.keys(patch).every(key => {
    const left = String(fields[key] ?? '').trim();
    const right = String(patch[key] ?? '').trim();
    // Hex colors from templates/presets may differ only by case.
    if (left.startsWith('#') && right.startsWith('#')) {
      return left.toLowerCase() === right.toLowerCase();
    }
    return left === right;
  });
}

/**
 * Best-matching look preset id for the current field dump, or null.
 *
 * Compare against the full applied patch (reset + dump). Dump-only matching
 * falsely marks Solid when Gradient (etc.) leaves reset defaults in place.
 *
 * @param fields Current studio fields.
 */
function matchingLookPreset(fields: BadgeFields): string | null {
  const fillMode = fields.badge_fill_mode || 'solid';

  for (const id of Object.keys(BADGE_LOOK_PRESETS)) {
    const patch = lookPresetPatch(id);
    if (!patch) {
      continue;
    }
    // Hard gate: a solid style must never win while fill is gradient.
    if ((patch.badge_fill_mode || 'solid') !== fillMode) {
      continue;
    }
    if (fieldsMatchPatch(fields, patch)) {
      return id;
    }
  }

  return null;
}

/**
 * Best-matching merchandising template id, or null.
 *
 * @param fields Current studio fields.
 */
function matchingTemplate(fields: BadgeFields): string | null {
  for (const id of Object.keys(BADGE_TEMPLATES)) {
    const patch = templatePatch(id);
    if (!patch) {
      continue;
    }
    if (fieldsMatchPatch(fields, patch)) {
      return id;
    }
  }

  return null;
}

/**
 * Checkmark badge for selected library cards.
 */
function SelectedMark() {
  return (
    <span className='aa-badge-studio__check' aria-hidden='true'>
      <svg viewBox='0 0 24 24' width='10' height='10' fill='none'>
        <polyline
          points='20 6 9 17 4 12'
          stroke='currentColor'
          strokeWidth='3'
          strokeLinecap='round'
          strokeLinejoin='round'
        />
      </svg>
    </span>
  );
}

/**
 * Left rail: styles, shapes, templates with search.
 *
 * @param props Component props.
 */
export default function Library({
  fields,
  presets,
  shapes,
  i18n,
  tab,
  search,
  onTabChange,
  onSearchChange,
  onPatch,
}: Props) {
  // Prefer template identity when a full template patch matches — Flash Sale
  // shares reset defaults with Solid and must not light up the Solid chip.
  const activeTemplate = matchingTemplate(fields);
  const activeLook = activeTemplate ? null : matchingLookPreset(fields);
  const q = search.trim().toLowerCase();
  const tabs: { id: LibraryTab; label: string }[] = [
    { id: 'styles', label: i18n.styles },
    { id: 'shapes', label: i18n.shapes },
    { id: 'templates', label: i18n.templates },
  ];

  const styleEntries = Object.entries(presets).filter(
    ([, label]) => !q || label.toLowerCase().includes(q)
  );
  const shapeEntries = Object.entries(shapes).filter(
    ([, label]) => !q || label.toLowerCase().includes(q)
  );
  const templateEntries = Object.entries(BADGE_TEMPLATES).filter(
    ([, meta]) =>
      !q ||
      meta.name.toLowerCase().includes(q) ||
      meta.caption.toLowerCase().includes(q)
  );

  return (
    <aside className='aa-badge-studio__library'>
      <div className='aa-badge-studio__library-head'>
        <div
          className='aa-badge-studio__segment'
          role='tablist'
          aria-label={i18n.library || i18n.styles}
        >
          {tabs.map(item => (
            <button
              key={item.id}
              type='button'
              role='tab'
              aria-selected={tab === item.id}
              className={
                tab === item.id
                  ? 'aa-badge-studio__segment-btn is-active'
                  : 'aa-badge-studio__segment-btn'
              }
              onClick={() => onTabChange(item.id)}
            >
              {item.label}
            </button>
          ))}
        </div>
        <div className='aa-badge-studio__search'>
          <svg
            viewBox='0 0 24 24'
            width='16'
            height='16'
            fill='none'
            aria-hidden='true'
          >
            <circle
              cx='11'
              cy='11'
              r='7'
              stroke='currentColor'
              strokeWidth='2'
            />
            <line
              x1='20'
              y1='20'
              x2='16.5'
              y2='16.5'
              stroke='currentColor'
              strokeWidth='2'
              strokeLinecap='round'
            />
          </svg>
          <input
            type='search'
            value={search}
            placeholder={i18n.searchLibrary}
            aria-label={i18n.searchLibrary}
            onChange={event => onSearchChange(event.target.value)}
          />
        </div>
      </div>

      <div className='aa-badge-studio__library-scroll'>
        {tab === 'styles' && (
          <section className='aa-badge-studio__library-block'>
            <h3 className='aa-badge-studio__library-title'>{i18n.styles}</h3>
            <div className='aa-badge-studio__preset-grid'>
              {styleEntries.map(([id, label]) => {
                const active = activeLook === id;
                return (
                  <button
                    key={id}
                    type='button'
                    aria-pressed={active}
                    className={
                      active
                        ? 'aa-badge-studio__preset is-active'
                        : 'aa-badge-studio__preset'
                    }
                    onClick={() => {
                      const patch = lookPresetPatch(id);
                      if (patch) {
                        onPatch(patch);
                      }
                    }}
                  >
                    <span
                      className={`aa-badge-studio__preset-chip aa-badge-studio__preset-chip--${id}`}
                      aria-hidden='true'
                    >
                      Sale
                    </span>
                    <span className='aa-badge-studio__preset-name'>
                      {label}
                    </span>
                    {active ? <SelectedMark /> : null}
                  </button>
                );
              })}
            </div>
          </section>
        )}

        {tab === 'shapes' && (
          <section className='aa-badge-studio__library-block'>
            <h3 className='aa-badge-studio__library-title'>{i18n.shapes}</h3>
            <div
              className='aa-badge-studio__shape-grid'
              role='radiogroup'
              aria-label={i18n.shapes}
            >
              {shapeEntries.map(([value, label]) => {
                const active = (fields.badge_shape || 'rect') === value;
                return (
                  <button
                    key={value}
                    type='button'
                    role='radio'
                    aria-checked={active}
                    className={
                      active
                        ? 'aa-badge-studio__shape is-active'
                        : 'aa-badge-studio__shape'
                    }
                    onClick={() =>
                      onPatch({
                        badge_shape: value,
                        ...(value === 'pill'
                          ? {
                              badge_radius_tl: '100',
                              badge_radius_tr: '100',
                              badge_radius_br: '100',
                              badge_radius_bl: '100',
                            }
                          : {}),
                      })
                    }
                  >
                    <span
                      className={`aa-badge-studio__shape-stub aa-badge-studio__shape-stub--${value}`}
                      aria-hidden='true'
                    >
                      {value === 'custom' ? '+' : 'Sale'}
                    </span>
                    <span className='aa-badge-studio__shape-name'>{label}</span>
                    {active ? <SelectedMark /> : null}
                  </button>
                );
              })}
            </div>
          </section>
        )}

        {tab === 'templates' && (
          <section className='aa-badge-studio__library-block'>
            <h3 className='aa-badge-studio__library-title'>{i18n.templates}</h3>
            <div className='aa-badge-studio__template-list'>
              {templateEntries.map(([id, meta]) => {
                const active = activeTemplate === id;
                return (
                  <button
                    key={id}
                    type='button'
                    aria-pressed={active}
                    className={
                      active
                        ? 'aa-badge-studio__template is-active'
                        : 'aa-badge-studio__template'
                    }
                    onClick={() => {
                      const patch = templatePatch(id);
                      if (patch) {
                        onPatch(patch);
                      }
                    }}
                  >
                    <span
                      className={`aa-badge-studio__template-thumb aa-badge-studio__template-thumb--${id}`}
                      aria-hidden='true'
                    >
                      Sale
                    </span>
                    <span className='aa-badge-studio__template-copy'>
                      <span className='aa-badge-studio__template-name'>
                        {meta.name}
                      </span>
                      <span className='aa-badge-studio__template-caption'>
                        {meta.caption}
                      </span>
                    </span>
                    {active ? <SelectedMark /> : null}
                  </button>
                );
              })}
            </div>
          </section>
        )}
      </div>
    </aside>
  );
}
