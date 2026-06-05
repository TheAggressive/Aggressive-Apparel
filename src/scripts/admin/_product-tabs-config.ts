/**
 * Product Tabs admin config access.
 *
 * @package Aggressive_Apparel
 */

import type { ProductTabsAdminConfig } from './_product-tabs-types';

const DEFAULT_CONFIG: ProductTabsAdminConfig = {
  layoutHelp: {},
  layouts: { rich_text: 'Rich Text' },
  sources: { manual: 'Manual / fallback content' },
  templates: {
    blank: {
      title: '',
      layout: 'rich_text',
      content: '',
      items: [],
    },
  },
  strings: {
    productDetails: 'Product Details',
    previewEmpty: 'Add content or layout items to preview the frontend output.',
    untitledTab: 'Untitled tab',
    enabled: 'Enabled',
    disabled: 'Disabled',
    ready: 'Ready',
    needsAttention: 'Needs Attention',
    addTabTitle: 'Add a tab title before saving.',
    addMetaKey: 'Add a product meta key or switch the source to Manual.',
    addAttribute:
      'Add a product attribute slug or switch the source to Manual.',
    addContent:
      'This tab will not render until it has content or layout items.',
    needsIntro: 'This layout needs intro/fallback content to render.',
    icon: 'Icon',
    label: 'Label / Question / Step',
    detail: 'Detail / Eyebrow',
    removeItem: 'Remove Item',
    text: 'Text / Answer / Value',
    enabledLabel: 'Enabled',
    priority: 'Priority',
    remove: 'Remove Tab',
    tabTitle: 'Tab Title',
    tabTitlePlaceholder: 'Size & Fit',
    contentLayout: 'Content Layout',
    contentSource: 'Content Source',
    productMetaField: 'Product Meta Field',
    productMetaHelp: 'Used only when Content Source is Product meta field.',
    productAttribute: 'Product Attribute',
    productAttributeHelp: 'Used only when Content Source is Product attribute.',
    selectAttribute: 'Select attribute...',
    introContent: 'Intro / Fallback Content',
    introHelp:
      'For structured layouts, this appears above the generated layout. For rich text, this is the full tab content.',
    layoutItemsHeader: 'No-Code Layout Items',
    layoutItemsHelp:
      'Add the rows this layout will render. The theme handles the frontend markup and styling.',
    addLayoutItem: 'Add Layout Item',
    layoutItemsFooter:
      'Use these rows for cards, specs tables, FAQs, timelines, care grids, and icon lists. Leave them empty for rich text or custom HTML tabs.',
    previewLabel: 'Frontend Preview',
    previewHelp:
      'Updates as you edit. Custom HTML is shown as plain text in the preview for admin safety.',
  },
};

export function getConfig(): ProductTabsAdminConfig {
  return window.aggressiveApparelProductTabs ?? DEFAULT_CONFIG;
}
