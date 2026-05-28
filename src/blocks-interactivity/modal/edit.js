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
  useBlockProps,
} from '@wordpress/block-editor';
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
import { cleanupAllHighlights, highlightModalTrigger } from './highlights.js';
import { useTriggerManagement } from './hooks/useTriggerManagement.js';
import { useUpdateBlockTriggerClass } from './hooks/useUpdateBlockTriggerClass.js';
import { copyTextFallback } from './utils/copyTextFallback.js';
import { Debug } from './utils/debug.js';
import {
  blockExists,
  isEditorReady,
  manageHighlight,
  safeUpdateTriggerClass,
} from './utils/editorHelpers.js';
import { generatePersistentId } from './utils/generatePersistentId.js';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @param {Object}   props               - Block properties
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to set block attributes
 * @param {string}   props.clientId      - Block client ID
 * @param {boolean}  props.isSelected    - Whether the block is selected
 * @return {Element} Element to render.
 */
export default function Edit({
  attributes,
  setAttributes,
  clientId,
  isSelected,
}) {
  const {
    position = 'center',
    openOnLoad = false,
    modalId = '',
    triggerBlockId = '',
    triggerBlockKey = '',
    triggerLabel = 'Open Modal',
    disableOverlay = false,
    enterAnimation = 'fade',
    animationDuration = 300,
    exitIntentTrigger = false,
    exitIntentReshowDays = 7,
  } = attributes;

  const updateBlockTriggerClass = useUpdateBlockTriggerClass();

  // Component state
  const [isHighlightActive, setIsHighlightActive] = useState(false);
  const previousHighlightedElements = useRef(new Set());
  const lastSelectedBlock = useRef(null);
  const triggerClassApplied = useRef(false);

  // Create safe values (never null)
  const safePosition = position || 'center';

  // Use the trigger management hook
  const { availableTriggers, safeTriggerBlockId, handleTriggerBlockChange } =
    useTriggerManagement({
      modalId,
      triggerBlockId,
      triggerBlockKey,
      setAttributes,
      updateBlockTriggerClass,
    });

  // Initialize modal ID once
  useEffect(() => {
    if (!modalId) {
      // Generate a new ID
      const newModalId = generatePersistentId();
      setAttributes({ modalId: newModalId });
      Debug.add(`Generated new modal ID: ${newModalId}`);
    } else {
      // If we already have a modal ID, log it
      Debug.add(`Using existing modal ID: ${modalId}`);

      // If we have a saved trigger block ID, ensure the class is applied to it
      // Only apply this on initial render, not on every change
      if (safeTriggerBlockId && !triggerClassApplied.current) {
        triggerClassApplied.current = true;
        // Make sure the trigger class is applied to the block
        updateBlockTriggerClass(safeTriggerBlockId, modalId, true);
        Debug.add(
          `Ensured class modal-trigger-${modalId} is applied to block ${safeTriggerBlockId}`
        );
      }
    }
  }, [modalId, setAttributes, safeTriggerBlockId, updateBlockTriggerClass]);

  // Handle highlighting when selection changes
  useEffect(() => {
    // Skip if editor isn't ready
    if (!isEditorReady()) {
      return;
    }

    // First make sure the trigger class is applied correctly
    safeUpdateTriggerClass(
      updateBlockTriggerClass,
      safeTriggerBlockId,
      modalId,
      true
    );

    // Apply or clean up highlights based on selection state
    manageHighlight({
      modalId,
      blockId: safeTriggerBlockId,
      isSelected,
      setIsHighlightActive,
      previousHighlightedElements: previousHighlightedElements.current,
    });

    // Clean up when unmounting
    return () => cleanupAllHighlights();
  }, [isSelected, safeTriggerBlockId, modalId, updateBlockTriggerClass]);

  // Add a global selection change listener to ensure highlights are cleaned up
  useEffect(() => {
    // Don't bother if we don't have a trigger block
    if (!safeTriggerBlockId) {
      return;
    }

    // Subscribe to selection changes in the block editor
    const { subscribe } = wp.data;
    if (!subscribe) {
      return;
    }

    const unsubscribe = subscribe(() => {
      // Skip if editor isn't ready
      if (!isEditorReady()) {
        return;
      }

      // First check if the trigger block still exists using our utility
      if (!blockExists(safeTriggerBlockId)) {
        return;
      }

      const blockEditor = wp.data.select('core/block-editor');
      const selectedBlockId = blockEditor?.getSelectedBlockClientId();

      // Skip if selection hasn't changed
      if (selectedBlockId === lastSelectedBlock.current) {
        return;
      }

      // Update our tracking ref
      lastSelectedBlock.current = selectedBlockId;

      // If the selected block exists and it's not our modal or a parent of our modal
      if (selectedBlockId && selectedBlockId !== clientId) {
        // Check if this block or any of its parents is our modal
        let isParentOfModal = false;
        const parentIds = blockEditor?.getBlockParents(clientId);

        if (parentIds && parentIds.includes(selectedBlockId)) {
          isParentOfModal = true;
        }

        // If it's not our modal or a parent of our modal, and we're showing a highlight,
        // clean up all highlights
        if (!isParentOfModal && isHighlightActive) {
          cleanupAllHighlights();
          setIsHighlightActive(false);
        }
      }
    });

    // Clean up subscription when component unmounts
    return () => {
      unsubscribe();
    };
  }, [safeTriggerBlockId, clientId, isHighlightActive, setAttributes]);

  /**
   * Refresh the trigger highlight manually
   */
  const handleRefreshHighlight = useCallback(() => {
    // Only refresh the highlight if the modal is selected
    if (isSelected && safeTriggerBlockId) {
      // Verify the trigger block still exists
      const blockEditor = wp.data.select('core/block-editor');
      const blockStillExists =
        blockEditor && blockEditor.getBlock(safeTriggerBlockId);

      if (!blockStillExists) {
        Debug.add(
          `Trigger block ${safeTriggerBlockId} no longer exists - cannot highlight`,
          true
        );
        // Clear the highlight state since the block no longer exists
        setIsHighlightActive(false);
        return;
      }

      // Cleanup existing highlights first
      cleanupAllHighlights();

      // Make sure the trigger class is still applied
      try {
        updateBlockTriggerClass(safeTriggerBlockId, modalId, true);
      } catch (error) {
        Debug.add(`Error refreshing trigger class: ${error.message}`, true);
        return;
      }

      // Use the direct highlighting function
      setTimeout(() => {
        try {
          highlightModalTrigger(null, modalId, safeTriggerBlockId, {
            discreet: true,
          });
          setIsHighlightActive(true);

          // Store any newly highlighted elements
          document.querySelectorAll('.modal-highlight-target').forEach(el => {
            previousHighlightedElements.current.add(el);
          });
        } catch (error) {
          Debug.add(`Error highlighting trigger: ${error.message}`, true);
          setIsHighlightActive(false);
        }
      }, 100);
    } else if (!isSelected) {
      // If the modal is not selected, inform the user
      Debug.add('Cannot refresh highlight when modal is not selected');
    } else if (!safeTriggerBlockId) {
      Debug.add('No trigger block is selected to highlight');
    }
  }, [safeTriggerBlockId, modalId, isSelected, updateBlockTriggerClass]);

  /**
   * Render the inspector controls
   *
   * @return {JSX.Element} Inspector controls
   */
  const renderInspectorControls = () => (
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
        <SelectControl
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

        {/* Open on load */}
        <ToggleControl
          label={__('Open on Page Load', 'aggressive-apparel')}
          checked={openOnLoad}
          onChange={value => setAttributes({ openOnLoad: value })}
          help={__(
            'When enabled, the modal will automatically open when the page loads',
            'aggressive-apparel'
          )}
          __nextHasNoMarginBottom
        />

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
              'Days before showing the exit intent modal again to the same visitor',
              'aggressive-apparel'
            )}
            __nextHasNoMarginBottom
          />
        )}

        {/* Trigger block select */}
        <SelectControl
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
                // Check if the Clipboard API is available
                if (
                  navigator &&
                  navigator.clipboard &&
                  navigator.clipboard.writeText
                ) {
                  navigator.clipboard.writeText(textToCopy).catch(() => {
                    // Fallback to textarea method if writeText fails
                    copyTextFallback(textToCopy);
                  });
                } else {
                  // Fallback method using a temporary textarea
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
        <SelectControl
          label={__('Enter Animation', 'aggressive-apparel')}
          value={enterAnimation}
          options={[
            { label: __('Fade', 'aggressive-apparel'), value: 'fade' },
            {
              label: __('Slide Down', 'aggressive-apparel'),
              value: 'slide-down',
            },
            { label: __('Slide Up', 'aggressive-apparel'), value: 'slide-up' },
            { label: __('Zoom In', 'aggressive-apparel'), value: 'zoom-in' },
            { label: __('None', 'aggressive-apparel'), value: 'none' },
          ]}
          onChange={value => setAttributes({ enterAnimation: value })}
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
    </InspectorControls>
  );

  // Block props
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
