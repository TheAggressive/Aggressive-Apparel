/**
 * Icon canvas preview for block editor components.
 *
 * @package Aggressive_Apparel
 */

import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useIconPreview } from './use-icon-preview';

export type IconEditorPreviewEmpty = 'hidden' | 'placeholder';

export interface IconEditorPreviewProps {
  slug: string;
  size: number;
  className?: string;
  /** What to render when slug is empty. Default `hidden`. */
  empty?: IconEditorPreviewEmpty;
  placeholderClassName?: string;
  placeholder?: string;
  /** Show a spinner while the first fetch is in flight. Default false. */
  showLoadingSpinner?: boolean;
}

/**
 * Render a sized icon preview in the block editor.
 */
export function IconEditorPreview({
  slug,
  size,
  className = '',
  empty = 'hidden',
  placeholderClassName = 'aggressive-apparel-icon-block__placeholder',
  placeholder,
  showLoadingSpinner = false,
}: IconEditorPreviewProps) {
  const { svg, isLoading } = useIconPreview(slug);

  if (!slug) {
    if (empty === 'placeholder') {
      return (
        <span className={placeholderClassName}>
          {placeholder ?? __('Select an icon', 'aggressive-apparel')}
        </span>
      );
    }

    return null;
  }

  if (showLoadingSpinner && isLoading && !svg) {
    return <Spinner />;
  }

  if (!svg) {
    return null;
  }

  const wrapClass = ['aggressive-apparel-icon__svg-wrap', className]
    .filter(Boolean)
    .join(' ');

  return (
    <span
      className={wrapClass}
      style={{ width: size, height: size }}
      aria-hidden='true'
      dangerouslySetInnerHTML={{ __html: svg }}
    />
  );
}
