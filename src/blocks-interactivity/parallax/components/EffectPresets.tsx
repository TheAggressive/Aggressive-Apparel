/**
 * EffectPresets - Quick-apply preset configurations for parallax effects
 */

import { Button, ButtonGroup } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

// Custom icons matching WordPress icon style (24x24 viewBox, scaled to 32px)
const subtleIcon = (
  <svg
    xmlns='http://www.w3.org/2000/svg'
    viewBox='0 0 24 24'
    width='32'
    height='32'
    aria-hidden='true'
  >
    <path
      fill='currentColor'
      d='M4 12c0-1.5 1.8-3 4-3s4 1.5 4 3-1.8 3-4 3-4-1.5-4-3zm8 0c0-1.5 1.8-3 4-3s4 1.5 4 3-1.8 3-4 3-4-1.5-4-3z'
      opacity='0.6'
    />
  </svg>
);

const floatIcon = (
  <svg
    xmlns='http://www.w3.org/2000/svg'
    viewBox='0 0 24 24'
    width='32'
    height='32'
    aria-hidden='true'
  >
    <path fill='currentColor' d='M12 4l-1.5 3h3L12 4z' />
    <rect fill='currentColor' x='8' y='10' width='8' height='6' rx='1' />
    <path
      fill='currentColor'
      opacity='0.4'
      d='M9 18h6M10 20h4'
      stroke='currentColor'
      strokeWidth='1.5'
      strokeLinecap='round'
    />
  </svg>
);

const dramaticIcon = (
  <svg
    xmlns='http://www.w3.org/2000/svg'
    viewBox='0 0 24 24'
    width='32'
    height='32'
    aria-hidden='true'
  >
    <path fill='currentColor' d='M12 2L8 8h3v6H8l4 6 4-6h-3V8h3L12 2z' />
    <path
      fill='currentColor'
      opacity='0.4'
      d='M4 12h2M18 12h2M5 8l1.5 1M17.5 9L19 8M5 16l1.5-1M17.5 15L19 16'
      stroke='currentColor'
      strokeWidth='1.5'
      strokeLinecap='round'
    />
  </svg>
);

interface EffectPresetsProps {
  // eslint-disable-next-line no-unused-vars
  onApplyPreset: (_preset: PresetConfig) => void;
  onReset: () => void;
}

export interface PresetConfig {
  name: string;
  settings: {
    enabled: boolean;
    speed: number;
    direction: string;
    delay: number;
    easing: string;
    effects: Record<string, any>;
  };
}

const PRESETS: Record<string, PresetConfig> = {
  subtle: {
    name: 'Subtle',
    settings: {
      enabled: true,
      speed: 0.5,
      direction: 'down',
      delay: 0,
      easing: 'easeOut',
      effects: {
        depthLevel: { value: 1.2 },
      },
    },
  },
  float: {
    name: 'Float',
    settings: {
      enabled: true,
      speed: 0.8,
      direction: 'down',
      delay: 0,
      easing: 'easeInOut',
      effects: {
        depthLevel: { value: 1.5 },
        scrollOpacity: {
          enabled: true,
          startOpacity: 0.8,
          endOpacity: 1,
          fadeRange: 0.3,
        },
      },
    },
  },
  dramatic: {
    name: 'Dramatic',
    settings: {
      enabled: true,
      speed: 1.5,
      direction: 'down',
      delay: 0,
      easing: 'easeOut',
      effects: {
        depthLevel: { value: 2.0 },
        zoom: {
          enabled: true,
          type: 'in',
          intensity: 0.15,
        },
        scrollOpacity: {
          enabled: true,
          startOpacity: 0,
          endOpacity: 1,
          fadeRange: 0.5,
        },
      },
    },
  },
};

export const EffectPresets = ({
  onApplyPreset,
  onReset,
}: EffectPresetsProps) => {
  return (
    <div
      style={{
        marginBottom: '16px',
        padding: '12px',
        backgroundColor: '#f8f9fa',
        borderRadius: '8px',
        border: '1px solid #e2e4e7',
      }}
    >
      <div
        style={{
          fontSize: '11px',
          fontWeight: 600,
          textTransform: 'uppercase',
          letterSpacing: '0.5px',
          color: '#757575',
          marginBottom: '8px',
        }}
      >
        {__('Quick Presets', 'aggressive-apparel')}
      </div>
      <ButtonGroup style={{ display: 'flex', gap: '8px' }}>
        <Button
          variant='secondary'
          onClick={() => onApplyPreset(PRESETS.subtle)}
          style={{
            flex: 1,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            gap: '4px',
            padding: '12px 8px',
            height: 'auto',
          }}
        >
          {subtleIcon}
          <span style={{ fontSize: '11px' }}>
            {__('Subtle', 'aggressive-apparel')}
          </span>
        </Button>
        <Button
          variant='secondary'
          onClick={() => onApplyPreset(PRESETS.float)}
          style={{
            flex: 1,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            gap: '4px',
            padding: '12px 8px',
            height: 'auto',
          }}
        >
          {floatIcon}
          <span style={{ fontSize: '11px' }}>
            {__('Float', 'aggressive-apparel')}
          </span>
        </Button>
        <Button
          variant='secondary'
          onClick={() => onApplyPreset(PRESETS.dramatic)}
          style={{
            flex: 1,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            gap: '4px',
            padding: '12px 8px',
            height: 'auto',
          }}
        >
          {dramaticIcon}
          <span style={{ fontSize: '11px' }}>
            {__('Dramatic', 'aggressive-apparel')}
          </span>
        </Button>
      </ButtonGroup>
      <Button
        variant='tertiary'
        size='small'
        onClick={onReset}
        style={{ marginTop: '8px', width: '100%' }}
        isDestructive
      >
        {__('Reset to Defaults', 'aggressive-apparel')}
      </Button>
    </div>
  );
};
