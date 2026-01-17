/**
 * Animate On Scroll Block Types
 *
 * @package Aggressive_Apparel
 */

export type EasingType =
  | 'ease'
  | 'linear'
  | 'ease-in'
  | 'ease-out'
  | 'ease-in-out'
  | 'cubic-bezier(0.68, -0.55, 0.265, 1.55)'
  | 'cubic-bezier(0.175, 0.885, 0.32, 1.275)';

export type StaggerPattern = 'sequential' | 'wave' | 'random';

export interface AnimationSequenceItem {
  animation: string;
  direction: string;
  slideDistance?: number;
  zoomInStart?: number;
  zoomOutStart?: number;
  rotationAngle?: number;
  blurAmount?: number;
  perspective?: number;
  bounceDistance?: number;
  elasticDistance?: number;
}

export interface DetectionBoundary {
  top: string;
  right: string;
  bottom: string;
  left: string;
}

export interface AnimateOnScrollAttributes {
  animation: string;
  direction: string;
  staggerChildren: boolean;
  staggerDelay: number;
  duration: number;
  threshold: string;
  detectionBoundary: DetectionBoundary;
  debugMode: boolean;
  initialDelay: number;
  useSequence: boolean;
  animationSequence: AnimationSequenceItem[];
  reverseOnScrollBack: boolean;
  easing: EasingType;
  staggerPattern: StaggerPattern;
  staggerWaveFrequency: number;
  staggerRandomMin: number;
  staggerRandomMax: number;
  respectReducedMotion: boolean;
  announceToScreenReader: boolean;
  slideDistance?: number;
  zoomInStart?: number;
  zoomOutStart?: number;
  rotationAngle?: number;
  blurAmount?: number;
  perspective?: number;
  bounceDistance?: number;
  elasticDistance?: number;
  sequenceCustomizations: Record<string, Record<string, number>>;
}
