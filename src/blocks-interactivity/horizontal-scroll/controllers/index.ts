import type { HScrollMode } from '../logic';
import { NativeCarouselController } from './native-carousel';
import { PagedController } from './paged';
import { PinnedController } from './pinned';
import { StaticController } from './static';
import type {
  Controller,
  ControllerElements,
  ControllerOptions,
  Geometry,
  Presentation,
} from './types';

export type {
  Controller,
  ControllerElements,
  ControllerOptions,
  Geometry,
  Presentation,
} from './types';

export function createController(
  mode: HScrollMode,
  elements: ControllerElements,
  presentation: Presentation,
  geometry: Geometry,
  options: ControllerOptions
): Controller {
  switch (mode) {
    case 'paged':
      return new PagedController(elements, presentation, geometry);
    case 'pinned':
      return new PinnedController(elements, presentation, geometry, options);
    case 'native':
      return new NativeCarouselController(elements, presentation, geometry);
    default:
      return new StaticController(elements, presentation);
  }
}
