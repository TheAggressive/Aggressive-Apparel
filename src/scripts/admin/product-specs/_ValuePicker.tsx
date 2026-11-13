/**
 * Product Specs settings — one spec's value, picked the way it shows.
 *
 * Scales are their steps as a segmented bar (a radio group, arrow keys move).
 * A text detail is a plain field (SpecInput) that suggests the attribute's
 * existing values as you type and settles the case on one when you leave it,
 * so near-duplicates like "Cotton" and "cotton" don't pile up as separate
 * terms. A text spec that holds several values (like Collection: Mens,
 * Womens) is a token field instead. The most used values sit under the field
 * as one-click chips. Numbers show their unit in the field and the step the
 * number lands on beside it.
 *
 * Values are what the REST API takes: a step index, a number, or text.
 *
 * @package Aggressive_Apparel
 */

import { useRef, useState } from '@wordpress/element';
import type { KeyboardEvent } from 'react';
import { Button, FormTokenField } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type { SpecItem } from './_types';
import {
	joinValues,
	settleTerm,
	splitValues,
	stepFor,
	unlistedValues,
} from './_utils';
import SpecInput from './_SpecInput';

type Props = {
	item: SpecItem;
	value: string;
	/** Accessible name (the visible name sits beside the field). */
	label: string;
	/** What shows when the value is empty, if anything. */
	inherited?: string;
	/**
	 * The user can't add catalog values: say so when a value may be new. The
	 * server keeps the product's own value if it is (never cleared here).
	 */
	existingOnly?: boolean;
	onChange: ( value: string ) => void;
};

function ScalePicker( { item, value, label, inherited, onChange }: Props ) {
	const current = value === '' ? -1 : Number( value );
	const buttons = useRef< ( HTMLButtonElement | null )[] >( [] );
	const last = item.steps.length - 1;

	const move = ( event: KeyboardEvent, from: number ) => {
		const to =
			event.key === 'ArrowRight' || event.key === 'ArrowDown'
				? Math.min( last, from + 1 )
				: event.key === 'ArrowLeft' || event.key === 'ArrowUp'
					? Math.max( 0, from - 1 )
					: event.key === 'Home'
						? 0
						: event.key === 'End'
							? last
							: null;
		if ( to !== null ) {
			event.preventDefault();
			onChange( String( to ) );
			buttons.current[ to ]?.focus();
		}
	};

	return (
		<div className="aa-ps-picker">
			<div
				role="radiogroup"
				aria-label={ label }
				className="aa-ps-segments"
				style={ {
					gridTemplateColumns: `repeat(${ item.steps.length }, minmax(0, 1fr))`,
				} }
			>
				{ item.steps.map( ( step, i ) => (
					<button
						key={ i }
						ref={ ( element ) => {
							buttons.current[ i ] = element;
						} }
						type="button"
						role="radio"
						aria-checked={ i === current }
						// One tab stop: the chosen step, or the first.
						tabIndex={ i === Math.max( 0, current ) ? 0 : -1 }
						className={ cx(
							'aa-ps-segments__step',
							i === current && 'is-active'
						) }
						onClick={ () => onChange( String( i ) ) }
						onKeyDown={ ( event ) => move( event, i ) }
					>
						{ step }
					</button>
				) ) }
			</div>
			<div className="aa-ps-picker__meta">
				{ current >= 0 ? (
					<Button
						variant="link"
						className="aa-ps-picker__clear"
						onClick={ () => onChange( '' ) }
					>
						{ inherited
							? sprintf(
									/* translators: %s: the value shown when this one is cleared. */
									__(
										'Clear (use %s)',
										'aggressive-apparel'
									),
									inherited
								)
							: __( 'Clear', 'aggressive-apparel' ) }
					</Button>
				) : (
					<span className="aa-ps-picker__hint">
						{ inherited
							? sprintf(
									/* translators: %s: the store-wide default value. */
									__(
										'Not set — shows %s',
										'aggressive-apparel'
									),
									inherited
								)
							: __( 'Not set', 'aggressive-apparel' ) }
					</span>
				) }
			</div>
		</div>
	);
}

/**
 * One text value: the field holds exactly what's typed, with the
 * attribute's values suggested as you type. Leaving it settles the case on
 * an existing value ("mens" saves as "Mens").
 */
function TermPicker( {
	item,
	value,
	label,
	inherited,
	existingOnly = false,
	onChange,
}: Props ) {
	const terms = item.terms ?? [];
	// What the field had: never treated as new.
	const [ kept ] = useState( () => splitValues( value ) );
	const unlisted = existingOnly
		? unlistedValues( splitValues( value ), terms, kept )
		: [];
	return (
		<div className="aa-ps-picker">
			<SpecInput
				item={ item }
				value={ value }
				label={ label }
				// What shows while it's empty.
				inherited={
					inherited
						? sprintf(
								/* translators: %s: the store-wide default value. */
								__(
									'Not set — shows %s',
									'aggressive-apparel'
								),
								inherited
							)
						: __( 'Type or pick a value', 'aggressive-apparel' )
				}
				onChange={ onChange }
				onBlur={ () => {
					const settled = settleTerm( terms, value );
					if ( settled !== value ) {
						onChange( settled );
					}
				} }
			/>
			<NewValueNote names={ unlisted } />
			{ value === '' && (
				<Chips terms={ terms } chosen={ [] } onPick={ onChange } />
			) }
		</div>
	);
}

/**
 * For someone who can't add catalog values: these may be new, and a new
 * value won't save (the product keeps the value it had).
 */
function NewValueNote( { names }: { names: string[] } ) {
	if ( names.length === 0 ) {
		return null;
	}
	return (
		<span className="aa-ps-picker__hint">
			{ sprintf(
				/* translators: %s: values typed, comma-separated. */
				__(
					'Only values the store has save for you. If “%s” is new, the product keeps the value it had: ask a store manager to add it.',
					'aggressive-apparel'
				),
				names.join( '”, “' )
			) }
		</span>
	);
}

/** Most used values not chosen yet, as one-click chips. */
function Chips( {
	terms,
	chosen,
	onPick,
}: {
	terms: { name: string; count: number }[];
	chosen: string[];
	onPick: ( name: string ) => void;
} ) {
	const left = terms
		.filter( ( term ) => ! chosen.includes( term.name ) )
		.slice( 0, 6 );
	if ( left.length === 0 ) {
		return null;
	}
	return (
		<div
			className="aa-ps-chips"
			role="group"
			aria-label={ __( 'Used values', 'aggressive-apparel' ) }
		>
			{ left.map( ( term ) => (
				<button
					key={ term.name }
					type="button"
					className="aa-ps-chip"
					onClick={ () => onPick( term.name ) }
				>
					{ term.name }
					{ term.count > 0 && (
						<span className="aa-ps-chip__count">
							{ term.count }
						</span>
					) }
				</button>
			) ) }
		</div>
	);
}

function TokensPicker( {
	item,
	value,
	label,
	inherited,
	existingOnly = false,
	onChange,
}: Props ) {
	const terms = item.terms ?? [];
	const [ kept ] = useState( () => splitValues( value ) );
	const unlisted = existingOnly
		? unlistedValues( splitValues( value ), terms, kept )
		: [];
	const counts = new Map(
		terms.map( ( term ) => [ term.name, term.count ] )
	);

	return (
		<div className="aa-ps-picker aa-ps-picker--tokens">
			<FormTokenField
				label={ label }
				value={ splitValues( value ) }
				suggestions={ terms.map( ( term ) => term.name ) }
				onChange={ ( tokens ) =>
					onChange(
						joinValues(
							splitValues(
								tokens
									.map( ( token ) =>
										settleTerm(
											terms,
											typeof token === 'string'
												? token
												: token.value
										)
									)
									.join( '|' )
							)
						)
					)
				}
				placeholder={
					value === ''
						? inherited
							? sprintf(
									/* translators: %s: the store-wide default values. */
									__(
										'Not set — shows %s',
										'aggressive-apparel'
									),
									inherited
								)
							: __( 'Type or pick values', 'aggressive-apparel' )
						: ''
				}
				__experimentalShowHowTo={ false }
				__experimentalRenderItem={ ( { item: name } ) => (
					<span className="aa-ps-term-option">
						<span>{ name }</span>
						{ ( counts.get( name ) ?? 0 ) > 0 && (
							<span className="aa-ps-term-option__count">
								{ sprintf(
									/* translators: %d: number of products with this value. */
									_n(
										'%d product',
										'%d products',
										counts.get( name ) ?? 0,
										'aggressive-apparel'
									),
									counts.get( name ) ?? 0
								) }
							</span>
						) }
					</span>
				) }
				tokenizeOnBlur
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<NewValueNote names={ unlisted } />
			<Chips
				terms={ terms }
				chosen={ splitValues( value ) }
				onPick={ ( name ) =>
					onChange( joinValues( [ ...splitValues( value ), name ] ) )
				}
			/>
		</div>
	);
}

function NumberPicker( { item, value, label, inherited, onChange }: Props ) {
	const number = Number( value );
	const step =
		value !== '' && number > 0
			? item.steps[ stepFor( number, item.thresholds ) ]
			: '';

	return (
		<div className="aa-ps-picker aa-ps-picker--number">
			<span className="aa-ps-number">
				<input
					className="aa-ps-number__input"
					aria-label={
						item.unit ? `${ label } (${ item.unit })` : label
					}
					type="number"
					min={ 0 }
					step="any"
					inputMode="decimal"
					value={ value }
					placeholder={ inherited ?? '' }
					onChange={ ( event ) => onChange( event.target.value ) }
				/>
				{ item.unit && (
					<span className="aa-ps-number__unit" aria-hidden="true">
						{ item.unit }
					</span>
				) }
			</span>
			<span className="aa-ps-picker__hint" aria-live="polite">
				{ step ||
					( inherited
						? sprintf(
								/* translators: %s: the store-wide default value. */
								__(
									'Not set — shows %s',
									'aggressive-apparel'
								),
								inherited
							)
						: '' ) }
			</span>
		</div>
	);
}

export default function ValuePicker( props: Props ) {
	if ( props.item.type === 'pick' ) {
		return <ScalePicker { ...props } />;
	}
	if ( props.item.type === 'text' ) {
		return props.item.multiple ? (
			<TokensPicker { ...props } />
		) : (
			<TermPicker { ...props } />
		);
	}
	return <NumberPicker { ...props } />;
}
