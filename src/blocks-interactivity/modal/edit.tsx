/**
 * Modal block edit component.
 *
 * @module src/blocks-interactivity/modal/edit
 */

/**
 * WordPress dependencies
 */
import {
  InnerBlocks,
  InspectorControls,
  PanelColorSettings,
  store as blockEditorStore,
  useBlockProps,
} from '@wordpress/block-editor';
import { BlockEditProps } from '@wordpress/blocks';
import { select, subscribe } from '@wordpress/data';
import {
  Button,
  Icon,
  Notice,
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
  Tooltip,
} from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { link as linkIcon } from '@wordpress/icons';
import './editor.css';
import { cleanupAllHighlights, highlightModalTrigger } from './highlights';
import { useTriggerManagement } from './hooks/useTriggerManagement';
import { useUpdateBlockTriggerClass } from './hooks/useUpdateBlockTriggerClass';
import type { ModalAttributes } from './types';
import { copyTextFallback } from './utils/copyTextFallback';
import { Debug } from './utils/debug';
import {
  blockExists,
  isEditorReady,
  manageHighlight,
  safeUpdateTriggerClass,
} from './utils/editorHelpers';
import { generatePersistentId } from './utils/generatePersistentId';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @param props - Block properties
 * @return Element to render.
 */
export default function Edit({
  attributes,
  setAttributes,
  clientId,
  isSelected,
}: BlockEditProps<ModalAttributes>): JSX.Element {
  const {
    position = 'center',
    openOnLoad = false,
    modalId = '',
    triggerBlockId = '',
    triggerBlockKey = '',
    triggerLabel = 'Open Modal',
    disableOverlay = false,
    enterAnimation = 'fade',
    exitAnimation = 'fade',
    animationDuration = 300,
    exitIntentTrigger = false,
    exitIntentReshowDays = 7,
    scrollDepthTrigger = false,
    scrollDepthPercent = 50,
    openOnLoadOnce = false,
    dialogMaxWidth = '',
    dialogPadding = '',
    dialogBorderRadius = '',
    overlayOpacity = 50,
    overlayBlur = 4,
    overlayColor = '',
    triggerVariant = 'outlined',
    triggerSize = 'md',
    triggerFullWidth = false,
    triggerBorderRadius = '',
    triggerBgColor = '',
    triggerTextColor = '',
    triggerHoverBgColor = '',
    triggerHoverTextColor = '',
    closeButtonPlacement = 'inside-top-right',
    closeButtonIcon = 'close',
    closeButtonSize = 'md',
    closeButtonVariant = 'ghost',
    closeButtonLabel = '',
    closeButtonColor = '',
    closeButtonBgColor = '',
    closeButtonHoverColor = '',
    closeButtonHoverBgColor = '',
  } = attributes;

  const updateBlockTriggerClass = useUpdateBlockTriggerClass();

  // Component state.
  const [isHighlightActive, setIsHighlightActive] = useState(false);
  const previousHighlightedElements = useRef<Set<Element>>(new Set());
  const lastSelectedBlock = useRef<string | null>(null);
  const triggerClassApplied = useRef(false);

  // Create safe values (never null).
  const safePosition = position || 'center';

  // Use the trigger management hook.
  const { availableTriggers, safeTriggerBlockId, handleTriggerBlockChange } =
    useTriggerManagement({
      modalId,
      triggerBlockId,
      triggerBlockKey,
      setAttributes,
      updateBlockTriggerClass,
    });

  // Initialize modal ID once.
  useEffect(() => {
    if (!modalId) {
      // Generate a new ID.
      const newModalId = generatePersistentId();
      setAttributes({ modalId: newModalId });
      Debug.add(`Generated new modal ID: ${newModalId}`);
    } else {
      // If we already have a modal ID, log it.
      Debug.add(`Using existing modal ID: ${modalId}`);

      // If we have a saved trigger block ID, ensure the class is applied to it.
      // Only apply this on initial render, not on every change.
      if (safeTriggerBlockId && !triggerClassApplied.current) {
        triggerClassApplied.current = true;
        // Make sure the trigger class is applied to the block.
        updateBlockTriggerClass(safeTriggerBlockId, modalId, true);
        Debug.add(
          `Ensured class modal-trigger-${modalId} is applied to block ${safeTriggerBlockId}`
        );
      }
    }
  }, [modalId, setAttributes, safeTriggerBlockId, updateBlockTriggerClass]);

  // Handle highlighting when selection changes.
  useEffect(() => {
    // Skip if editor isn't ready.
    if (!isEditorReady()) {
      return;
    }

    // First make sure the trigger class is applied correctly.
    safeUpdateTriggerClass(
      updateBlockTriggerClass,
      safeTriggerBlockId,
      modalId,
      true
    );

    // Apply or clean up highlights based on selection state.
    manageHighlight({
      modalId,
      blockId: safeTriggerBlockId,
      isSelected,
      setIsHighlightActive,
      previousHighlightedElements: previousHighlightedElements.current,
    });

    // Clean up when unmounting.
    return () => cleanupAllHighlights();
  }, [isSelected, safeTriggerBlockId, modalId, updateBlockTriggerClass]);

  // Add a global selection change listener to ensure highlights are cleaned up.
  useEffect(() => {
    // Don't bother if we don't have a trigger block.
    if (!safeTriggerBlockId) {
      return;
    }

    // Subscribe to selection changes in the block editor.
    const unsubscribe = subscribe(() => {
      // Skip if editor isn't ready.
      if (!isEditorReady()) {
        return;
      }

      // First check if the trigger block still exists using our utility.
      if (!blockExists(safeTriggerBlockId)) {
        return;
      }

      const blockEditor = select(blockEditorStore);
      const selectedBlockId = blockEditor?.getSelectedBlockClientId();

      // Skip if selection hasn't changed.
      if (selectedBlockId === lastSelectedBlock.current) {
        return;
      }

      // Update our tracking ref.
      lastSelectedBlock.current = selectedBlockId;

      // If the selected block exists and it's not our modal or a parent of our modal.
      if (selectedBlockId && selectedBlockId !== clientId) {
        // Check if this block or any of its parents is our modal.
        let isParentOfModal = false;
        const parentIds = blockEditor?.getBlockParents(clientId);

        if (parentIds && parentIds.includes(selectedBlockId)) {
          isParentOfModal = true;
        }

        // If it's not our modal or a parent of our modal, and we're showing a highlight,
        // clean up all highlights.
        if (!isParentOfModal && isHighlightActive) {
          cleanupAllHighlights();
          setIsHighlightActive(false);
        }
      }
    });

    // Clean up subscription when component unmounts.
    return () => {
      unsubscribe();
    };
  }, [safeTriggerBlockId, clientId, isHighlightActive, setAttributes]);

  /**
   * Refresh the trigger highlight manually
   */
  const handleRefreshHighlight = useCallback(() => {
    // Only refresh the highlight if the modal is selected.
    if (isSelected && safeTriggerBlockId) {
      // Verify the trigger block still exists.
      const blockEditor = select(blockEditorStore);
      const blockStillExists =
        blockEditor && blockEditor.getBlock(safeTriggerBlockId);

      if (!blockStillExists) {
        Debug.add(
          `Trigger block ${safeTriggerBlockId} no longer exists - cannot highlight`,
          true
        );
        // Clear the highlight state since the block no longer exists.
        setIsHighlightActive(false);
        return;
      }

      // Cleanup existing highlights first.
      cleanupAllHighlights();

      // Make sure the trigger class is still applied.
      try {
        updateBlockTriggerClass(safeTriggerBlockId, modalId, true);
      } catch (error) {
        Debug.add(
          `Error refreshing trigger class: ${(error as Error).message}`,
          true
        );
        return;
      }

      // Use the direct highlighting function.
      setTimeout(() => {
        try {
          highlightModalTrigger(null, modalId, safeTriggerBlockId, {
            discreet: true,
          });
          setIsHighlightActive(true);

          // Store any newly highlighted elements.
          document.querySelectorAll('.modal-highlight-target').forEach(el => {
            previousHighlightedElements.current.add(el);
          });
        } catch (error) {
          Debug.add(
            `Error highlighting trigger: ${(error as Error).message}`,
            true
          );
          setIsHighlightActive(false);
        }
      }, 100);
    } else if (!isSelected) {
      // If the modal is not selected, inform the user.
      Debug.add('Cannot refresh highlight when modal is not selected');
    } else if (!safeTriggerBlockId) {
      Debug.add('No trigger block is selected to highlight');
    }
  }, [safeTriggerBlockId, modalId, isSelected, updateBlockTriggerClass]);

  /**
   * Render the inspector controls
   *
   * @return Inspector controls
   */
  const renderInspectorControls = (): JSX.Element => (
    <InspectorControls>
      <PanelBody
        title={__('Modal Settings', 'aggressive-apparel')}
        initialOpen={true}
      >
        {/* Modal ID */}
        <TextControl
          label={__('Modal ID', 'aggressive-apparel')}
          value={modalId}
          onChange={value => setAttributes({ modalId: value })}
          help={__(
            'Unique identifier for this modal. Used to link triggers to this modal.',
            'aggressive-apparel'
          )}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />

        {/* Position */}
        <SelectControl<string>
          label={__('Modal Position', 'aggressive-apparel')}
          value={safePosition}
          options={[
            { label: __('Center', 'aggressive-apparel'), value: 'center' },
            { label: __('Top Left', 'aggressive-apparel'), value: 'top-left' },
            {
              label: __('Top Right', 'aggressive-apparel'),
              value: 'top-right',
            },
            {
              label: __('Bottom Left', 'aggressive-apparel'),
              value: 'bottom-left',
            },
            {
              label: __('Bottom Right', 'aggressive-apparel'),
              value: 'bottom-right',
            },
            {
              label: __('Bottom Sheet', 'aggressive-apparel'),
              value: 'bottom',
            },
            { label: __('Top Drawer', 'aggressive-apparel'), value: 'top' },
            { label: __('Left Panel', 'aggressive-apparel'), value: 'left' },
            { label: __('Right Panel', 'aggressive-apparel'), value: 'right' },
          ]}
          onChange={value => setAttributes({ position: value })}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />

        {/* Max width */}
        <TextControl
          label={__('Max Width', 'aggressive-apparel')}
          value={dialogMaxWidth}
          placeholder='40rem'
          onChange={value => setAttributes({ dialogMaxWidth: value })}
          help={__(
            'e.g. 40rem, 600px, 80vw. Leave empty for default (40rem).',
            'aggressive-apparel'
          )}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />

        {/* Open on load */}
        <ToggleControl
          label={__('Open on Page Load', 'aggressive-apparel')}
          checked={openOnLoad}
          onChange={value => setAttributes({ openOnLoad: value })}
          help={__(
            'Automatically open the modal when the page loads.',
            'aggressive-apparel'
          )}
          __nextHasNoMarginBottom
        />

        {openOnLoad && (
          <ToggleControl
            label={__('Show Once Per Visitor', 'aggressive-apparel')}
            checked={openOnLoadOnce}
            onChange={value => setAttributes({ openOnLoadOnce: value })}
            help={__(
              "Don't reopen after the visitor has seen it once.",
              'aggressive-apparel'
            )}
            __nextHasNoMarginBottom
          />
        )}

        {/* Disable overlay */}
        <ToggleControl
          label={__('Disable Overlay', 'aggressive-apparel')}
          checked={disableOverlay}
          onChange={value => setAttributes({ disableOverlay: value })}
          help={__(
            'When enabled, the modal will not have a background overlay',
            'aggressive-apparel'
          )}
          __nextHasNoMarginBottom
        />

        {/* Exit intent trigger */}
        <ToggleControl
          label={__('Exit Intent Trigger', 'aggressive-apparel')}
          checked={exitIntentTrigger}
          onChange={value => setAttributes({ exitIntentTrigger: value })}
          help={__(
            'Open the modal when the visitor shows intent to leave the page (mouse leaving viewport on desktop, rapid scroll-up on mobile)',
            'aggressive-apparel'
          )}
          __nextHasNoMarginBottom
        />

        {exitIntentTrigger && (
          <RangeControl
            label={__('Re-show After (days)', 'aggressive-apparel')}
            value={exitIntentReshowDays}
            onChange={value => setAttributes({ exitIntentReshowDays: value })}
            min={1}
            max={90}
            step={1}
            help={__(
              'Days before showing the exit intent modal again to the same visitor.',
              'aggressive-apparel'
            )}
            __nextHasNoMarginBottom
          />
        )}

        {/* Scroll depth trigger */}
        <ToggleControl
          label={__('Scroll Depth Trigger', 'aggressive-apparel')}
          checked={scrollDepthTrigger}
          onChange={value => setAttributes({ scrollDepthTrigger: value })}
          help={__(
            'Open when the visitor scrolls to a percentage of the page. Works on all devices.',
            'aggressive-apparel'
          )}
          __nextHasNoMarginBottom
        />

        {scrollDepthTrigger && (
          <RangeControl
            label={__('Scroll Depth (%)', 'aggressive-apparel')}
            value={scrollDepthPercent}
            onChange={value => setAttributes({ scrollDepthPercent: value })}
            min={10}
            max={100}
            step={5}
            help={__(
              'Percentage of the page scrolled before the modal opens.',
              'aggressive-apparel'
            )}
            __nextHasNoMarginBottom
          />
        )}

        {/* Trigger block select */}
        <SelectControl<string>
          label={__('Trigger Block', 'aggressive-apparel')}
          value={safeTriggerBlockId}
          options={availableTriggers}
          onChange={handleTriggerBlockChange}
          help={__(
            'Select a block to trigger this modal',
            'aggressive-apparel'
          )}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />

        {/* Show highlight status */}
        {isHighlightActive && safeTriggerBlockId && isSelected && (
          <Notice status='info' isDismissible={false}>
            {__(
              'Trigger block is highlighted in the editor',
              'aggressive-apparel'
            )}
          </Notice>
        )}

        {/* Show message when not selected */}
        {safeTriggerBlockId && !isSelected && (
          <Notice status='warning' isDismissible={false}>
            {__(
              'Select this modal to highlight the trigger block',
              'aggressive-apparel'
            )}
          </Notice>
        )}

        {/* Refresh highlight button */}
        {safeTriggerBlockId && (
          <Tooltip
            text={
              !isSelected
                ? __(
                    'Select the modal first to use this button',
                    'aggressive-apparel'
                  )
                : ''
            }
          >
            <div>
              <Button
                variant='secondary'
                onClick={handleRefreshHighlight}
                className='refresh-highlight-button'
                disabled={!isSelected}
              >
                {__('Refresh Highlight', 'aggressive-apparel')}
              </Button>
            </div>
          </Tooltip>
        )}

        {/* Trigger label (only if no block selected) */}
        {!safeTriggerBlockId && (
          <TextControl
            label={__('Trigger Button Label', 'aggressive-apparel')}
            value={triggerLabel}
            onChange={value => setAttributes({ triggerLabel: value })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        )}
      </PanelBody>

      {/* Manual connection panel */}
      <PanelBody
        title={__('Manual Connection', 'aggressive-apparel')}
        initialOpen={false}
      >
        <p>
          {__(
            'To connect any HTML element to this modal, add this class:',
            'aggressive-apparel'
          )}
        </p>
        {modalId && (
          <>
            <code className='modal-connection-code'>
              modal-trigger-{modalId}
            </code>
            <p className='modal-connection-example'>
              {__('Example:', 'aggressive-apparel')}
              <br />
              <code>{`<a href="#" class="modal-trigger-${modalId}">Open Modal</a>`}</code>
            </p>
            <Button
              variant='secondary'
              onClick={() => {
                const textToCopy = `modal-trigger-${modalId}`;
                // Check if the Clipboard API is available.
                if (
                  navigator &&
                  navigator.clipboard &&
                  navigator.clipboard.writeText
                ) {
                  navigator.clipboard.writeText(textToCopy).catch(() => {
                    // Fallback to textarea method if writeText fails.
                    copyTextFallback(textToCopy);
                  });
                } else {
                  // Fallback method using a temporary textarea.
                  copyTextFallback(textToCopy);
                }
              }}
            >
              {__('Copy to Clipboard', 'aggressive-apparel')}
            </Button>
          </>
        )}
      </PanelBody>

      {/* Animation panel */}
      <PanelBody
        title={__('Animation Settings', 'aggressive-apparel')}
        initialOpen={false}
      >
        {/* Enter animation */}
        <SelectControl<string>
          label={__('Enter Animation', 'aggressive-apparel')}
          value={enterAnimation}
          options={[
            { label: __('Fade', 'aggressive-apparel'), value: 'fade' },
            { label: __('Slide Up', 'aggressive-apparel'), value: 'slide-up' },
            {
              label: __('Slide Down', 'aggressive-apparel'),
              value: 'slide-down',
            },
            {
              label: __('Slide Left', 'aggressive-apparel'),
              value: 'slide-left',
            },
            {
              label: __('Slide Right', 'aggressive-apparel'),
              value: 'slide-right',
            },
            { label: __('Zoom In', 'aggressive-apparel'), value: 'zoom-in' },
            { label: __('Expand', 'aggressive-apparel'), value: 'expand' },
            { label: __('Recede', 'aggressive-apparel'), value: 'recede' },
            { label: __('Lift', 'aggressive-apparel'), value: 'lift' },
            { label: __('Spring', 'aggressive-apparel'), value: 'spring' },
            { label: __('Pop', 'aggressive-apparel'), value: 'pop' },
            { label: __('Warp', 'aggressive-apparel'), value: 'warp' },
            { label: __('Material', 'aggressive-apparel'), value: 'material' },
            { label: __('Float', 'aggressive-apparel'), value: 'float' },
            { label: __('Drift', 'aggressive-apparel'), value: 'drift' },
            { label: __('Flip Up', 'aggressive-apparel'), value: 'flip-up' },
            { label: __('Blur', 'aggressive-apparel'), value: 'blur' },
            { label: __('None', 'aggressive-apparel'), value: 'none' },
          ]}
          onChange={value => setAttributes({ enterAnimation: value })}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />

        {/* Exit animation */}
        <SelectControl<string>
          label={__('Exit Animation', 'aggressive-apparel')}
          value={exitAnimation}
          options={[
            { label: __('Fade', 'aggressive-apparel'), value: 'fade' },
            { label: __('Slide Up', 'aggressive-apparel'), value: 'slide-up' },
            {
              label: __('Slide Down', 'aggressive-apparel'),
              value: 'slide-down',
            },
            {
              label: __('Slide Left', 'aggressive-apparel'),
              value: 'slide-left',
            },
            {
              label: __('Slide Right', 'aggressive-apparel'),
              value: 'slide-right',
            },
            { label: __('Zoom Out', 'aggressive-apparel'), value: 'zoom-out' },
            { label: __('Zoom In', 'aggressive-apparel'), value: 'zoom-in' },
            { label: __('Expand', 'aggressive-apparel'), value: 'expand' },
            { label: __('Recede', 'aggressive-apparel'), value: 'recede' },
            { label: __('Pop', 'aggressive-apparel'), value: 'pop' },
            {
              label: __('Flip Down', 'aggressive-apparel'),
              value: 'flip-down',
            },
            { label: __('Blur', 'aggressive-apparel'), value: 'blur' },
            { label: __('None', 'aggressive-apparel'), value: 'none' },
          ]}
          onChange={value => setAttributes({ exitAnimation: value })}
          help={__(
            'Drawers and sheets always exit off-screen regardless of this setting.',
            'aggressive-apparel'
          )}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />

        {/* Animation Duration */}
        <RangeControl
          label={__('Animation Duration (ms)', 'aggressive-apparel')}
          value={animationDuration}
          onChange={value => setAttributes({ animationDuration: value })}
          min={100}
          max={1000}
          step={50}
          __nextHasNoMarginBottom
        />
      </PanelBody>

      {/* Close Button panel */}
      <PanelBody
        title={__('Close Button', 'aggressive-apparel')}
        initialOpen={false}
      >
        <SelectControl<string>
          label={__('Placement', 'aggressive-apparel')}
          value={closeButtonPlacement}
          options={[
            {
              label: __('Inside — Top Right', 'aggressive-apparel'),
              value: 'inside-top-right',
            },
            {
              label: __('Inside — Top Left', 'aggressive-apparel'),
              value: 'inside-top-left',
            },
            {
              label: __('Inside — Bottom Right', 'aggressive-apparel'),
              value: 'inside-bottom-right',
            },
            {
              label: __('Inside — Bottom Left', 'aggressive-apparel'),
              value: 'inside-bottom-left',
            },
            {
              label: __('Sticky — Top Right', 'aggressive-apparel'),
              value: 'sticky-top-right',
            },
            {
              label: __('Outside — Top Right', 'aggressive-apparel'),
              value: 'outside-top-right',
            },
            {
              label: __('Outside — Top Left', 'aggressive-apparel'),
              value: 'outside-top-left',
            },
            { label: __('Hidden', 'aggressive-apparel'), value: 'none' },
          ]}
          onChange={value => setAttributes({ closeButtonPlacement: value })}
          help={
            closeButtonPlacement === 'none'
              ? __(
                  'Modal closes via backdrop click or Escape only.',
                  'aggressive-apparel'
                )
              : closeButtonPlacement.startsWith('outside-')
                ? __(
                    'Button floats in the overlay corner, independent of the dialog.',
                    'aggressive-apparel'
                  )
                : undefined
          }
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />

        {closeButtonPlacement !== 'none' && (
          <>
            <SelectControl<string>
              label={__('Icon', 'aggressive-apparel')}
              value={closeButtonIcon}
              options={[
                {
                  label: __('Close (×)', 'aggressive-apparel'),
                  value: 'close',
                },
                {
                  label: __('Arrow Left (←)', 'aggressive-apparel'),
                  value: 'arrow-left',
                },
                {
                  label: __('Chevron Down (↓)', 'aggressive-apparel'),
                  value: 'chevron-down',
                },
                {
                  label: __('Text only', 'aggressive-apparel'),
                  value: 'text-only',
                },
              ]}
              onChange={value => setAttributes({ closeButtonIcon: value })}
              __next40pxDefaultSize
              __nextHasNoMarginBottom
            />

            <SelectControl<string>
              label={__('Size', 'aggressive-apparel')}
              value={closeButtonSize}
              options={[
                {
                  label: __('Small (32px)', 'aggressive-apparel'),
                  value: 'sm',
                },
                {
                  label: __('Medium (44px)', 'aggressive-apparel'),
                  value: 'md',
                },
                {
                  label: __('Large (56px)', 'aggressive-apparel'),
                  value: 'lg',
                },
              ]}
              onChange={value => setAttributes({ closeButtonSize: value })}
              __next40pxDefaultSize
              __nextHasNoMarginBottom
            />

            <SelectControl<string>
              label={__('Style', 'aggressive-apparel')}
              value={closeButtonVariant}
              options={[
                {
                  label: __('Ghost (transparent)', 'aggressive-apparel'),
                  value: 'ghost',
                },
                { label: __('Filled', 'aggressive-apparel'), value: 'filled' },
                {
                  label: __('Outlined', 'aggressive-apparel'),
                  value: 'outlined',
                },
              ]}
              onChange={value => setAttributes({ closeButtonVariant: value })}
              __next40pxDefaultSize
              __nextHasNoMarginBottom
            />

            <TextControl
              label={__('Label', 'aggressive-apparel')}
              value={closeButtonLabel}
              placeholder={__('e.g. Close', 'aggressive-apparel')}
              onChange={value => setAttributes({ closeButtonLabel: value })}
              help={__(
                'Optional visible text alongside the icon.',
                'aggressive-apparel'
              )}
              __next40pxDefaultSize
              __nextHasNoMarginBottom
            />
          </>
        )}
      </PanelBody>

      {closeButtonPlacement !== 'none' && (
        <PanelBody
          title={__('Close Button Colors', 'aggressive-apparel')}
          initialOpen={false}
        >
          <PanelColorSettings
            __experimentalIsRenderedInSidebar
            title=''
            colorSettings={[
              {
                value: closeButtonColor,
                onChange: (value: string | undefined) =>
                  setAttributes({ closeButtonColor: value ?? '' }),
                label: __('Icon / text color', 'aggressive-apparel'),
              },
              {
                value: closeButtonBgColor,
                onChange: (value: string | undefined) =>
                  setAttributes({ closeButtonBgColor: value ?? '' }),
                label: __('Background', 'aggressive-apparel'),
              },
              {
                value: closeButtonHoverColor,
                onChange: (value: string | undefined) =>
                  setAttributes({ closeButtonHoverColor: value ?? '' }),
                label: __('Hover icon / text color', 'aggressive-apparel'),
              },
              {
                value: closeButtonHoverBgColor,
                onChange: (value: string | undefined) =>
                  setAttributes({ closeButtonHoverBgColor: value ?? '' }),
                label: __('Hover background', 'aggressive-apparel'),
              },
            ]}
          />
        </PanelBody>
      )}

      {/* Trigger Button panel — only relevant when using the built-in trigger */}
      {!safeTriggerBlockId && (
        <PanelBody
          title={__('Trigger Button', 'aggressive-apparel')}
          initialOpen={false}
        >
          <SelectControl<string>
            label={__('Variant', 'aggressive-apparel')}
            value={triggerVariant}
            options={[
              {
                label: __('Outlined', 'aggressive-apparel'),
                value: 'outlined',
              },
              { label: __('Filled', 'aggressive-apparel'), value: 'filled' },
              { label: __('Ghost', 'aggressive-apparel'), value: 'ghost' },
              { label: __('Text', 'aggressive-apparel'), value: 'text' },
            ]}
            onChange={value => setAttributes({ triggerVariant: value })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <SelectControl<string>
            label={__('Size', 'aggressive-apparel')}
            value={triggerSize}
            options={[
              {
                label: __('Small (32px)', 'aggressive-apparel'),
                value: 'sm',
              },
              {
                label: __('Medium (44px)', 'aggressive-apparel'),
                value: 'md',
              },
              {
                label: __('Large (52px)', 'aggressive-apparel'),
                value: 'lg',
              },
            ]}
            onChange={value => setAttributes({ triggerSize: value })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <ToggleControl
            label={__('Full Width', 'aggressive-apparel')}
            checked={triggerFullWidth}
            onChange={value => setAttributes({ triggerFullWidth: value })}
            help={__(
              'Stretch the button to fill its container.',
              'aggressive-apparel'
            )}
            __nextHasNoMarginBottom
          />

          <TextControl
            label={__('Border Radius', 'aggressive-apparel')}
            value={triggerBorderRadius}
            placeholder='0.25rem'
            onChange={value => setAttributes({ triggerBorderRadius: value })}
            help={__(
              'e.g. 0.25rem, 9999px for pill. Leave empty for square.',
              'aggressive-apparel'
            )}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <PanelColorSettings
            __experimentalIsRenderedInSidebar
            title={__('Colors', 'aggressive-apparel')}
            colorSettings={[
              {
                value: triggerBgColor,
                onChange: (value: string | undefined) =>
                  setAttributes({ triggerBgColor: value ?? '' }),
                label: __('Background', 'aggressive-apparel'),
              },
              {
                value: triggerTextColor,
                onChange: (value: string | undefined) =>
                  setAttributes({ triggerTextColor: value ?? '' }),
                label: __('Text', 'aggressive-apparel'),
              },
              {
                value: triggerHoverBgColor,
                onChange: (value: string | undefined) =>
                  setAttributes({ triggerHoverBgColor: value ?? '' }),
                label: __('Hover background', 'aggressive-apparel'),
              },
              {
                value: triggerHoverTextColor,
                onChange: (value: string | undefined) =>
                  setAttributes({ triggerHoverTextColor: value ?? '' }),
                label: __('Hover text', 'aggressive-apparel'),
              },
            ]}
          />
        </PanelBody>
      )}

      {/* Modal Design panel */}
      <PanelBody
        title={__('Modal Design', 'aggressive-apparel')}
        initialOpen={false}
      >
        <p className='components-base-control__help' style={{ marginTop: 0 }}>
          {__(
            'Use theme color presets for automatic light/dark mode adaptation.',
            'aggressive-apparel'
          )}
        </p>

        <TextControl
          label={__('Padding', 'aggressive-apparel')}
          value={dialogPadding}
          placeholder='2rem'
          onChange={value => setAttributes({ dialogPadding: value })}
          help={__(
            'e.g. 2rem, 1.5rem 2rem. Leave empty for no padding.',
            'aggressive-apparel'
          )}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />

        <TextControl
          label={__('Border Radius', 'aggressive-apparel')}
          value={dialogBorderRadius}
          placeholder='0.5rem'
          onChange={value => setAttributes({ dialogBorderRadius: value })}
          help={__(
            'Overrides the border radius set in the Border panel above.',
            'aggressive-apparel'
          )}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />

        <RangeControl
          label={__('Overlay Opacity (%)', 'aggressive-apparel')}
          value={overlayOpacity}
          onChange={value => setAttributes({ overlayOpacity: value })}
          min={0}
          max={90}
          step={5}
          help={__('Darkness of the backdrop overlay.', 'aggressive-apparel')}
          __nextHasNoMarginBottom
        />

        <RangeControl
          label={__('Overlay Blur (px)', 'aggressive-apparel')}
          value={overlayBlur}
          onChange={value => setAttributes({ overlayBlur: value })}
          min={0}
          max={20}
          step={1}
          help={__(
            'Backdrop blur behind the overlay. Set to 0 to disable.',
            'aggressive-apparel'
          )}
          __nextHasNoMarginBottom
        />

        <PanelColorSettings
          __experimentalIsRenderedInSidebar
          title={__('Overlay Color', 'aggressive-apparel')}
          colorSettings={[
            {
              value: overlayColor,
              onChange: (value: string | undefined) =>
                setAttributes({ overlayColor: value ?? '' }),
              label: __('Backdrop color', 'aggressive-apparel'),
            },
          ]}
        />
      </PanelBody>
    </InspectorControls>
  );

  // Block props.
  const blockProps = useBlockProps();

  return (
    <>
      {renderInspectorControls()}

      <div {...blockProps}>
        <div className='wp-block-aggressive-apparel-modal__container'>
          <InnerBlocks
            template={[
              [
                'core/heading',
                {
                  level: 3,
                  content: __('Modal Title', 'aggressive-apparel'),
                },
              ],
              [
                'core/paragraph',
                {
                  content: __(
                    'Add your modal content here…',
                    'aggressive-apparel'
                  ),
                },
              ],
            ]}
            templateLock={false}
          />
        </div>

        <div className='modal-editor-footer'>
          <div className='modal-editor-footer-item'>
            {__('Position:', 'aggressive-apparel')}{' '}
            {safePosition.charAt(0).toUpperCase() + safePosition.slice(1)}
          </div>

          {openOnLoad && (
            <div className='modal-editor-footer-item'>
              {__('Opens Automatically', 'aggressive-apparel')}
            </div>
          )}

          {disableOverlay && (
            <div className='modal-editor-footer-item'>
              {__('Disabled Overlay', 'aggressive-apparel')}
            </div>
          )}

          {safeTriggerBlockId ? (
            <div className='modal-editor-footer-item'>
              <Icon icon={linkIcon} size={14} />
              {__('Uses Trigger Block', 'aggressive-apparel')}{' '}
            </div>
          ) : (
            <div className='modal-editor-footer-item'>
              {__('Uses Trigger Label', 'aggressive-apparel')}
            </div>
          )}
        </div>
      </div>
    </>
  );
}
