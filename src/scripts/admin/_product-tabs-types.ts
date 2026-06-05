/**
 * Shared Product Tabs admin types.
 *
 * @package Aggressive_Apparel
 */

export interface TabItem {
  icon: string;
  title: string;
  meta: string;
  text: string;
}

export interface TabTemplate {
  title: string;
  layout: string;
  content: string;
  items: TabItem[];
}

export interface ProductTabsAdminConfig {
  layoutHelp: Record<string, string>;
  layouts: Record<string, string>;
  sources: Record<string, string>;
  templates: Record<string, TabTemplate>;
  strings: {
    productDetails: string;
    previewEmpty: string;
    untitledTab: string;
    enabled: string;
    disabled: string;
    ready: string;
    needsAttention: string;
    addTabTitle: string;
    addMetaKey: string;
    addAttribute: string;
    addContent: string;
    needsIntro: string;
    icon: string;
    label: string;
    detail: string;
    removeItem: string;
    text: string;
    enabledLabel: string;
    priority: string;
    remove: string;
    tabTitle: string;
    tabTitlePlaceholder: string;
    contentLayout: string;
    contentSource: string;
    productMetaField: string;
    productMetaHelp: string;
    productAttribute: string;
    productAttributeHelp: string;
    selectAttribute: string;
    introContent: string;
    introHelp: string;
    layoutItemsHeader: string;
    layoutItemsHelp: string;
    addLayoutItem: string;
    layoutItemsFooter: string;
    previewLabel: string;
    previewHelp: string;
  };
}

declare global {
  interface Window {
    aggressiveApparelProductTabs?: ProductTabsAdminConfig;
    aggressiveApparelTabOverridesReady?: boolean;
  }
}
