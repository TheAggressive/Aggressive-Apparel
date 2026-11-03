/**
 * Product Specs settings — an On/Off switch with its state beside it.
 *
 * The one switch for specs: on the Specs tab it turns a spec on or off for the
 * store, and in a size guide it shows or hides one on that guide's products.
 *
 * @package Aggressive_Apparel
 */

import { FormToggle } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

type Props = {
  checked: boolean;
  /** What switching it does, for screen readers ("Use Fit"). */
  label: string;
  onChange: (checked: boolean) => void;
};

export default function OnOff({ checked, label, onChange }: Props) {
  return (
    <div className='aa-ps-card__switch'>
      <FormToggle
        checked={checked}
        aria-label={label}
        onChange={() => onChange(!checked)}
      />
      <span aria-hidden='true'>
        {checked
          ? __('On', 'aggressive-apparel')
          : __('Off', 'aggressive-apparel')}
      </span>
    </div>
  );
}
