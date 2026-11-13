/**
 * Product Specs settings — helpers.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import type { DraftItem, ItemType, SpecItem } from './_types';

let counter = 0;

/**
 * A failed request's message, else the fallback, plus any per-item problems
 * the server listed (data.errors).
 */
export function errorText( failure: unknown, fallback: string ): string {
	const { message, data } = ( failure ?? {} ) as {
		message?: string;
		data?: { errors?: string[] };
	};
	return [ message || fallback, ...( data?.errors ?? [] ) ].join( ' ' );
}

/** Stable React key for an item that has no saved key yet. */
export function uid(): string {
	counter += 1;
	return `item-${ Date.now() }-${ counter }`;
}

/**
 * A name as it compares (spaces collapsed, any case), as the server compares
 * names (Match_Name::key): "Regular  fit" matches "regular fit".
 */
export function matchKey( name: string ): string {
	return name.trim().replace( /\s+/g, ' ' ).toLocaleLowerCase();
}

/**
 * Typed text as a value: an existing one if it matches whatever the case and
 * spacing ("mens" is "Mens"), else the text, trimmed. No "|", which divides
 * a list of values.
 */
export function settleTerm(
	terms: { name: string }[] | undefined,
	text: string
): string {
	const clean = text.replace( /\|/g, '' ).trim();
	return (
		( terms ?? [] ).find(
			( term ) => matchKey( term.name ) === matchKey( clean )
		)?.name ?? clean
	);
}

/**
 * Values that are neither among the terms listed nor ones the field already
 * had. The list is the most used terms, not all of them, so these are only
 * possibly new: the server, which has every term, decides.
 */
export function unlistedValues(
	values: string[],
	terms: { name: string }[] | undefined,
	kept: string[]
): string[] {
	const known = new Set(
		[ ...( terms ?? [] ).map( ( term ) => term.name ), ...kept ].map(
			matchKey
		)
	);
	return values.filter(
		( value ) => value !== '' && ! known.has( matchKey( value ) )
	);
}

/** Meta-key-safe key from a name: "Fabric Weight" → "fabric_weight". */
export function slugify( name: string ): string {
	return name
		.toLowerCase()
		.normalize( 'NFKD' )
		.replace( /[^a-z0-9]+/g, '_' )
		.replace( /^_+|_+$/g, '' )
		.slice( 0, 32 );
}

/** A saved spec for editing. Its key is unique and fixed, so it's the uid. */
export function toDraft( item: SpecItem ): DraftItem {
	return { ...item, uid: `spec:${ item.key }`, isNew: false };
}

/** Strip editor-only fields (and the values sent to pick from) before saving. */
export function toStored( item: DraftItem | SpecItem ): SpecItem {
	const {
		uid: _uid,
		isNew: _isNew,
		terms: _terms,
		...stored
	} = item as DraftItem;
	return { ...stored, key: stored.key || slugify( stored.name ) };
}

/** Default label for start/middle/end when left empty. */
export function edgeLabels( item: SpecItem ): {
	start: string;
	mid: string;
	end: string;
} {
	const count = item.steps.length;
	return {
		start: item.start || item.steps[ 0 ] || '',
		mid:
			item.mid ||
			( count % 2 === 1 ? item.steps[ Math.floor( count / 2 ) ] : '' ),
		end: item.end || item.steps[ count - 1 ] || '',
	};
}

/** Step reached by a number given ascending boundaries. */
export function stepFor( value: number, thresholds: number[] ): number {
	return thresholds.filter( ( boundary ) => value >= boundary ).length;
}

/** A new, empty item of a type. */
export function blankItem( type: ItemType, steps: string[] ): DraftItem {
	return {
		key: '',
		type,
		name: '',
		enabled: true,
		steps: type === 'text' ? [] : steps,
		start: '',
		mid: '',
		end: '',
		centered: false,
		unit: '',
		thresholds:
			type === 'number'
				? steps.slice( 1 ).map( ( _, i ) => ( i + 1 ) * 10 )
				: [],
		advice: [],
		attributes: [],
		scope: 'all',
		categories: [],
		category_steps: [],
		in_table: true,
		default: '',
		default_term: 0,
		taxonomy: '',
		step_terms: [],
		multiple: false,
		archives: false,
		uid: uid(),
		isNew: true,
	};
}

/**
 * A new draft item from a preset, suggestion, or blank type, keyed so it never
 * shares another item's values (a second "Fit" becomes fit_2).
 *
 * @param partial Fields to start from.
 * @param taken   Items whose keys are in use (the whole registry).
 */
export function newItem(
	partial: Partial< SpecItem > & Pick< SpecItem, 'type' >,
	taken: SpecItem[]
): DraftItem {
	const base = blankItem( partial.type, [
		__( 'Low', 'aggressive-apparel' ),
		__( 'Medium', 'aggressive-apparel' ),
		__( 'High', 'aggressive-apparel' ),
	] );
	const item: DraftItem = { ...base, ...partial, uid: base.uid, isNew: true };

	item.key = uniqueKey( item.key || slugify( item.name ), taken );

	return item;
}

/** Longest key the server keeps (Size_Guide_Spec_Items::key()). */
export const KEY_MAX = 32;

/**
 * The key, or the first "key_N" no other item uses ('' stays ''), at most
 * KEY_MAX characters, the way the server picks one, so both agree.
 */
export function uniqueKey( stem: string, taken: SpecItem[] ): string {
	const keys = new Set(
		taken.map( ( existing ) => existing.key || slugify( existing.name ) )
	);
	const base = stem.slice( 0, KEY_MAX );
	let key = base;
	for ( let n = 2; key && keys.has( key ); n++ ) {
		const suffix = `_${ n }`;
		key = base.slice( 0, KEY_MAX - suffix.length ) + suffix;
	}
	return key;
}

/**
 * A key for a spec whose value is edited before it is saved (a size guide's
 * own spec): from its name, or "spec", "spec_2"… when the name has no Latin
 * letters, so the value always has a key the server keeps.
 *
 * @param name  Spec name.
 * @param taken Items whose keys are in use.
 */
export function specKey( name: string, taken: SpecItem[] ): string {
	return uniqueKey( slugify( name ) || 'spec', taken );
}

/** Separator between several values, as WooCommerce writes attribute values. */
export const DELIMITER = '|';

/** The values in one value ("Mens | Womens" → ["Mens", "Womens"]). */
export function splitValues( value: string ): string[] {
	return [
		...new Set(
			value
				.split( DELIMITER )
				.map( ( part ) => part.trim() )
				.filter( Boolean )
		),
	];
}

/** Several values as one value. */
export function joinValues( values: string[] ): string {
	return values.join( ` ${ DELIMITER } ` );
}

/** Smooth scrolling, unless the user prefers less motion. */
export function scrollBehavior(): ScrollBehavior {
	return window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
		? 'auto'
		: 'smooth';
}

/** A stored value as people read it ('' for none). */
export function describeValue( item: SpecItem, value: string ): string {
	if ( value === '' ) {
		return '';
	}
	if ( item.type === 'pick' ) {
		return item.steps[ Number( value ) ] ?? '';
	}
	if ( item.type === 'number' ) {
		return `${ value } ${ item.unit }`.trim();
	}
	return item.multiple ? splitValues( value ).join( ', ' ) : value;
}
