/**
 * Badge studio property inspector.
 *
 * @package Aggressive_Apparel
 */

import {
  RangeControl,
  TextControl,
  TextareaControl,
  ToggleControl,
} from '@wordpress/components';
import ColorField from './_ColorField';
import EnumField from './_EnumField';
import type { BadgeFields, InspectorTab, StudioPaletteColor } from './_types';

type Props = {
  fields: BadgeFields;
  tab: InspectorTab;
  onTabChange: (tab: InspectorTab) => void;
  icons: string[];
  palette: StudioPaletteColor[];
  i18n: Record<string, string>;
  onPatch: (patch: Partial<BadgeFields>) => void;
  isSystem: boolean;
};

const TABS: InspectorTab[] = ['fill', 'border', 'type', 'icon', 'layout'];

/**
 * Compact inspector with tabbed property groups.
 *
 * @param props Component props.
 */
export default function Inspector({
  fields,
  tab,
  onTabChange,
  icons,
  palette,
  i18n,
  onPatch,
  isSystem,
}: Props) {
  const set = (key: string, value: string | number | boolean) => {
    if (typeof value === 'boolean') {
      onPatch({ [key]: value ? '1' : '0' });
      return;
    }
    onPatch({ [key]: String(value) });
  };

  const tabLabel = (id: InspectorTab): string => {
    const map: Record<InspectorTab, string> = {
      fill: i18n.fill,
      border: i18n.border,
      type: i18n.type,
      icon: i18n.icon,
      layout: i18n.layout,
    };
    return map[id];
  };

  return (
    <aside className='aa-badge-studio__inspector' aria-label={i18n.inspector}>
      <div className='aa-badge-studio__tabs' role='tablist'>
        {TABS.map(id => (
          <button
            key={id}
            type='button'
            role='tab'
            aria-selected={tab === id}
            className={
              tab === id
                ? 'aa-badge-studio__tab is-active'
                : 'aa-badge-studio__tab'
            }
            onClick={() => onTabChange(id)}
          >
            {tabLabel(id)}
          </button>
        ))}
      </div>
      <div className='aa-badge-studio__panel' role='tabpanel'>
        {tab === 'fill' && (
          <>
            {/*
             * Native select — WP SelectControl can emit onChange when the
             * value is updated from a style/template patch, clobbering
             * gradient back to solid (Flash Sale then looks like Solid).
             */}
            <label className='aa-badge-studio__native-field'>
              <span className='aa-badge-studio__native-label'>{i18n.fill}</span>
              <select
                className='aa-badge-studio__native-select'
                value={fields.badge_fill_mode || 'solid'}
                onChange={event =>
                  set('badge_fill_mode', event.target.value || 'solid')
                }
              >
                <option value='solid'>{i18n.solidFill}</option>
                <option value='gradient'>{i18n.gradientFill}</option>
              </select>
            </label>
            {(fields.badge_fill_mode || 'solid') === 'solid' ? (
              <ColorField
                label={i18n.fill}
                value={fields.badge_bg_color || ''}
                i18n={i18n}
                palette={palette}
                onChange={value => set('badge_bg_color', value)}
              />
            ) : (
              <>
                <ColorField
                  label='From'
                  value={fields.badge_gradient_from || ''}
                  i18n={i18n}
                  palette={palette}
                  onChange={value => set('badge_gradient_from', value)}
                />
                <ColorField
                  label='To'
                  value={fields.badge_gradient_to || ''}
                  i18n={i18n}
                  palette={palette}
                  fallback='#444444'
                  onChange={value => set('badge_gradient_to', value)}
                />
                <RangeControl
                  label='Angle'
                  value={parseInt(fields.badge_gradient_angle || '135', 10)}
                  onChange={value => set('badge_gradient_angle', value ?? 135)}
                  min={0}
                  max={360}
                />
              </>
            )}
            <ColorField
              label='Text'
              value={fields.badge_text_color || ''}
              i18n={i18n}
              palette={palette}
              fallback='#ffffff'
              onChange={value => set('badge_text_color', value)}
            />
            <ToggleControl
              label={i18n.glassEffect}
              checked={fields.badge_glass === '1'}
              onChange={checked => set('badge_glass', checked)}
            />
          </>
        )}

        {tab === 'border' && (
          <>
            <EnumField
              label={i18n.border}
              value={fields.badge_border_mode || 'none'}
              options={[
                { label: i18n.none, value: 'none' },
                { label: 'Single', value: 'single' },
                { label: 'Layered', value: 'layered' },
              ]}
              onChange={value => {
                const next = value || 'none';
                if (next === 'none') {
                  set('badge_border_mode', 'none');
                  return;
                }

                const patch: Partial<BadgeFields> = {
                  badge_border_mode: next,
                };
                const width = parseInt(fields.badge_border_width || '0', 10);
                if (!(width > 0)) {
                  patch.badge_border_width = '2';
                }
                if (
                  !fields.badge_border_style ||
                  fields.badge_border_style === 'none'
                ) {
                  patch.badge_border_style = 'solid';
                }
                if (!fields.badge_border_color) {
                  patch.badge_border_color =
                    fields.badge_text_color || '#ffffff';
                }
                if (next === 'layered') {
                  const innerWidth = parseInt(
                    fields.badge_inner_border_width || '0',
                    10
                  );
                  if (!(innerWidth > 0)) {
                    patch.badge_inner_border_width = '1';
                  }
                  if (
                    !fields.badge_inner_border_color ||
                    fields.badge_inner_border_color === 'transparent'
                  ) {
                    patch.badge_inner_border_color =
                      fields.badge_border_color ||
                      fields.badge_text_color ||
                      '#ffffff';
                  }
                  const gap = parseInt(fields.badge_border_gap || '0', 10);
                  if (!(gap > 0)) {
                    patch.badge_border_gap = '2';
                  }
                }
                onPatch(patch);
              }}
            />
            {(fields.badge_border_mode || 'none') !== 'none' && (
              <>
                <ColorField
                  label='Color'
                  value={fields.badge_border_color || ''}
                  i18n={i18n}
                  palette={palette}
                  allowEmpty
                  onChange={value => set('badge_border_color', value)}
                />
                <RangeControl
                  label='Width'
                  value={parseInt(fields.badge_border_width || '0', 10)}
                  onChange={value => set('badge_border_width', value ?? 0)}
                  min={0}
                  max={10}
                />
                <EnumField
                  label='Style'
                  value={
                    !fields.badge_border_style ||
                    fields.badge_border_style === 'none'
                      ? 'solid'
                      : fields.badge_border_style
                  }
                  options={[
                    { label: 'Solid', value: 'solid' },
                    { label: 'Dashed', value: 'dashed' },
                    { label: 'Dotted', value: 'dotted' },
                    { label: 'Double', value: 'double' },
                  ]}
                  onChange={value => {
                    const style = value || 'solid';
                    const patch: Partial<BadgeFields> = {
                      badge_border_style: style,
                    };
                    if (style === 'double') {
                      const width = parseInt(
                        fields.badge_border_width || '0',
                        10
                      );
                      if (width < 3) {
                        patch.badge_border_width = '3';
                      }
                    }
                    onPatch(patch);
                  }}
                />
              </>
            )}
            {fields.badge_border_mode === 'layered' && (
              <>
                <ColorField
                  label='Inner color'
                  value={fields.badge_inner_border_color || ''}
                  i18n={i18n}
                  palette={palette}
                  allowEmpty
                  onChange={value => set('badge_inner_border_color', value)}
                />
                <RangeControl
                  label='Inner width'
                  value={parseInt(fields.badge_inner_border_width || '0', 10)}
                  onChange={value =>
                    set('badge_inner_border_width', value ?? 0)
                  }
                  min={0}
                  max={6}
                />
                <RangeControl
                  label='Gap'
                  value={parseInt(fields.badge_border_gap || '0', 10)}
                  onChange={value => set('badge_border_gap', value ?? 0)}
                  min={0}
                  max={20}
                />
              </>
            )}
            {(fields.badge_shape || 'rect') !== 'rect' &&
              (fields.badge_shape || '') !== 'pill' && (
                <>
                  <EnumField
                    label='Shape render'
                    value={fields.badge_shape_mode || 'mask'}
                    options={[
                      {
                        label: i18n.maskMode,
                        value: 'mask',
                      },
                      {
                        label: i18n.frameMode,
                        value: 'frame',
                      },
                    ]}
                    onChange={value => set('badge_shape_mode', value || 'mask')}
                  />
                  {fields.badge_shape_mode === 'frame' && (
                    <>
                      <ColorField
                        label='Frame color'
                        value={fields.badge_frame_color || ''}
                        i18n={i18n}
                        palette={palette}
                        allowEmpty
                        onChange={value => set('badge_frame_color', value)}
                      />
                      <RangeControl
                        label='Frame width'
                        value={parseInt(fields.badge_frame_width || '2', 10)}
                        onChange={value => set('badge_frame_width', value ?? 2)}
                        min={0}
                        max={8}
                      />
                    </>
                  )}
                </>
              )}
            {fields.badge_shape === 'custom' && (
              <TextareaControl
                label={i18n.customSvg}
                value={fields.badge_shape_svg || ''}
                onChange={value => set('badge_shape_svg', value || '')}
                help='Paste a path or full SVG. Sanitized on save.'
              />
            )}
            <div className='aa-badge-studio__radius-row'>
              {(
                [
                  ['badge_radius_tl', 'TL'],
                  ['badge_radius_tr', 'TR'],
                  ['badge_radius_br', 'BR'],
                  ['badge_radius_bl', 'BL'],
                ] as const
              ).map(([key, label]) => (
                <RangeControl
                  key={key}
                  label={label}
                  value={parseInt(fields[key] || '4', 10)}
                  onChange={value => set(key, value ?? 4)}
                  min={0}
                  max={100}
                />
              ))}
            </div>
          </>
        )}

        {tab === 'type' && (
          <>
            {isSystem && (
              <p className='aa-badge-studio__notice'>{i18n.systemLocked}</p>
            )}
            <EnumField
              label='Size'
              value={fields.badge_font_size || 'x-small'}
              options={[
                { label: 'Small', value: 'x-small' },
                { label: 'Medium', value: 'small' },
                { label: 'Large', value: 'medium' },
                { label: 'Custom px', value: 'custom' },
              ]}
              onChange={value => {
                const next = value || 'x-small';
                if (next === 'custom') {
                  const current = parseInt(
                    fields.badge_font_size_px || '0',
                    10
                  );
                  onPatch({
                    badge_font_size: 'custom',
                    badge_font_size_px: String(current >= 4 ? current : 12),
                  });
                  return;
                }
                onPatch({
                  badge_font_size: next,
                  badge_font_size_px: '0',
                });
              }}
            />
            {fields.badge_font_size === 'custom' && (
              <RangeControl
                label='Pixels'
                value={parseInt(fields.badge_font_size_px || '12', 10)}
                onChange={value => set('badge_font_size_px', value ?? 12)}
                min={4}
                max={32}
              />
            )}
            <EnumField
              label='Weight'
              value={fields.badge_font_weight || '700'}
              options={[
                { label: 'Medium', value: '500' },
                { label: 'Semibold', value: '600' },
                { label: 'Bold', value: '700' },
                { label: 'Extra bold', value: '800' },
              ]}
              onChange={value => set('badge_font_weight', value || '700')}
            />
            <EnumField
              label='Case'
              value={fields.badge_text_transform || 'uppercase'}
              options={[
                { label: 'Original', value: 'none' },
                { label: 'Uppercase', value: 'uppercase' },
              ]}
              onChange={value =>
                set('badge_text_transform', value || 'uppercase')
              }
            />
            <EnumField
              label='Tracking'
              value={fields.badge_letter_spacing || 'wide'}
              options={[
                { label: 'Normal', value: 'normal' },
                { label: 'Comfortable', value: 'comfortable' },
                { label: 'Wide', value: 'wide' },
                { label: 'Extra wide', value: 'extra-wide' },
              ]}
              onChange={value => set('badge_letter_spacing', value || 'wide')}
            />
          </>
        )}

        {tab === 'icon' && (
          <>
            <EnumField
              label='Placement'
              value={fields.badge_icon_position || 'start'}
              options={[
                { label: 'Before text', value: 'start' },
                { label: 'After text', value: 'end' },
                { label: 'Icon only', value: 'only' },
              ]}
              onChange={value => set('badge_icon_position', value || 'start')}
            />
            <TextControl
              label={i18n.emoji}
              value={fields.badge_icon || ''}
              onChange={value => {
                onPatch({
                  badge_icon: value || '',
                  badge_library_icon: value ? '' : fields.badge_library_icon,
                  badge_svg_icon: value ? '' : fields.badge_svg_icon,
                });
              }}
            />
            <EnumField
              label={i18n.library}
              value={fields.badge_library_icon || ''}
              options={[
                { label: i18n.none, value: '' },
                ...icons.slice(0, 80).map(slug => ({
                  label: slug,
                  value: slug,
                })),
              ]}
              onChange={value => {
                onPatch({
                  badge_library_icon: value || '',
                  badge_icon: value ? '' : fields.badge_icon,
                  badge_svg_icon: value ? '' : fields.badge_svg_icon,
                });
              }}
            />
            <TextareaControl
              label={i18n.svg}
              value={fields.badge_svg_icon || ''}
              onChange={value => {
                onPatch({
                  badge_svg_icon: value || '',
                  badge_icon: value ? '' : fields.badge_icon,
                  badge_library_icon: value ? '' : fields.badge_library_icon,
                });
              }}
            />
            <ColorField
              label='Icon color'
              value={fields.badge_icon_color || ''}
              i18n={i18n}
              palette={palette}
              allowEmpty
              onChange={value => set('badge_icon_color', value)}
            />
            <RangeControl
              label='Icon size'
              value={parseInt(fields.badge_icon_size || '0', 10)}
              onChange={value => set('badge_icon_size', value ?? 0)}
              min={0}
              max={64}
            />
            <RangeControl
              label='Icon gap'
              value={parseInt(fields.badge_icon_gap || '0', 10)}
              onChange={value => set('badge_icon_gap', value ?? 0)}
              min={0}
              max={40}
            />
          </>
        )}

        {tab === 'layout' && (
          <>
            <RangeControl
              label='Padding X'
              value={parseInt(fields.badge_padding_x || '8', 10)}
              onChange={value => set('badge_padding_x', value ?? 8)}
              min={0}
              max={50}
            />
            <RangeControl
              label='Padding Y'
              value={parseInt(fields.badge_padding_y || '3', 10)}
              onChange={value => set('badge_padding_y', value ?? 3)}
              min={0}
              max={50}
            />
            <EnumField
              label='Position'
              value={fields.badge_position || 'top-left'}
              options={[
                'top-left',
                'top',
                'top-right',
                'left',
                'right',
                'bottom-left',
                'bottom',
                'bottom-right',
              ].map(value => ({
                label: value.replace(/-/g, ' '),
                value,
              }))}
              onChange={value => set('badge_position', value || 'top-left')}
            />
            <RangeControl
              label='Offset X'
              value={parseInt(fields.badge_offset_x || '0', 10)}
              onChange={value => set('badge_offset_x', value ?? 0)}
              min={-40}
              max={40}
            />
            <RangeControl
              label='Offset Y'
              value={parseInt(fields.badge_offset_y || '0', 10)}
              onChange={value => set('badge_offset_y', value ?? 0)}
              min={-40}
              max={40}
            />
            <RangeControl
              label='Rotation'
              value={parseInt(fields.badge_rotation || '0', 10)}
              onChange={value => set('badge_rotation', value ?? 0)}
              min={-45}
              max={45}
            />
            <RangeControl
              label='Shadow blur'
              value={parseInt(fields.badge_shadow_blur || '0', 10)}
              onChange={value => set('badge_shadow_blur', value ?? 0)}
              min={0}
              max={40}
            />
            <RangeControl
              label='Shadow spread'
              value={parseInt(fields.badge_shadow_spread || '0', 10)}
              onChange={value => set('badge_shadow_spread', value ?? 0)}
              min={0}
              max={20}
            />
            <ColorField
              label='Shadow color'
              value={fields.badge_shadow_color || ''}
              i18n={i18n}
              palette={palette}
              allowEmpty
              onChange={value => set('badge_shadow_color', value)}
            />
            <RangeControl
              label='Priority'
              value={parseInt(fields.badge_priority || '10', 10)}
              onChange={value => set('badge_priority', value ?? 10)}
              min={0}
              max={100}
            />
          </>
        )}
      </div>
    </aside>
  );
}
