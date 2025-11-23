# Animate On Scroll Block - Improvement Plan

## Overview
This document outlines a staged improvement plan for the `aggressive-apparel/animate-on-scroll` block. Each stage should be completed and tested before moving to the next.

---

## Stage 1: Animation Presets System
**Goal**: Add predefined animation presets for quick setup

### Features
- **Preset Categories**:
  - Subtle (gentle fade, small slide)
  - Moderate (standard animations)
  - Dramatic (large movements, strong effects)
- **One-Click Application**: Apply preset with single click
- **Preset Preview**: Show what the preset includes before applying
- **Custom Preset Save**: Allow saving current settings as custom preset

### Implementation
- Add `preset` attribute to `block.json`
- Create preset definitions in `edit.tsx`
- Add `SelectControl` for preset selection
- Update attributes when preset is selected
- Add "Save as Preset" functionality

### Testing Checklist
- [ ] Presets apply correctly
- [ ] Preset selection updates all relevant attributes
- [ ] Custom presets can be saved and loaded
- [ ] Preset preview shows correct information
- [ ] No conflicts with manual attribute changes

---

## Stage 2: Enhanced Editor Preview
**Goal**: Improve the preview functionality in the editor

### Features
- **Live Preview**: Show animation in editor without needing to preview button
- **Preview Button Enhancement**: Better visual feedback when clicked
- **Preview Reset**: Easy way to reset and replay animation
- **Preview Settings**: Option to auto-preview on attribute change

### Implementation
- Enhance existing preview button functionality
- Add `useEffect` to trigger preview on attribute changes (optional toggle)
- Improve visual feedback (loading state, success indicator)
- Add animation reset capability

### Testing Checklist
- [ ] Preview button works correctly
- [ ] Animation resets properly
- [ ] Visual feedback is clear
- [ ] No performance issues with auto-preview
- [ ] Works with all animation types

---

## Stage 3: Additional Animation Types
**Goal**: Add more animation options

### New Animations
- **Scale**: Scale up/down (different from zoom)
- **Bounce**: Bouncy entrance effect
- **Elastic**: Elastic/stretchy effect
- **Scale-Up**: Scale from center with different easing

### Implementation
- Add new animation types to `baseAnimations` in `edit.tsx`
- Add corresponding CSS in `style.css`
- Update `render.php` to handle new types
- Ensure all directions work correctly

### Testing Checklist
- [ ] All new animations work correctly
- [ ] Directions apply properly where applicable
- [ ] CSS animations are smooth
- [ ] No conflicts with existing animations
- [ ] Performance is acceptable

---

## Stage 4: Custom Animation Distance/Amount Controls
**Goal**: Allow users to customize animation distances and amounts

### Features
- **Slide Distance**: Customizable distance for slide animations (currently 50px)
- **Zoom Amount**: Customizable scale values for zoom (currently 0.5/1.5)
- **Rotation Angle**: Customizable rotation angle (currently 90deg)
- **Blur Amount**: Customizable blur intensity (currently 20px)
- **Perspective**: Customizable 3D perspective (currently 1000px)

### Implementation
- Add new attributes to `block.json`
- Add `RangeControl` or `UnitControl` for each customizable value
- Update CSS to use custom properties from attributes
- Update `render.php` to output CSS variables

### Testing Checklist
- [ ] All controls work correctly
- [ ] Values update CSS custom properties
- [ ] Animations reflect custom values
- [ ] Default values work when not set
- [ ] Min/max values are appropriate

---

## Stage 5: Animation Delay Offset
**Goal**: Add initial delay before animation starts

### Features
- **Initial Delay**: Delay before animation begins (separate from stagger)
- **Delay Control**: Range control for delay (0-2 seconds)
- **Use Case**: Allow elements to appear after a set time even when visible

### Implementation
- Add `initialDelay` attribute to `block.json`
- Add `RangeControl` in `edit.tsx`
- Update CSS to use `transition-delay` on base element
- Ensure it works with stagger (stagger should be added to initial delay)

### Testing Checklist
- [ ] Initial delay works correctly
- [ ] Combines properly with stagger delay
- [ ] Works with all animation types
- [ ] No conflicts with existing functionality
- [ ] Performance is acceptable

---

## Stage 6: Performance Optimizations
**Goal**: Optimize performance and reduce unnecessary work

### Optimizations
- **Observer Disconnect**: Disconnect IntersectionObserver after animation completes (if not re-animating)
- **Reduce Reflows**: Minimize layout thrashing
- **CSS Optimization**: Use `transform` and `opacity` only (GPU-accelerated)
- **Debounce Resize**: Better debouncing for resize events
- **Lazy Observer**: Only create observer when needed

### Implementation
- Update `view.ts` to disconnect observer after animation
- Optimize CSS to use only GPU-accelerated properties
- Improve resize handler debouncing
- Add performance monitoring (optional)

### Testing Checklist
- [ ] No performance regressions
- [ ] Animations still work correctly
- [ ] Observer disconnects properly
- [ ] Resize handling is smooth
- [ ] Memory usage is acceptable

---

## Stage 7: Accessibility Enhancements
**Goal**: Improve accessibility and user experience

### Features
- **Enhanced Reduced Motion**: Better handling of `prefers-reduced-motion`
- **ARIA Labels**: Add appropriate ARIA attributes
- **Keyboard Navigation**: Ensure animations don't interfere with keyboard navigation
- **Focus Management**: Proper focus handling during animations
- **Screen Reader Support**: Announce animation state changes

### Implementation
- Enhance `prefers-reduced-motion` detection and handling
- Add ARIA attributes to block wrapper
- Ensure keyboard navigation works
- Add screen reader announcements
- Test with assistive technologies

### Testing Checklist
- [ ] Reduced motion is respected
- [ ] ARIA attributes are correct
- [ ] Keyboard navigation works
- [ ] Screen readers announce correctly
- [ ] No accessibility regressions

---

## Implementation Order

1. **Stage 1** - Animation Presets (Quick wins, improves UX)
2. **Stage 2** - Enhanced Editor Preview (Improves developer experience)
3. **Stage 3** - Additional Animation Types (Expands capabilities)
4. **Stage 4** - Custom Controls (Gives more control)
5. **Stage 5** - Animation Delay (Adds timing control)
6. **Stage 6** - Performance (Optimizes existing features)
7. **Stage 7** - Accessibility (Ensures compliance)

---

## Success Criteria

Each stage is considered complete when:
- ✅ All features work as specified
- ✅ All tests pass
- ✅ No regressions in existing functionality
- ✅ Code follows project standards
- ✅ Documentation is updated
- ✅ User confirms it works as expected

---

## Notes

- Each stage should be implemented, tested, and confirmed working before moving to the next
- If issues arise, fix them before proceeding
- Keep code clean and maintainable throughout
- Document any breaking changes or migration needs

