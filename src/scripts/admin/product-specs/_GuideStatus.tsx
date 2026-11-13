/**
 * Product Specs settings — a size guide at a glance.
 *
 * One line per part of the guide, saying what shoppers get from it, each a
 * link to that part. Worked out by guideStatus, the same as the rest of the
 * editor.
 *
 * @package Aggressive_Apparel
 */

import { __, _n, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import { SECTION, type GuideStatus, type PartState } from './_guideSummary';

/** Categories named before the rest are counted. */
const NAMED = 2;

function partText( state: PartState ): string {
	if ( state === 'shown' ) {
		return __( 'Shown', 'aggressive-apparel' );
	}
	return state === 'hidden'
		? __( 'Hidden', 'aggressive-apparel' )
		: __( 'None', 'aggressive-apparel' );
}

function chartText( sizes: number | null ): string {
	if ( sizes === null ) {
		return __( 'Old layout', 'aggressive-apparel' );
	}
	return sizes === 0
		? __( 'None, so no Size Guide button', 'aggressive-apparel' )
		: sprintf(
				/* translators: %d: number of sizes. */
				_n( '%d size', '%d sizes', sizes, 'aggressive-apparel' ),
				sizes
			);
}

function specsText( { shown, empty, hidden }: GuideStatus[ 'specs' ] ): string {
	const parts: string[] = [
		sprintf(
			/* translators: %d: number of specs. */
			_n( '%d shown', '%d shown', shown, 'aggressive-apparel' ),
			shown
		),
	];
	if ( empty > 0 ) {
		parts.push(
			sprintf(
				/* translators: %d: number of specs. */
				_n(
					'%d with no value',
					'%d with no value',
					empty,
					'aggressive-apparel'
				),
				empty
			)
		);
	}
	if ( hidden > 0 ) {
		parts.push(
			sprintf(
				/* translators: %d: number of specs. */
				_n( '%d hidden', '%d hidden', hidden, 'aggressive-apparel' ),
				hidden
			)
		);
	}
	return parts.join( ' · ' );
}

function whereText( { categories, isDefault }: GuideStatus ): string {
	const parts: string[] = [];
	if ( categories.length > 0 ) {
		const rest = categories.length - NAMED;
		parts.push(
			rest > 0
				? sprintf(
						/* translators: 1: category names, 2: number of other categories. */
						_n(
							'%1$s and %2$d more',
							'%1$s and %2$d more',
							rest,
							'aggressive-apparel'
						),
						categories.slice( 0, NAMED ).join( ', ' ),
						rest
					)
				: categories.join( ', ' )
		);
	}
	if ( isDefault ) {
		parts.push( __( 'store-wide default', 'aggressive-apparel' ) );
	}
	return parts.length > 0
		? parts.join( ' · ' )
		: __( 'Only products that pick it', 'aggressive-apparel' );
}

export default function GuideStatusStrip( {
	status,
}: {
	status: GuideStatus;
} ) {
	const rows = [
		{
			id: SECTION.chart,
			label: __( 'Size chart', 'aggressive-apparel' ),
			text: chartText( status.sizes ),
			empty: status.sizes === 0,
		},
		{
			id: SECTION.notes,
			label: __( 'Notes', 'aggressive-apparel' ),
			text: partText( status.notes ),
			empty: status.notes !== 'shown',
		},
		{
			id: SECTION.notes,
			label: __( 'Diagram', 'aggressive-apparel' ),
			text: partText( status.diagram ),
			empty: status.diagram !== 'shown',
		},
		{
			id: SECTION.specs,
			label: __( 'Specs', 'aggressive-apparel' ),
			text: specsText( status.specs ),
			empty: status.specs.shown === 0,
		},
		{
			id: SECTION.where,
			label: __( 'Used for', 'aggressive-apparel' ),
			text: whereText( status ),
			empty: status.categories.length === 0 && ! status.isDefault,
		},
	];

	return (
		<ul
			className="aa-ps-guide-status"
			aria-label={ __( 'This guide at a glance', 'aggressive-apparel' ) }
		>
			{ rows.map( ( row ) => (
				<li key={ row.label }>
					<a
						href={ `#${ row.id }` }
						className={ cx(
							'aa-ps-guide-status__item',
							row.empty && 'is-empty'
						) }
					>
						<span className="aa-ps-eyebrow">{ row.label }</span>
						<span className="aa-ps-guide-status__text">
							{ row.text }
						</span>
					</a>
				</li>
			) ) }
		</ul>
	);
}
