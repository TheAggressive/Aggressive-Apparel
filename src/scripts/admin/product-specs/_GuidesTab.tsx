/**
 * Product Specs settings — Size guides tab.
 *
 * The list of guides (one per garment blank) and, when one is open, its
 * editor. The open guide is kept in the URL (?guide=ID or ?guide=new) so a
 * link from a product lands straight on it. The list can also show every
 * guide's spec values as a grid (_GuidesGrid), to set one on many at once.
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { Config, GuideSummary, Registry, SpecItem, Usage } from './_types';
import { cx } from '../../../utils/product-specs-data';
import GuideEditor from './_GuideEditor';
import GuidesGrid from './_GuidesGrid';
import ImportFlow from './_ImportFlow';
import { countEdits, type GridEdits } from './_gridEdits';

type Props = {
	config: Config;
	/** Saved spec items, for the guide's Specs section. */
	items: SpecItem[];
	onRegistry: ( registry: Registry ) => void;
	/** Global attributes, taxonomy => label. */
	taxonomies: Record< string, string >;
	guides: GuideSummary[];
	onGuides: ( guides: GuideSummary[] ) => void;
	onDirty: ( dirty: boolean ) => void;
	/** Open the Specs tab (a guide's edits stay, since this tab stays mounted). */
	onManageSpecs: () => void;
	/** The saved registry's revision and setting, for saving spec edits. */
	registry: { revision: number; hideDimensions: boolean };
};

function setGuideParam( value: number | 'new' | null ): void {
	const url = new URL( window.location.href );
	if ( value === null ) {
		url.searchParams.delete( 'guide' );
	} else {
		url.searchParams.set( 'guide', String( value ) );
	}
	window.history.replaceState( null, '', url.toString() );
}

function usageText( usage: Usage ): string {
	const parts: string[] = [];
	if ( usage.global ) {
		parts.push( __( 'Store default', 'aggressive-apparel' ) );
	}
	parts.push( ...usage.categories );
	if ( usage.products > 0 ) {
		parts.push(
			sprintf(
				/* translators: %d: number of products. */
				_n(
					'%d product',
					'%d products',
					usage.products,
					'aggressive-apparel'
				),
				usage.products
			)
		);
	}
	return parts.length > 0
		? parts.join( ', ' )
		: __( 'Not used yet', 'aggressive-apparel' );
}

export default function GuidesTab( {
	config,
	items,
	onRegistry,
	taxonomies,
	guides,
	onGuides,
	onDirty,
	onManageSpecs,
	registry,
}: Props ) {
	const [ open, setOpen ] = useState< number | 'new' | null >(
		config.guide === 'new' || config.guide > 0 ? config.guide : null
	);
	const [ loading, setLoading ] = useState( false );
	const [ importing, setImporting ] = useState( false );
	// The list as guides, or as their spec values side by side.
	const [ view, setView ] = useState< 'list' | 'grid' >( 'list' );
	const [ gridEdits, setGridEdits ] = useState< GridEdits >( {} );

	// Unsaved grid values count as unsaved work while no guide is open (an
	// open guide reports its own).
	useEffect( () => {
		if ( open === null ) {
			onDirty( countEdits( gridEdits ) > 0 );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps -- onDirty is stable in intent.
	}, [ gridEdits, open ] );

	const show = ( value: number | 'new' | null ) => {
		setOpen( value );
		setGuideParam( value );
	};

	const refresh = async () => {
		setLoading( true );
		try {
			onGuides(
				( await apiFetch( {
					path: config.guidesPath,
				} ) ) as GuideSummary[]
			);
		} finally {
			setLoading( false );
		}
	};

	if ( importing && open === null ) {
		return (
			<ImportFlow
				config={ config }
				items={ items }
				taxonomies={ taxonomies }
				onRegistry={ onRegistry }
				onCancel={ () => {
					setImporting( false );
					void refresh();
				} }
				onOpen={ ( id ) => {
					setImporting( false );
					show( id );
					void refresh();
				} }
			/>
		);
	}

	if ( open !== null ) {
		return (
			<GuideEditor
				key={ String( open ) }
				config={ config }
				items={ items }
				onRegistry={ onRegistry }
				guideId={ open }
				onDirty={ onDirty }
				onManageSpecs={ onManageSpecs }
				registry={ registry }
				taxonomies={ taxonomies }
				onOpen={ ( id ) => {
					show( id );
					void refresh();
				} }
				onCreated={ ( id ) => {
					setGuideParam( id );
					void refresh();
				} }
				onClose={ ( changed ) => {
					onDirty( false );
					show( null );
					if ( changed || open !== null ) {
						void refresh();
					}
				} }
			/>
		);
	}

	return (
		<div className="aa-ps-tab aa-ps-guides">
			<div className="aa-ps-guides__head">
				<p className="aa-ps-intro">
					{ __(
						'One guide per garment blank: its size chart and notes. Products pick a guide, or get one from their category.',
						'aggressive-apparel'
					) }
				</p>
				<div className="aa-ps-guides__actions">
					<Button
						variant="secondary"
						onClick={ () => setImporting( true ) }
					>
						{ __( 'Import from Printful', 'aggressive-apparel' ) }
					</Button>
					<Button variant="primary" onClick={ () => show( 'new' ) }>
						{ __( 'New size guide', 'aggressive-apparel' ) }
					</Button>
				</div>
			</div>

			{ guides.length > 0 && (
				<div
					className="aa-ps-pills aa-ps-guides__view"
					role="radiogroup"
					aria-label={ __( 'Show', 'aggressive-apparel' ) }
				>
					{ (
						[
							[ 'list', __( 'Guides', 'aggressive-apparel' ) ],
							[
								'grid',
								__( 'Spec values', 'aggressive-apparel' ),
							],
						] as const
					 ).map( ( [ name, label ] ) => (
						<button
							key={ name }
							type="button"
							role="radio"
							aria-checked={ view === name }
							className={ cx(
								'aa-ps-pill',
								view === name && 'is-active'
							) }
							onClick={ () => setView( name ) }
						>
							{ label }
							{ name === 'grid' &&
								countEdits( gridEdits ) > 0 && (
									<>
										<span
											className="aa-ps-tabs__dot"
											aria-hidden="true"
										/>
										<span className="screen-reader-text">
											{ __(
												'(unsaved changes)',
												'aggressive-apparel'
											) }
										</span>
									</>
								) }
						</button>
					) ) }
				</div>
			) }

			{ loading && <Spinner /> }

			{ view === 'grid' && guides.length > 0 ? (
				<GuidesGrid
					config={ config }
					items={ items }
					edits={ gridEdits }
					onEdits={ setGridEdits }
					onOpen={ ( id ) => show( id ) }
					onSaved={ () => void refresh() }
				/>
			) : guides.length === 0 ? (
				<div className="aa-ps-empty">
					<p>
						{ __(
							'No size guides yet. Create one from a template in a minute.',
							'aggressive-apparel'
						) }
					</p>
					<Button variant="secondary" onClick={ () => show( 'new' ) }>
						{ __( 'New size guide', 'aggressive-apparel' ) }
					</Button>
				</div>
			) : (
				<ul className="aa-ps-guides__list">
					{ guides.map( ( guide ) => (
						<li key={ guide.id }>
							<button
								type="button"
								className="aa-ps-guides__item"
								onClick={ () => show( guide.id ) }
							>
								<span className="aa-ps-guides__name">
									{ guide.title }
									{ guide.legacy && (
										<span className="aa-ps-badge">
											{ __(
												'Old layout',
												'aggressive-apparel'
											) }
										</span>
									) }
								</span>
								<span className="aa-ps-guides__meta">
									{ guide.sizes > 0
										? sprintf(
												/* translators: 1: sizes, 2: measurements. */
												__(
													'%1$d sizes × %2$d measurements',
													'aggressive-apparel'
												),
												guide.sizes,
												guide.columns
											)
										: guide.legacy
											? __(
													'Free-form content',
													'aggressive-apparel'
												)
											: guide.specs
												? __(
														'Specs only, no chart',
														'aggressive-apparel'
													)
												: __(
														'Empty, not used yet',
														'aggressive-apparel'
													) }
								</span>
								<span className="aa-ps-guides__usage">
									{ usageText( guide.usage ) }
								</span>
							</button>
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}
