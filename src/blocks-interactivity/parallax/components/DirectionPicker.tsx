/**
 * DirectionPicker - Visual arrow-based direction selector
 */

import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface DirectionPickerProps {
  value: string;
  // eslint-disable-next-line no-unused-vars
  onChange: (_direction: string) => void;
}

const DIRECTIONS = [
  { value: 'up', label: '↑', title: __('Up', 'aggressive-apparel') },
  { value: 'down', label: '↓', title: __('Down', 'aggressive-apparel') },
  { value: 'left', label: '←', title: __('Left', 'aggressive-apparel') },
  { value: 'right', label: '→', title: __('Right', 'aggressive-apparel') },
];

export const DirectionPicker = ({ value, onChange }: DirectionPickerProps) => {
  return (
    <div style={{ marginBottom: '16px', textAlign: 'center' }}>
      <div
        style={{
          fontSize: '11px',
          fontWeight: 500,
          marginBottom: '8px',
          color: '#1e1e1e',
        }}
      >
        {__('Scroll Direction', 'aggressive-apparel')}
      </div>
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(3, 1fr)',
          gridTemplateRows: 'repeat(3, 1fr)',
          gap: '4px',
          width: '120px',
          margin: '0 auto',
        }}
      >
        {/* Empty top-left */}
        <div />
        {/* Up */}
        <Button
          variant={value === 'up' ? 'primary' : 'secondary'}
          onClick={() => onChange('up')}
          style={{
            minWidth: '36px',
            height: '36px',
            padding: 0,
            fontSize: '18px',
            display: 'flex',
            justifyContent: 'center',
          }}
          title={__('Up', 'aggressive-apparel')}
          aria-label={__('Scroll Up', 'aggressive-apparel')}
        >
          ↑
        </Button>
        {/* Empty top-right */}
        <div />
        {/* Left */}
        <Button
          variant={value === 'left' ? 'primary' : 'secondary'}
          onClick={() => onChange('left')}
          style={{
            minWidth: '36px',
            height: '36px',
            padding: 0,
            fontSize: '18px',
            display: 'flex',
            justifyContent: 'center',
          }}
          title={__('Left', 'aggressive-apparel')}
          aria-label={__('Scroll Left', 'aggressive-apparel')}
        >
          ←
        </Button>
        {/* Center indicator */}
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            backgroundColor: '#f0f0f0',
            borderRadius: '4px',
            fontSize: '12px',
            color: '#757575',
          }}
        >
          ●
        </div>
        {/* Right */}
        <Button
          variant={value === 'right' ? 'primary' : 'secondary'}
          onClick={() => onChange('right')}
          style={{
            minWidth: '36px',
            height: '36px',
            padding: 0,
            fontSize: '18px',
            display: 'flex',
            justifyContent: 'center',
          }}
          title={__('Right', 'aggressive-apparel')}
          aria-label={__('Scroll Right', 'aggressive-apparel')}
        >
          →
        </Button>
        {/* Empty bottom-left */}
        <div />
        {/* Down */}
        <Button
          variant={value === 'down' ? 'primary' : 'secondary'}
          onClick={() => onChange('down')}
          style={{
            minWidth: '36px',
            height: '36px',
            padding: 0,
            fontSize: '18px',
            display: 'flex',
            justifyContent: 'center',
          }}
          title={__('Down', 'aggressive-apparel')}
          aria-label={__('Scroll Down', 'aggressive-apparel')}
        >
          ↓
        </Button>
        {/* Empty bottom-right */}
        <div />
      </div>
      <div
        style={{
          fontSize: '11px',
          color: '#757575',
          textAlign: 'center',
          marginTop: '8px',
        }}
      >
        {__('Element moves', 'aggressive-apparel')}{' '}
        <strong>
          {DIRECTIONS.find(d => d.value === value)?.title || value}
        </strong>{' '}
        {__('on scroll', 'aggressive-apparel')}
      </div>
    </div>
  );
};
