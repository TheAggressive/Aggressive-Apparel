/**
 * SequenceBuilder - Visual editor for the animation sequence.
 *
 * Renders the sequence as numbered, collapsible step cards with
 * reorder / duplicate / remove actions, inline per-step customization,
 * a compact flow strip and a hint describing how steps map onto the
 * block's children.
 *
 * @package Aggressive_Apparel
 */

import {
  Button,
  RangeControl,
  SelectControl,
  Tooltip,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
  chevronDown,
  chevronUp,
  chevronRight,
  copy,
  plus,
  trash,
} from '@wordpress/icons';
import type { AnimationSequenceItem } from '../types';
import {
  describeSequenceItem,
  getAnimationOptions,
  getDefaultDirection,
  getDirectionOptions,
} from '../animation-config';

interface SequenceBuilderProps {
  sequence: AnimationSequenceItem[];
  childCount: number;
  onChange: (_sequence: AnimationSequenceItem[]) => void;
  /** Fallback animation/direction used when adding a new step. */
  fallbackAnimation: string;
  fallbackDirection: string;
}

/** Compact "1 Fade → 2 Slide ↑ → …" overview of the whole pattern. */
const FlowStrip = ({ sequence }: { sequence: AnimationSequenceItem[] }) => (
  <div
    className='aggressive-apparel-animate-on-scroll-sequence__flow'
    aria-hidden='true'
  >
    {sequence.map((item, index) => (
      <div
        key={index}
        className='aggressive-apparel-animate-on-scroll-sequence__flow-item'
      >
        {index > 0 && (
          <span className='aggressive-apparel-animate-on-scroll-sequence__flow-separator'>
            →
          </span>
        )}
        <span className='aggressive-apparel-animate-on-scroll-sequence__chip'>
          <span className='aggressive-apparel-animate-on-scroll-sequence__badge aggressive-apparel-animate-on-scroll-sequence__badge--sm'>
            {index + 1}
          </span>
          {describeSequenceItem(item)}
        </span>
      </div>
    ))}
  </div>
);

/** Explains how the pattern maps onto the block's actual children. */
const ChildMappingHint = ({
  sequence,
  childCount,
}: {
  sequence: AnimationSequenceItem[];
  childCount: number;
}) => {
  if (sequence.length === 0) {
    return null;
  }

  let message: string;
  if (childCount === 0) {
    message = __(
      'Add child blocks inside this block — each child plays the step at its position.',
      'aggressive-apparel'
    );
  } else if (childCount === sequence.length) {
    message = sprintf(
      /* translators: %d: number of child blocks. */
      __(
        '%d child blocks — each child gets its own step.',
        'aggressive-apparel'
      ),
      childCount
    );
  } else if (childCount > sequence.length) {
    message = sprintf(
      /* translators: %d: number of child blocks. */
      __(
        '%d child blocks — the pattern repeats from step 1 when it runs out of steps.',
        'aggressive-apparel'
      ),
      childCount
    );
  } else {
    message = sprintf(
      /* translators: %d: number of child blocks. */
      __(
        'Only %d child blocks — later steps are unused until more children are added.',
        'aggressive-apparel'
      ),
      childCount
    );
  }

  return (
    <p className='components-base-control__help aggressive-apparel-animate-on-scroll-sequence__hint'>
      {message}
    </p>
  );
};

interface StepCustomizationProps {
  item: AnimationSequenceItem;
  onUpdate: (_patch: Partial<AnimationSequenceItem>) => void;
}

/** Per-animation fine-tuning controls, shown inside the expanded step. */
const StepCustomization = ({ item, onUpdate }: StepCustomizationProps) => {
  switch (item.animation) {
    case 'slide':
      return (
        <RangeControl
          label={__('Slide Distance (px)', 'aggressive-apparel')}
          value={item.slideDistance ?? 50}
          onChange={slideDistance => onUpdate({ slideDistance })}
          min={10}
          max={200}
          step={5}
        />
      );
    case 'zoom':
      return item.direction === 'out' ? (
        <RangeControl
          label={__('Zoom Out Start Scale', 'aggressive-apparel')}
          value={item.zoomOutStart ?? 1.5}
          onChange={zoomOutStart => onUpdate({ zoomOutStart })}
          min={1.1}
          max={3}
          step={0.1}
        />
      ) : (
        <RangeControl
          label={__('Zoom In Start Scale', 'aggressive-apparel')}
          value={item.zoomInStart ?? 0.5}
          onChange={zoomInStart => onUpdate({ zoomInStart })}
          min={0.1}
          max={0.9}
          step={0.1}
        />
      );
    case 'rotate':
      return (
        <RangeControl
          label={__('Rotation Angle (degrees)', 'aggressive-apparel')}
          value={item.rotationAngle ?? 90}
          onChange={rotationAngle => onUpdate({ rotationAngle })}
          min={15}
          max={360}
          step={15}
        />
      );
    case 'blur':
      return (
        <RangeControl
          label={__('Blur Amount (px)', 'aggressive-apparel')}
          value={item.blurAmount ?? 20}
          onChange={blurAmount => onUpdate({ blurAmount })}
          min={1}
          max={50}
          step={1}
        />
      );
    case 'flip':
      return (
        <RangeControl
          label={__('Perspective (px)', 'aggressive-apparel')}
          value={item.perspective ?? 1000}
          onChange={perspective => onUpdate({ perspective })}
          min={500}
          max={3000}
          step={100}
        />
      );
    case 'bounce':
      return (
        <>
          <RangeControl
            label={__('Bounce Distance (px)', 'aggressive-apparel')}
            value={item.bounceDistance ?? 30}
            onChange={bounceDistance => onUpdate({ bounceDistance })}
            min={10}
            max={100}
            step={5}
          />
          {item.direction === 'elastic' && (
            <RangeControl
              label={__('Elastic Distance (px)', 'aggressive-apparel')}
              value={item.elasticDistance ?? 50}
              onChange={elasticDistance => onUpdate({ elasticDistance })}
              min={20}
              max={150}
              step={5}
            />
          )}
        </>
      );
    default:
      return null;
  }
};

export const SequenceBuilder = ({
  sequence,
  childCount,
  onChange,
  fallbackAnimation,
  fallbackDirection,
}: SequenceBuilderProps) => {
  const [expandedIndex, setExpandedIndex] = useState<number | null>(null);

  const updateItem = (
    index: number,
    patch: Partial<AnimationSequenceItem>
  ): void => {
    const next = [...sequence];
    next[index] = { ...next[index], ...patch };
    onChange(next);
  };

  const moveItem = (index: number, delta: number): void => {
    const target = index + delta;
    if (target < 0 || target >= sequence.length) {
      return;
    }
    const next = [...sequence];
    [next[index], next[target]] = [next[target], next[index]];
    onChange(next);
    if (expandedIndex === index) {
      setExpandedIndex(target);
    } else if (expandedIndex === target) {
      setExpandedIndex(index);
    }
  };

  const duplicateItem = (index: number): void => {
    const next = [...sequence];
    next.splice(index + 1, 0, { ...sequence[index] });
    onChange(next);
    if (expandedIndex !== null && expandedIndex > index) {
      setExpandedIndex(expandedIndex + 1);
    }
  };

  const removeItem = (index: number): void => {
    onChange(sequence.filter((_, i) => i !== index));
    if (expandedIndex === index) {
      setExpandedIndex(null);
    } else if (expandedIndex !== null && expandedIndex > index) {
      setExpandedIndex(expandedIndex - 1);
    }
  };

  const addItem = (): void => {
    onChange([
      ...sequence,
      {
        animation: fallbackAnimation || 'fade',
        direction: fallbackDirection || '',
      },
    ]);
    setExpandedIndex(sequence.length);
  };

  return (
    <div className='aggressive-apparel-animate-on-scroll-sequence'>
      {sequence.length > 1 && <FlowStrip sequence={sequence} />}
      <ChildMappingHint sequence={sequence} childCount={childCount} />

      {sequence.map((item, index) => {
        const isExpanded = expandedIndex === index;
        const summary = describeSequenceItem(item);

        return (
          <div
            key={index}
            className='aggressive-apparel-animate-on-scroll-sequence__step'
          >
            <div className='aggressive-apparel-animate-on-scroll-sequence__step-header'>
              <Button
                className='aggressive-apparel-animate-on-scroll-sequence__step-toggle'
                icon={isExpanded ? chevronDown : chevronRight}
                size='small'
                onClick={() => setExpandedIndex(isExpanded ? null : index)}
                label={
                  isExpanded
                    ? __('Collapse step', 'aggressive-apparel')
                    : __('Edit step', 'aggressive-apparel')
                }
                aria-expanded={isExpanded}
              />
              <span className='aggressive-apparel-animate-on-scroll-sequence__badge aggressive-apparel-animate-on-scroll-sequence__badge--md'>
                {index + 1}
              </span>
              <button
                type='button'
                className='aggressive-apparel-animate-on-scroll-sequence__step-summary'
                onClick={() => setExpandedIndex(isExpanded ? null : index)}
              >
                {summary}
              </button>
              <Tooltip text={__('Move up', 'aggressive-apparel')}>
                <Button
                  icon={chevronUp}
                  size='small'
                  onClick={() => moveItem(index, -1)}
                  disabled={index === 0}
                  label={__('Move step up', 'aggressive-apparel')}
                />
              </Tooltip>
              <Tooltip text={__('Move down', 'aggressive-apparel')}>
                <Button
                  icon={chevronDown}
                  size='small'
                  onClick={() => moveItem(index, 1)}
                  disabled={index === sequence.length - 1}
                  label={__('Move step down', 'aggressive-apparel')}
                />
              </Tooltip>
              <Tooltip text={__('Duplicate', 'aggressive-apparel')}>
                <Button
                  icon={copy}
                  size='small'
                  onClick={() => duplicateItem(index)}
                  label={__('Duplicate step', 'aggressive-apparel')}
                />
              </Tooltip>
              <Tooltip text={__('Remove', 'aggressive-apparel')}>
                <Button
                  icon={trash}
                  size='small'
                  isDestructive
                  onClick={() => removeItem(index)}
                  label={__('Remove step', 'aggressive-apparel')}
                />
              </Tooltip>
            </div>

            {isExpanded && (
              <div className='aggressive-apparel-animate-on-scroll-sequence__step-body'>
                <SelectControl
                  label={__('Animation', 'aggressive-apparel')}
                  value={item.animation}
                  options={getAnimationOptions()}
                  onChange={animation => {
                    updateItem(index, {
                      animation,
                      direction: getDefaultDirection(animation),
                    });
                  }}
                  __next40pxDefaultSize
                />
                {getDirectionOptions(item.animation).length > 0 && (
                  <SelectControl
                    label={__('Direction', 'aggressive-apparel')}
                    value={item.direction}
                    options={getDirectionOptions(item.animation)}
                    onChange={direction => updateItem(index, { direction })}
                    __next40pxDefaultSize
                  />
                )}
                <StepCustomization
                  item={item}
                  onUpdate={patch => updateItem(index, patch)}
                />
              </div>
            )}
          </div>
        );
      })}

      <Button
        variant='secondary'
        icon={plus}
        className='aggressive-apparel-animate-on-scroll-sequence__add'
        onClick={addItem}
      >
        {__('Add Step', 'aggressive-apparel')}
      </Button>
    </div>
  );
};
