/**
 * Product Specs settings — every size guide's spec values in one grid.
 *
 * The Products tab's grid (SpecGrid), with guides for rows: a value several
 * guides share ("Care: cold wash" on every tee) is set once, for all the
 * ticked guides, instead of guide by guide. An empty cell shows the spec's
 * default in grey. Edits live with the Size guides tab, so switching back
 * to the list keeps them until saved (Size_Guide_Grid).
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useState } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Notice,
	Spinner,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { Config, SpecItem } from './_types';
import { describeValue, errorText } from './_utils';
import SpecGrid, { BulkBar, type SpecRow } from './_SpecGrid';
import { countEdits, type GridEdits } from './_gridEdits';

type GuideRow = {
	id: number;
	title: string;
	categories: string[];
	values: Record< string, string >;
	hidden: string[];
};

type Props = {
	config: Config;
	/** Saved spec items. */
	items: SpecItem[];
	edits: GridEdits;
	onEdits: ( edits: GridEdits ) => void;
	/** Open a guide in the editor. */
	onOpen: ( id: number ) => void;
	/** Values were saved (the guide list may read differently). */
	onSaved: () => void;
};

export default function GuidesGrid( {
	config,
	items,
	edits,
	onEdits,
	onOpen,
	onSaved,
}: Props ) {
	const specs = items.filter( ( item ) => item.enabled );
	const [ rows, setRows ] = useState< GuideRow[] | null >( null );
	const [ error, setError ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ gapsOnly, setGapsOnly ] = useState( false );
	const [ selected, setSelected ] = useState< number[] >( [] );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState< {
		status: 'success' | 'warning' | 'error';
		text: string;
	} | null >( null );
	const [ reload, setReload ] = useState( 0 );
	const changes = countEdits( edits );

	useEffect( () => {
		let current = true;
		setError( '' );
		apiFetch< GuideRow[] >( { path: `${ config.guidesPath }/specs` } )
			.then( ( result ) => current && setRows( result ) )
			.catch(
				( failure: unknown ) =>
					current &&
					setError(
						errorText(
							failure,
							__(
								'The size guides could not be loaded.',
								'aggressive-apparel'
							)
						)
					)
			);
		return () => {
			current = false;
		};
	}, [ config.guidesPath, reload ] );

	const edit = ( id: number, key: string, value: string ) =>
		onEdits( {
			...edits,
			[ id ]: { ...( edits[ id ] ?? {} ), [ key ]: value },
		} );

	const valueOf = ( row: GuideRow, key: string ) =>
		edits[ row.id ]?.[ key ] ?? row.values[ key ] ?? '';

	// A spec this guide shows with nothing to show: no value, no default.
	const hasGap = ( row: GuideRow ) =>
		specs.some(
			( item ) =>
				! row.hidden.includes( item.key ) &&
				valueOf( row, item.key ) === '' &&
				item.default === ''
		);

	const needle = search.trim().toLowerCase();
	const shown = ( rows ?? [] ).filter(
		( row ) =>
			( needle === '' || row.title.toLowerCase().includes( needle ) ) &&
			( ! gapsOnly || hasGap( row ) )
	);

	const visibleSelected = selected.filter( ( id ) =>
		shown.some( ( row ) => row.id === id )
	);

	const open = ( id: number ) => {
		if ( changes > 0 ) {
			setNotice( {
				status: 'warning',
				text: __(
					'Save or discard the values you changed here before opening a guide.',
					'aggressive-apparel'
				),
			} );
			return;
		}
		onOpen( id );
	};

	const gridRows: SpecRow[] = shown.map( ( row ) => ( {
		id: row.id,
		title: row.title,
		head: (
			<span className="aa-ps-guide-cell">
				<Button variant="link" onClick={ () => open( row.id ) }>
					{ row.title }
				</Button>
				<span className="aa-ps-table__usage">
					{ row.categories.length > 0
						? row.categories.join( ', ' )
						: __( 'No categories', 'aggressive-apparel' ) }
				</span>
			</span>
		),
		cells: Object.fromEntries(
			specs.map( ( item ) => [
				item.key,
				{
					applies: true,
					own: row.values[ item.key ] ?? '',
					inherited: describeValue( item, item.default ),
					note: row.hidden.includes( item.key )
						? __( 'Hidden on this guide', 'aggressive-apparel' )
						: undefined,
				},
			] )
		),
	} ) );

	const save = async () => {
		setSaving( true );
		setNotice( null );
		try {
			const result = await apiFetch< { saved: number; skipped: number } >(
				{
					path: `${ config.guidesPath }/specs`,
					method: 'POST',
					data: { guides: edits },
				}
			);
			onEdits( {} );
			setSelected( [] );
			setReload( ( value ) => value + 1 );
			onSaved();
			setNotice( {
				status: result.skipped > 0 ? 'warning' : 'success',
				text:
					result.skipped > 0
						? sprintf(
								/* translators: 1: guides saved, 2: guides skipped. */
								__(
									'Saved %1$d guides. %2$d couldn’t be edited.',
									'aggressive-apparel'
								),
								result.saved,
								result.skipped
							)
						: sprintf(
								/* translators: %d: guides saved. */
								_n(
									'Saved values on %d guide.',
									'Saved values on %d guides.',
									result.saved,
									'aggressive-apparel'
								),
								result.saved
							),
			} );
		} catch ( failure ) {
			setNotice( {
				status: 'error',
				text: errorText(
					failure,
					__( 'Saving failed. Try again.', 'aggressive-apparel' )
				),
			} );
		} finally {
			setSaving( false );
		}
	};

	if ( specs.length === 0 ) {
		return (
			<p className="aa-ps-empty">
				{ __(
					'Turn on or add a spec on the Specs tab first.',
					'aggressive-apparel'
				) }
			</p>
		);
	}

	return (
		<div className="aa-ps-guides-grid">
			<p className="aa-ps-help">
				{ __(
					'Each guide’s values, side by side. Tick several guides to give them one value at once. An empty cell uses the spec’s default, shown in grey.',
					'aggressive-apparel'
				) }
			</p>

			<div className="aa-ps-toolbar">
				<input
					type="search"
					className="aa-ps-toolbar__search"
					placeholder={ __(
						'Search size guides',
						'aggressive-apparel'
					) }
					aria-label={ __(
						'Search size guides',
						'aggressive-apparel'
					) }
					value={ search }
					onChange={ ( event ) => setSearch( event.target.value ) }
				/>
				<CheckboxControl
					label={ __(
						'Only guides with empty values',
						'aggressive-apparel'
					) }
					checked={ gapsOnly }
					onChange={ setGapsOnly }
				/>
				{ rows === null && ! error && <Spinner /> }
			</div>

			<BulkBar
				specs={ specs }
				count={ visibleSelected.length }
				// Only ticked guides in view: a filtered-out one is never changed.
				onApply={ ( key, value ) =>
					onEdits(
						visibleSelected.reduce(
							( next, id ) => ( {
								...next,
								[ id ]: {
									...( next[ id ] ?? {} ),
									[ key ]: value,
								},
							} ),
							edits
						)
					)
				}
				onClear={ () => setSelected( [] ) }
			/>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ rows !== null && (
				<SpecGrid
					specs={ specs }
					rows={ gridRows }
					edits={ edits }
					onEdit={ edit }
					selected={ selected }
					onSelect={ setSelected }
					heading={ __( 'Size guide', 'aggressive-apparel' ) }
					empty={
						gapsOnly
							? __(
									'No guides with empty values.',
									'aggressive-apparel'
								)
							: __(
									'No size guides match.',
									'aggressive-apparel'
								)
					}
				/>
			) }

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }

			<div className="aa-ps-save">
				<Button
					variant="primary"
					disabled={ changes === 0 || saving }
					onClick={ () => void save() }
				>
					{ saving
						? __( 'Saving…', 'aggressive-apparel' )
						: __( 'Save values', 'aggressive-apparel' ) }
				</Button>
				{ saving && <Spinner /> }
				{ changes > 0 && ! saving && (
					<>
						<Button
							variant="tertiary"
							onClick={ () => onEdits( {} ) }
						>
							{ __( 'Discard changes', 'aggressive-apparel' ) }
						</Button>
						<span className="aa-ps-help">
							{ sprintf(
								/* translators: %d: number of changed values. */
								_n(
									'%d unsaved change',
									'%d unsaved changes',
									changes,
									'aggressive-apparel'
								),
								changes
							) }
						</span>
					</>
				) }
			</div>
		</div>
	);
}
