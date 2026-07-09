/**
 * @jest-environment jsdom
 */

import { isLegacyMotionAttrs, migrateLegacyMotionAttrs } from '../migrate';

describe('legacy motion attribute migration', () => {
  it('detects kenBurns attrs', () => {
    expect(isLegacyMotionAttrs({ kenBurns: 'zoom-in' })).toBe(true);
    expect(isLegacyMotionAttrs({ kenBurnsDuration: 8 })).toBe(true);
    expect(isLegacyMotionAttrs({ motion: 'zoom-in' })).toBe(false);
  });

  it('migrates kenBurns → motion', () => {
    const migrated = migrateLegacyMotionAttrs({
      kenBurns: 'grain',
      kenBurnsDuration: 9,
      autoplay: true,
    });

    expect(migrated.motion).toBe('grain');
    expect(migrated.motionDuration).toBe(9);
    expect(migrated.autoplay).toBe(true);
    expect(Object.prototype.hasOwnProperty.call(migrated, 'kenBurns')).toBe(
      false
    );
  });

  it('prefers already-migrated motion keys when both exist', () => {
    const migrated = migrateLegacyMotionAttrs({
      kenBurns: 'zoom-in',
      motion: 'orbit',
      kenBurnsDuration: 5,
      motionDuration: 14,
    });

    expect(migrated.motion).toBe('orbit');
    expect(migrated.motionDuration).toBe(14);
  });
});
