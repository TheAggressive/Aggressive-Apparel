import type { SpecItem } from '../_types';
import { blankItem } from '../_utils';
import {
	conflicts,
	discardEdit,
	edited,
	rebase,
	recordEdit,
} from '../_specEdits';

const fit: SpecItem = {
	...blankItem( 'pick', [ 'Snug', 'Regular', 'Relaxed' ] ),
	key: 'fit',
	name: 'Fit',
};

describe( 'recordEdit and edited', () => {
	it( 'lays only the fields changed over the latest saved spec', () => {
		const edits = recordEdit( {}, fit, { unit: 'cm' } );
		const renamedElsewhere = {
			...fit,
			steps: [ 'Snug', 'Classic', 'Relaxed' ],
		};

		expect( edited( renamedElsewhere, edits ) ).toEqual( {
			...renamedElsewhere,
			unit: 'cm',
		} );
	} );

	it( 'forgets a field changed back, and the spec once nothing is left', () => {
		const edits = recordEdit( recordEdit( {}, fit, { unit: 'cm' } ), fit, {
			unit: fit.unit,
		} );

		expect( edits ).toEqual( {} );
	} );

	it( 'remembers what a field was before its first change only', () => {
		const edits = recordEdit( recordEdit( {}, fit, { unit: 'cm' } ), fit, {
			unit: 'in',
		} );

		expect( edits.fit ).toEqual( {
			patch: { unit: 'in' },
			base: { unit: '' },
		} );
	} );
} );

describe( 'conflicts and rebase', () => {
	const edits = recordEdit( {}, fit, { unit: 'cm', name: 'Tee fit' } );

	it( 'finds none while the Specs tab changed other fields', () => {
		expect(
			conflicts( edits, [ { ...fit, steps: [ 'A', 'B', 'C' ] } ] )
		).toEqual( [] );
	} );

	it( 'finds a field changed in both places, and a spec deleted', () => {
		expect( conflicts( edits, [ { ...fit, unit: 'mm' } ] ) ).toEqual( [
			{ key: 'fit', name: 'Fit', removed: false },
		] );
		expect( conflicts( edits, [] ) ).toEqual( [
			{ key: 'fit', name: 'Fit', removed: true },
		] );
	} );

	it( 'drops only the conflicting fields, and a deleted spec’s changes', () => {
		expect( rebase( edits, [ { ...fit, unit: 'mm' } ] ) ).toEqual( {
			fit: { patch: { name: 'Tee fit' }, base: { name: 'Fit' } },
		} );
		expect( rebase( edits, [] ) ).toEqual( {} );
	} );
} );

describe( 'discardEdit', () => {
	it( 'drops one spec’s changes', () => {
		expect(
			discardEdit( recordEdit( {}, fit, { unit: 'cm' } ), 'fit' )
		).toEqual( {} );
	} );
} );
