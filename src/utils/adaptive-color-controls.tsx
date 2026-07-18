/**
 * Adaptive Color UI — scheme toggle + native WordPress color/gradient rows.
 *
 * Channel layout mirrors Styles (title → Color / Gradient rows) without nesting
 * ToolsPanels inside PanelBody (those add per-section ⋮ menus and grid chrome).
 * Link uses core’s dual-indicator + Default/Hover tabs pattern.
 *
 * @package Aggressive_Apparel
 * @since 1.70.0
 */

import {
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalColorGradientControl as ColorGradientControl,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
  useSettings,
} from '@wordpress/block-editor';
import {
  Button,
  ColorIndicator,
  Dropdown,
  Flex,
  FlexItem,
  TabPanel,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalDropdownContentWrapper as DropdownContentWrapper,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalHStack as HStack,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalZStack as ZStack,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useMemo, useRef } from '@wordpress/element';
import { reset as resetIcon } from '@wordpress/icons';

import {
  applyPickerColorToPair,
  getChannelPickerState,
  pairFromCssValue,
  pairToCssAttribute,
} from './adaptive-color-apply';
import type {
  AdaptiveColorPair,
  AdaptivePalettePair,
} from './adaptive-color-value';
import { hasAdaptivePairValue } from './adaptive-color-value';
import { ColorModeToggle, useEditorColorScheme } from './editor-color-scheme';
import type { ColorScheme } from './color-scheme-storage';
import { flattenPresetColors, type PresetColorOrigin } from './preset-colors';
import {
  ADMIN_HELP_TEXT_STYLE,
  EDITOR_INFO_NOTICE_STYLE,
} from './editor-style-tokens';

import './adaptive-color-controls.css';

export interface AdaptiveColorSetting {
  /** Stable id for React keys. */
  id: string;
  /** Section title (shared by grouped channels such as Link). */
  label: string;
  /** Group key — channels with the same group share one section. */
  group?: string;
  /** Row label inside a `rows` section (defaults to “Color”). */
  rowLabel?: string;
  /**
   * Inspector layout for a group. `dual-tabs` matches core Styles → Color → Link.
   */
  presentation?: 'rows' | 'dual-tabs';
  value: AdaptiveColorPair | undefined;
  onChange: (value: AdaptiveColorPair | undefined) => void;
  /** Allow CSS gradients (separate Gradient row, like Styles → Background). */
  allowGradient?: boolean;
  /** Whether a conflicting core color is set (info notice). */
  hasCoreConflict?: boolean;
}

type ChannelKind = 'color' | 'gradient';

/**
 * Panel chrome: help text + Light/Dark toggle bound to editor canvas preview.
 */
export function AdaptiveColorPanelHeader({
  description,
}: {
  description?: string;
}) {
  const { colorMode, switchColorMode } = useEditorColorScheme();

  return (
    <div className='aa-adaptive-color-panel__header'>
      <p
        className='aa-adaptive-color-panel__help'
        style={ADMIN_HELP_TEXT_STYLE}
      >
        {description ??
          __(
            'Choose Light or Dark, then set colors with the standard WordPress color picker. Adaptive palette swatches set both modes at once.',
            'aggressive-apparel'
          )}
      </p>
      <ColorModeToggle mode={colorMode} onChange={switchColorMode} />
    </div>
  );
}

/**
 * One Color / Gradient dropdown row.
 */
function AdaptiveColorDropdownRow({
  kind,
  channelLabel,
  label,
  displayValue,
  indicatorValue,
  onApply,
  onClear,
  colors,
  gradients,
  disableCustomColors,
}: {
  kind: ChannelKind;
  channelLabel: string;
  label: string;
  displayValue: string | undefined;
  indicatorValue: string | undefined;
  onApply: (next?: string) => void;
  onClear: () => void;
  colors: unknown;
  gradients: unknown;
  disableCustomColors?: boolean;
}) {
  const toggleRef = useRef<HTMLButtonElement | null>(null);
  const isGradient = kind === 'gradient';
  const hasValue = Boolean(displayValue);
  const toggleLabel = sprintf(
    /* translators: 1: channel name (Typography), 2: Color or Gradient */
    __('%1$s %2$s', 'aggressive-apparel'),
    channelLabel,
    label
  );

  return (
    <div className='aa-adaptive-color-channel__item'>
      <Dropdown
        className='aa-adaptive-color-channel__dropdown'
        popoverProps={{
          placement: 'left-start',
          offset: 36,
          shift: true,
        }}
        renderToggle={({ isOpen, onToggle }) => (
          <>
            <Button
              __next40pxDefaultSize
              onClick={onToggle}
              aria-expanded={isOpen}
              aria-haspopup='dialog'
              aria-label={toggleLabel}
              ref={toggleRef}
              className={[
                'aa-adaptive-color-channel__toggle',
                isOpen ? 'is-open' : '',
              ]
                .filter(Boolean)
                .join(' ')}
            >
              <HStack justify='flex-start'>
                <ColorIndicator
                  className='aa-adaptive-color-channel__indicator'
                  colorValue={indicatorValue}
                />
                <FlexItem
                  className='aa-adaptive-color-channel__name'
                  title={label}
                >
                  {label}
                </FlexItem>
              </HStack>
            </Button>
            {hasValue && (
              <Button
                __next40pxDefaultSize
                label={sprintf(
                  /* translators: %s: Color or Gradient */
                  __('Reset %s', 'aggressive-apparel'),
                  label
                )}
                className='aa-adaptive-color-channel__reset'
                size='small'
                icon={resetIcon}
                onClick={() => {
                  onClear();
                  if (isOpen) {
                    onToggle();
                  }
                  toggleRef.current?.focus();
                }}
              />
            )}
          </>
        )}
        renderContent={() => (
          <DropdownContentWrapper paddingSize='none'>
            <div className='aa-adaptive-color-channel__popover'>
              <ColorGradientControl
                label={toggleLabel}
                showTitle={false}
                __experimentalIsRenderedInSidebar
                colors={isGradient ? [] : colors}
                gradients={isGradient ? gradients : []}
                disableCustomColors={isGradient || disableCustomColors}
                disableCustomGradients={!isGradient}
                colorValue={isGradient ? undefined : displayValue}
                gradientValue={isGradient ? displayValue : undefined}
                onColorChange={isGradient ? undefined : onApply}
                onGradientChange={isGradient ? onApply : undefined}
                clearable
              />
            </div>
          </DropdownContentWrapper>
        )}
      />
    </div>
  );
}

/**
 * Core Styles → Color → Link: stacked indicators + Default / Hover tabs.
 */
function AdaptiveColorDualTabRow({
  label,
  defaultSetting,
  hoverSetting,
  colorMode,
  colors,
  disableCustomColors,
  adaptivePairs,
}: {
  label: string;
  defaultSetting: AdaptiveColorSetting;
  hoverSetting: AdaptiveColorSetting;
  colorMode: ColorScheme;
  colors: unknown;
  disableCustomColors?: boolean;
  adaptivePairs: readonly AdaptivePalettePair[];
}) {
  const toggleRef = useRef<HTMLButtonElement | null>(null);
  const presets = useMemo(
    () => flattenPresetColors(colors as PresetColorOrigin[] | undefined),
    [colors]
  );

  const defaultState = useMemo(
    () =>
      getChannelPickerState(
        colorMode,
        defaultSetting.value,
        presets,
        adaptivePairs
      ),
    [adaptivePairs, colorMode, defaultSetting.value, presets]
  );
  const hoverState = useMemo(
    () =>
      getChannelPickerState(
        colorMode,
        hoverSetting.value,
        presets,
        adaptivePairs
      ),
    [adaptivePairs, colorMode, hoverSetting.value, presets]
  );

  const applyDefault = useCallback(
    (next?: string): void => {
      defaultSetting.onChange(
        applyPickerColorToPair(
          colorMode,
          defaultSetting.value,
          next,
          presets,
          adaptivePairs
        )
      );
    },
    [adaptivePairs, colorMode, defaultSetting, presets]
  );

  const applyHover = useCallback(
    (next?: string): void => {
      hoverSetting.onChange(
        applyPickerColorToPair(
          colorMode,
          hoverSetting.value,
          next,
          presets,
          adaptivePairs
        )
      );
    },
    [adaptivePairs, colorMode, hoverSetting, presets]
  );

  const hasValue =
    hasAdaptivePairValue(defaultSetting.value) ||
    hasAdaptivePairValue(hoverSetting.value);

  const clearBoth = useCallback((): void => {
    defaultSetting.onChange(undefined);
    hoverSetting.onChange(undefined);
  }, [defaultSetting, hoverSetting]);

  const defaultIndicator = defaultState.sideIsGradient
    ? undefined
    : defaultState.indicatorValue;
  const hoverIndicator = hoverState.sideIsGradient
    ? undefined
    : hoverState.indicatorValue;

  const tabs = useMemo(
    () => [
      {
        name: 'default',
        title: __('Default', 'aggressive-apparel'),
        colorValue: defaultState.colorValue,
        onApply: applyDefault,
      },
      {
        name: 'hover',
        title: __('Hover', 'aggressive-apparel'),
        colorValue: hoverState.colorValue,
        onApply: applyHover,
      },
    ],
    [applyDefault, applyHover, defaultState.colorValue, hoverState.colorValue]
  );

  return (
    <div className='aa-adaptive-color-channel__item'>
      <Dropdown
        className='aa-adaptive-color-channel__dropdown'
        popoverProps={{
          placement: 'left-start',
          offset: 36,
          shift: true,
        }}
        renderToggle={({ isOpen, onToggle }) => (
          <>
            <Button
              __next40pxDefaultSize
              onClick={onToggle}
              aria-expanded={isOpen}
              aria-haspopup='dialog'
              aria-label={label}
              ref={toggleRef}
              className={[
                'aa-adaptive-color-channel__toggle',
                isOpen ? 'is-open' : '',
              ]
                .filter(Boolean)
                .join(' ')}
            >
              <HStack justify='flex-start'>
                <ZStack isLayered={false} offset={-8}>
                  {[defaultIndicator, hoverIndicator].map(
                    (indicator, index) => (
                      <Flex key={index} expanded={false}>
                        <ColorIndicator
                          className='aa-adaptive-color-channel__indicator'
                          colorValue={indicator}
                        />
                      </Flex>
                    )
                  )}
                </ZStack>
                <FlexItem
                  className='aa-adaptive-color-channel__name'
                  title={label}
                >
                  {label}
                </FlexItem>
              </HStack>
            </Button>
            {hasValue && (
              <Button
                __next40pxDefaultSize
                label={sprintf(
                  /* translators: %s: channel label (Link) */
                  __('Reset %s', 'aggressive-apparel'),
                  label
                )}
                className='aa-adaptive-color-channel__reset'
                size='small'
                icon={resetIcon}
                onClick={() => {
                  clearBoth();
                  if (isOpen) {
                    onToggle();
                  }
                  toggleRef.current?.focus();
                }}
              />
            )}
          </>
        )}
        renderContent={() => (
          <DropdownContentWrapper paddingSize='none'>
            <div className='aa-adaptive-color-channel__popover aa-adaptive-color-channel__popover--tabs'>
              <TabPanel className='aa-adaptive-color-channel__tabs' tabs={tabs}>
                {tab => (
                  <div className='aa-adaptive-color-channel__tab-panel'>
                    <ColorGradientControl
                      label={`${label} ${tab.title}`}
                      showTitle={false}
                      __experimentalIsRenderedInSidebar
                      colors={colors}
                      gradients={[]}
                      disableCustomColors={disableCustomColors}
                      disableCustomGradients
                      colorValue={tab.colorValue}
                      onColorChange={tab.onApply}
                      clearable
                    />
                  </div>
                )}
              </TabPanel>
            </div>
          </DropdownContentWrapper>
        )}
      />
    </div>
  );
}

/**
 * Color (+ optional Gradient) rows for one adaptive setting.
 */
function AdaptiveColorSettingRows({
  setting,
  sectionLabel,
  colorMode,
  colors,
  gradients,
  disableCustomColors,
  adaptivePairs,
}: {
  setting: AdaptiveColorSetting;
  sectionLabel: string;
  colorMode: ColorScheme;
  colors: unknown;
  gradients: unknown;
  disableCustomColors?: boolean;
  adaptivePairs: readonly AdaptivePalettePair[];
}) {
  const presets = useMemo(
    () => flattenPresetColors(colors as PresetColorOrigin[] | undefined),
    [colors]
  );

  const pickerState = useMemo(
    () =>
      getChannelPickerState(colorMode, setting.value, presets, adaptivePairs),
    [adaptivePairs, colorMode, presets, setting.value]
  );

  const applyValue = useCallback(
    (next?: string): void => {
      setting.onChange(
        applyPickerColorToPair(
          colorMode,
          setting.value,
          next,
          presets,
          adaptivePairs
        )
      );
    },
    [adaptivePairs, colorMode, presets, setting]
  );

  const clearColor = useCallback((): void => {
    if (pickerState.sideIsGradient) {
      return;
    }
    setting.onChange(
      applyPickerColorToPair(
        colorMode,
        setting.value,
        undefined,
        presets,
        adaptivePairs
      )
    );
  }, [adaptivePairs, colorMode, pickerState.sideIsGradient, presets, setting]);

  const clearGradient = useCallback((): void => {
    if (!pickerState.sideIsGradient) {
      return;
    }
    setting.onChange(
      applyPickerColorToPair(
        colorMode,
        setting.value,
        undefined,
        presets,
        adaptivePairs
      )
    );
  }, [adaptivePairs, colorMode, pickerState.sideIsGradient, presets, setting]);

  const rowLabel = setting.rowLabel ?? __('Color', 'aggressive-apparel');

  return (
    <>
      <AdaptiveColorDropdownRow
        kind='color'
        channelLabel={`${sectionLabel} ${rowLabel}`}
        label={rowLabel}
        displayValue={pickerState.colorValue}
        indicatorValue={
          pickerState.sideIsGradient ? undefined : pickerState.indicatorValue
        }
        onApply={applyValue}
        onClear={clearColor}
        colors={colors}
        gradients={gradients}
        disableCustomColors={disableCustomColors}
      />
      {setting.allowGradient && (
        <AdaptiveColorDropdownRow
          kind='gradient'
          channelLabel={sectionLabel}
          label={__('Gradient', 'aggressive-apparel')}
          displayValue={pickerState.gradientValue}
          indicatorValue={
            pickerState.sideIsGradient ? pickerState.indicatorValue : undefined
          }
          onApply={applyValue}
          onClear={clearGradient}
          colors={colors}
          gradients={gradients}
          disableCustomColors={disableCustomColors}
        />
      )}
    </>
  );
}

/**
 * Group settings that share a section (e.g. Link dual-tabs).
 */
function groupAdaptiveSettings(settings: AdaptiveColorSetting[]): Array<{
  key: string;
  label: string;
  presentation: 'rows' | 'dual-tabs';
  rows: AdaptiveColorSetting[];
}> {
  const groups: Array<{
    key: string;
    label: string;
    presentation: 'rows' | 'dual-tabs';
    rows: AdaptiveColorSetting[];
  }> = [];
  const indexByKey = new Map<string, number>();

  for (const setting of settings) {
    const key = setting.group ?? setting.id;
    const existing = indexByKey.get(key);
    if (existing !== undefined) {
      groups[existing].rows.push(setting);
      continue;
    }
    indexByKey.set(key, groups.length);
    groups.push({
      key,
      label: setting.label,
      presentation: setting.presentation ?? 'rows',
      rows: [setting],
    });
  }

  return groups;
}

/**
 * Channel group: title + Color / Gradient rows, or dual-tab Link row.
 */
function AdaptiveColorChannelGroup({
  label,
  presentation,
  rows,
  colorMode,
  colors,
  gradients,
  disableCustomColors,
  adaptivePairs,
}: {
  label: string;
  presentation: 'rows' | 'dual-tabs';
  rows: AdaptiveColorSetting[];
  colorMode: ColorScheme;
  colors: unknown;
  gradients: unknown;
  disableCustomColors?: boolean;
  adaptivePairs: readonly AdaptivePalettePair[];
}) {
  const isDualTabs = presentation === 'dual-tabs' && rows.length >= 2;

  return (
    <div className='aa-adaptive-color-channel'>
      {!isDualTabs && (
        <div className='aa-adaptive-color-channel__title'>{label}</div>
      )}
      <div className='aa-adaptive-color-channel__items'>
        {isDualTabs ? (
          <AdaptiveColorDualTabRow
            label={label}
            defaultSetting={rows[0]}
            hoverSetting={rows[1]}
            colorMode={colorMode}
            colors={colors}
            disableCustomColors={disableCustomColors}
            adaptivePairs={adaptivePairs}
          />
        ) : (
          rows.map(setting => (
            <AdaptiveColorSettingRows
              key={setting.id}
              setting={setting}
              sectionLabel={label}
              colorMode={colorMode}
              colors={colors}
              gradients={gradients}
              disableCustomColors={disableCustomColors}
              adaptivePairs={adaptivePairs}
            />
          ))
        )}
      </div>
    </div>
  );
}

/**
 * Native WordPress color/gradient rows for adaptive channel settings.
 */
/**
 * theme.json adaptiveColors via block editor settings (no extra network).
 */
function useAdaptivePalettePairs(): readonly AdaptivePalettePair[] {
  const [raw] = useSettings('custom.adaptiveColors');

  return useMemo(() => {
    if (!Array.isArray(raw)) {
      return [];
    }

    return raw.filter((entry): entry is AdaptivePalettePair =>
      Boolean(
        entry &&
        typeof entry === 'object' &&
        typeof (entry as AdaptivePalettePair).slug === 'string' &&
        typeof (entry as AdaptivePalettePair).light === 'string' &&
        typeof (entry as AdaptivePalettePair).dark === 'string'
      )
    );
  }, [raw]);
}

export function AdaptiveColorSettingsDropdown({
  settings,
}: {
  panelId?: string;
  settings: AdaptiveColorSetting[];
}) {
  const { colorMode } = useEditorColorScheme();
  const colorGradientSettings = useMultipleOriginColorsAndGradients();
  const adaptivePairs = useAdaptivePalettePairs();
  const groups = useMemo(() => groupAdaptiveSettings(settings), [settings]);

  if (!settings.length) {
    return null;
  }

  return (
    <div className='aa-adaptive-color-panel__channels'>
      {groups.map(group => (
        <AdaptiveColorChannelGroup
          key={group.key}
          label={group.label}
          presentation={group.presentation}
          rows={group.rows}
          colorMode={colorMode}
          colors={colorGradientSettings.colors}
          gradients={colorGradientSettings.gradients}
          disableCustomColors={Boolean(
            colorGradientSettings.disableCustomColors
          )}
          adaptivePairs={adaptivePairs}
        />
      ))}
    </div>
  );
}

/**
 * Full Adaptive Color panel body (header tabs + native settings + conflicts).
 */
export function AdaptiveColorPanelBody({
  panelId,
  settings,
  description,
}: {
  panelId?: string;
  settings: AdaptiveColorSetting[];
  description?: string;
}) {
  const showConflict = settings.some(
    setting => setting.hasCoreConflict && hasAdaptivePairValue(setting.value)
  );

  return (
    <div className='aa-adaptive-color-panel'>
      <AdaptiveColorPanelHeader description={description} />
      <AdaptiveColorSettingsDropdown panelId={panelId} settings={settings} />
      {showConflict && (
        <div
          className='aa-adaptive-color-panel__notice'
          style={{ ...EDITOR_INFO_NOTICE_STYLE, marginTop: '8px' }}
          role='status'
        >
          <p style={{ margin: 0, fontSize: '12px' }}>
            {__(
              'Adaptive Color overrides the matching standard color settings for this block.',
              'aggressive-apparel'
            )}
          </p>
        </div>
      )}
    </div>
  );
}

/**
 * Adaptive controls for string attributes stored as `light-dark(...)`.
 */
export function AdaptiveCssValueSettingsDropdown({
  panelId,
  settings,
}: {
  panelId?: string;
  settings: Array<{
    id: string;
    label: string;
    value: string | undefined;
    onChange: (value: string | undefined) => void;
    allowGradient?: boolean;
  }>;
}) {
  const pairSettings: AdaptiveColorSetting[] = settings.map(setting => ({
    id: setting.id,
    label: setting.label,
    allowGradient: setting.allowGradient,
    value: pairFromCssValue(setting.value),
    onChange: pair => setting.onChange(pairToCssAttribute(pair)),
  }));

  return (
    <AdaptiveColorSettingsDropdown panelId={panelId} settings={pairSettings} />
  );
}
