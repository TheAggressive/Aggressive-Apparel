/**
 * Product Specs settings — import size guides from Printful.
 *
 * Three steps: choose blanks (_ImportSearch), review them side by side
 * (_ImportBlank, one hang tag per blank), and see what was created. A
 * decision about a description line applies to the same line on every blank
 * being imported, and a new attribute is created once and reused by the
 * others (store-wide) or shared with them (this guide only). Guides are made
 * through the ordinary create route, so each is a normal, editable guide.
 *
 * A blank the store already has a guide for is left alone (keepsExisting).
 * Blanks with nothing to sign off (importStops) can be created in one click
 * while the rest stay in review. Each category goes to the first blank that
 * fits it, since a category holds one guide.
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useRef, useState } from '@wordpress/element';
import { Button, Notice, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type {
	Config,
	GuideDetail,
	ImportAttribute,
	ImportBlankState,
	ImportDraft,
	ImportMapping,
	ImportResult,
	ImportStop,
	PrintfulProduct,
	Registry,
	SpecItem,
} from './_types';
import { errorText, scrollBehavior, specKey } from './_utils';
import { attributeItem, nameFrom, valueFor } from './_import';
import {
	findSaved,
	freeSuggestions,
	guideRequest,
	importStops,
	keepsExisting,
	liveTaken,
	savedKey,
	type ImportClaim,
	type ImportPlaced,
} from './_importGuide';
import ImportSearch from './_ImportSearch';
import ImportBlank from './_ImportBlank';
import ImportTag from './_ImportTag';
import ImportDone from './_ImportDone';

type Props = {
	config: Config;
	/** Saved registry items. */
	items: SpecItem[];
	/** Global attributes, taxonomy => label. */
	taxonomies: Record< string, string >;
	onCancel: () => void;
	onOpen: ( guide: number ) => void;
	onRegistry: ( registry: Registry ) => void;
};

const STEPS = [
	__( 'Choose blanks', 'aggressive-apparel' ),
	__( 'Review', 'aggressive-apparel' ),
	__( 'Done', 'aggressive-apparel' ),
];

/** The field or message to show for each thing a blank is waiting on. */
const STOP_TARGET: Record< ImportStop, string > = {
	decide: '.aa-ps-line.is-unsure select',
	confirm: '.aa-ps-line.is-guess button',
	name: '.aa-ps-review__name input',
	where: '.aa-ps-chips button',
	empty: '.aa-ps-review__stops',
};

export default function ImportFlow( {
	config,
	items,
	taxonomies,
	onCancel,
	onOpen,
	onRegistry,
}: Props ) {
	const path = `${ config.guidesPath }/printful`;
	const [ step, setStep ] = useState< 0 | 1 | 2 >( 0 );
	const [ selected, setSelected ] = useState< PrintfulProduct[] >( [] );
	// Blanks already sent to create, so a partial import can carry on.
	const [ finished, setFinished ] = useState< PrintfulProduct[] >( [] );
	const [ drafts, setDrafts ] = useState< Record< number, ImportDraft > >(
		{}
	);
	const [ states, setStates ] = useState<
		Record< number, ImportBlankState >
	>( {} );
	const [ created, setCreated ] = useState<
		Record< string, ImportAttribute >
	>( {} );
	const [ active, setActive ] = useState( 0 );
	const [ loading, setLoading ] = useState( false );
	const [ progress, setProgress ] = useState( 0 );
	const [ batch, setBatch ] = useState( 0 );
	// Categories the guides made so far hold, so the blanks left know.
	const [ placed, setPlaced ] = useState< ImportPlaced >( {} );
	const [ notice, setNotice ] = useState( '' );
	const [ results, setResults ] = useState< ImportResult[] >( [] );
	const [ jump, setJump ] = useState( 0 );
	// Specs made by this import: the key sent => the key saved. Kept across
	// retries, so trying a failed blank again never makes a spec twice.
	const madeRef = useRef( new Map< string, string >() );
	const [ error, setError ] = useState( '' );

	const specs = items.filter( ( item ) => item.enabled );
	const free = Object.fromEntries(
		Object.entries( taxonomies ).filter(
			( [ taxonomy ] ) =>
				! items.some( ( item ) => item.taxonomy === taxonomy )
		)
	);
	const kept = ( id: number ) =>
		Boolean(
			drafts[ id ] &&
			states[ id ] &&
			keepsExisting( drafts[ id ], states[ id ] )
		);
	const pending = ( id: number ) =>
		kept( id )
			? 0
			: ( states[ id ]?.mappings.filter(
					( mapping ) => mapping.target === ''
				).length ?? 0 );
	const totalPending = selected.reduce(
		( sum, product ) => sum + pending( product.id ),
		0
	);

	const toggle = ( product: PrintfulProduct ) =>
		setSelected(
			selected.some( ( other ) => other.id === product.id )
				? selected.filter( ( other ) => other.id !== product.id )
				: [ ...selected, product ]
		);

	/** A blank's first state; `claims` are the blanks already set up. */
	const startState = (
		draft: ImportDraft,
		claims: ImportClaim[]
	): ImportBlankState => {
		const kind =
			draft.charts.garment || ! draft.charts.body ? 'garment' : 'body';
		// A blank that keeps the existing guide claims nothing from the others.
		const categories =
			draft.guide > 0
				? []
				: freeSuggestions( draft, claims, placed, config.categories );
		return {
			title: draft.title,
			kind,
			notes: { ...draft.notes },
			withNotes: true,
			diagram: true,
			secondGuide: false,
			categories,
			taken: draft.where.taken,
			isDefault: false,
			mappings: draft.facts.map( ( fact ) => {
				const attribute = created[ fact.key ];
				return attribute
					? {
							target: 'new',
							value: valueFor(
								attributeItem( attribute ),
								fact.text
							),
							touched: false,
						}
					: {
							target: fact.target,
							value: fact.value,
							touched: false,
						};
			} ),
		};
	};

	/** Load every blank at once; one that fails is set aside, not the batch. */
	const review = async () => {
		setLoading( true );
		setError( '' );
		const nextDrafts = { ...drafts };
		const nextStates = { ...states };
		const loaded = await Promise.allSettled(
			selected.map( ( product ) =>
				nextDrafts[ product.id ]
					? Promise.resolve( nextDrafts[ product.id ] )
					: ( apiFetch( {
							path: `${ path }/${ product.id }`,
						} ) as Promise< ImportDraft > )
			)
		);
		const ready: PrintfulProduct[] = [];
		const failed: string[] = [];
		loaded.forEach( ( outcome, i ) => {
			const product = selected[ i ];
			if ( outcome.status === 'rejected' ) {
				failed.push(
					sprintf(
						/* translators: 1: blank name, 2: error message. */
						__( '%1$s: %2$s', 'aggressive-apparel' ),
						product.model || product.title,
						errorText(
							outcome.reason,
							__(
								'It could not be loaded from Printful.',
								'aggressive-apparel'
							)
						)
					)
				);
				return;
			}
			nextDrafts[ product.id ] = outcome.value;
			// Only blanks still chosen count: one taken out holds nothing.
			nextStates[ product.id ] ??= startState(
				outcome.value,
				selected.flatMap( ( other ) => nextStates[ other.id ] ?? [] )
			);
			ready.push( product );
		} );
		setLoading( false );
		setDrafts( nextDrafts );
		setStates( nextStates );
		if ( failed.length > 0 ) {
			setError(
				[
					_n(
						'This blank was left out. Try it again later:',
						'These blanks were left out. Try them again later:',
						failed.length,
						'aggressive-apparel'
					),
					...failed,
				].join( ' ' )
			);
		}
		if ( ready.length > 0 ) {
			setSelected( ready );
			setActive( ready[ 0 ].id );
			setStep( 1 );
		}
	};

	/** Apply a change to one line, and its target to the same line elsewhere. */
	const map = (
		id: number,
		index: number,
		patch: Partial< ImportMapping >
	) => {
		const key = drafts[ id ].facts[ index ].key;
		const next = { ...states };
		for ( const product of selected ) {
			const state = next[ product.id ];
			const draft = drafts[ product.id ];
			if ( ! state || ! draft ) {
				continue;
			}
			next[ product.id ] = {
				...state,
				mappings: state.mappings.map( ( mapping, i ) => {
					if ( product.id === id && i === index ) {
						return { ...mapping, ...patch, touched: true };
					}
					if (
						patch.target === undefined ||
						mapping.touched ||
						draft.facts[ i ].key !== key
					) {
						return mapping;
					}
					const spec = specs.find(
						( item ) => item.key === patch.target
					);
					return {
						...mapping,
						target: patch.target,
						value: spec
							? valueFor( spec, draft.facts[ i ].text )
							: '',
						confirmed: true,
					};
				} ),
			};
		}
		setStates( next );
	};

	/** Start or change the new attribute a line (and its twins) goes to. */
	const attribute = (
		id: number,
		index: number,
		patch: Partial< ImportAttribute >
	) => {
		const fact = drafts[ id ].facts[ index ];
		const others = Object.entries( created )
			.filter( ( [ key ] ) => key !== fact.key )
			.map( ( [ , value ] ) => attributeItem( value ) );
		const base: ImportAttribute = created[ fact.key ] ?? {
			key: '',
			name: nameFrom( fact.text ),
			kind: 'text',
			archives: false,
			steps: 'Low, Medium, High',
			unit: '',
			taxonomy: '',
		};
		const merged = { ...base, ...patch };
		// Keyed from its name, unique across the store and this import.
		merged.key = specKey( merged.name, [ ...items, ...others ] );
		setCreated( { ...created, [ fact.key ]: merged } );

		const item = attributeItem( merged );
		const next = { ...states };
		for ( const product of selected ) {
			const state = next[ product.id ];
			const draft = drafts[ product.id ];
			if ( ! state || ! draft ) {
				continue;
			}
			next[ product.id ] = {
				...state,
				mappings: state.mappings.map( ( mapping, i ) =>
					draft.facts[ i ].key === fact.key &&
					( mapping.target === 'new' ||
						! mapping.touched ||
						( product.id === id && i === index ) )
						? {
								target: 'new',
								value: valueFor( item, draft.facts[ i ].text ),
								touched:
									mapping.touched ||
									( product.id === id && i === index ),
							}
						: mapping
				),
			};
		}
		setStates( next );
	};

	/** Create these blanks' guides; the rest of the selection stays in review. */
	const create = async ( list: PrintfulProduct[] ) => {
		setError( '' );
		setNotice( '' );
		setBatch( list.length );
		const made = madeRef.current;
		const before = new Set( [
			...items.map( ( item ) => item.key ),
			...made.values(),
		] );
		const done: ImportResult[] = [];
		/** Record the specs a save made, as the registry now has them. */
		const reconcile = ( registry: Registry, used: ImportAttribute[] ) => {
			for ( const attr of used.filter(
				( other ) => ! made.has( other.key )
			) ) {
				const item = findSaved( registry.items, attr, before );
				if ( item ) {
					made.set( attr.key, item.key );
					before.add( item.key );
				}
			}
		};
		for ( const [ n, product ] of list.entries() ) {
			setProgress( n + 1 );
			const { data, used, summary } = guideRequest( {
				draft: drafts[ product.id ],
				state: states[ product.id ],
				specs,
				created,
				made,
				categories: config.categories,
			} );

			try {
				const result = ( await apiFetch( {
					path: config.guidesPath,
					method: 'POST',
					data,
				} ) ) as GuideDetail & { registry?: Registry };
				if ( result.registry ) {
					reconcile( result.registry, used );
					onRegistry( result.registry );
				}
				used.forEach( ( attr ) =>
					made.set( attr.key, savedKey( made, attr ) )
				);
				done.push( { ...summary, guide: result.id, error: '' } );
			} catch ( failure ) {
				// The save may have gone through before the reply was lost: look
				// again, so the next blank joins what it made instead of a copy.
				try {
					const registry = ( await apiFetch( {
						path: config.restPath,
					} ) ) as Registry;
					reconcile( registry, used );
					onRegistry( registry );
				} catch {
					// Nothing more to learn; the next blank sends the spec again.
				}
				done.push( {
					...summary,
					guide: 0,
					error: errorText(
						failure,
						__(
							'The guide could not be created.',
							'aggressive-apparel'
						)
					),
				} );
			}
		}
		setProgress( 0 );

		// A created blank is forgotten, so choosing it again loads it afresh and
		// warns that it's already imported. A failed one stays for the retry.
		// Functional: the blanks still in review stay editable while this runs.
		const forget = < T, >(
			current: Record< number, T >
		): Record< number, T > => {
			const next = { ...current };
			done.filter( ( result ) => result.guide > 0 ).forEach(
				( result ) => delete next[ result.id ]
			);
			return next;
		};
		const remaining = selected.filter(
			( product ) => ! list.some( ( other ) => other.id === product.id )
		);
		const nowPlaced: ImportPlaced = {};
		done.filter( ( result ) => result.guide > 0 ).forEach( ( result ) =>
			states[ result.id ]?.categories.forEach( ( id ) => {
				nowPlaced[ id ] = result.title;
			} )
		);
		setPlaced( ( current ) => ( { ...current, ...nowPlaced } ) );
		setDrafts( forget );
		setStates( forget );
		setResults( [ ...results, ...done ] );
		setFinished( [ ...finished, ...list ] );
		setSelected( remaining );

		const left = remaining.filter( ( product ) => ! kept( product.id ) );
		if ( left.length === 0 ) {
			setStep( 2 );
			return;
		}
		setActive( left[ 0 ].id );
		const succeeded = done.filter( ( result ) => result.guide > 0 ).length;
		setNotice(
			succeeded === done.length
				? sprintf(
						/* translators: %d: number of guides. */
						_n(
							'%d size guide created. Here’s what’s left to look at.',
							'%d size guides created. Here’s what’s left to look at.',
							succeeded,
							'aggressive-apparel'
						),
						succeeded
					)
				: sprintf(
						/* translators: 1: guides created, 2: guides attempted. */
						__(
							'%1$d of %2$d size guides created. You can retry the others once you finish these.',
							'aggressive-apparel'
						),
						succeeded,
						done.length
					)
		);
	};

	/** Back to review with only the blanks that failed. */
	const retry = () => {
		const failed = finished.filter( ( product ) =>
			results.some(
				( result ) => result.id === product.id && result.guide === 0
			)
		);
		setFinished(
			finished.filter( ( product ) => ! failed.includes( product ) )
		);
		setSelected( failed );
		setActive( failed[ 0 ]?.id ?? 0 );
		setResults( results.filter( ( result ) => result.guide > 0 ) );
		setStep( 1 );
	};

	const restart = () => {
		madeRef.current = new Map();
		setSelected( [] );
		setFinished( [] );
		setDrafts( {} );
		setStates( {} );
		setCreated( {} );
		setResults( [] );
		setPlaced( {} );
		setNotice( '' );
		setStep( 0 );
	};

	const current = drafts[ active ];
	const label = ( product: PrintfulProduct ) =>
		product.model || product.title;
	const unnamed = ( id: number ) =>
		! kept( id ) && states[ id ]?.title.trim() === '';
	// What creating would make: never a rival to a guide already in place.
	const queue = selected.filter( ( product ) => ! kept( product.id ) );
	const waiting = selected.filter( ( product ) => pending( product.id ) > 0 );
	const blocked = selected.find( ( product ) => unnamed( product.id ) );
	/** A blank's state with what other blanks hold right now. */
	const live = ( id: number ): ImportBlankState => ( {
		...states[ id ],
		taken: liveTaken(
			drafts[ id ],
			states[ id ],
			selected
				.filter(
					( other ) =>
						other.id !== id &&
						states[ other.id ] &&
						! kept( other.id )
				)
				.map( ( other ) => states[ other.id ] ),
			placed,
			config.categories
		),
	} );
	const stopsFor = ( id: number ): ImportStop[] =>
		drafts[ id ] && states[ id ] && ! kept( id )
			? importStops( drafts[ id ], live( id ) )
			: [];
	const ready = queue.filter(
		( product ) => stopsFor( product.id ).length === 0
	);
	const attention = queue.filter(
		( product ) => stopsFor( product.id ).length > 0
	);
	// Some can go now and some can't, so offer the ones that can.
	const partial = ready.length > 0 && attention.length > 0;

	/** Show the next thing a blank is waiting on. */
	const goNext = () => {
		const start = Math.max(
			0,
			selected.findIndex( ( product ) => product.id === active )
		);
		const order = [
			...selected.slice( start ),
			...selected.slice( 0, start ),
		];
		// Lines to decide come first: only they stop everything being created.
		const target =
			order.find( ( product ) => pending( product.id ) > 0 ) ??
			order.find( ( product ) => stopsFor( product.id ).length > 0 );
		if ( target ) {
			setActive( target.id );
			setJump( jump + 1 );
		}
	};

	useEffect( () => {
		if ( jump === 0 ) {
			return;
		}
		const stop = stopsFor( active )[ 0 ];
		const field = stop
			? document.querySelector< HTMLElement >(
					`.aa-ps-review ${ STOP_TARGET[ stop ] }`
				)
			: null;
		field?.scrollIntoView( {
			block: 'center',
			behavior: scrollBehavior(),
		} );
		field?.focus( { preventScroll: true } );
		// eslint-disable-next-line react-hooks/exhaustive-deps -- only on a jump.
	}, [ jump ] );

	let status: string = __( 'Everything is placed', 'aggressive-apparel' );
	if ( progress > 0 ) {
		status = sprintf(
			/* translators: 1: guide being created, 2: all guides. */
			__( 'Creating %1$d of %2$d…', 'aggressive-apparel' ),
			progress,
			batch
		);
	} else if ( waiting.length === 1 ) {
		status = sprintf(
			/* translators: 1: lines needing a decision, 2: blank model, like 18500. */
			_n(
				'%1$d line on %2$s needs a decision',
				'%1$d lines on %2$s need a decision',
				totalPending,
				'aggressive-apparel'
			),
			totalPending,
			label( waiting[ 0 ] )
		);
	} else if ( waiting.length > 1 ) {
		status = sprintf(
			/* translators: 1: lines needing a decision, 2: number of blanks. */
			_n(
				'%1$d line on %2$d blanks needs a decision',
				'%1$d lines on %2$d blanks need a decision',
				totalPending,
				'aggressive-apparel'
			),
			totalPending,
			waiting.length
		);
	} else if ( blocked ) {
		status = sprintf(
			/* translators: %s: blank model, like 18500. */
			__( 'The %s guide needs a name', 'aggressive-apparel' ),
			label( blocked )
		);
	} else if ( attention.length === 1 ) {
		status = sprintf(
			/* translators: %s: blank model, like 18500. */
			__( '%s needs a look', 'aggressive-apparel' ),
			label( attention[ 0 ] )
		);
	} else if ( attention.length > 1 ) {
		status = sprintf(
			/* translators: %d: number of blanks. */
			__( '%d blanks need a look', 'aggressive-apparel' ),
			attention.length
		);
	}
	if ( progress === 0 && queue.length === 0 ) {
		status = __(
			'You already have these guides, so there’s nothing to create.',
			'aggressive-apparel'
		);
	}
	if ( progress === 0 && partial ) {
		status = `${ sprintf(
			/* translators: %d: number of blanks. */
			__( '%d ready to create.', 'aggressive-apparel' ),
			ready.length
		) } ${ status }`;
	}

	return (
		<div className="aa-ps-importer">
			<Button
				variant="link"
				className="aa-ps-importer__back"
				onClick={ onCancel }
			>
				{ __( 'All size guides', 'aggressive-apparel' ) }
			</Button>
			<header className="aa-ps-importer__head">
				<h2 className="aa-ps-importer__title">
					{ __( 'Import from Printful', 'aggressive-apparel' ) }
				</h2>
				<ol
					className="aa-ps-steps-bar"
					aria-label={ __( 'Import steps', 'aggressive-apparel' ) }
				>
					{ STEPS.map( ( name, i ) => (
						<li
							key={ name }
							className={ cx(
								i === step && 'is-current',
								i < step && 'is-done'
							) }
							aria-current={ i === step ? 'step' : undefined }
						>
							{ name }
						</li>
					) ) }
				</ol>
			</header>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ notice && step === 1 && (
				<Notice status="success" onRemove={ () => setNotice( '' ) }>
					{ notice }
				</Notice>
			) }

			{ step === 0 && (
				<>
					<p className="aa-ps-importer__lede">
						{ __(
							'Pick the blanks you print on. Their size charts, measuring guides, and specs come in ready to review, so the guides exist before you add products.',
							'aggressive-apparel'
						) }
					</p>
					<ImportSearch
						path={ path }
						selected={ selected }
						onToggle={ toggle }
						onOpen={ onOpen }
						onReview={ () => void review() }
					/>
					{ loading && <Spinner /> }
				</>
			) }

			{ step === 1 && current && states[ active ] && (
				<div className="aa-ps-review">
					<nav
						className="aa-ps-review__rail"
						aria-label={ __(
							'Blanks to review',
							'aggressive-apparel'
						) }
					>
						{ selected.map( ( product ) => (
							<ImportTag
								key={ product.id }
								product={ product }
								active={ product.id === active }
								pending={ pending( product.id ) }
								kept={ kept( product.id ) }
								ready={
									! kept( product.id ) &&
									stopsFor( product.id ).length === 0
								}
								attention={ stopsFor( product.id ).length > 0 }
								onSelect={ () => setActive( product.id ) }
							/>
						) ) }
					</nav>
					<ImportBlank
						draft={ current }
						state={ live( active ) }
						specs={ specs }
						attributes={ free }
						created={ created }
						categories={ config.categories }
						stops={ stopsFor( active ) }
						onOpen={ onOpen }
						onState={ ( patch ) =>
							setStates( {
								...states,
								[ active ]: { ...states[ active ], ...patch },
							} )
						}
						onMapping={ ( index, patch ) =>
							map( active, index, patch )
						}
						onAttribute={ ( index, patch ) =>
							attribute( active, index, patch )
						}
					/>
					<div className="aa-ps-importer__bar">
						<p
							className="aa-ps-importer__status"
							aria-live="polite"
						>
							{ status }
						</p>
						{ progress === 0 && attention.length > 0 && (
							<Button variant="secondary" onClick={ goNext }>
								{ waiting.length > 0
									? __(
											'Go to next decision',
											'aggressive-apparel'
										)
									: __( 'Go to it', 'aggressive-apparel' ) }
							</Button>
						) }
						<Button
							variant="tertiary"
							disabled={ progress > 0 }
							onClick={ () => setStep( 0 ) }
						>
							{ __( 'Change blanks', 'aggressive-apparel' ) }
						</Button>
						{ partial && totalPending === 0 && ! blocked && (
							<Button
								variant="secondary"
								disabled={ progress > 0 }
								onClick={ () => void create( queue ) }
							>
								{ sprintf(
									/* translators: %d: number of guides. */
									__(
										'Create all %d anyway',
										'aggressive-apparel'
									),
									queue.length
								) }
							</Button>
						) }
						{ partial ? (
							<Button
								variant="primary"
								disabled={ progress > 0 }
								isBusy={ progress > 0 }
								onClick={ () => void create( ready ) }
							>
								{ sprintf(
									/* translators: %d: number of guides. */
									_n(
										'Create %d ready guide',
										'Create %d ready guides',
										ready.length,
										'aggressive-apparel'
									),
									ready.length
								) }
							</Button>
						) : queue.length === 0 ? (
							<Button variant="primary" onClick={ onCancel }>
								{ __(
									'Back to size guides',
									'aggressive-apparel'
								) }
							</Button>
						) : (
							<Button
								variant="primary"
								disabled={
									totalPending > 0 ||
									progress > 0 ||
									Boolean( blocked )
								}
								isBusy={ progress > 0 }
								onClick={ () => void create( queue ) }
							>
								{ sprintf(
									/* translators: %d: number of guides. */
									_n(
										'Create %d size guide',
										'Create %d size guides',
										queue.length,
										'aggressive-apparel'
									),
									queue.length
								) }
							</Button>
						) }
					</div>
				</div>
			) }

			{ step === 2 && (
				<ImportDone
					results={ results }
					products={ finished }
					kept={ selected.filter( ( product ) =>
						kept( product.id )
					) }
					onOpen={ onOpen }
					onRetry={ retry }
					onMore={ restart }
					onClose={ onCancel }
				/>
			) }
		</div>
	);
}
