/**
 * WordPress dependencies
 */
import { store as blockEditorStore } from '@wordpress/block-editor';
import { select } from '@wordpress/data';

/**
 * Highlight and visual feedback functionality for modal trigger blocks
 */

import { Debug } from './utils/debug';

/**
 * Selectors for editor canvas iframes (post editor + site editor).
 * Scripts run in the admin document; block DOM may live in these frames.
 */
const EDITOR_IFRAME_SELECTORS = [
  'iframe[name="editor-canvas"]',
  '.edit-site-visual-editor iframe',
  '.edit-site-canvas iframe',
  'iframe.components-sandbox',
] as const;

/**
 * Collect the admin document plus every reachable editor canvas document.
 *
 * @return Documents to search / clean for block DOM.
 */
const getEditorDocuments = (): Document[] => {
  const docs: Document[] = [document];
  const seen = new Set<Document>([document]);

  for (const selector of EDITOR_IFRAME_SELECTORS) {
    document.querySelectorAll<HTMLIFrameElement>(selector).forEach(iframe => {
      try {
        const iframeDoc = iframe.contentDocument;
        if (iframeDoc && !seen.has(iframeDoc)) {
          seen.add(iframeDoc);
          docs.push(iframeDoc);
        }
      } catch {
        // Inaccessible iframe (rare in same-origin editor canvases).
      }
    });
  }

  return docs;
};

/**
 * Query a selector across the admin document and all editor canvases.
 *
 * @param selector - CSS selector.
 * @return Matching elements from every reachable editor document.
 */
export const queryAllEditorDocuments = <T extends Element = Element>(
  selector: string
): T[] => {
  const results: T[] = [];

  for (const doc of getEditorDocuments()) {
    results.push(...Array.from(doc.querySelectorAll<T>(selector)));
  }

  return results;
};

/**
 * Whether a node is still attached to its owner document.
 *
 * @param node - Node to check.
 * @return True when the node is in its document tree.
 */
const ownerDocumentContains = (node: Node): boolean => {
  return node.ownerDocument?.contains(node) ?? false;
};

/**
 * Window for a node's owner document (canvas or admin).
 *
 * @param node - Node whose view is needed.
 * @return The document's defaultView, or null.
 */
const getOwnerView = (node: Node): Window | null => {
  return node.ownerDocument?.defaultView ?? null;
};

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

  const isInEditorCanvas = (element: Element): boolean => {
    return !!(
      element.closest('.editor-styles-wrapper') ||
      element.closest('.edit-site-visual-editor') ||
      element.closest('.editor-canvas') ||
      element.closest('.edit-post-visual-editor') ||
      // Iframed canvas body has no admin chrome classes — treat any hit
      // inside a non-admin document as in-canvas.
      element.ownerDocument !== document
    );
  };

  const alternativeSelectors = [
    `[data-block="${clientId}"]`,
    `[id="${clientId}"]`,
    `[data-id="${clientId}"]`,
    `[data-block-id="${clientId}"]`,
  ];

  // Search admin document + every editor canvas document.
  for (const doc of getEditorDocuments()) {
    for (const selector of alternativeSelectors) {
      const blockElement = doc.querySelector(selector);
      if (blockElement && isInEditorCanvas(blockElement)) {
        if (doc !== document) {
          Debug.add(`Found block ${clientId} in editor canvas document`);
        }
        return blockElement;
      }
    }
  }

  // Final fallback - custom linkage attributes across all documents.
  Debug.add(`Fallback: looking for link or button with ${clientId}`);
  const linkageMatches = queryAllEditorDocuments(
    `[data-wp-block-linkage="${clientId}"], [data-block-linkage="${clientId}"]`
  );

  if (linkageMatches.length > 0) {
    Debug.add(`Found block using linkage attribute: ${clientId}`);
    return linkageMatches[0];
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

  // Remove all event listeners (module-level map) from every editor document.
  eventListeners.forEach(listener => {
    for (const doc of getEditorDocuments()) {
      doc.removeEventListener('keydown', listener);
    }
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
  const findAndCleanElements = (rootElement: Document | HTMLElement): void => {
    const allHighlightSelector = highlightClassSelectors.join(',');

    rootElement
      .querySelectorAll<HTMLElement>(allHighlightSelector)
      .forEach(element => {
        Debug.add(`Cleaning up highlight element: ${element.tagName}`);

        highlightClassSelectors.forEach(selector => {
          const className = selector.substring(1);
          element.classList.remove(className);
        });

        element.style.outline = '';
        element.style.outlineOffset = '';
        element.style.boxShadow = '';
        element.style.animation = '';
        element.style.zIndex = '';
        element.style.border = '';
        element.style.position = '';
        element.style.background = '';

        highlightedElements.add(element);
      });

    // Safety cleanup for modal-trigger classes when a modalId is provided.
    if (modalId) {
      rootElement
        .querySelectorAll<HTMLElement>(`[class*="modal-trigger-${modalId}"]`)
        .forEach(element => {
          if (
            element.className &&
            element.className.includes(`modal-trigger-${modalId}`)
          ) {
            element.className = element.className
              .split(' ')
              .filter(cls => cls !== `modal-trigger-${modalId}`)
              .join(' ');

            Debug.add(
              `Removed specific modal-trigger-${modalId} class from element`
            );
          }
        });
    }
  };

  // Clean elements in the admin document and every editor canvas.
  try {
    for (const doc of getEditorDocuments()) {
      findAndCleanElements(doc);
    }
  } catch (error) {
    Debug.add(
      `Error cleaning up highlights in editor documents: ${(error as Error).message}`,
      true
    );
  }

  // Step 3: Clean up specific elements we've tracked in our maps.
  highlightElements.forEach(element => {
    if (element && ownerDocumentContains(element)) {
      element.style.outline = '';
      element.style.outlineOffset = '';
      element.style.boxShadow = '';
      element.style.animation = '';
      element.style.zIndex = '';
      element.style.border = '';
      element.style.position = '';
      element.style.background = '';

      highlightClassSelectors.forEach(selector => {
        const className = selector.substring(1);
        element.classList.remove(className);
      });
    }
  });

  // Clear our tracking maps.
  highlightElements.clear();
  styleElements.clear();

  // Remove style tags / debug nodes from every editor document.
  for (const doc of getEditorDocuments()) {
    doc
      .querySelectorAll('style[id^="modal-direct-highlight-style-"]')
      .forEach(styleTag => {
        styleTag.parentNode?.removeChild(styleTag);
      });

    doc.querySelectorAll('.modal-highlight-debug').forEach(el => {
      el.parentNode?.removeChild(el);
    });
  }

  // Final pass: clear residual blue outline/shadow via computed styles.
  try {
    for (const doc of getEditorDocuments()) {
      const view = doc.defaultView;
      if (!view) {
        continue;
      }

      doc.querySelectorAll<HTMLElement>('*').forEach(element => {
        const computedStyle = view.getComputedStyle(element);
        if (
          computedStyle.outline?.includes('rgb(0, 124, 186)') ||
          computedStyle.boxShadow?.includes('rgb(0, 124, 186)')
        ) {
          Debug.add(`Found element with highlight styles via computed style`);
          element.style.outline = '';
          element.style.boxShadow = '';
          element.style.animation = '';
        }
      });
    }
  } catch (error) {
    Debug.add(`Error in final cleanup pass: ${(error as Error).message}`, true);
  }
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

  // Overlays must live in the same document as the trigger (canvas iframe in 7.1).
  const ownerDoc = triggerElement.ownerDocument;
  const ownerView = getOwnerView(triggerElement);
  const ownerRoot = ownerDoc.documentElement;
  const ownerBody = ownerDoc.body;

  // Get position of the trigger within its document.
  const rect = triggerElement.getBoundingClientRect();
  const scrollX = ownerView?.scrollX ?? ownerRoot.scrollLeft;
  const scrollY = ownerView?.scrollY ?? ownerRoot.scrollTop;

  // Create highlight element.
  const highlight = ownerDoc.createElement('div');
  highlight.className = 'modal-trigger-highlight';
  highlight.style.width = `${rect.width}px`;
  highlight.style.height = `${rect.height}px`;
  highlight.style.top = `${rect.top + scrollY}px`;
  highlight.style.left = `${rect.left + scrollX}px`;
  highlight.style.borderColor = '#007cba';
  highlight.style.backgroundColor = 'rgba(0, 124, 186, 0.1)';
  ownerBody.appendChild(highlight);
  highlightData.highlights.push(highlight);

  // Add tooltip if text is provided.
  if (tooltipText) {
    const tooltip = ownerDoc.createElement('div');
    tooltip.className = 'modal-trigger-tooltip';
    tooltip.textContent = tooltipText;
    tooltip.style.left = `${rect.left + scrollX + rect.width / 2}px`;
    tooltip.style.top = `${rect.top + scrollY}px`;
    tooltip.style.backgroundColor = '#007cba';
    tooltip.style.color = '#ffffff';
    ownerBody.appendChild(tooltip);
    highlightData.tooltips.push(tooltip);
  }

  // Add pulse effect if requested.
  if (showPulse) {
    const pulse = ownerDoc.createElement('div');
    pulse.className = 'modal-trigger-pulse';
    pulse.style.width = `${rect.width}px`;
    pulse.style.height = `${rect.height}px`;
    pulse.style.top = `${rect.top + scrollY}px`;
    pulse.style.left = `${rect.left + scrollX}px`;
    pulse.style.borderColor = '#007cba';
    pulse.style.boxShadow = '0 0 15px rgba(0, 124, 186, 0.7)';
    ownerBody.appendChild(pulse);
    highlightData.pulseElements.push(pulse);
  }

  const updateOverlayPositions = (): void => {
    if (!triggerElement || !ownerDocumentContains(triggerElement)) {
      return;
    }

    const newRect = triggerElement.getBoundingClientRect();
    const newScrollX = ownerView?.scrollX ?? ownerRoot.scrollLeft;
    const newScrollY = ownerView?.scrollY ?? ownerRoot.scrollTop;

    highlightData.highlights.forEach(el => {
      el.style.width = `${newRect.width}px`;
      el.style.height = `${newRect.height}px`;
      el.style.top = `${newRect.top + newScrollY}px`;
      el.style.left = `${newRect.left + newScrollX}px`;
    });

    highlightData.pulseElements.forEach(el => {
      el.style.width = `${newRect.width}px`;
      el.style.height = `${newRect.height}px`;
      el.style.top = `${newRect.top + newScrollY}px`;
      el.style.left = `${newRect.left + newScrollX}px`;
    });

    highlightData.tooltips.forEach(el => {
      el.style.left = `${newRect.left + newScrollX + newRect.width / 2}px`;
      el.style.top = `${newRect.top + newScrollY}px`;
    });
  };

  // ResizeObserver is a browser global — prefer the canvas view's ctor when present.
  type WindowWithResizeObserver = Window & {
    ResizeObserver?: typeof ResizeObserver;
  };
  const ResizeObserverCtor =
    (ownerView as WindowWithResizeObserver | null)?.ResizeObserver ??
    (typeof ResizeObserver !== 'undefined' ? ResizeObserver : null);

  if (ResizeObserverCtor) {
    const resizeObserver = new ResizeObserverCtor(updateOverlayPositions);
    resizeObserver.observe(ownerBody);
    highlightData.resizeObserver = resizeObserver;
  }

  // Scroll listener on the canvas window so tablet/mobile preview resizes track correctly.
  if (ownerView) {
    ownerView.addEventListener('scroll', updateOverlayPositions, {
      passive: true,
    });
    highlightData.eventListeners.push({
      element: ownerView,
      eventType: 'scroll',
      callback: updateOverlayPositions,
    });
  }

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

  // Style tags must live in the same document as the target (canvas head when iframed).
  const ownerDoc = targetElement.ownerDocument;
  const ownerView = getOwnerView(targetElement);

  ownerDoc
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
    const styleTag = ownerDoc.createElement('style');
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

    ownerDoc.head.appendChild(styleTag);
    styleElements.set(styleTagId, styleTag);

    // Apply the animation explicitly.
    targetElement.style.setProperty(
      'animation',
      `modal-highlight-pulse-${modalId} 1.5s infinite`,
      'important'
    );

    // Scroll the element into view within the canvas viewport.
    setTimeout(() => {
      const rect = targetElement.getBoundingClientRect();
      const viewportHeight =
        ownerView?.innerHeight ?? ownerDoc.documentElement.clientHeight;
      const viewportWidth =
        ownerView?.innerWidth ?? ownerDoc.documentElement.clientWidth;
      const isInView =
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= viewportHeight &&
        rect.right <= viewportWidth;

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
