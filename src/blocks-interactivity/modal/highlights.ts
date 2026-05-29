/**
 * WordPress dependencies
 */
import { store as blockEditorStore } from '@wordpress/block-editor';
import { select } from '@wordpress/data';

/**
 * Highlight and visual feedback functionality for modal trigger blocks
 */

import { Debug } from './utils/debug';

interface HighlightInfo {
  targetElement: HTMLElement;
  styleTag: HTMLStyleElement;
  originalStyles: Record<string, string>;
}

interface HighlightResult {
  highlighted: boolean;
  element: Element | null;
}

interface TrackedListener {
  element: EventTarget;
  eventType: string;
  callback: EventListener;
}

interface HighlightData {
  highlights: HTMLElement[];
  tooltips: HTMLElement[];
  pulseElements: HTMLElement[];
  timers: ReturnType<typeof setTimeout>[];
  eventListeners: TrackedListener[];
  resizeObserver: ResizeObserver | null;
}

// Track current highlights for cleanup.
// eslint-disable-next-line @typescript-eslint/no-unused-vars
let currentHighlight: HighlightInfo | null = null;
let highlightTimer: ReturnType<typeof setInterval> | null = null;

// Track which elements have been highlighted so we can clean them up precisely.
const highlightedElements = new Set<Element>();

// Store global references to cleanup timers and listeners.
const highlightElements = new Map<string, HTMLElement>();
const styleElements = new Map<string, HTMLStyleElement>();
const animationTimers = new Map<string, ReturnType<typeof setInterval>>();
const eventListeners = new Map<string, EventListener>();

// Initialize arrays for tracking elements.
const highlightData: HighlightData = {
  highlights: [],
  tooltips: [],
  pulseElements: [],
  timers: [],
  eventListeners: [],
  resizeObserver: null,
};

/**
 * Find a block's DOM element by clientId
 *
 * @param clientId - ClientId to find DOM element for
 * @return DOM element or null if not found
 */
export const findBlockDomElement = (clientId: string): Element | null => {
  if (!clientId) {
    Debug.add('findBlockDomElement: No clientId provided', true);
    return null;
  }

  // Try direct approach first - this will catch most cases.
  let blockElement: Element | null = document.querySelector(
    `[data-block="${clientId}"]`
  );

  // Verify element is in editor content area if found.
  if (blockElement) {
    const isInEditor =
      blockElement.closest('.editor-styles-wrapper') ||
      blockElement.closest('.edit-site-visual-editor') ||
      blockElement.closest('.editor-canvas') ||
      blockElement.closest('.edit-post-visual-editor');

    if (isInEditor) {
      return blockElement;
    }
  }

  // If not found directly or not in editor, try template parts and iframes.
  Debug.add(`Looking for block ${clientId} in iframes (template parts)`);

  // Collect all possible editor iframes.
  const editorIframes: HTMLIFrameElement[] = [
    // Site editor canvas.
    ...Array.from(
      document.querySelectorAll<HTMLIFrameElement>(
        'iframe[name="editor-canvas"]'
      )
    ),
    // Template part editor frames and template editor frames.
    ...Array.from(
      document.querySelectorAll<HTMLIFrameElement>(
        '.edit-site-visual-editor iframe'
      )
    ),
    ...Array.from(
      document.querySelectorAll<HTMLIFrameElement>('.edit-site-canvas iframe')
    ),
    // Block editor iframes.
    ...Array.from(
      document.querySelectorAll<HTMLIFrameElement>('iframe.components-sandbox')
    ),
  ];

  // Try to find the block in each iframe.
  for (const iframe of editorIframes) {
    try {
      // Skip iframes without contentDocument access.
      if (!iframe.contentDocument) {
        continue;
      }

      // Try finding in this iframe.
      const iframeDoc = iframe.contentDocument;
      blockElement = iframeDoc.querySelector(`[data-block="${clientId}"]`);

      // If found in this iframe, return it.
      if (blockElement) {
        Debug.add(
          `Found block ${clientId} in iframe: ${iframe.name || 'unnamed'}`
        );
        return blockElement;
      }

      // Try alternative selectors as fallbacks.
      const alternativeSelectors = [
        `[id="${clientId}"]`,
        `[data-id="${clientId}"]`,
        `[data-block-id="${clientId}"]`,
        `[id*="${clientId}"]`,
        `[class*="${clientId}"]`,
      ];

      for (const selector of alternativeSelectors) {
        blockElement = iframeDoc.querySelector(selector);
        if (blockElement) {
          Debug.add(
            `Found block ${clientId} using alternative selector: ${selector}`
          );
          return blockElement;
        }
      }
    } catch (error) {
      Debug.add(`Error accessing iframe: ${(error as Error).message}`, true);
    }
  }

  // Final fallback - look for link or button with the clientId in a custom attribute.
  Debug.add(`Fallback: looking for link or button with ${clientId}`);

  const allElements = document.querySelectorAll(
    `[data-wp-block-linkage="${clientId}"], [data-block-linkage="${clientId}"]`
  );

  if (allElements.length > 0) {
    Debug.add(`Found block using linkage attribute: ${clientId}`);
    return allElements[0];
  }

  // Try accessing the WordPress data store to get block info.
  try {
    const blockEditor = select(blockEditorStore);
    if (blockEditor) {
      const blockInfo = blockEditor.getBlock(clientId);
      if (blockInfo) {
        Debug.add(
          `Block exists in store but can't find DOM element: ${clientId}`
        );
        Debug.add(
          `Block type: ${blockInfo.name}, is valid: ${blockEditor.isBlockValid(clientId)}`
        );
      }
    }
  } catch (error) {
    Debug.add(`Error accessing block store: ${(error as Error).message}`, true);
  }

  Debug.add(`Could not find DOM element for block: ${clientId}`, true);
  return null;
};

/**
 * Utility function to remove all highlight styles and elements
 *
 * @param modalId - Optional modalId to target specific cleanup
 */
export const cleanupAllHighlights = (modalId: string | null = null): void => {
  // Clear any timers.
  if (highlightTimer) {
    clearInterval(highlightTimer);
    highlightTimer = null;
  }

  // Clear all animation timers.
  animationTimers.forEach(timer => clearInterval(timer));
  animationTimers.clear();

  // Remove all event listeners (module-level map).
  eventListeners.forEach(listener => {
    document.removeEventListener('keydown', listener);
  });
  eventListeners.clear();

  // Remove event listeners tracked in highlightData.
  highlightData.eventListeners.forEach(({ element, eventType, callback }) => {
    element.removeEventListener(eventType, callback);
  });
  highlightData.eventListeners = [];

  // Clear timers tracked in highlightData.
  highlightData.timers.forEach(timer => clearTimeout(timer));
  highlightData.timers = [];

  // Disconnect resize observer.
  if (highlightData.resizeObserver) {
    highlightData.resizeObserver.disconnect();
    highlightData.resizeObserver = null;
  }

  // Remove appended DOM elements (highlights, tooltips, pulse rings).
  [
    ...highlightData.highlights,
    ...highlightData.tooltips,
    ...highlightData.pulseElements,
  ].forEach(el => el?.parentNode?.removeChild(el));
  highlightData.highlights = [];
  highlightData.tooltips = [];
  highlightData.pulseElements = [];

  // Step 1: Define all selectors we might need to clean up.
  const highlightClassSelectors = [
    '.modal-highlight-target',
    '.modal-trigger-highlight',
    '.modal-trigger-highlight-discreet',
    '.no-layout-shift',
    '.modal-direct-highlight',
    '.modal-highlight-arrow',
    '.modal-highlight-label',
  ];

  // Step 2: Find all elements with highlight styles applied via DOM classes or inline styles.
  // This is our first pass for cleanup.
  const findAndCleanElements = (
    rootElement: Document | HTMLElement = document
  ): void => {
    // Create a combined selector for all highlight elements.
    const allHighlightSelector = highlightClassSelectors.join(',');

    // Find elements with highlight classes.
    rootElement
      .querySelectorAll<HTMLElement>(allHighlightSelector)
      .forEach(element => {
        Debug.add(`Cleaning up highlight element: ${element.tagName}`);

        // Remove highlight classes.
        highlightClassSelectors.forEach(selector => {
          // Remove the . from the selector.
          const className = selector.substring(1);
          element.classList.remove(className);
        });

        // Reset all highlight-related styles.
        element.style.outline = '';
        element.style.outlineOffset = '';
        element.style.boxShadow = '';
        element.style.animation = '';
        element.style.zIndex = '';
        element.style.border = '';
        element.style.position = '';
        element.style.background = '';

        // Add to our tracked set for future reference.
        highlightedElements.add(element);
      });

    // For modal-trigger classes, handle specifically if a modalId is provided.
    // Removing modal-trigger classes should happen at the attribute level in edit.tsx.
    // This is just a safety cleanup for the DOM.
    if (modalId) {
      rootElement
        .querySelectorAll<HTMLElement>(`[class*="modal-trigger-${modalId}"]`)
        .forEach(element => {
          if (
            element.className &&
            element.className.includes(`modal-trigger-${modalId}`)
          ) {
            const newClasses = element.className
              .split(' ')
              .filter(cls => cls !== `modal-trigger-${modalId}`)
              .join(' ');
            element.className = newClasses;

            Debug.add(
              `Removed specific modal-trigger-${modalId} class from element`
            );
          }
        });
    }
  };

  // Clean elements in main document.
  findAndCleanElements(document);

  // Also clean elements in editor iframes.
  try {
    // Check in site editor iframe.
    const siteEditorIframe = document.querySelector<HTMLIFrameElement>(
      'iframe[name="editor-canvas"]'
    );
    if (siteEditorIframe?.contentDocument) {
      findAndCleanElements(siteEditorIframe.contentDocument);
    }

    // Check other editor iframes too.
    document
      .querySelectorAll<HTMLIFrameElement>(
        '.edit-site-visual-editor iframe, .edit-site-canvas iframe'
      )
      .forEach(iframe => {
        if (iframe?.contentDocument) {
          findAndCleanElements(iframe.contentDocument);
        }
      });
  } catch (error) {
    Debug.add(
      `Error cleaning up highlights in iframe: ${(error as Error).message}`,
      true
    );
  }

  // Step 3: Clean up specific elements we've tracked in our maps.
  highlightElements.forEach(element => {
    if (element && document.contains(element)) {
      // Reset all highlight-related styles.
      element.style.outline = '';
      element.style.outlineOffset = '';
      element.style.boxShadow = '';
      element.style.animation = '';
      element.style.zIndex = '';
      element.style.border = '';
      element.style.position = '';
      element.style.background = '';

      // Remove all highlight classes.
      highlightClassSelectors.forEach(selector => {
        // Remove the . from the selector.
        const className = selector.substring(1);
        element.classList.remove(className);
      });
    }
  });

  // Clear our tracking maps.
  highlightElements.clear();
  styleElements.clear();

  // Remove any style tags we've added.
  document
    .querySelectorAll('style[id^="modal-direct-highlight-style-"]')
    .forEach(styleTag => {
      styleTag.parentNode?.removeChild(styleTag);
    });

  // Remove any debug elements that might have been added.
  document.querySelectorAll('.modal-highlight-debug').forEach(el => {
    el.parentNode?.removeChild(el);
  });

  // Final pass: Look for elements that still have highlight styles by computed style.
  // This is a more aggressive approach to ensure nothing is missed.
  try {
    const allElements = document.querySelectorAll<HTMLElement>('*');
    for (const element of allElements) {
      const computedStyle = window.getComputedStyle(element);
      // Check if this element has blue outline or box-shadow that might be from our highlights.
      if (
        computedStyle.outline?.includes('rgb(0, 124, 186)') || // Blue outline.
        computedStyle.boxShadow?.includes('rgb(0, 124, 186)') // Blue shadow.
      ) {
        Debug.add(`Found element with highlight styles via computed style`);
        element.style.outline = '';
        element.style.boxShadow = '';
        element.style.animation = '';
      }
    }
  } catch (error) {
    Debug.add(`Error in final cleanup pass: ${(error as Error).message}`, true);
  }

  // Reset the current highlight.
  currentHighlight = null;
};

// For convenience, create an alias for cleanupAllHighlights.
export const removeHighlight = cleanupAllHighlights;

/**
 * Global function to add animation to a trigger element
 * This can be called from anywhere in the codebase
 *
 * @param triggerElement         - The DOM element to highlight or null to find by ID
 * @param modalId                - The modal ID
 * @param selectedTriggerBlockId - The client ID of the trigger block
 * @param _options               - Optional settings for the highlight (reserved)
 * @return Information about whether the highlight was applied
 */
export const highlightModalTrigger = (
  triggerElement: HTMLElement | null,
  modalId: string,
  selectedTriggerBlockId: string,
  _options: { discreet?: boolean } = {}
): HighlightResult => {
  // Remove any existing highlight first.
  cleanupAllHighlights(modalId);

  Debug.add(`Attempting to highlight trigger for modal ${modalId}`);
  Debug.add(`Trigger block ID: ${selectedTriggerBlockId}`);

  // If no trigger element provided, try to find it by ID.
  if (!triggerElement && selectedTriggerBlockId) {
    Debug.add(
      `No trigger element provided, searching for ${selectedTriggerBlockId}`
    );

    // Search for the element in the editor canvas.
    const blockElement = findBlockDomElement(selectedTriggerBlockId);

    if (blockElement) {
      Debug.add('Found block element in editor canvas');

      // Make sure the element has the correct modal-trigger class.
      // This check helps ensure the DOM reflects the block attributes.
      if (!blockElement.className.includes(`modal-trigger-${modalId}`)) {
        Debug.add(`Adding missing modal-trigger-${modalId} class to element`);
        blockElement.classList.add(`modal-trigger-${modalId}`);
      }

      // First try to find a specific trigger inside the block (button, link, etc.).
      let targetElement: HTMLElement;

      // Look for a button element or link inside the block.
      const buttonOrLink = blockElement.querySelector<HTMLElement>(
        'a, button, .wp-block-button__link, [role="button"]'
      );
      if (buttonOrLink) {
        Debug.add('Found button or link inside the block');
        targetElement = buttonOrLink;
      } else {
        // If no specific trigger element found, use the block itself.
        Debug.add('Using block element itself as target');
        targetElement = blockElement as HTMLElement;
      }

      // Make sure to track the block element itself too for complete cleanup.
      highlightedElements.add(blockElement);

      // Create standard highlight with the selected target.
      const highlightInfo = createDirectHighlight(targetElement, modalId);

      if (highlightInfo) {
        // Store for cleanup.
        currentHighlight = highlightInfo;

        // Track which elements were highlighted in this session.
        if (targetElement) {
          highlightedElements.add(targetElement);
        }

        return {
          highlighted: true,
          element: targetElement,
        };
      }
    } else {
      Debug.add(
        `Could not find block element for ID: ${selectedTriggerBlockId}`
      );
    }
  } else if (triggerElement) {
    // Use the provided element directly.
    Debug.add('Using provided trigger element');

    // Create direct highlight with the provided element.
    const highlightInfo = createDirectHighlight(triggerElement, modalId);

    if (highlightInfo) {
      // Store for cleanup.
      currentHighlight = highlightInfo;

      // Track the element.
      highlightedElements.add(triggerElement);

      return {
        highlighted: true,
        element: triggerElement,
      };
    }
  }

  Debug.add('Could not highlight modal trigger');
  return {
    highlighted: false,
    element: null,
  };
};

/**
 * Finds a trigger element within a block
 *
 * @param blockElement - The block element to search within
 * @param modalId      - The modal ID to find triggers for
 * @return The trigger element or null if not found
 */
export const findTriggerElement = (
  blockElement: Element | null,
  modalId: string
): Element | null => {
  if (!blockElement) {
    return null;
  }

  // Look for elements with the modal-trigger-{modalId} class.
  const triggerClass = `modal-trigger-${modalId}`;
  const directElement = blockElement.querySelector(`.${triggerClass}`);

  if (directElement) {
    return directElement;
  }

  // Check if the block element itself has the class.
  if (blockElement.classList.contains(triggerClass)) {
    return blockElement;
  }

  // Special case for buttons, links, and other possible triggers.
  const potentialTriggers = [
    ...blockElement.querySelectorAll(
      'a, button, .wp-block-button__link, [role="button"]'
    ),
  ];

  if (potentialTriggers.length === 1) {
    return potentialTriggers[0];
  }

  return null;
};

/**
 * Highlights a trigger block for a modal
 *
 * @param triggerBlockId - The client ID of the trigger block
 * @param modalId        - The ID of the modal being triggered
 * @param showPulse      - Whether to add a pulse animation
 * @param tooltipText    - Text to show in the tooltip
 */
export const highlightTriggerBlock = (
  triggerBlockId: string,
  modalId: string,
  showPulse = true,
  tooltipText = ''
): void => {
  // Clean up any existing highlights first.
  cleanupAllHighlights();

  if (!triggerBlockId || !modalId) {
    return;
  }

  // Find the block element.
  const blockElement = findBlockDomElement(triggerBlockId);
  if (!blockElement) {
    Debug.add(`Could not find block element with ID: ${triggerBlockId}`, true);
    return;
  }

  // Find the trigger element within the block.
  let triggerElement = findTriggerElement(blockElement, modalId);

  // If no specific trigger found, use the block element itself.
  if (!triggerElement) {
    triggerElement = blockElement;
  }

  // Get position of the trigger.
  const rect = triggerElement.getBoundingClientRect();
  const scrollX = window.scrollX || document.documentElement.scrollLeft;
  const scrollY = window.scrollY || document.documentElement.scrollTop;

  // Create highlight element.
  const highlight = document.createElement('div');
  highlight.className = 'modal-trigger-highlight';
  highlight.style.width = `${rect.width}px`;
  highlight.style.height = `${rect.height}px`;
  highlight.style.top = `${rect.top + scrollY}px`;
  highlight.style.left = `${rect.left + scrollX}px`;
  highlight.style.borderColor = '#007cba';
  highlight.style.backgroundColor = 'rgba(0, 124, 186, 0.1)';
  document.body.appendChild(highlight);
  highlightData.highlights.push(highlight);

  // Add tooltip if text is provided.
  if (tooltipText) {
    const tooltip = document.createElement('div');
    tooltip.className = 'modal-trigger-tooltip';
    tooltip.textContent = tooltipText;
    tooltip.style.left = `${rect.left + scrollX + rect.width / 2}px`;
    tooltip.style.top = `${rect.top + scrollY}px`;
    tooltip.style.backgroundColor = '#007cba';
    tooltip.style.color = '#ffffff';
    document.body.appendChild(tooltip);
    highlightData.tooltips.push(tooltip);
  }

  // Add pulse effect if requested.
  if (showPulse) {
    const pulse = document.createElement('div');
    pulse.className = 'modal-trigger-pulse';
    pulse.style.width = `${rect.width}px`;
    pulse.style.height = `${rect.height}px`;
    pulse.style.top = `${rect.top + scrollY}px`;
    pulse.style.left = `${rect.left + scrollX}px`;
    pulse.style.borderColor = '#007cba';
    pulse.style.boxShadow = '0 0 15px rgba(0, 124, 186, 0.7)';
    document.body.appendChild(pulse);
    highlightData.pulseElements.push(pulse);
  }

  // Set up resize observer to update position.
  highlightData.resizeObserver = new ResizeObserver(() => {
    // Only update if elements still exist.
    if (triggerElement && document.body.contains(triggerElement)) {
      const newRect = triggerElement.getBoundingClientRect();
      const newScrollX = window.scrollX || document.documentElement.scrollLeft;
      const newScrollY = window.scrollY || document.documentElement.scrollTop;

      // Update highlight position.
      highlightData.highlights.forEach(el => {
        el.style.width = `${newRect.width}px`;
        el.style.height = `${newRect.height}px`;
        el.style.top = `${newRect.top + newScrollY}px`;
        el.style.left = `${newRect.left + newScrollX}px`;
      });

      // Update pulse position.
      highlightData.pulseElements.forEach(el => {
        el.style.width = `${newRect.width}px`;
        el.style.height = `${newRect.height}px`;
        el.style.top = `${newRect.top + newScrollY}px`;
        el.style.left = `${newRect.left + newScrollX}px`;
      });

      // Update tooltip position.
      highlightData.tooltips.forEach(el => {
        el.style.left = `${newRect.left + newScrollX + newRect.width / 2}px`;
        el.style.top = `${newRect.top + newScrollY}px`;
      });
    }
  });

  highlightData.resizeObserver.observe(document.body);

  // Set up scroll listener to update position.
  const scrollListener = (): void => {
    if (triggerElement && document.body.contains(triggerElement)) {
      const newRect = triggerElement.getBoundingClientRect();
      const newScrollX = window.scrollX || document.documentElement.scrollLeft;
      const newScrollY = window.scrollY || document.documentElement.scrollTop;

      // Update highlight position.
      highlightData.highlights.forEach(el => {
        el.style.top = `${newRect.top + newScrollY}px`;
        el.style.left = `${newRect.left + newScrollX}px`;
      });

      // Update pulse position.
      highlightData.pulseElements.forEach(el => {
        el.style.top = `${newRect.top + newScrollY}px`;
        el.style.left = `${newRect.left + newScrollX}px`;
      });

      // Update tooltip position.
      highlightData.tooltips.forEach(el => {
        el.style.left = `${newRect.left + newScrollX + newRect.width / 2}px`;
        el.style.top = `${newRect.top + newScrollY}px`;
      });
    }
  };

  window.addEventListener('scroll', scrollListener, { passive: true });
  highlightData.eventListeners.push({
    element: window,
    eventType: 'scroll',
    callback: scrollListener,
  });

  // Auto cleanup after a delay.
  const timer = setTimeout(() => {
    cleanupAllHighlights();
  }, 10000); // 10 seconds.

  highlightData.timers.push(timer);
};

/**
 * Refreshes the highlight on the modal trigger
 *
 * @param triggerBlockId - Block ID containing the trigger
 * @param modalId        - ID of the modal being triggered
 */
export const refreshHighlight = (
  triggerBlockId: string,
  modalId: string
): void => {
  highlightTriggerBlock(triggerBlockId, modalId, true, 'Modal Trigger');
};

/**
 * Gets information about a block by client ID
 *
 * @param clientId - Block client ID
 * @return Block information
 */
export const getBlockInfo = (
  clientId: string
): {
  name: string;
  attributes: Record<string, unknown>;
  clientId: string;
} | null => {
  if (!clientId) {
    return null;
  }

  const blockEditor = select(blockEditorStore);
  if (!blockEditor) {
    return null;
  }

  const block = blockEditor.getBlock(clientId);
  if (!block) {
    return null;
  }

  return {
    name: block.name,
    attributes: block.attributes,
    clientId: block.clientId,
  };
};

/**
 * Creates a direct highlight on the element itself using CSS classes
 *
 * @param targetElement - Element to highlight
 * @param modalId       - Modal ID for reference
 * @return Information about the highlight for cleanup
 */
export const createDirectHighlight = (
  targetElement: HTMLElement,
  modalId: string
): HighlightInfo | null => {
  if (!targetElement) {
    Debug.add('Cannot create direct highlight - no target element', true);
    return null;
  }

  // Clean up any existing highlights first to avoid multiples.
  cleanupAllHighlights(modalId);

  // Remove any lingering highlight styles that might be cached.
  document
    .querySelectorAll('style[id^="modal-direct-highlight-style-"]')
    .forEach(el => el.remove());

  Debug.add('Creating direct highlight on element');

  try {
    // Track this element for precise cleanup later.
    highlightedElements.add(targetElement);

    // Store original styles to restore later.
    const originalStyles: Record<string, string> = {
      position: targetElement.style.position,
      zIndex: targetElement.style.zIndex,
      outline: targetElement.style.outline,
      outlineOffset: targetElement.style.outlineOffset,
      boxShadow: targetElement.style.boxShadow,
      animation: targetElement.style.animation,
      className: targetElement.className,
    };

    // Add specific classes for highlighting.
    targetElement.classList.add('modal-highlight-target', 'no-layout-shift');

    // Apply blue highlight styles directly with !important to override any existing styles.
    targetElement.style.setProperty(
      'outline',
      '3px solid #007cba',
      'important'
    );
    targetElement.style.setProperty('outline-offset', '3px', 'important');
    targetElement.style.setProperty(
      'box-shadow',
      '0 0 15px rgba(0, 124, 186, 0.7)',
      'important'
    );
    targetElement.style.setProperty('position', 'relative', 'important');
    targetElement.style.setProperty('z-index', '1000', 'important');

    // Create and add animation style with a unique ID based on timestamp.
    const styleTagId = `modal-direct-highlight-style-${modalId}-${Date.now()}`;
    const styleTag = document.createElement('style');
    styleTag.id = styleTagId;
    styleTag.innerHTML = `
			@keyframes modal-highlight-pulse-${modalId} {
				0% { outline-color: #007cba !important; box-shadow: 0 0 15px rgba(0, 124, 186, 0.7) !important; }
				50% { outline-color: #4ca8d8 !important; box-shadow: 0 0 25px rgba(0, 124, 186, 0.9) !important; }
				100% { outline-color: #007cba !important; box-shadow: 0 0 15px rgba(0, 124, 186, 0.7) !important; }
			}

			.modal-highlight-target {
				position: relative !important;
				z-index: 1000 !important;
				animation: modal-highlight-pulse-${modalId} 1.5s infinite !important;
				outline: 3px solid #007cba !important;
				outline-offset: 3px !important;
				box-shadow: 0 0 15px rgba(0, 124, 186, 0.7) !important;
			}

			.no-layout-shift {
				margin: 0 !important;
				padding: 0 !important;
			}
		`;

    document.head.appendChild(styleTag);
    styleElements.set(styleTagId, styleTag);

    // Apply the animation explicitly.
    targetElement.style.setProperty(
      'animation',
      `modal-highlight-pulse-${modalId} 1.5s infinite`,
      'important'
    );

    // Scroll the element into view.
    setTimeout(() => {
      // Only scroll if the element isn't already in view.
      const rect = targetElement.getBoundingClientRect();
      const isInView =
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <=
          (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <=
          (window.innerWidth || document.documentElement.clientWidth);

      if (!isInView) {
        targetElement.scrollIntoView({
          behavior: 'smooth',
          block: 'center',
        });
        Debug.add('Scrolled element into view');
      } else {
        Debug.add('Element already in view, no scrolling needed');
      }
    }, 50);

    Debug.add('Direct blue highlight applied successfully');

    // Return highlight info for cleanup and reference.
    return {
      targetElement,
      styleTag,
      originalStyles,
    };
  } catch (error) {
    Debug.add(
      `Error in createDirectHighlight: ${(error as Error).message}`,
      true
    );
    return null;
  }
};
