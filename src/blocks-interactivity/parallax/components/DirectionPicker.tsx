/**
 * DirectionPicker — native segmented control for the scroll direction.
 */

import {
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalToggleGroupControl as ToggleGroupControl,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalToggleGroupControlOptionIcon as ToggleGroupControlOptionIcon,
} from '@wordpress/components';
import { arrowDown, arrowLeft, arrowRight, arrowUp } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

interface DirectionPickerProps {
  value: string;
  onChange: (_direction: string) => void;
}

const DIRECTIONS = [
  { value: 'up', icon: arrowUp, label: __('Up', 'aggressive-apparel') },
  { value: 'down', icon: arrowDown, label: __('Down', 'aggressive-apparel') },
  { value: 'left', icon: arrowLeft, label: __('Left', 'aggressive-apparel') },
  {
    value: 'right',
    icon: arrowRight,
    label: __('Right', 'aggressive-apparel'),
  },
];

export const DirectionPicker = ({ value, onChange }: DirectionPickerProps) => (
  <ToggleGroupControl
    __next40pxDefaultSize
    __nextHasNoMarginBottom
    isBlock
    label={__('Scroll direction', 'aggressive-apparel')}
    value={value}
    onChange={next => onChange(String(next))}
  >
    {DIRECTIONS.map(direction => (
      <ToggleGroupControlOptionIcon
        key={direction.value}
        value={direction.value}
        icon={direction.icon}
        label={direction.label}
      />
    ))}
  </ToggleGroupControl>
);
