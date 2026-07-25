/**
 * Animate On Scroll Block Editor Component
 *
 * @package Aggressive_Apparel
 */

import {
  InnerBlocks,
  useBlockProps,
  store as blockEditorStore,
} from '@wordpress/block-editor';
import { BlockEditProps } from '@wordpress/blocks';
import { useSelect } from '@wordpress/data';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n } from '@wordpress/i18n';
import type { CSSProperties } from 'react';
import type { AnimateOnScrollAttributes, AnimationSequenceItem } from './types';
import { AosInspector } from './inspector';
import {
  createStaggerSeed,
  getChildStaggerDelay,
  type StaggerConfig,
} from './stagger-math';

type EditProps = BlockEditProps<AnimateOnScrollAttributes>;

type PreviewPhase = 'idle' | 'armed' | 'visible';

const LAYOUT_WRAPPER_BLOCKS = new Set([
  'core/group',
  'core/columns',
  'core/column',
  'core/row',
  'core/stack',
  'core/grid',
]);

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 */
export default function Edit({
  attributes,
  setAttributes,
  clientId,
}: EditProps) {
  const [previewPhase, setPreviewPhase] = useState<PreviewPhase>('idle');
  const previewTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const blockRef = useRef<HTMLDivElement | null>(null);

  // Direct children drive stagger/sequence; a single Group hides the cascade.
  const { childCount, hasSingleLayoutWrapper } = useSelect(
    select => {
      const block = select(blockEditorStore).getBlock(clientId);
      const inner = block?.innerBlocks ?? [];
      return {
        childCount: inner.length,
        hasSingleLayoutWrapper:
          inner.length === 1 && LAYOUT_WRAPPER_BLOCKS.has(inner[0].name),
      };
    },
    [clientId]
  );

  const showNestedChildWarning =
    hasSingleLayoutWrapper &&
    (attributes.staggerChildren || attributes.useSequence);

  const isPreviewing = previewPhase !== 'idle';
  const useSequencePreview =
    attributes.useSequence && (attributes.animationSequence?.length ?? 0) > 0;
  const previewAnimationClass =
    attributes.animation === 'blur' ? 'blur-in' : attributes.animation;

  const blockProps = useBlockProps({
    ref: blockRef,
    className: [
      isPreviewing ? 'is-aos-previewing' : '',
      isPreviewing && useSequencePreview ? 'has-animation-sequence' : '',
      isPreviewing && !useSequencePreview ? previewAnimationClass : '',
      isPreviewing && !useSequencePreview && attributes.direction
        ? attributes.direction
        : '',
      previewPhase === 'visible' ? 'is-visible' : '',
    ]
      .filter(Boolean)
      .join(' '),
    ...(isPreviewing
      ? {
          'data-animate-id': 'editor-preview',
          'data-stagger-children':
            attributes.staggerChildren || useSequencePreview ? 'true' : 'false',
        }
      : {}),
    style: {
      '--wp-block-animate-on-scroll-animation-duration': `${attributes.duration}s`,
      '--wp-block-animate-on-scroll-initial-delay': `${attributes.initialDelay}s`,
      '--wp-block-animate-on-scroll-stagger-delay': `${attributes.staggerDelay}s`,
      '--wp-block-animate-on-scroll-animation-timing': attributes.easing,
      '--wp-block-animate-on-scroll-slide-distance': `${attributes.slideDistance ?? 50}px`,
      '--wp-block-animate-on-scroll-zoom-in-start': `${attributes.zoomInStart ?? 0.5}`,
      '--wp-block-animate-on-scroll-zoom-out-start': `${attributes.zoomOutStart ?? 1.5}`,
      '--wp-block-animate-on-scroll-rotate-angle': `${attributes.rotationAngle ?? 90}deg`,
      '--wp-block-animate-on-scroll-blur-amount': `${attributes.blurAmount ?? 20}px`,
      '--wp-block-animate-on-scroll-perspective': `${attributes.perspective ?? 1000}px`,
      '--wp-block-animate-on-scroll-bounce-distance': `${attributes.bounceDistance ?? 30}px`,
      '--wp-block-animate-on-scroll-elastic-distance': `${attributes.elasticDistance ?? 50}px`,
    } as CSSProperties,
  });

  const playPreview = useCallback(() => {
    if (previewTimerRef.current) {
      clearTimeout(previewTimerRef.current);
      previewTimerRef.current = null;
    }
    setPreviewPhase('armed');
  }, []);

  const clearPreviewChildState = useCallback((root: HTMLElement) => {
    Array.from(root.children).forEach(child => {
      const el = child as HTMLElement;
      el.style.removeProperty('--wp-block-animate-on-scroll-stagger-delay');
      el.removeAttribute('data-animate-sequence-type');
      el.removeAttribute('data-animate-sequence-direction');
      [
        '--wp-block-animate-on-scroll-slide-distance',
        '--wp-block-animate-on-scroll-zoom-in-start',
        '--wp-block-animate-on-scroll-zoom-out-start',
        '--wp-block-animate-on-scroll-rotate-angle',
        '--wp-block-animate-on-scroll-blur-amount',
        '--wp-block-animate-on-scroll-perspective',
        '--wp-block-animate-on-scroll-bounce-distance',
        '--wp-block-animate-on-scroll-elastic-distance',
      ].forEach(prop => el.style.removeProperty(prop));
    });
  }, []);

  useEffect(() => {
    if (previewPhase !== 'armed' || !blockRef.current) {
      return;
    }

    const root = blockRef.current;
    const children = Array.from(root.children) as HTMLElement[];
    const sequence = attributes.animationSequence ?? [];
    const staggerConfig: StaggerConfig = {
      pattern: attributes.staggerPattern,
      delay: attributes.staggerDelay,
      waveFrequency: attributes.staggerWaveFrequency,
      randomMin: attributes.staggerRandomMin,
      randomMax: attributes.staggerRandomMax,
      seed: attributes.staggerSeed || createStaggerSeed(),
    };

    // Sequence preview: stamp per-child types (CSS matches any element).
    if (useSequencePreview) {
      children.forEach((child, index) => {
        const step: AnimationSequenceItem = sequence[index % sequence.length];
        const type = step.animation === 'blur' ? 'blur-in' : step.animation;
        child.setAttribute('data-animate-sequence-type', type);
        if (step.direction) {
          child.setAttribute('data-animate-sequence-direction', step.direction);
        }
        if (step.slideDistance != null) {
          child.style.setProperty(
            '--wp-block-animate-on-scroll-slide-distance',
            `${step.slideDistance}px`
          );
        }
        if (step.zoomInStart != null) {
          child.style.setProperty(
            '--wp-block-animate-on-scroll-zoom-in-start',
            String(step.zoomInStart)
          );
        }
        if (step.zoomOutStart != null) {
          child.style.setProperty(
            '--wp-block-animate-on-scroll-zoom-out-start',
            String(step.zoomOutStart)
          );
        }
        if (step.rotationAngle != null) {
          child.style.setProperty(
            '--wp-block-animate-on-scroll-rotate-angle',
            `${step.rotationAngle}deg`
          );
        }
        if (step.blurAmount != null) {
          child.style.setProperty(
            '--wp-block-animate-on-scroll-blur-amount',
            `${step.blurAmount}px`
          );
        }
        if (step.perspective != null) {
          child.style.setProperty(
            '--wp-block-animate-on-scroll-perspective',
            `${step.perspective}px`
          );
        }
        if (step.bounceDistance != null) {
          child.style.setProperty(
            '--wp-block-animate-on-scroll-bounce-distance',
            `${step.bounceDistance}px`
          );
        }
        if (step.elasticDistance != null) {
          child.style.setProperty(
            '--wp-block-animate-on-scroll-elastic-distance',
            `${step.elasticDistance}px`
          );
        }
      });
    }

    // Stagger delays (sequence always cascades; non-sequence when toggled).
    if (attributes.staggerChildren || useSequencePreview) {
      children.forEach((child, index) => {
        const delay = getChildStaggerDelay(
          index,
          children.length,
          staggerConfig,
          false
        );
        child.style.setProperty(
          '--wp-block-animate-on-scroll-stagger-delay',
          `${delay}s`
        );
      });
    }

    // Double rAF so the browser paints the hidden/offset state first.
    let raf2 = 0;
    const raf1 = requestAnimationFrame(() => {
      raf2 = requestAnimationFrame(() => setPreviewPhase('visible'));
    });

    let maxStagger = 0;
    if (
      (attributes.staggerChildren || useSequencePreview) &&
      children.length > 1
    ) {
      children.forEach((_, index) => {
        maxStagger = Math.max(
          maxStagger,
          getChildStaggerDelay(index, children.length, staggerConfig, false)
        );
      });
    }
    const holdMs =
      (attributes.duration + attributes.initialDelay + maxStagger + 0.35) *
      1000;

    previewTimerRef.current = setTimeout(() => {
      setPreviewPhase('idle');
      if (blockRef.current) {
        clearPreviewChildState(blockRef.current);
      }
      previewTimerRef.current = null;
    }, holdMs);

    return () => {
      cancelAnimationFrame(raf1);
      cancelAnimationFrame(raf2);
    };
  }, [
    previewPhase,
    attributes.staggerChildren,
    attributes.staggerDelay,
    attributes.staggerPattern,
    attributes.staggerWaveFrequency,
    attributes.staggerRandomMin,
    attributes.staggerRandomMax,
    attributes.staggerSeed,
    attributes.duration,
    attributes.initialDelay,
    attributes.animationSequence,
    useSequencePreview,
    clearPreviewChildState,
  ]);

  useEffect(
    () => () => {
      if (previewTimerRef.current) {
        clearTimeout(previewTimerRef.current);
      }
    },
    []
  );

  // Persist a seed for blocks that already use random without one.
  useEffect(() => {
    if (
      attributes.staggerChildren &&
      attributes.staggerPattern === 'random' &&
      !attributes.staggerSeed
    ) {
      setAttributes({ staggerSeed: createStaggerSeed() });
    }
  }, [
    attributes.staggerChildren,
    attributes.staggerPattern,
    attributes.staggerSeed,
    setAttributes,
  ]);

  return (
    <>
      <AosInspector
        attributes={attributes}
        setAttributes={setAttributes}
        childCount={childCount}
        isPreviewing={isPreviewing}
        showNestedChildWarning={showNestedChildWarning}
        playPreview={playPreview}
      />

      <div {...blockProps}>
        <InnerBlocks />
      </div>
    </>
  );
}
