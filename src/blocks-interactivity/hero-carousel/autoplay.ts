/**
 * Hero Carousel — autoplay timer + interaction hold.
 *
 * Owns the dwell timeout, resume delay, and `is-playing` class. Pause freezes
 * the CSS progress pill (via `animation-play-state`) and the JS timer at the
 * same remaining duration so they stay in sync on resume.
 *
 * @package Aggressive_Apparel
 */

import { canAdvance, normalizeAutoplaySpeed } from './logic';
import { RESUME_DELAY_MS } from './constants';
import { prefersReducedData, prefersReducedMotion } from './prefs';

export interface AutoplayHost {
  readonly root: HTMLElement;
  readonly autoplay: boolean;
  readonly autoplaySpeed: number;
  readonly count: number;
  readonly loop: boolean;
  readonly activeIndex: number;
  readonly isVisible: boolean;
  readonly wrapping: boolean;
  isPlaying: boolean;
  isPaused: boolean;
  /** Advance one slide as an autoplay tick. */
  onTick: () => void;
}

export class AutoplayEngine {
  private timer = 0;
  private resumeTimer = 0;
  /** Last applied `is-playing` class state — avoid no-op toggles. */
  private playingClass: boolean | null = null;
  /** Ms left in the current dwell when paused (0 = start a full dwell). */
  private remainingMs = 0;
  /** `Date.now()` when the current timeout was scheduled (Jest-friendly). */
  private runningSince = 0;

  constructor(private readonly host: AutoplayHost) {}

  /** Hover / focus pause. `hold` true = pause, false = allow resume. */
  hold(paused: boolean): void {
    this.host.isPaused = paused;
    this.reconcile();
  }

  togglePlay(): void {
    this.host.isPlaying = !this.host.isPlaying;
    this.reconcile();
  }

  stop(): void {
    this.host.isPlaying = false;
    this.remainingMs = 0;
    this.reconcile();
  }

  /**
   * Slide changed (manual or auto). Reset the dwell so the next run starts a
   * full interval — matches the progress pill restarting on the new dot.
   */
  notifySlideChange(): void {
    this.remainingMs = 0;
    if (this.timer) {
      this.clearTimer();
      if (this.shouldRun()) {
        this.schedule(this.fullInterval());
      }
    }
  }

  /** After manual interaction, resume autoplay only after a short delay. */
  deferResume(): void {
    if (!this.host.autoplay || !this.host.isPlaying) return;
    // New slide / user nav — next dwell should be full length.
    this.remainingMs = 0;
    this.host.isPaused = true;
    this.reconcile();
    window.clearTimeout(this.resumeTimer);
    this.resumeTimer = window.setTimeout(() => {
      this.host.isPaused = false;
      this.reconcile();
    }, RESUME_DELAY_MS);
  }

  reconcile(): void {
    const showPlaying = this.host.isPlaying && !this.host.isPaused;
    const run = this.shouldRun();

    // `is-playing` drives animation-play-state (running vs paused) — do not
    // remove the animation itself, or the pill snaps back to empty.
    if (this.playingClass !== showPlaying) {
      this.playingClass = showPlaying;
      this.host.root.classList.toggle('is-playing', showPlaying);
    }

    if (run) {
      if (!this.timer) {
        const ms =
          this.remainingMs > 0 ? this.remainingMs : this.fullInterval();
        this.schedule(ms);
      }
    } else if (this.timer) {
      this.captureRemaining();
      this.clearTimer();
    }
  }

  private fullInterval(): number {
    return normalizeAutoplaySpeed(this.host.autoplaySpeed);
  }

  private schedule(ms: number): void {
    this.clearTimer();
    const delay = Math.max(16, ms);
    this.remainingMs = delay;
    this.runningSince = Date.now();
    this.timer = window.setTimeout(() => {
      this.timer = 0;
      this.remainingMs = 0;
      this.host.onTick();
      // Next dwell starts fresh after the tick advances the slide.
      if (this.shouldRun()) {
        this.schedule(this.fullInterval());
      }
    }, delay);
  }

  private captureRemaining(): void {
    if (!this.timer || !this.runningSince) return;
    const elapsed = Date.now() - this.runningSince;
    this.remainingMs = Math.max(0, this.remainingMs - elapsed);
    this.runningSince = 0;
  }

  private clearTimer(): void {
    window.clearTimeout(this.timer);
    this.timer = 0;
  }

  private shouldRun(): boolean {
    return (
      this.host.isPlaying &&
      this.host.isVisible &&
      !this.host.isPaused &&
      !this.host.wrapping &&
      !document.hidden &&
      !prefersReducedMotion() &&
      !prefersReducedData() &&
      this.host.count > 1 &&
      canAdvance(this.host.activeIndex, this.host.count, this.host.loop)
    );
  }

  destroy(): void {
    this.clearTimer();
    window.clearTimeout(this.resumeTimer);
    this.resumeTimer = 0;
  }
}
