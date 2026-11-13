/**
 * Product Specs settings — one size guide.
 *
 * Everything about a garment blank in one form: its name, size chart, notes
 * and measuring diagram, specs, and where it applies. One save writes it all
 * (Size_Guide_Rest), along with any spec edited or added here: edits save
 * through the Specs tab's own save first (saveRegistry), new specs with the
 * guide, which adds them to the store's specs (Size_Guide_Spec_Items).
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useMemo, useState } from '@wordpress/element';
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import type {
	Config,
	DraftItem,
	GuideDetail,
	Registry,
	SpecItem,
} from './_types';
import { templates } from './_chart';
import ChartEditor from './_ChartEditor';
import ChartPreview from './_ChartPreview';
import GuideNotes, { NotesPreview } from './_GuideNotes';
import GuideSpecs from './_GuideSpecs';
import GuideStatusStrip from './_GuideStatus';
import WhereItApplies from './_WhereItApplies';
import { ShopperPreview } from './_Preview';
import { SECTION, guideSpecs, guideStatus, hasChart } from './_guideSummary';
import saveRegistry from './_saveRegistry';
import {
	conflicts,
	discardEdit,
	edited,
	rebase,
	recordEdit,
	type Conflict,
	type SpecEdits,
} from './_specEdits';
import { errorText, toStored } from './_utils';
import { readWording } from './_wording';
import ApplyToProducts from './_ApplyToProducts';
import { editGuideValues, guideValues, type ValueEdits } from './_guideValues';

type Props = {
	config: Config;
	/** Saved spec items (the Specs tab's last save). */
	items: SpecItem[];
	guideId: number | 'new';
	/** A save changed the spec registry. */
	onRegistry: ( registry: Registry ) => void;
	onClose: ( changed: boolean ) => void;
	onOpen: ( id: number ) => void;
	onCreated: ( id: number ) => void;
	/** Global attributes, taxonomy => label (current, not as loaded). */
	taxonomies: Record< string, string >;
	/** Open the Specs tab, keeping this guide's unsaved edits. */
	onManageSpecs: () => void;
	/** The saved registry's revision and setting, for saving spec edits. */
	registry: { revision: number; hideDimensions: boolean };
	onDirty: ( dirty: boolean ) => void;
};

/** Why a save stopped: specs changed on the Specs tab since they were changed here. */
function conflictText( clashes: Conflict[] ): string {
	return clashes
		.map( ( clash ) =>
			clash.removed
				? sprintf(
						/* translators: %s: spec name. */
						__(
							'“%s” was deleted on the Specs tab, so your changes to it here were dropped.',
							'aggressive-apparel'
						),
						clash.name
					)
				: sprintf(
						/* translators: %s: spec name. */
						__(
							'“%s” was changed on the Specs tab while you edited it here. Its latest version is shown, with your other changes kept. Check it, then save again.',
							'aggressive-apparel'
						),
						clash.name
					)
		)
		.join( ' ' );
}

function blank(): GuideDetail {
	return {
		id: 0,
		title: '',
		chart: templates()[ 0 ].chart,
		notes: '',
		diagram: null,
		showNotes: true,
		showDiagram: true,
		specs: {},
		hidden: [],
		wording: {},
		source: null,
		categories: [],
		isDefault: false,
		usage: { global: false, categories: [], products: 0 },
		legacyHtml: '',
		legacyEditUrl: '',
	};
}

export default function GuideEditor( {
	config,
	items,
	guideId,
	onRegistry,
	onClose,
	onOpen,
	onCreated,
	onDirty,
	taxonomies,
	onManageSpecs,
	registry,
}: Props ) {
	const [ guide, setGuide ] = useState< GuideDetail | null >(
		guideId === 'new' ? blank() : null
	);
	const [ dirty, setDirtyState ] = useState( guideId === 'new' );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	// A converted guide had content the chart can't hold (shown as a notice).
	const [ partial, setPartial ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ confirmDelete, setConfirmDelete ] = useState( false );
	// A new guide starts dirty (it can be saved as-is), but leaving it
	// untouched discards nothing worth asking about.
	const [ touched, setTouched ] = useState( false );
	const [ confirmLeave, setConfirmLeave ] = useState( false );
	// Specs changed (for every guide) or added here, saved with the guide.
	const [ edits, setEdits ] = useState< SpecEdits >( {} );
	const [ added, setAdded ] = useState< DraftItem[] >( [] );
	// The guide's specs as listed, worked out once per change.
	const specs = useMemo(
		() => guideSpecs( items, edits, added ),
		[ items, edits, added ]
	);
	const [ valueItems, setValueItems ] = useState( items );
	const [ valueEdits, setValueEdits ] = useState< ValueEdits >( {} );

	const setDirty = ( value: boolean ) => {
		setDirtyState( value );
		onDirty( value );
	};

	useEffect( () => {
		if ( guideId === 'new' ) {
			onDirty( true );
			return;
		}
		apiFetch( { path: `${ config.guidesPath }/${ guideId }` } )
			.then( ( result ) => {
				const detail = result as GuideDetail;
				setGuide( {
					...detail,
					wording: readWording( detail.wording ),
				} );
			} )
			.catch( ( failure: unknown ) =>
				setError(
					errorText(
						failure,
						__(
							'The size guide could not be loaded.',
							'aggressive-apparel'
						)
					)
				)
			);
		// eslint-disable-next-line react-hooks/exhaustive-deps -- load once per guide.
	}, [ guideId ] );

	// Whatever this editor left unsaved goes with it.
	// eslint-disable-next-line react-hooks/exhaustive-deps -- unmount only.
	useEffect( () => () => onDirty( false ), [] );

	if ( ! guide ) {
		return error ? (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const markDirty = () => {
		setDirty( true );
		setTouched( true );
		setConfirmLeave( false );
		setSaved( false );
	};

	// Functional, so a value move and a spec edit in one change both land.
	const update = ( patch: Partial< GuideDetail > ) => {
		setGuide( ( current ) => current && { ...current, ...patch } );
		markDirty();
	};

	const editSpec = ( key: string, patch: Partial< SpecItem > ) => {
		const saved = items.find( ( item ) => item.key === key );
		if ( saved ) {
			setEdits( ( current ) => recordEdit( current, saved, patch ) );
			markDirty();
		}
	};

	const discardSpecEdit = ( key: string ) => {
		setEdits( ( current ) => discardEdit( current, key ) );
		markDirty();
	};

	const updateAdded = ( next: DraftItem[] ) => {
		setAdded( next );
		markDirty();
	};

	const currentItems = [
		...items.map( ( item ) => edited( item, edits ) ),
		...added,
	];
	const values = guideValues(
		guide.specs,
		valueItems,
		currentItems,
		valueEdits
	);

	/** Save the guide; true when it saved. */
	const save = async (): Promise< boolean > => {
		setSaving( true );
		setError( '' );
		try {
			let savedItems = items;
			// A spec changed here and on the Specs tab since: stop, and show the
			// latest with the rest of this guide's changes on it.
			const clashes = conflicts( edits, items );
			if ( clashes.length > 0 ) {
				setEdits( rebase( edits, items ) );
				setError( conflictText( clashes ) );
				return false;
			}
			// Spec edits first, through the Specs tab's save, so the guide's
			// values below are saved against the specs as edited.
			const changed = Object.keys( edits );
			if ( changed.length > 0 ) {
				const savedRegistry = await saveRegistry( config.restPath, {
					items: items.map( ( item ) =>
						toStored( edited( item, edits ) )
					),
					hideDimensions: registry.hideDimensions,
					revision: registry.revision,
					changed,
				} );
				savedItems = savedRegistry.items;
				onRegistry( savedRegistry );
				setEdits( {} );
			}
			const result = ( await apiFetch( {
				path: guide.id
					? `${ config.guidesPath }/${ guide.id }`
					: config.guidesPath,
				method: 'POST',
				data: {
					title: guide.title,
					chart: guide.chart,
					notes: guide.notes,
					diagram: guide.diagram?.id ?? 0,
					showNotes: guide.showNotes,
					showDiagram: guide.showDiagram,
					specs: guide.id
						? Object.fromEntries(
								Object.keys( valueEdits ).map( ( key ) => [
									key,
									values[ key ],
								] )
							)
						: values,
					hidden: guide.hidden,
					wording: guide.wording,
					...( added.length > 0 && {
						specItems: added.map( toStored ),
					} ),
					categories: guide.categories,
					isDefault: guide.isDefault,
				},
			} ) ) as GuideDetail & { registry?: Registry };
			const { registry: saved, ...detail } = result;
			setGuide( { ...detail, wording: readWording( detail.wording ) } );
			setAdded( [] );
			setValueItems( saved?.items ?? savedItems );
			setValueEdits( {} );
			if ( saved ) {
				onRegistry( saved );
			}
			setDirty( false );
			setSaved( true );
			if ( ! guide.id ) {
				// Stay in this editor (keeping the saved notice); just record the ID.
				onCreated( result.id );
			}
			return true;
		} catch ( failure ) {
			setError(
				errorText(
					failure,
					__( 'Saving failed. Try again.', 'aggressive-apparel' )
				)
			);
			return false;
		} finally {
			setSaving( false );
		}
	};

	// Unsaved edits are saved first, so the copy is what's on screen.
	const duplicate = async () => {
		if ( dirty && ! ( await save() ) ) {
			return;
		}
		setSaving( true );
		setError( '' );
		try {
			const copy = ( await apiFetch( {
				path: `${ config.guidesPath }/${ guide.id }/duplicate`,
				method: 'POST',
			} ) ) as GuideDetail & { registry?: Registry };
			if ( copy.registry ) {
				onRegistry( copy.registry );
			}
			onOpen( copy.id );
		} catch ( failure ) {
			setError(
				errorText(
					failure,
					__(
						'The guide could not be duplicated.',
						'aggressive-apparel'
					)
				)
			);
		} finally {
			setSaving( false );
		}
	};

	const remove = async () => {
		setSaving( true );
		setError( '' );
		try {
			await apiFetch( {
				path: `${ config.guidesPath }/${ guide.id }`,
				method: 'DELETE',
			} );
			setDirty( false );
			onClose( true );
		} catch ( failure ) {
			setConfirmDelete( false );
			setError(
				errorText(
					failure,
					__(
						'The guide could not be deleted.',
						'aggressive-apparel'
					)
				)
			);
		} finally {
			setSaving( false );
		}
	};

	const convertLegacy = async () => {
		setError( '' );
		try {
			const imported = ( await apiFetch( {
				path: `${ config.guidesPath }/${ guide.id }/convert`,
				method: 'POST',
			} ) ) as Pick< GuideDetail, 'chart' | 'notes' | 'diagram' > & {
				lossless: boolean;
			};
			update( {
				chart: imported.chart,
				notes: guide.notes || imported.notes,
				diagram: guide.diagram ?? imported.diagram,
				legacyHtml: '',
			} );
			setPartial( ! imported.lossless );
		} catch ( failure ) {
			setError(
				errorText(
					failure,
					__(
						'The guide could not be converted.',
						'aggressive-apparel'
					)
				)
			);
		}
	};

	const startChart = () =>
		update( { chart: templates()[ 0 ].chart, legacyHtml: '' } );

	return (
		<div className="aa-ps-guide">
			<div className="aa-ps-guide__top">
				{ confirmLeave ? (
					<div className="aa-ps-guide__leave">
						<span className="aa-ps-help">
							{ __(
								'Discard your unsaved changes?',
								'aggressive-apparel'
							) }
						</span>
						<Button
							variant="secondary"
							isDestructive
							onClick={ () => {
								setDirty( false );
								onClose( false );
							} }
						>
							{ __( 'Discard', 'aggressive-apparel' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setConfirmLeave( false ) }
						>
							{ __( 'Keep editing', 'aggressive-apparel' ) }
						</Button>
					</div>
				) : (
					<Button
						variant="link"
						onClick={ () =>
							dirty && ( guide.id > 0 || touched )
								? setConfirmLeave( true )
								: onClose( false )
						}
					>
						{ __( '← All size guides', 'aggressive-apparel' ) }
					</Button>
				) }
				{ guide.id > 0 && (
					<span className="aa-ps-help">
						{ sprintf(
							/* translators: %d: number of products. */
							_n(
								'Used by %d product directly',
								'Used by %d products directly',
								guide.usage.products,
								'aggressive-apparel'
							),
							guide.usage.products
						) }
					</span>
				) }
			</div>

			{ guide.source && (
				<p className="aa-ps-guide__source">
					{ sprintf(
						/* translators: %s: Printful product name. */
						__(
							'Imported from Printful: %s',
							'aggressive-apparel'
						),
						guide.source.title ||
							sprintf(
								/* translators: %d: Printful product ID. */
								__( 'product #%d', 'aggressive-apparel' ),
								guide.source.id
							)
					) }
				</p>
			) }

			<TextControl
				className="aa-ps-guide__name"
				label={ __( 'Name', 'aggressive-apparel' ) }
				value={ guide.title }
				placeholder={ __( 'Jersey Tee 4.2 oz', 'aggressive-apparel' ) }
				onChange={ ( title ) => update( { title } ) }
				help={ __(
					'Name it after the garment blank, so it is easy to pick on a product.',
					'aggressive-apparel'
				) }
				__next40pxDefaultSize
			/>

			<GuideStatusStrip
				status={ guideStatus(
					{ ...guide, specs: values },
					specs,
					config.categories
				) }
			/>

			{ guide.legacyHtml && (
				<div id={ SECTION.chart } className="aa-ps-legacy">
					<p>
						<strong>
							{ __(
								'This guide uses the old free-form layout.',
								'aggressive-apparel'
							) }
						</strong>{ ' ' }
						{ __(
							'Convert it to an editable chart: the table becomes the grid and the other text becomes notes. Nothing changes on the store until you save.',
							'aggressive-apparel'
						) }
					</p>
					<div className="aa-ps-legacy__actions">
						{ guide.legacyHtml.includes( '<table' ) ? (
							<Button variant="primary" onClick={ convertLegacy }>
								{ __(
									'Convert to chart',
									'aggressive-apparel'
								) }
							</Button>
						) : (
							<Button variant="primary" onClick={ startChart }>
								{ __( 'Start a chart', 'aggressive-apparel' ) }
							</Button>
						) }
						{ guide.legacyEditUrl && (
							<Button
								variant="tertiary"
								href={ guide.legacyEditUrl }
							>
								{ __(
									'Edit the old layout instead',
									'aggressive-apparel'
								) }
							</Button>
						) }
					</div>
					<div
						className="aa-ps-legacy__preview"
						// Server-rendered with wp_kses_post (Size_Guide_Rest::guide).
						dangerouslySetInnerHTML={ { __html: guide.legacyHtml } }
					/>
				</div>
			) }

			{ ! guide.legacyHtml && (
				<section id={ SECTION.chart } className="aa-ps-guide__section">
					<div className="aa-ps-guide__section-head">
						<h2>{ __( 'Size chart', 'aggressive-apparel' ) }</h2>
						{ guide.id === 0 && (
							<div
								className="aa-ps-templates"
								role="group"
								aria-label={ __(
									'Start from',
									'aggressive-apparel'
								) }
							>
								<span className="aa-ps-help">
									{ __(
										'Start from:',
										'aggressive-apparel'
									) }
								</span>
								{ templates().map( ( template ) => (
									<Button
										key={ template.name }
										variant="secondary"
										size="compact"
										onClick={ () =>
											update( { chart: template.chart } )
										}
									>
										{ template.name }
									</Button>
								) ) }
							</div>
						) }
					</div>
					{ partial && (
						<Notice
							status="warning"
							onRemove={ () => setPartial( false ) }
						>
							{ __(
								'Some of the old content couldn’t be carried over (extra images, embeds, or other tables). Check the chart and notes before saving; the old content stays on the guide until you do.',
								'aggressive-apparel'
							) }
						</Notice>
					) }
					<div className="aa-ps-guide__chart">
						<ChartEditor
							chart={ guide.chart }
							limits={ config.limits }
							onChange={ ( chart ) => update( { chart } ) }
						/>
						<ShopperPreview>
							<ChartPreview chart={ guide.chart } />
							<NotesPreview
								notes={ guide.notes }
								diagram={ guide.diagram }
								showNotes={ guide.showNotes }
								showDiagram={ guide.showDiagram }
							/>
						</ShopperPreview>
					</div>
				</section>
			) }

			<GuideNotes
				notes={ guide.notes }
				diagram={ guide.diagram }
				showNotes={ guide.showNotes }
				showDiagram={ guide.showDiagram }
				onChange={ update }
			/>

			<GuideSpecs
				config={ config }
				taxonomies={ taxonomies }
				items={ items }
				specs={ specs }
				edits={ edits }
				added={ added }
				values={ values }
				hasChart={ hasChart( guide ) }
				hidden={ guide.hidden }
				wording={ guide.wording }
				onGuide={ update }
				onValues={ ( next ) => {
					setValueEdits(
						editGuideValues(
							values,
							next,
							currentItems,
							valueEdits
						)
					);
					markDirty();
				} }
				onEdit={ editSpec }
				onDiscardEdit={ discardSpecEdit }
				onAdded={ updateAdded }
				onManage={ onManageSpecs }
				footer={
					guide.id > 0 && (
						<ApplyToProducts
							guidesPath={ config.guidesPath }
							guideId={ guide.id }
							dirty={ dirty }
						/>
					)
				}
			/>

			<section id={ SECTION.where } className="aa-ps-guide__section">
				<h2>{ __( 'Where it applies', 'aggressive-apparel' ) }</h2>
				<WhereItApplies
					categories={ config.categories }
					selected={ guide.categories }
					onCategories={ ( categories ) => update( { categories } ) }
					isDefault={ guide.isDefault }
					onDefault={ ( isDefault ) => update( { isDefault } ) }
				/>
			</section>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ saved && (
				<Notice status="success" onRemove={ () => setSaved( false ) }>
					{ __( 'Size guide saved.', 'aggressive-apparel' ) }
				</Notice>
			) }

			<div className="aa-ps-save aa-ps-guide__footer">
				<Button
					variant="primary"
					disabled={ ! dirty || saving }
					onClick={ () => void save() }
				>
					{ saving
						? __( 'Saving…', 'aggressive-apparel' )
						: __( 'Save size guide', 'aggressive-apparel' ) }
				</Button>
				{ saving && <Spinner /> }
				{ guide.id > 0 && (
					<>
						<Button
							variant="secondary"
							disabled={ saving }
							onClick={ duplicate }
						>
							{ dirty
								? __(
										'Save and duplicate',
										'aggressive-apparel'
									)
								: __( 'Duplicate', 'aggressive-apparel' ) }
						</Button>
						{ confirmDelete ? (
							<>
								<span className="aa-ps-help">
									{ __(
										'Move to trash? Products using it fall back to their category or the default.',
										'aggressive-apparel'
									) }
								</span>
								<Button
									variant="secondary"
									isDestructive
									disabled={ saving }
									onClick={ remove }
								>
									{ __(
										'Move to trash',
										'aggressive-apparel'
									) }
								</Button>
								<Button
									variant="tertiary"
									onClick={ () => setConfirmDelete( false ) }
								>
									{ __( 'Cancel', 'aggressive-apparel' ) }
								</Button>
							</>
						) : (
							<Button
								variant="tertiary"
								isDestructive
								onClick={ () => setConfirmDelete( true ) }
							>
								{ __( 'Delete', 'aggressive-apparel' ) }
							</Button>
						) }
					</>
				) }
				{ dirty && ! saving && (
					<span className="aa-ps-help">
						{ __( 'Unsaved changes', 'aggressive-apparel' ) }
					</span>
				) }
			</div>
		</div>
	);
}
