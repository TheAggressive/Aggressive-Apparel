import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

import { findVoiceViolations } from './voice.mjs';

const THEME = path.resolve(
	path.dirname( fileURLToPath( import.meta.url ) ),
	'../..'
);

const pot = ( ...msgids ) =>
	[
		'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n',
		...msgids.map(
			( id, i ) =>
				`#: patterns/probe.php:${ i + 1 }\nmsgid "${ id }"\nmsgstr ""\n`
		),
	].join( '\n' );

test( 'flags retired clichés with the file that ships them', () => {
	const found = findVoiceViolations(
		pot( 'Fresh drops, just landed.', 'New arrivals' )
	);

	assert.equal( found.length, 2 );
	assert.deepEqual( found[ 0 ].refs, [ 'patterns/probe.php:1' ] );
	assert.match( found.map( ( f ) => f.reason ).join( ' ' ), /cliché/ );
} );

test( 'flags shouted short messages but not calm ones or long admin sentences', () => {
	assert.equal( findVoiceViolations( pot( 'Added to cart!' ) ).length, 1 );
	assert.deepEqual( findVoiceViolations( pot( 'Added to your cart' ) ), [] );
	assert.deepEqual(
		findVoiceViolations(
			pot(
				'Terms of Service page setting updated successfully. Remember to update your menus, and check the footer links too!'
			)
		),
		[]
	);
} );

test( 'flags unsupported claims and invented attribution', () => {
	assert.equal(
		findVoiceViolations( pot( 'Limited runs sell out fast.' ) ).length,
		1
	);
	assert.equal(
		findVoiceViolations( pot( 'PREMIUM QUALITY GUARANTEED' ) ).length,
		1
	);
	assert.equal(
		findVoiceViolations( pot( '— Alex Rivera, Founder' ) ).length,
		1
	);
} );

test( 'flags subculture lists but allows a single scene in context', () => {
	assert.equal(
		findVoiceViolations(
			pot( 'Shaped by punk, metal, skate and motocross culture.' )
		).length,
		1
	);
	assert.deepEqual(
		findVoiceViolations(
			pot( 'Drawn from the pit lane and moto culture.' )
		),
		[]
	);
	assert.deepEqual(
		findVoiceViolations( pot( 'Shaped by alternative culture.' ) ),
		[]
	);
} );

test( 'theme-owned copy in the POT follows the voice guide', () => {
	const found = findVoiceViolations(
		fs.readFileSync(
			path.join( THEME, 'languages/aggressive-apparel.pot' ),
			'utf8'
		)
	);

	assert.deepEqual(
		found.map(
			( f ) => `${ f.reason }: "${ f.msgid }" (${ f.refs.join( ', ' ) })`
		),
		[]
	);
} );
