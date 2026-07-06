/**
 * EffectTimingControls - Reusable timing controls for parallax effects
 */

import { RangeControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  EDITOR_COLOR_TOKENS,
  EDITOR_FIELDSET_STYLE,
  EDITOR_META_TEXT_STYLE,
  EDITOR_RADIUS_TOKENS,
} from '../../../utils/editor-style-tokens';

interface EffectTimingControlsProps {
  effectType: string;
  effectStart?: number;
  effectEnd?: number;
  effectMode?: 'sustain' | 'peek' | 'reverse';
  onUpdate: (_key: string, _value: string | number) => void;
}

export const EffectTimingControls = ({
  // effectType, // Unused but kept in props for potential future use
  effectStart,
  effectEnd,
  effectMode,
  onUpdate,
}: EffectTimingControlsProps) => {
  // Determine current preset based on effectStart/effectEnd values
  const getCurrentPreset = () => {
    if (effectStart === undefined || effectEnd === undefined) {
      return 'quick'; // default
    }
    if (effectStart === 0.0 && effectEnd === 0.05) return 'instant';
    if (effectStart === 0.0 && effectEnd === 0.25) return 'quick';
    if (effectStart === 0.0 && effectEnd === 0.5) return 'gradual';
    if (effectStart === 0.0 && effectEnd === 0.7) return 'full';
    return 'custom';
  };

  const currentPreset = getCurrentPreset();
  const isCustom =
    currentPreset === 'custom' ||
    (effectStart !== undefined && effectEnd !== undefined);

  // Validation: effectEnd must be greater than effectStart
  const startValue = effectStart ?? 0;
  const endValue = effectEnd ?? 0.25;
  const hasValidationError = endValue <= startValue;

  return (
    <div
      style={{
        ...EDITOR_FIELDSET_STYLE,
        marginTop: '16px',
      }}
    >
      <div
        style={{
          fontWeight: '600',
          marginBottom: '12px',
          fontSize: '13px',
          color: EDITOR_COLOR_TOKENS.foreground,
        }}
      >
        {__('Effect Timing', 'aggressive-apparel')}
      </div>

      <SelectControl
        label={__('Timing Preset', 'aggressive-apparel')}
        value={currentPreset}
        options={[
          {
            label: __('Instant (0-5%)', 'aggressive-apparel'),
            value: 'instant',
          },
          {
            label: __('Quick (0-25%) - Default', 'aggressive-apparel'),
            value: 'quick',
          },
          {
            label: __('Gradual (0-50%)', 'aggressive-apparel'),
            value: 'gradual',
          },
          {
            label: __('Full Range (0-70%)', 'aggressive-apparel'),
            value: 'full',
          },
          { label: __('Custom', 'aggressive-apparel'), value: 'custom' },
        ]}
        onChange={value => {
          if (value === 'instant') {
            onUpdate('effectStart', 0.0);
            onUpdate('effectEnd', 0.05);
          } else if (value === 'quick') {
            onUpdate('effectStart', 0.0);
            onUpdate('effectEnd', 0.25);
          } else if (value === 'gradual') {
            onUpdate('effectStart', 0.0);
            onUpdate('effectEnd', 0.5);
          } else if (value === 'full') {
            onUpdate('effectStart', 0.0);
            onUpdate('effectEnd', 0.7);
          }
          // 'custom' doesn't change values, just shows sliders
        }}
        help={__(
          'When the effect starts and completes within the scroll journey',
          'aggressive-apparel'
        )}
      />

      {/* Show custom sliders when Custom is selected or values are set */}
      {isCustom && (
        <>
          <RangeControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label={__('Effect Start Position (%)', 'aggressive-apparel')}
            value={startValue * 100}
            onChange={value => {
              const newStart = (value ?? 0) / 100;
              onUpdate('effectStart', newStart);
            }}
            min={0}
            max={100}
            step={5}
            help={__(
              'When effect animation begins (% through scroll)',
              'aggressive-apparel'
            )}
          />
          <RangeControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label={__('Effect End Position (%)', 'aggressive-apparel')}
            value={endValue * 100}
            onChange={value => {
              const newEnd = (value ?? 25) / 100;
              onUpdate('effectEnd', newEnd);
            }}
            min={0}
            max={100}
            step={5}
            help={__(
              'When effect reaches maximum (% through scroll)',
              'aggressive-apparel'
            )}
          />
          {hasValidationError && (
            <div
              style={{
                color: EDITOR_COLOR_TOKENS.error,
                fontSize: '12px',
                marginTop: '8px',
              }}
            >
              ⚠️{' '}
              {__(
                'End position must be greater than start position',
                'aggressive-apparel'
              )}
            </div>
          )}
        </>
      )}

      <SelectControl
        label={__('Effect Mode', 'aggressive-apparel')}
        value={effectMode || 'sustain'}
        options={[
          {
            label: __('Sustain - Hold at maximum', 'aggressive-apparel'),
            value: 'sustain',
          },
          {
            label: __('Peek - Bell curve (fade in/out)', 'aggressive-apparel'),
            value: 'peek',
          },
          {
            label: __('Reverse - Fade back to start', 'aggressive-apparel'),
            value: 'reverse',
          },
        ]}
        onChange={value => onUpdate('effectMode', value)}
        help={__(
          'How the effect behaves after reaching maximum',
          'aggressive-apparel'
        )}
      />

      {/* Visual Preview */}
      <div style={{ marginTop: '12px' }}>
        <div style={{ ...EDITOR_META_TEXT_STYLE, marginBottom: '4px' }}>
          {__('Preview:', 'aggressive-apparel')}
        </div>
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            height: '24px',
            backgroundColor: 'color-mix(in srgb, currentColor 8%, transparent)',
            border: `1px solid ${EDITOR_COLOR_TOKENS.border}`,
            borderRadius: EDITOR_RADIUS_TOKENS.control,
            overflow: 'hidden',
            position: 'relative',
          }}
        >
          {/* Timeline markers */}
          <div
            style={{
              position: 'absolute',
              top: 0,
              left: 0,
              right: 0,
              bottom: 0,
              display: 'flex',
            }}
          >
            {[0, 25, 50, 75, 100].map(percent => (
              <div
                key={percent}
                style={{
                  position: 'absolute',
                  left: `${percent}%`,
                  top: 0,
                  bottom: 0,
                  width: '1px',
                  backgroundColor: EDITOR_COLOR_TOKENS.border,
                }}
              />
            ))}
          </div>
          {/* Effect range indicator */}
          <div
            style={{
              position: 'absolute',
              left: `${startValue * 100}%`,
              width: `${(endValue - startValue) * 100}%`,
              height: '100%',
              backgroundColor:
                effectMode === 'peek'
                  ? EDITOR_COLOR_TOKENS.infoBg
                  : EDITOR_COLOR_TOKENS.successBg,
              borderLeft:
                '2px solid ' +
                (effectMode === 'peek'
                  ? EDITOR_COLOR_TOKENS.info
                  : EDITOR_COLOR_TOKENS.success),
              borderRight:
                '2px solid ' +
                (effectMode === 'peek'
                  ? EDITOR_COLOR_TOKENS.info
                  : EDITOR_COLOR_TOKENS.success),
            }}
          />
        </div>
        <div
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            fontSize: '10px',
            color: EDITOR_COLOR_TOKENS.subtle,
            marginTop: '2px',
          }}
        >
          <span>0%</span>
          <span>25%</span>
          <span>50%</span>
          <span>75%</span>
          <span>100%</span>
        </div>
      </div>
    </div>
  );
};
