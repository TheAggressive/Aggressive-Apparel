/**
 * Copyright Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import {
  AlignmentControl,
  BlockControls,
  InspectorControls,
  RichText,
  useBlockProps,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  PanelBody,
  SelectControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { decodeEntities } from '@wordpress/html-entities';
import { __, sprintf } from '@wordpress/i18n';
import type { CSSProperties } from 'react';
import {
  LEGAL_ENTITY_CUSTOM,
  LEGAL_ENTITY_NONE,
  LEGAL_ENTITY_PRESETS,
  formatCopyrightOwner,
} from './legal-entity';
import type { CopyrightAttributes, CopyrightOwnerSource } from './types';
import { getCopyrightYearDisplay } from './year-display';

const LEGAL_ENTITY_OPTIONS = [
  {
    label: __('None', 'aggressive-apparel'),
    value: LEGAL_ENTITY_NONE,
  },
  ...LEGAL_ENTITY_PRESETS.map(preset => ({
    label: preset,
    value: preset,
  })),
  {
    label: __('Custom…', 'aggressive-apparel'),
    value: LEGAL_ENTITY_CUSTOM,
  },
];

const OWNER_SOURCE_OPTIONS: {
  label: string;
  value: CopyrightOwnerSource;
}[] = [
  {
    label: __('Site title', 'aggressive-apparel'),
    value: 'site_title',
  },
  {
    label: __('Legal name', 'aggressive-apparel'),
    value: 'legal_name',
  },
  {
    label: __('Custom name', 'aggressive-apparel'),
    value: 'custom',
  },
];

/**
 * Resolve the live site title the same way core/site-title does.
 */
function useSiteTitleValue(): string {
  return useSelect(select => {
    const { canUser, getEditedEntityRecord, getEntityRecord } =
      select(coreStore);

    const canEdit = canUser('update', {
      kind: 'root',
      name: 'site',
    });

    if (canEdit) {
      // Site settings are a singleton; core/site-title also omits the record key.
      const settings = (
        getEditedEntityRecord as (
          kind: string,
          name: string
        ) => { title?: string } | false
      )('root', 'site');
      return decodeEntities(
        settings && typeof settings.title === 'string' ? settings.title : ''
      ).trim();
    }

    const base = getEntityRecord('root', '__unstableBase') as
      | { name?: string }
      | undefined;
    return decodeEntities(base?.name ?? '').trim();
  }, []);
}

function getEditorData() {
  return window.aggressiveApparelCopyright;
}

function ownerSourceHelp(
  ownerSource: CopyrightOwnerSource,
  siteTitle: string,
  legalName: string
): string {
  if (ownerSource === 'legal_name') {
    return legalName
      ? sprintf(
          /* translators: %s: configured legal / organization name. */
          __('Currently: %s (Settings → Terms)', 'aggressive-apparel'),
          legalName
        )
      : __(
          'Set under Settings → Terms. Falls back to the site title when empty.',
          'aggressive-apparel'
        );
  }

  if (ownerSource === 'site_title') {
    return siteTitle
      ? sprintf(
          /* translators: %s: current site title. */
          __('Currently: %s', 'aggressive-apparel'),
          siteTitle
        )
      : __(
          'Pulls from Settings → General → Site Title (or the Site Editor site title).',
          'aggressive-apparel'
        );
  }

  return __('Enter a custom rights-holder name below.', 'aggressive-apparel');
}

export type { CopyrightAttributes };

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<CopyrightAttributes>) {
  const {
    ownerSource,
    ownerName,
    legalEntity,
    legalEntityCustom,
    showStartYear,
    startYear,
    separator,
    prefix,
    suffix,
    showLegalLinks,
    legalLinksSeparator,
    showSchema,
    textAlign,
  } = attributes;

  const siteTitle = useSiteTitleValue();
  const editorData = getEditorData();
  const legalName = editorData?.legalName?.trim() ?? '';
  const currentYear = new Date().getFullYear();

  let baseOwner = siteTitle;
  if (ownerSource === 'legal_name') {
    baseOwner = legalName || siteTitle;
  } else if (ownerSource === 'custom') {
    baseOwner = ownerName.trim() || siteTitle;
  }

  const resolvedOwner = formatCopyrightOwner(
    baseOwner || __('Site Title', 'aggressive-apparel'),
    legalEntity,
    legalEntityCustom
  );

  const yearDisplay = getCopyrightYearDisplay(
    showStartYear,
    startYear,
    separator,
    currentYear
  );

  const copyrightText = `${prefix} ${yearDisplay} ${resolvedOwner}`;

  const privacyLabel =
    editorData?.privacyLabel || __('Privacy', 'aggressive-apparel');
  const termsLabel =
    editorData?.termsLabel || __('Terms', 'aggressive-apparel');
  const hasPrivacy = Boolean(editorData?.privacyUrl);
  const hasTerms = Boolean(editorData?.termsUrl);
  const linkSep = legalLinksSeparator || '|';

  const legalLinksHelp = (() => {
    if (!showLegalLinks) {
      return __(
        'Appends Privacy (Settings → Privacy) and Terms (Settings → Terms).',
        'aggressive-apparel'
      );
    }
    if (!hasPrivacy && !hasTerms) {
      return __(
        'No pages configured yet. Set Privacy under Settings → Privacy, and Terms under Settings → Terms.',
        'aggressive-apparel'
      );
    }
    if (!hasPrivacy) {
      return __(
        'Terms is set. Privacy is missing — choose and publish a Privacy Policy page under Settings → Privacy.',
        'aggressive-apparel'
      );
    }
    if (!hasTerms) {
      return __(
        'Privacy is set. Terms is missing — choose a Terms page under Settings → Terms (and publish it).',
        'aggressive-apparel'
      );
    }
    return __('Privacy and Terms pages are configured.', 'aggressive-apparel');
  })();

  const blockProps = useBlockProps({
    className: textAlign ? `has-text-align-${textAlign}` : undefined,
    style: {
      textAlign: (textAlign || undefined) as CSSProperties['textAlign'],
    },
  });

  return (
    <>
      <BlockControls>
        <AlignmentControl
          value={textAlign}
          onChange={value => setAttributes({ textAlign: value ?? '' })}
        />
      </BlockControls>
      <InspectorControls>
        <PanelBody title={__('Copyright Settings', 'aggressive-apparel')}>
          <SelectControl<CopyrightOwnerSource>
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label={__('Owner source', 'aggressive-apparel')}
            help={ownerSourceHelp(ownerSource, siteTitle, legalName)}
            value={ownerSource}
            options={OWNER_SOURCE_OPTIONS}
            onChange={value =>
              setAttributes({
                ownerSource: (value || 'site_title') as CopyrightOwnerSource,
              })
            }
          />
          {ownerSource === 'custom' && (
            <TextControl
              __next40pxDefaultSize
              __nextHasNoMarginBottom
              label={__('Owner Name', 'aggressive-apparel')}
              value={ownerName}
              onChange={value => setAttributes({ ownerName: value })}
            />
          )}
          <SelectControl<string>
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label={__('Organization type', 'aggressive-apparel')}
            help={__(
              'Legal entity designation appended after the owner name (e.g. LLC, Inc.).',
              'aggressive-apparel'
            )}
            value={legalEntity || LEGAL_ENTITY_NONE}
            options={LEGAL_ENTITY_OPTIONS}
            onChange={value => setAttributes({ legalEntity: value })}
          />
          {legalEntity === LEGAL_ENTITY_CUSTOM && (
            <TextControl
              __next40pxDefaultSize
              __nextHasNoMarginBottom
              label={__('Custom designation', 'aggressive-apparel')}
              help={__('Example: S.A., Pty Ltd, PLLC.', 'aggressive-apparel')}
              value={legalEntityCustom}
              onChange={value => setAttributes({ legalEntityCustom: value })}
            />
          )}
          <TextControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label={__('Prefix', 'aggressive-apparel')}
            help={__('Symbol or text before the year.', 'aggressive-apparel')}
            value={prefix}
            onChange={value => setAttributes({ prefix: value })}
          />
          <ToggleControl
            __nextHasNoMarginBottom
            label={__('Show Start Year', 'aggressive-apparel')}
            help={__(
              'Display a year range (e.g. 2012–2026) instead of just the current year.',
              'aggressive-apparel'
            )}
            checked={showStartYear}
            onChange={value => setAttributes({ showStartYear: value })}
          />
          {showStartYear && (
            <>
              <TextControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__('Start Year', 'aggressive-apparel')}
                type='number'
                value={String(startYear)}
                min={1000}
                max={currentYear}
                step={1}
                onChange={value => {
                  const parsed = parseInt(value, 10);
                  setAttributes({
                    startYear: Number.isFinite(parsed)
                      ? Math.min(Math.max(parsed, 1000), currentYear)
                      : currentYear,
                  });
                }}
              />
              <SelectControl<string>
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__('Year Separator', 'aggressive-apparel')}
                value={separator}
                options={[
                  {
                    label: __('– (en dash)', 'aggressive-apparel'),
                    value: '–',
                  },
                  {
                    label: __('- (hyphen)', 'aggressive-apparel'),
                    value: '-',
                  },
                  {
                    label: __('/ (slash)', 'aggressive-apparel'),
                    value: '/',
                  },
                ]}
                onChange={value => setAttributes({ separator: value })}
              />
            </>
          )}
        </PanelBody>
        <PanelBody
          title={__('Legal & SEO', 'aggressive-apparel')}
          initialOpen={false}
        >
          <ToggleControl
            __nextHasNoMarginBottom
            label={__('Show Privacy & Terms links', 'aggressive-apparel')}
            help={legalLinksHelp}
            checked={showLegalLinks}
            onChange={value => setAttributes({ showLegalLinks: value })}
          />
          {showLegalLinks && (
            <SelectControl<string>
              __next40pxDefaultSize
              __nextHasNoMarginBottom
              label={__('Link separator', 'aggressive-apparel')}
              help={__(
                'Character between the notice and links, and between Privacy and Terms.',
                'aggressive-apparel'
              )}
              value={linkSep}
              options={[
                {
                  label: __('| (pipe)', 'aggressive-apparel'),
                  value: '|',
                },
                {
                  label: __('· (middle dot)', 'aggressive-apparel'),
                  value: '·',
                },
                {
                  label: __('• (bullet)', 'aggressive-apparel'),
                  value: '•',
                },
                {
                  label: __('– (en dash)', 'aggressive-apparel'),
                  value: '–',
                },
                {
                  label: __('- (hyphen)', 'aggressive-apparel'),
                  value: '-',
                },
                {
                  label: __('/ (slash)', 'aggressive-apparel'),
                  value: '/',
                },
              ]}
              onChange={value =>
                setAttributes({ legalLinksSeparator: value || '|' })
              }
            />
          )}
          <ToggleControl
            __nextHasNoMarginBottom
            label={__('Schema.org JSON-LD', 'aggressive-apparel')}
            help={__(
              'Outputs Organization + WebSite copyrightHolder / copyrightYear (once per page).',
              'aggressive-apparel'
            )}
            checked={showSchema}
            onChange={value => setAttributes({ showSchema: value })}
          />
        </PanelBody>
      </InspectorControls>
      <p {...blockProps}>
        <span className='wp-block-aggressive-apparel-copyright__text'>
          {copyrightText}
        </span>
        <RichText
          tagName='span'
          className='wp-block-aggressive-apparel-copyright__suffix'
          value={suffix}
          onChange={value => setAttributes({ suffix: value })}
          placeholder={__('. All rights reserved.', 'aggressive-apparel')}
          allowedFormats={['core/bold', 'core/italic', 'core/link']}
        />
        {showLegalLinks && (hasPrivacy || hasTerms) && (
          <span className='wp-block-aggressive-apparel-copyright__legal-links'>
            {' '}
            <span
              className='wp-block-aggressive-apparel-copyright__legal-sep'
              aria-hidden='true'
            >
              {linkSep}
            </span>{' '}
            {hasPrivacy && (
              <a
                className='wp-block-aggressive-apparel-copyright__legal-link'
                href={editorData?.privacyUrl}
                onClick={event => event.preventDefault()}
              >
                {privacyLabel}
              </a>
            )}
            {hasPrivacy && hasTerms && (
              <>
                {' '}
                <span
                  className='wp-block-aggressive-apparel-copyright__legal-sep'
                  aria-hidden='true'
                >
                  {linkSep}
                </span>{' '}
              </>
            )}
            {hasTerms && (
              <a
                className='wp-block-aggressive-apparel-copyright__legal-link'
                href={editorData?.termsUrl}
                onClick={event => event.preventDefault()}
              >
                {termsLabel}
              </a>
            )}
          </span>
        )}
      </p>
    </>
  );
}
