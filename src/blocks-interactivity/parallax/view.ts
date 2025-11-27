/// <reference types="@wordpress/interactivity" />
import { getContext, getElement, store } from '@wordpress/interactivity';
import { EasingType, PARALLAX_CONFIG } from './config';
import { ElementSettings, ParallaxContext, ParallaxLayer } from './types';
import {
  calculateParallaxMovement,
  clamp,
  getCSSEasing,
  getElementStableId,
  prefersReducedMotion,
} from './utils';

// =============================================================================
// PARALLAX SYSTEM CLASS
// =============================================================================

/**
 * Manages the parallax effect for a block.
 */
class ParallaxSystem {
  private observer: IntersectionObserver | null = null;
  private context: ParallaxContext;
  private element: HTMLElement;
  private cachedElements: HTMLElement[] = [];
  private layers: Record<string, ParallaxLayer> = {};
  private isIntersecting: boolean = false;
  private animationFrameId: number | null = null;

  constructor(context: ParallaxContext, element: HTMLElement) {
    this.context = context;
    this.element = element;
    // If Intersection Observer is disabled, assume it's always intersecting for testing/direct activation
    if (!this.context.enableIntersectionObserver) {
      this.isIntersecting = true;
    }
    this.setupObserver();
  }

  private setupObserver(): void {
    if (!this.context.enableIntersectionObserver) {
      this.startParallaxEffect(); // Start directly if observer is off
      return;
    }

    this.observer = new IntersectionObserver(
      entries => {
        entries.forEach(entry => {
          this.handleIntersection(entry);
        });
      },
      {
        threshold: this.context.intersectionThreshold,
        rootMargin: '50px',
      }
    );

    // Observe the parallax container
    this.observer.observe(this.element);
  }

  private handleIntersection(entry: IntersectionObserverEntry): void {
    const wasIntersecting = this.isIntersecting;
    this.isIntersecting = entry.isIntersecting;
    this.context.isIntersecting = entry.isIntersecting; // Update shared context

    // Trigger parallax effect when entering viewport
    if (this.isIntersecting && !wasIntersecting) {
      this.startParallaxEffect();
    } else if (!this.isIntersecting && wasIntersecting) {
      this.pauseParallaxEffect();
    }
  }

  private startParallaxEffect(): void {
    this.initializeLayers();
    this.startScrollListener();
    if (this.context.enableMouseInteraction) {
      this.startMouseListener();
    }
  }

  private pauseParallaxEffect(): void {
    this.stopScrollListener();
    this.stopMouseListener();
    if (this.animationFrameId) {
      cancelAnimationFrame(this.animationFrameId);
      this.animationFrameId = null;
    }

    // Reset container rotation
    const contentContainer = this.element.querySelector(
      '.parallax-content'
    ) as HTMLElement;
    if (contentContainer) {
      contentContainer.style.transform = '';
    }

    // Reset individual element transforms
    Object.values(this.context.layers || {}).forEach((layer: any) => {
      if (layer.element) {
        layer.element.style.setProperty('--parallax-translate-x', '0px');
        layer.element.style.setProperty('--parallax-translate-y', '0px');
        layer.element.style.setProperty('--parallax-translate-z', '0px');
        layer.element.style.setProperty('--parallax-transition-duration', '0s'); // Reset transition
        layer.element.style.setProperty('--parallax-transition-delay', '0s');
      }
    });
  }

  private initializeLayers(): void {
    // Initialize elements and layer state
    this.initializeElements();
  }

  private initializeElements(): void {
    // Find the parallax content container
    const contentContainer = this.element.querySelector(
      '.parallax-content'
    ) as HTMLElement;
    if (!contentContainer) {
      return;
    }

    this.cachedElements = []; // Clear previous cache
    this.context.layers = {}; // Clear previous layers

    // Collect all elements with data-parallax-enabled="true" within the parallax content
    const elements = contentContainer.querySelectorAll(
      PARALLAX_CONFIG.ELEMENT_SELECTORS[0]
    );
    elements.forEach(element => {
      this.cachedElements.push(element as HTMLElement);
    });

    // Sort by document position (top to bottom) - only once
    this.cachedElements.sort((a, b) => {
      const rectA = a.getBoundingClientRect();
      const rectB = b.getBoundingClientRect();
      return rectA.top - rectB.top;
    });

    // Initialize layer data - only for blocks with parallax enabled
    this.cachedElements.forEach((blockElement, index) => {
      const layerId = getElementStableId(blockElement, index);

      // Assign a unique data-layer-id to the element for consistent targeting
      blockElement.setAttribute('data-layer-id', layerId);

      // Read settings directly from data attributes
      const enabled =
        blockElement.getAttribute('data-parallax-enabled') === 'true';

      if (enabled) {
        const speed = parseFloat(
          blockElement.getAttribute('data-parallax-speed') || '1.0'
        );
        const direction = (blockElement.getAttribute(
          'data-parallax-direction'
        ) || 'down') as ElementSettings['direction'];
        const delay = parseInt(
          blockElement.getAttribute('data-parallax-delay') || '0',
          10
        );
        const easing = (blockElement.getAttribute('data-parallax-easing') ||
          'linear') as EasingType;

        this.context.layers[layerId] = {
          element: blockElement,
          initialY: 0, // This might be useful for more complex effects
          speed,
          direction,
          delay,
          easing,
          isActive: true, // Assume active if enabled and in view
        };
      }
    });
  }

  private startScrollListener(): void {
    // Basic scroll listener groundwork
    const handleScroll = () => {
      if (!this.isIntersecting) return; // Use internal state

      // Calculate scroll progress within the element
      const rect = this.element.getBoundingClientRect();
      const elementTop = rect.top;
      const elementHeight = rect.height;
      const viewportHeight = window.innerHeight;

      // For parallax, we want progress based on how far we've scrolled through the element
      // When element just enters viewport (bottom): progress = 0
      // When element just exits viewport (top): progress = 1

      const startPoint = viewportHeight; // Element enters at viewport bottom
      const endPoint = -elementHeight; // Element exits at viewport top

      const currentPosition = elementTop;
      const totalDistance = startPoint - endPoint;

      // Progress from 0 (just entering) to 1 (just exiting)
      const rawProgress = (startPoint - currentPosition) / totalDistance;
      const scrollProgress = clamp(rawProgress, 0, 1);

      // Update shared state for Interactivity API callbacks
      this.context.scrollProgress = scrollProgress;

      // Update transforms immediately
      this.updateLayerTransforms();
    };

    // Throttled scroll listener
    let ticking = false;
    const throttledScroll = () => {
      if (!ticking) {
        requestAnimationFrame(() => {
          handleScroll();
          ticking = false;
        });
        ticking = true;
      }
    };

    window.addEventListener('scroll', throttledScroll, { passive: true });

    // Store cleanup function
    (this.element as any)._parallaxScrollCleanup = () => {
      window.removeEventListener('scroll', throttledScroll);
    };
  }

  private stopScrollListener(): void {
    const cleanupScroll = (this.element as any)._parallaxScrollCleanup;
    if (cleanupScroll) {
      cleanupScroll();
    }
  }

  private startMouseListener(): void {
    const handleMouseMove = (event: MouseEvent) => {
      if (!this.isIntersecting) return;

      // Calculate mouse position relative to the viewport
      const mouseX = event.clientX / window.innerWidth; // 0-1 normalized
      const mouseY = event.clientY / window.innerHeight; // 0-1 normalized

      // Update context
      this.context.mouseX = mouseX;
      this.context.mouseY = mouseY;

      // Apply 3D rotation to the parallax content container
      const contentContainer = this.element.querySelector(
        '.parallax-content'
      ) as HTMLElement;
      if (contentContainer) {
        // Convert mouse position to rotation angles (subtle effect)
        const rotateY =
          (mouseX - 0.5) * PARALLAX_CONFIG.MAX_ROTATION_DEGREES * 2; // Horizontal rotation
        const rotateX =
          (mouseY - 0.5) * -PARALLAX_CONFIG.MAX_ROTATION_DEGREES * 2; // Vertical rotation (inverted)

        contentContainer.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
      }

      // Update individual layer transforms
      this.updateLayerTransforms();
    };

    // Throttled mouse listener
    let ticking = false;
    const throttledMouseMove = (event: MouseEvent) => {
      if (!ticking) {
        requestAnimationFrame(() => {
          handleMouseMove(event);
          ticking = false;
        });
        ticking = true;
      }
    };

    document.addEventListener('mousemove', throttledMouseMove, {
      passive: true,
    });

    // Store cleanup function
    (this.element as any)._parallaxMouseCleanup = () => {
      document.removeEventListener('mousemove', throttledMouseMove);
    };
  }

  private stopMouseListener(): void {
    const cleanupMouse = (this.element as any)._parallaxMouseCleanup;
    if (cleanupMouse) {
      cleanupMouse();
    }
  }

  /**
   * Force initialization immediately (used when Intersection Observer is disabled)
   */
  public initializeImmediately(): void {
    this.startParallaxEffect();
  }

  /**
   * Update layer transforms directly (called from Interactivity API)
   */
  public updateLayerTransforms(): void {
    // Apply parallax to each active layer (blocks with parallax enabled)
    Object.entries(this.context.layers || {}).forEach(
      ([, layerData]: [string, any]) => {
        if (!layerData || !layerData.element || !this.isIntersecting) {
          // Use internal state
          return;
        }

        const blockElement = layerData.element as HTMLElement;
        const scrollProgress = this.context.scrollProgress || 0;

        const speed = layerData.speed;
        const direction = layerData.direction;
        const delay = layerData.delay || 0;
        const easing = layerData.easing || 'linear';

        // Calculate parallax movement using utility function
        const movement = calculateParallaxMovement(
          scrollProgress,
          this.context.intensity,
          speed,
          direction,
          this.context.mouseX,
          this.context.mouseY,
          this.context.enableMouseInteraction
        );

        // Apply 3D transform via CSS variables
        const movementXValue = `${movement.x.toFixed(2)}px`;
        const movementYValue = `${movement.y.toFixed(2)}px`;

        // Calculate Z-depth based on speed (higher speed = closer/deeper)
        const depthZ = (speed - 1) * PARALLAX_CONFIG.DEPTH_MULTIPLIER; // Configurable depth range
        const movementZValue = `${depthZ.toFixed(2)}px`;

        blockElement.style.setProperty(
          '--parallax-translate-x',
          movementXValue
        );
        blockElement.style.setProperty(
          '--parallax-translate-y',
          movementYValue
        );
        blockElement.style.setProperty(
          '--parallax-translate-z',
          movementZValue
        );
        blockElement.style.setProperty(
          '--parallax-transition-duration',
          `${PARALLAX_CONFIG.TRANSITION_DURATION}s`
        );
        blockElement.style.setProperty(
          '--parallax-transition-delay',
          `${delay}ms`
        );
        blockElement.style.setProperty(
          '--parallax-easing',
          getCSSEasing(easing)
        );
      }
    );
  }

  public destroy(): void {
    if (this.observer) {
      this.observer.disconnect();
      this.observer = null;
    }

    // Cleanup scroll listener
    const cleanupScroll = (this.element as any)._parallaxScrollCleanup;
    if (cleanupScroll) {
      cleanupScroll();
    }

    // Cleanup mouse listener
    const cleanupMouse = (this.element as any)._parallaxMouseCleanup;
    if (cleanupMouse) {
      cleanupMouse();
    }

    // Reset container rotation
    const contentContainer = this.element.querySelector(
      '.parallax-content'
    ) as HTMLElement;
    if (contentContainer) {
      contentContainer.style.transform = '';
    }

    // Reset transforms and clean up cached elements
    this.cachedElements.forEach(element => {
      element.style.setProperty('--parallax-translate-x', '');
      element.style.setProperty('--parallax-translate-y', '');
      element.style.setProperty('--parallax-translate-z', '');
      element.style.setProperty('--parallax-transition-duration', '');
      element.style.setProperty('--parallax-transition-delay', '');
      element.style.setProperty('--parallax-easing', '');
      element.removeAttribute('data-layer-id');
    });

    this.cachedElements = [];
    this.context.layers = {};
  }
}

// =============================================================================
// INTERACTIVITY STORE
// =============================================================================

const { state } = store('aggressive-apparel/parallax', {
  state: {
    // Runtime state managed per instance
    isIntersecting: false,
    scrollProgress: 0,
    mouseX: 0,
    mouseY: 0,
    hasInitialized: false,
    layers: {} as Record<string, any>,
    elementRef: null as HTMLElement | null,
  },

  actions: {
    // Actions can be added here for complex operations
  },

  callbacks: {
    /**
     * Initialize parallax system.
     */
    initParallax: () => {
      const ctx = getContext<ParallaxContext>();
      const { ref } = getElement();

      if (!ref) {
        return;
      }

      // Check for reduced motion preference
      if (prefersReducedMotion()) {
        return;
      }

      // Update state with context and element
      state.elementRef = ref;
      state.isIntersecting = ctx.isIntersecting; // Sync initial state

      // Initialize parallax system
      const parallaxSystem = new ParallaxSystem(ctx, ref);

      // Store parallax system reference for cleanup
      (ref as any)._parallaxSystem = parallaxSystem;

      // Force initialize layers immediately for testing if IO is disabled
      if (!ctx.enableIntersectionObserver) {
        parallaxSystem.initializeImmediately();
      }

      // Mark as initialized
      ctx.hasInitialized = true;
      state.hasInitialized = true;

      // Return cleanup function
      return () => {
        parallaxSystem.destroy();
      };
    },

    /**
     * Watch for intersection state changes.
     */
    watchIntersection: () => {
      const ctx = getContext<ParallaxContext>();
      const { ref } = getElement();

      if (!ref) return;

      // Update intersection state using basic bounding rect check
      const rect = ref.getBoundingClientRect();

      ctx.isIntersecting = rect.top < window.innerHeight && rect.bottom > 0;
      state.isIntersecting = ctx.isIntersecting;
    },

    /**
     * Get debug information.
     */
    getDebugInfo: () => {
      const ctx = getContext<ParallaxContext>();

      if (!ctx?.debugMode) {
        return '';
      }

      return `
                <div style="
                  position: fixed;
                  top: 20px;
                  right: 20px;
                  background: rgba(255,0,0,0.9);
                  color: white;
                  padding: 15px;
                  font-size: 14px;
                  font-family: monospace;
                  border-radius: 8px;
                  z-index: 999999;
                  border: 3px solid yellow;
                  box-shadow: 0 0 20px rgba(255,0,0,0.5);
                  max-width: 300px;
                  pointer-events: auto;
                ">
                  <div style="font-weight: bold; margin-bottom: 8px;">🚀 PARALLAX DEBUG</div>
                  <div>Intersecting: ${ctx.isIntersecting ? '✅ Yes' : '❌ No'}</div>
                  <div>Scroll Progress: ${(ctx.scrollProgress * 100).toFixed(1)}%</div>
                  <div>Layers: ${Object.keys(ctx.layers || {}).length}</div>
                  <div>Initialized: ${ctx.hasInitialized ? '✅ Yes' : '❌ No'}</div>
                  <div>Direction: ${ctx.parallaxDirection}</div>
                  <div>Intensity: ${ctx.intensity}</div>
                  <div style="margin-top: 8px; font-size: 12px; color: yellow;">First element should move!</div>
                </div>
            `;
    },
  },
});
