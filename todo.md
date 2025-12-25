# Navigation Pro Block

An advanced Gutenberg navigation block that provides core navigation parity plus mega menus and mobile interaction patterns.

## Features

### Core Navigation Parity
- ✅ Menu selection (WordPress navigation menus)
- ✅ Nested submenus with unlimited depth
- ✅ Responsive hamburger toggle
- ✅ Keyboard navigation (Tab, Arrow keys, Escape)
- ✅ ARIA attributes and screen reader support
- ✅ Active/current page highlighting
- ✅ Focus trapping in mobile menu

### Advanced Features
- 🎯 **Mega Menus**: Full-width panels with inner block content
- 📱 **Mobile Breakpoint**: Configurable responsive behavior
- 🎛️ **Mobile Patterns**: Accordion, drilldown, tabs, hybrid, and sheet modes
- 🍔 **Hamburger Animations**: Spin, squeeze, arrow, collapse, or none
- 🎨 **Native Styling**: Typography, colors, spacing, hover/active states
- ♿ **Accessibility**: WCAG 2.1 AA compliant
- ⚡ **Performance**: Interactivity API with progressive enhancement

## Installation

1. **Build the block** (if developing):
   ```bash
   # From theme root
   pnpm build:interactivity
   ```

2. **The block is automatically registered** when the theme is active.

## Block Usage

### Block Editor Experience
This block provides a **traditional WordPress block editor experience** rather than WYSIWYG preview. You'll see block outlines and edit menu items as individual blocks.

### Basic Navigation
1. Add the "Navigation Pro" block to your post/page
2. Click "Add Menu Item" or use the default template items
3. Edit each menu item by clicking on it (opens block settings in sidebar)
4. Configure links, labels, and submenu options per item
5. Use sidebar panels to configure global navigation settings

### Mega Menu Setup
1. Enable "Mega Menu" toggle on any top-level menu item
2. Click the mega menu panel to edit content
3. Add columns, headings, links, images, etc.
4. Content appears on hover (desktop) or click (mobile)

### Mobile Configuration
1. Set custom breakpoint (default: 782px)
2. Choose mobile submenu mode:
   - **Accordion**: Expand/collapse inline
   - **Drilldown**: Navigate deeper with back button
   - **Tabs**: Submenus as tab panels
   - **Hybrid**: Accordion + drilldown for deeper levels
   - **Sheet**: Overlay sheet for submenus

## Editor Experience

The Navigation Pro block provides a **native WordPress editing experience** that shows a live preview of how the navigation will appear on your site, with all configuration handled through WordPress InspectorControls panels.

### Editor Interface
- **Live Preview**: Shows exactly how the navigation will look on the frontend
- **WordPress Panels**: All settings are organized in proper InspectorControls panels
- **Toolbar Shortcuts**: Quick access buttons to jump to specific settings sections
- **Template Integration**: Works seamlessly with WordPress template editing

### Editor Preview Features
- **Hamburger Toggle Preview**: Shows the mobile menu button with current animation
- **Menu Structure Preview**: Displays sample menu items with submenus and mega menus
- **Responsive Indicators**: Visual cues for mobile breakpoints
- **Interactive Elements**: Hover and focus states previewed in editor

### Settings Organization
All configuration is organized in logical WordPress panels:

#### Menu Panel
- Navigation menu selection
- Layout orientation
- Mega menu toggle

#### Mobile Panel
- Breakpoint configuration
- Submenu interaction mode
- Back button customization

#### Submenu Behavior Panel
- Desktop interaction (hover/click)
- Animation style selection
- Timing controls

#### Hamburger Toggle Panel
- Animation style selection
- Button positioning
- Color customization

#### Styling Panels
- Typography controls (font, size, weight, transform)
- Color palettes for links and backgrounds
- Spacing and padding controls
- Border and radius settings
- Hover and active state styling

### Toolbar Controls
- **≡ Menu Settings**: Jump to menu configuration
- **📱 Mobile Settings**: Jump to mobile options
- Standard WordPress block controls (move, duplicate, delete)

## Block Attributes

### Menu Settings
- `ref`: Navigation menu ID (number)
- `orientation`: Layout direction (`horizontal`|`vertical`)

### Mega Menu
- `hasMegaMenu`: Enable mega menu support (boolean)

### Mobile Behavior
- `mobileBreakpoint`: Responsive breakpoint in px (number)
- `mobileSubmenuMode`: Mobile interaction pattern (string)
- `backButtonLabel`: Drilldown back button text (string)

### Submenu Behavior
- `submenuBehavior`: How submenus open (`hover`|`click`|`hover-delay`)
- `animationStyle`: Transition style (`none`|`slide`|`fade`)
- `openDelay`: Delay before opening submenu (ms)
- `closeDelay`: Delay before closing submenu (ms)

### Hamburger Toggle
- `hamburgerAnimation`: Button animation style (string)
- `togglePosition`: Button placement (string)
- `toggleBackgroundColor`: Button background color (string)
- `toggleTextColor`: Button text color (string)

### Styling
- `textColor`: Link text color (string)
- `backgroundColor`: Navigation background (string)
- `fontSize`: Typography size (string)
- `fontFamily`: Font family (string)
- `fontWeight`: Font weight (string)
- `textTransform`: Text transformation (string)
- `letterSpacing`: Letter spacing (string)

### States
- `hoverStyle`: Hover effect (`underline`|`background`|`indicator`|`none`)
- `hoverBackgroundColor`: Hover background (string)
- `hoverTextColor`: Hover text color (string)
- `activeStyle`: Active/current page effect (string)
- `activeBackgroundColor`: Active background (string)
- `activeTextColor`: Active text color (string)

### Submenu Styling
- `submenuBackgroundColor`: Dropdown background (string)
- `submenuTextColor`: Dropdown text color (string)
- `submenuBorderRadius`: Border radius (string)
- `submenuBorderWidth`: Border width (string)
- `submenuBorderColor`: Border color (string)

## Block Structure

```
src/blocks-interactivity/navigation-pro/
├── block.json           # Block configuration
├── index.ts            # Block registration
├── edit.tsx            # Editor component
├── save.tsx            # Save component (dynamic block)
├── render.php          # Server-side rendering
├── view.ts             # Interactivity API
├── style.css           # Frontend styles
├── editor.css          # Editor styles
├── types.ts            # TypeScript definitions
└── README.md           # This file
```

## Technical Implementation

### Interactivity API
The block uses WordPress 6.4+ Interactivity API for frontend behavior:

- **Namespace**: `aggressive-apparel/navigation-pro`
- **Context**: Navigation state and configuration
- **Actions**: Menu toggle, submenu interactions, keyboard navigation
- **Callbacks**: Resize handling, outside clicks, focus management

### Mobile Patterns

#### Accordion Mode
```javascript
// Expand/collapse submenus inline
submenuElement.style.maxHeight = isOpen ? '1000px' : '0';
```

#### Drilldown Mode
```javascript
// Hide current level, show submenu
currentLevel.style.display = 'none';
submenuElement.style.display = 'block';
```

#### Sheet Mode
```javascript
// Create overlay with submenu content
const overlay = document.createElement('div');
overlay.className = 'sheet-overlay';
document.body.appendChild(overlay);
```

### Accessibility Features

#### ARIA Attributes
- `aria-expanded`: Menu open/closed state
- `aria-controls`: Relationship to controlled element
- `aria-haspopup`: Indicates popup submenus
- `role="menubar"`: Navigation landmark
- `role="menuitem"`: Individual menu items

#### Keyboard Navigation
- **Tab**: Navigate through menu items
- **Enter/Space**: Activate menu items and toggles
- **Arrow Keys**: Navigate submenu items
- **Escape**: Close open menus
- **Focus Trapping**: Keep focus within mobile menu

#### Screen Reader Support
- Hidden labels for toggle buttons
- Announcement of menu state changes
- Semantic HTML structure

## CSS Custom Properties

The block uses CSS custom properties for theming:

```css
/* Mobile breakpoint */
--nav-mobile-breakpoint: 782px;

/* Item spacing */
--nav-item-gap: 1rem;

/* Submenu colors */
--nav-submenu-bg: #ffffff;
--nav-submenu-text: inherit;

/* State colors */
--nav-hover-bg: transparent;
--nav-hover-text: inherit;
--nav-active-bg: transparent;
--nav-active-text: inherit;

/* Toggle colors */
--nav-toggle-bg: transparent;
--nav-toggle-text: currentColor;
```

## Extension Points

### Filters

#### Menu Data Filtering
```php
// Filter menu items before rendering
$menu_data = apply_filters('aggressive_apparel_navigation_menu_data', $menu_data, $attributes, $block);
```

#### Block Attributes
```php
// Filter block attributes before processing
$attributes = apply_filters('aggressive_apparel_navigation_attributes', $attributes);
```

#### Allowed Mega Menu Blocks
```php
// Control which blocks are allowed in mega menus
$allowed_blocks = apply_filters('aggressive_apparel_navigation_mega_menu_blocks', [
    'core/columns',
    'core/column',
    'core/heading',
    'core/paragraph',
    'core/list',
    'core/buttons',
    'core/image'
]);
```

### Actions

#### Before Rendering
```php
// Action fired before navigation rendering
do_action('aggressive_apparel_navigation_before_render', $attributes, $menu_data);
```

#### After Rendering
```php
// Action fired after navigation rendering
do_action('aggressive_apparel_navigation_after_render', $attributes, $menu_data, $output);
```

## Performance Considerations

### Interactivity API Benefits
- ✅ No external JavaScript dependencies
- ✅ Progressive enhancement (works without JS)
- ✅ Server-side rendering for initial state
- ✅ Optimized re-rendering

### CSS Optimizations
- ✅ CSS custom properties for theming
- ✅ Minimal specificity conflicts
- ✅ Reduced motion support
- ✅ High contrast mode support

### Mobile Performance
- ✅ Touch event handling
- ✅ Hardware acceleration for animations
- ✅ Efficient DOM manipulation
- ✅ Memory leak prevention

## Browser Support

- ✅ WordPress 6.4+ (Interactivity API requirement)
- ✅ Modern browsers with ES2018+ support
- ✅ Progressive enhancement for older browsers
- ✅ Graceful degradation for JavaScript-disabled users

## Troubleshooting

### Common Issues

#### Menu Not Appearing
- Ensure a WordPress navigation menu exists
- Check menu contains published pages/posts
- Verify block is properly configured

#### Mobile Menu Not Working
- Check mobile breakpoint setting
- Verify JavaScript is loading
- Test on actual mobile device (not just browser dev tools)

#### Mega Menu Not Showing
- Enable "Mega Menu" toggle on menu item
- Add content to mega menu panel
- Check for CSS conflicts

#### Styling Issues
- Check for theme CSS conflicts
- Verify CSS custom properties are set
- Test in different browsers

### Debug Mode
Enable debugging in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check browser console and WordPress debug log for errors.

## Development

### Building
```bash
# Build all interactivity blocks
pnpm build:interactivity

# Watch mode for development
pnpm start
```

### Testing
```bash
# Run PHP tests
pnpm test:php

# Run JavaScript tests
pnpm test:js

# Run all tests
pnpm qa
```

### Code Quality
```bash
# Lint PHP code
pnpm lint:php

# Lint JavaScript/TypeScript
pnpm lint:js

# Format code
pnpm format
```

## Migration from Core Navigation

### Feature Comparison

| Feature           | Core Navigation | Navigation Pro |
| ----------------- | --------------- | -------------- |
| Basic menus       | ✅               | ✅              |
| Responsive        | ✅               | ✅              |
| Keyboard nav      | ✅               | ✅              |
| ARIA support      | ✅               | ✅              |
| Mega menus        | ❌               | ✅              |
| Mobile patterns   | Basic           | 5 modes        |
| Styling controls  | Basic           | Advanced       |
| Interactivity API | ❌               | ✅              |

### Migration Steps

1. **Replace block**: Change `core/navigation` to `aggressive-apparel/navigation-pro`
2. **Configure menu**: Select the same navigation menu
3. **Test responsive**: Verify mobile behavior matches expectations
4. **Add mega menus**: Enable mega menu toggle on desired items
5. **Style as needed**: Use inspector controls for custom styling

## Contributing

1. Follow WordPress Coding Standards
2. Use TypeScript for new JavaScript code
3. Add PHP tests for new functionality
4. Update documentation for new features
5. Test accessibility with screen readers

## License

GPL-2.0-or-later

## Changelog

### 1.0.0
- Initial release
- Core navigation parity
- Mega menu support
- Mobile interaction patterns
- Interactivity API implementation
- Full accessibility support
