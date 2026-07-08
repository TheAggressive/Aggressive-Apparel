import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

import './editor.css';

interface DarkModeToggleEditProps {
  attributes: {
    label?: string;
    showLabel?: boolean;
    size?: 'small' | 'medium' | 'large';
    alignment?: 'left' | 'center' | 'right';
  };
}

export default function Edit({ attributes }: DarkModeToggleEditProps) {
  const {
    label = __('Dark Mode', 'aggressive-apparel'),
    showLabel = true,
    size = 'medium',
    alignment = 'left',
  } = attributes;

  const blockProps = useBlockProps({
    className: `is-size-${size} has-text-align-${alignment}`,
  });

  return (
    <div {...blockProps}>
      <button
        type='button'
        className='dark-mode-toggle__button'
        aria-pressed='false'
        aria-label={__('Switch to dark mode', 'aggressive-apparel')}
      >
        <span className='dark-mode-toggle__track' aria-hidden='true'>
          <span className='dark-mode-toggle__thumb' />
        </span>
        {showLabel && label ? (
          <span className='dark-mode-toggle__label'>{label}</span>
        ) : null}
      </button>
    </div>
  );
}
