/**
 * Product Specs settings — which products a size guide is for.
 *
 * Its categories and whether it's the store-wide default, the same in the
 * guide editor and the import. The caller gives the heading; `children` adds
 * notes about the choice.
 *
 * @package Aggressive_Apparel
 */

import type { ReactNode } from 'react';
import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { Category } from './_types';
import CategoryPicker from './_CategoryPicker';

type Props = {
	categories: Category[];
	selected: number[];
	onCategories: ( selected: number[] ) => void;
	isDefault: boolean;
	onDefault: ( isDefault: boolean ) => void;
	children?: ReactNode;
};

export default function WhereItApplies( {
	categories,
	selected,
	onCategories,
	isDefault,
	onDefault,
	children,
}: Props ) {
	return (
		<>
			<p className="aa-ps-help">
				{ __(
					'Products in these categories use this guide, unless they pick another on their Size & specs tab.',
					'aggressive-apparel'
				) }
			</p>
			<CategoryPicker
				categories={ categories }
				selected={ selected }
				onChange={ onCategories }
				label={ __( 'Categories', 'aggressive-apparel' ) }
			/>
			{ children }
			<ToggleControl
				label={ __( 'Store-wide default', 'aggressive-apparel' ) }
				help={ __(
					'Used for products with no guide of their own or from their category.',
					'aggressive-apparel'
				) }
				checked={ isDefault }
				onChange={ onDefault }
				__nextHasNoMarginBottom
			/>
		</>
	);
}
