/**
 * Nav Link Block Save Component
 *
 * Static block — saves markup directly.
 *
 * @package Aggressive_Apparel
 */

import { RichText, useBlockProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import type { NavLinkAttributes } from './types';

export default function Save({
  attributes,
}: BlockSaveProps<NavLinkAttributes>) {
  const { label, url, opensInNewTab, description, isCurrent } = attributes;

  const blockProps = useBlockProps.save({
    className: `wp-block-aggressive-apparel-nav-link${isCurrent ? ' is-current' : ''}`,
  });

  // Build link attributes.
  const linkProps: Record<string, string | undefined> = {
    className: 'wp-block-aggressive-apparel-nav-link__link',
    href: url || '#',
    role: 'menuitem',
  };

  if (opensInNewTab) {
    linkProps.target = '_blank';
    linkProps.rel = 'noopener noreferrer';
  }

  if (isCurrent) {
    linkProps['aria-current'] = 'page';
  }

  return (
    <li {...blockProps} role='none'>
      <a {...linkProps}>
        <RichText.Content
          tagName='span'
          className='wp-block-aggressive-apparel-nav-link__label'
          value={label}
        />
        {description && (
          <span className='wp-block-aggressive-apparel-nav-link__description'>
            {description}
          </span>
        )}
      </a>
    </li>
  );
}
