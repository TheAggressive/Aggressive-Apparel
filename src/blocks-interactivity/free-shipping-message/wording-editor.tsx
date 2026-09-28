/**
 * Free-shipping wording — shared editor UI for the message and bar blocks.
 *
 * By default the text in the block edits the site-wide Store Copy wording
 * (like the Site Title block edits the site title): one change updates every
 * free-shipping message and bar, and it saves with the other site changes.
 * "Custom wording for this block" switches the block to its own attributes.
 *
 * @package Aggressive_Apparel
 */

import { BlockControls, RichText } from '@wordpress/block-editor';
import {
  Button,
  ExternalLink,
  Notice,
  PanelBody,
  ToggleControl,
  ToolbarButton,
  ToolbarGroup,
} from '@wordpress/components';
import { store as coreStore, useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import {
  WORDING_OPTIONS,
  defaultWording,
  htmlToWording,
  unknownTokens,
  wordingToHtml,
  type WordingState,
} from './wording';

export type WordingAttributes = {
  useCustomWording: boolean;
  progressText: string;
  unlockedText: string;
  emphasisText?: string;
};

const BLOCK_ATTRIBUTE = {
  progress: 'progressText',
  unlocked: 'unlockedText',
} as const;

const STORE_COPY_URL = addQueryArgs('themes.php', {
  page: 'aggressive-apparel-features',
  tab: 'copy',
});

export interface Wording {
  state: WordingState;
  setState: (state: WordingState) => void;
  /** Wording shown in the block for the current state. */
  wording: string;
  setWording: (wording: string) => void;
  /** Whether the current state's wording differs from what it inherits. */
  isChanged: boolean;
  reset: () => void;
  isCustom: boolean;
  setCustom: (custom: boolean) => void;
  canEdit: boolean;
  unknown: string[];
}

/**
 * Resolve and edit the wording a block shows, per its layer.
 */
export function useWording(
  attributes: WordingAttributes,
  setAttributes: (attributes: Partial<WordingAttributes>) => void
): Wording {
  const [state, setState] = useState<WordingState>('progress');
  const [siteWording, setSiteWording] = useEntityProp(
    'root',
    'site',
    WORDING_OPTIONS[state]
  ) as [string | undefined, (value: string) => void, unknown];
  const canEditSite = useSelect(
    select =>
      !!select(coreStore).canUser('update', { kind: 'root', name: 'site' }),
    []
  );

  const isCustom = attributes.useCustomWording;
  const attribute = BLOCK_ATTRIBUTE[state];
  const themeDefault = defaultWording(state, attributes.emphasisText ?? '');
  // Blank site wording follows the translated theme default; blank block
  // wording follows the site wording.
  const inherited = isCustom ? siteWording || themeDefault : themeDefault;
  const own = isCustom ? attributes[attribute] : (siteWording ?? '');
  const wording = own || inherited;

  const setWording = (next: string): void => {
    // Wording equal to what it inherits is stored blank, so it keeps
    // following that layer (and its translations).
    const value = next === inherited ? '' : next;

    if (isCustom) {
      setAttributes({ [attribute]: value });
    } else {
      setSiteWording(value);
    }
  };

  return {
    state,
    setState,
    wording,
    setWording,
    isChanged: '' !== own,
    reset: () => setWording(inherited),
    isCustom,
    setCustom: custom =>
      setAttributes({
        useCustomWording: custom,
        // Turning it off discards the block's own wording outright.
        ...(custom ? {} : { progressText: '', unlockedText: '' }),
      }),
    canEdit: isCustom || canEditSite,
    unknown: unknownTokens(wording, state),
  };
}

/** Block toolbar switch between the two messages. */
export function WordingToolbar({ state, setState }: Wording) {
  return (
    <BlockControls>
      <ToolbarGroup>
        <ToolbarButton
          isPressed={state === 'progress'}
          onClick={() => setState('progress')}
        >
          {__('In progress', 'aggressive-apparel')}
        </ToolbarButton>
        <ToolbarButton
          isPressed={state === 'unlocked'}
          onClick={() => setState('unlocked')}
        >
          {__('Unlocked', 'aggressive-apparel')}
        </ToolbarButton>
      </ToolbarGroup>
    </BlockControls>
  );
}

/** Inspector panel: which layer the block edits, validation, reset. */
export function WordingPanel(wording: Wording) {
  const { state, isCustom, setCustom, canEdit, unknown, isChanged, reset } =
    wording;

  return (
    <PanelBody title={__('Wording', 'aggressive-apparel')} initialOpen>
      <ToggleControl
        __nextHasNoMarginBottom
        label={__('Custom wording for this block', 'aggressive-apparel')}
        checked={isCustom}
        onChange={setCustom}
        help={
          isCustom
            ? __(
                'This block shows its own wording. Turn off to use the shared wording again.',
                'aggressive-apparel'
              )
            : __(
                'Editing the text in the block changes the shared wording used by every free shipping message and progress bar. It is saved with your other site changes.',
                'aggressive-apparel'
              )
        }
      />

      {!canEdit && (
        <Notice status='warning' isDismissible={false}>
          {__(
            'Only administrators can change the shared wording.',
            'aggressive-apparel'
          )}
        </Notice>
      )}

      {unknown.length > 0 && (
        <Notice status='error' isDismissible={false}>
          {state === 'progress'
            ? sprintf(
                /* translators: %s: comma-separated unrecognised placeholders, e.g. {amout}. */
                __(
                  '%s is not a placeholder this message understands. Use {amount} for the amount still needed.',
                  'aggressive-apparel'
                ),
                unknown.join(', ')
              )
            : sprintf(
                /* translators: %s: comma-separated unrecognised placeholders, e.g. {amount}. */
                __(
                  '%s cannot be used here: the unlocked message has no placeholders.',
                  'aggressive-apparel'
                ),
                unknown.join(', ')
              )}
        </Notice>
      )}

      <p className='components-base-control__help'>
        {state === 'progress'
          ? __(
              'Type in the block. Use {amount} for the amount still needed and Bold to highlight words. Switch messages in the block toolbar.',
              'aggressive-apparel'
            )
          : __(
              'Type in the block and use Bold to highlight words. Switch messages in the block toolbar.',
              'aggressive-apparel'
            )}
      </p>

      {isChanged && canEdit && (
        <p>
          <Button variant='secondary' size='compact' onClick={reset}>
            {isCustom
              ? __('Use the shared wording', 'aggressive-apparel')
              : __('Restore the default wording', 'aggressive-apparel')}
          </Button>
        </p>
      )}

      {!isCustom && (
        <ExternalLink href={STORE_COPY_URL}>
          {__('All store wording', 'aggressive-apparel')}
        </ExternalLink>
      )}
    </PanelBody>
  );
}

/** The editable message text in the canvas. */
export function WordingText({
  wording,
  setWording,
  canEdit,
  className,
}: Wording & { className: string }) {
  if (!canEdit) {
    return (
      <span
        className={className}
        dangerouslySetInnerHTML={{ __html: wordingToHtml(wording) }}
      />
    );
  }

  return (
    <RichText
      tagName='span'
      className={className}
      value={wordingToHtml(wording)}
      onChange={html => setWording(htmlToWording(html))}
      allowedFormats={['core/bold']}
      withoutInteractiveFormatting
      placeholder={__('Add free shipping message…', 'aggressive-apparel')}
    />
  );
}
