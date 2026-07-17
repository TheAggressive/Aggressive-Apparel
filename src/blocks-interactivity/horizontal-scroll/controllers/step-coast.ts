import { COAST_GAP_MS, COAST_MAX_MS } from './step-constants';

/**
 * Gap-bounded same-direction coast after a step lands. Trailing inertia that
 * arrives within {@link COAST_GAP_MS} of the previous event is swallowed (up to
 * {@link COAST_MAX_MS} from land). A quiet gap ends the stream so the next
 * deliberate flick is accepted immediately.
 */
export class StepCoast {
  private startedAt = 0;
  private lastEventAt = 0;
  /** Direction of the step that armed this coast (0 = inactive). */
  direction: 1 | -1 | 0 = 0;

  /** Clears the active stream timestamps. Leaves {@link direction} intact. */
  clear = (): void => {
    this.startedAt = 0;
    this.lastEventAt = 0;
  };

  /** Clears timestamps and the remembered step direction. */
  reset = (): void => {
    this.clear();
    this.direction = 0;
  };

  arm = (direction: 1 | -1 | 0, now = performance.now()): void => {
    if (direction === 0) {
      this.reset();
      return;
    }
    this.direction = direction;
    this.startedAt = now;
    this.lastEventAt = now;
  };

  /**
   * Returns true when this event is part of the active coast stream and should
   * be swallowed. Advances the stream timestamp when consumed.
   */
  consume = (direction: 1 | -1, now = performance.now()): boolean => {
    if (this.startedAt === 0 || this.direction !== direction) {
      return false;
    }

    if (now - this.startedAt > COAST_MAX_MS) {
      this.clear();
      return false;
    }
    if (now - this.lastEventAt > COAST_GAP_MS) {
      this.clear();
      return false;
    }

    this.lastEventAt = now;
    return true;
  };
}
