/**
 * Sale Countdown Timer Block — Editor Component.
 *
 * @package Aggressive_Apparel
 * @since 1.87.0
 */

import { __ } from '@wordpress/i18n';
import {
  useBlockProps,
  InspectorControls,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
  Button,
  DateTimePicker,
  Dropdown,
  PanelBody,
  SelectControl,
  TextControl,
} from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { calendar } from '@wordpress/icons';
import {
  normalizeCountdownDisplayStyle,
  getCountdownColorStyles,
  getCountdownDisplayStyleOptions,
  type CountdownDisplayStyle,
  type CountdownTimerAttributes,
} from './display-styles';

const COUNTDOWN_SEGMENTS = ['d', 'h', 'm', 's'] as const;

function getRemaining(endDateTime: string): [number, number, number, number] {
  const endMs = Date.parse(endDateTime);
  if (!Number.isFinite(endMs)) {
    return [0, 0, 0, 0];
  }

  const diff = Math.max(0, Math.floor((endMs - Date.now()) / 1000));

  return [
    Math.floor(diff / 86400),
    Math.floor((diff % 86400) / 3600),
    Math.floor((diff % 3600) / 60),
    diff % 60,
  ];
}

function formatDeadline(endDateTime: string): string {
  if (!endDateTime) {
    return __('Product sale end date', 'aggressive-apparel');
  }

  const date = new Date(endDateTime);
  if (!Number.isFinite(date.getTime())) {
    return __('Invalid deadline', 'aggressive-apparel');
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date);
}

export default function Edit({
  attributes,
  setAttributes,
  clientId,
}: BlockEditProps<CountdownTimerAttributes>) {
  const displayStyleOptions = useMemo(getCountdownDisplayStyleOptions, []);
  const colorGradientSettings = useMultipleOriginColorsAndGradients();
  const [remaining, setRemaining] = useState<[number, number, number, number]>(
    () => getRemaining(attributes.endDateTime)
  );
  const displayStyle = normalizeCountdownDisplayStyle(attributes.displayStyle);
  const blockProps = useBlockProps({
    className: `aggressive-apparel-countdown aggressive-apparel-countdown--${displayStyle}`,
    style: getCountdownColorStyles(attributes),
  });

  useEffect(() => {
    setRemaining(getRemaining(attributes.endDateTime));

    if (!attributes.endDateTime) {
      return undefined;
    }

    const intervalId = window.setInterval(() => {
      setRemaining(getRemaining(attributes.endDateTime));
    }, 1000);

    return () => window.clearInterval(intervalId);
  }, [attributes.endDateTime]);

  const previewValues = attributes.endDateTime
    ? remaining
    : ['--', '--', '--', '--'];
  const deadlineSummary = formatDeadline(attributes.endDateTime);

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Appearance', 'aggressive-apparel')}>
          <SelectControl<CountdownDisplayStyle>
            label={__('Style', 'aggressive-apparel')}
            value={displayStyle}
            options={displayStyleOptions}
            onChange={(value: CountdownDisplayStyle) =>
              setAttributes({ displayStyle: value })
            }
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        </PanelBody>
        <PanelBody title={__('Deadline & Redirect', 'aggressive-apparel')}>
          <div className='aa-countdown-deadline-control'>
            <div className='aa-countdown-deadline-control__row'>
              <span className='components-base-control__label aa-countdown-deadline-control__label'>
                {__('Deadline', 'aggressive-apparel')}
              </span>
              <Dropdown
                className='aa-countdown-deadline-control__dropdown'
                contentClassName='aa-countdown-deadline-popover'
                expandOnMobile
                headerTitle={__('Countdown deadline', 'aggressive-apparel')}
                popoverProps={{ placement: 'left-start' }}
                renderToggle={({ isOpen, onToggle }) => (
                  <Button
                    className='aa-countdown-deadline-control__toggle'
                    icon={calendar}
                    onClick={onToggle}
                    variant='secondary'
                    aria-expanded={isOpen}
                    aria-haspopup='dialog'
                  >
                    <span className='aa-countdown-deadline-control__toggle-text'>
                      {deadlineSummary}
                    </span>
                  </Button>
                )}
                renderContent={({ onClose }) => (
                  <div className='aa-countdown-deadline-popover__inner'>
                    <DateTimePicker
                      currentDate={
                        attributes.endDateTime || new Date().toISOString()
                      }
                      onChange={(val: string | null) =>
                        setAttributes({ endDateTime: val || '' })
                      }
                      dateOrder='mdy'
                      is12Hour={true}
                    />
                    <div className='aa-countdown-deadline-popover__actions'>
                      {attributes.endDateTime && (
                        <Button
                          variant='tertiary'
                          onClick={() => setAttributes({ endDateTime: '' })}
                        >
                          {__(
                            'Use product sale end date',
                            'aggressive-apparel'
                          )}
                        </Button>
                      )}
                      <Button variant='primary' onClick={onClose}>
                        {__('Done', 'aggressive-apparel')}
                      </Button>
                    </div>
                  </div>
                )}
              />
            </div>
            <p className='components-base-control__help'>
              {attributes.endDateTime
                ? __('Manual deadline for this block.', 'aggressive-apparel')
                : __(
                    'Uses the current product sale end date when available.',
                    'aggressive-apparel'
                  )}
            </p>
          </div>
          <TextControl
            label={__('Drop Page URL', 'aggressive-apparel')}
            help={__(
              'Redirect visitors to this URL the moment the countdown reaches zero. Leave empty to do nothing.',
              'aggressive-apparel'
            )}
            value={attributes.dropPageUrl}
            onChange={(val: string) => setAttributes({ dropPageUrl: val })}
            type='url'
            placeholder='https://'
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        </PanelBody>
      </InspectorControls>
      <InspectorControls group='color'>
        <ColorGradientSettingsDropdown
          panelId={clientId}
          settings={[
            {
              label: __('Sale label', 'aggressive-apparel'),
              colorValue: attributes.saleLabelColor || undefined,
              onColorChange: (value?: string) =>
                setAttributes({ saleLabelColor: value ?? '' }),
            },
            {
              label: __('Time numbers', 'aggressive-apparel'),
              colorValue: attributes.timeValueColor || undefined,
              onColorChange: (value?: string) =>
                setAttributes({ timeValueColor: value ?? '' }),
            },
            {
              label: __('Unit labels', 'aggressive-apparel'),
              colorValue: attributes.unitLabelColor || undefined,
              onColorChange: (value?: string) =>
                setAttributes({ unitLabelColor: value ?? '' }),
            },
            {
              label: __('Timer border', 'aggressive-apparel'),
              colorValue: attributes.timerBorderColor || undefined,
              onColorChange: (value?: string) =>
                setAttributes({ timerBorderColor: value ?? '' }),
            },
          ]}
          __experimentalIsRenderedInSidebar
          {...colorGradientSettings}
        />
      </InspectorControls>
      <div {...blockProps}>
        <span className='aggressive-apparel-countdown__label'>
          {__('Sale ends in', 'aggressive-apparel')}
        </span>
        {COUNTDOWN_SEGMENTS.map((unit, index) => (
          <span key={unit} className='aggressive-apparel-countdown__segment'>
            <span className='aggressive-apparel-countdown__value'>
              {previewValues[index]}
            </span>
            <span className='aggressive-apparel-countdown__unit'>{unit}</span>
          </span>
        ))}
      </div>
    </>
  );
}
