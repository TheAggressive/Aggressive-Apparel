import type { HScrollMode } from '../logic';
import { NativeCarouselController } from './native-carousel';
import { ScrubController } from './scrub';
import { StaticController } from './static';
import { StepController } from './step';
import type {
  Controller,
  ControllerElements,
  Geometry,
  Presentation,
} from './types';

export type {
  Controller,
  ControllerElements,
  Geometry,
  Presentation,
} from './types';

export function createController(
  mode: HScrollMode,
  elements: ControllerElements,
  presentation: Presentation,
  geometry: Geometry
): Controller {
  switch (mode) {
    // 'paged' = one deliberate gesture advances one slide (directional snap).
    case 'paged':
      return new StepController(elements, presentation, geometry);
    // 'pinned' = continuous scrub; the track follows scroll each frame.
    case 'pinned':
      return new ScrubController(elements, presentation, geometry);
    case 'native':
      return new NativeCarouselController(elements, presentation, geometry);
    default:
      return new StaticController(elements, presentation);
  }
}
