import { usedOn, usedOnPatch } from '../_placement';

describe('usedOn', () => {
  it('reads every guide from an empty guide list', () => {
    expect(usedOn({ enabled: true, guides: [] })).toBe('every');
  });

  it('reads some guides from a guide list', () => {
    expect(usedOn({ enabled: true, guides: [3] })).toBe('some');
  });

  it('reads nowhere from a spec turned off, whatever its guides', () => {
    expect(usedOn({ enabled: false, guides: [] })).toBe('nowhere');
    expect(usedOn({ enabled: false, guides: [3] })).toBe('nowhere');
  });
});

describe('usedOnPatch', () => {
  it('clears the guide list for every guide', () => {
    expect(usedOnPatch('every')).toEqual({ enabled: true, guides: [] });
  });

  it('keeps guides already picked for some guides', () => {
    expect(usedOnPatch('some')).toEqual({ enabled: true });
  });

  it('keeps the guides when put away, so they come back with it', () => {
    expect(usedOnPatch('nowhere')).toEqual({ enabled: false });
  });

  it('round-trips through usedOn', () => {
    for (const value of ['every', 'nowhere'] as const) {
      expect(
        usedOn({ enabled: true, guides: [3], ...usedOnPatch(value) })
      ).toBe(value);
    }
    expect(
      usedOn({ enabled: false, guides: [3], ...usedOnPatch('some') })
    ).toBe('some');
  });
});
