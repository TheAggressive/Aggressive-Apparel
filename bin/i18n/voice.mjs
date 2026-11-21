/**
 * Brand voice guard for theme-owned copy (docs/brand-voice.md).
 *
 * Every customer-facing string the theme ships is a msgid in the POT, so the
 * POT is the inventory this checks. It enforces only what can be decided
 * mechanically: phrases the voice guide retires, invented attribution, and
 * shouted short confirmations. Tone beyond that is a review call.
 */

import { parsePo } from './po.mjs';

/**
 * Retired phrases, with the reason the guide gives. Case-insensitive.
 *
 * @type {Array<{ pattern: RegExp, reason: string }>}
 */
export const VOICE_RULES = [
	{ pattern: /\bunleash/i, reason: 'generic rebellion cliché' },
	{
		pattern: /stand out from the crowd/i,
		reason: 'generic rebellion cliché',
	},
	{ pattern: /\belevate your\b/i, reason: 'generic fashion cliché' },
	{ pattern: /\bfor the bold\b/i, reason: 'generic rebellion cliché' },
	{ pattern: /\bbuilt different\b/i, reason: 'generic rebellion cliché' },
	{ pattern: /\bnot for everyone\b/i, reason: 'manufactured exclusivity' },
	{ pattern: /\bno apologies\b/i, reason: 'manufactured attitude' },
	{ pattern: /\bfresh drops?\b/i, reason: 'generic drop cliché' },
	{ pattern: /\bjust landed\b/i, reason: 'generic drop cliché' },
	{
		pattern: /\brefuses? to blend in\b/i,
		reason: 'generic rebellion cliché',
	},
	{ pattern: /\bwear your attitude\b/i, reason: 'manufactured attitude' },
	{ pattern: /\bgame[- ]?changer\b/i, reason: 'empty superlative' },
	{ pattern: /\bmust[- ]have\b/i, reason: 'empty superlative' },
	{ pattern: /\blevel up\b/i, reason: 'generic cliché' },
	{ pattern: /\bunlocked\b/i, reason: 'gamified shipping language' },
	{
		pattern: /\b(sell|selling) out fast\b|\bmove fast\b/i,
		reason: 'unsupported scarcity claim',
	},
	{
		pattern: /\bpremium quality\b|\bquality guaranteed\b/i,
		reason: 'unsupported quality claim',
	},
	{
		// Placeholder people the starter patterns once shipped as if real.
		pattern:
			/\b(Alex Rivera|Zara Okonkwo|Kai Mercer|Rico Vale|Nia Flux|Jules Park)\b/,
		reason: 'invented person: feature a real one or use a [bracketed] prompt',
	},
];

/**
 * Scenes the brand grew out of. Naming one is fine in context (a capsule's
 * own world); listing three or more frames the label as one scene's merch,
 * which the positioning rules out: say "alternative culture" instead.
 */
const SUBCULTURES =
	/\b(skate\w*|punk\w*|tattoo\w*|moto\w*|metal|bmx|surf\w*|hip[- ]hop|rave\w*|grunge)\b/gi;

/** Short confirmations and labels end calmly: "Added", not "Added!". */
const SHOUTED_SHORT = /^[^.?]{1,60}!$/;

/**
 * Voice violations in POT/PO content, one per offending msgid and rule.
 *
 * @param {string} content POT or PO file content.
 * @returns {Array<{ msgid: string, reason: string, refs: string[] }>}
 */
export function findVoiceViolations( content ) {
	const { entries } = parsePo( content );
	const found = [];

	for ( const entry of entries ) {
		const msgid = /** @type {string} */ ( entry.msgid );
		const refs = entry.comments
			.filter( ( line ) => line.startsWith( '#:' ) )
			.flatMap( ( line ) => line.slice( 2 ).trim().split( /\s+/ ) );

		for ( const { pattern, reason } of VOICE_RULES ) {
			if ( pattern.test( msgid ) ) {
				found.push( { msgid, reason, refs } );
			}
		}
		const scenes = new Set(
			( msgid.match( SUBCULTURES ) ?? [] ).map( ( word ) =>
				word.toLowerCase().slice( 0, 4 )
			)
		);
		if ( scenes.size >= 3 ) {
			found.push( { msgid, reason: 'lists subcultures', refs } );
		}
		if ( SHOUTED_SHORT.test( msgid.trim() ) ) {
			found.push( { msgid, reason: 'shouted short message', refs } );
		}
	}

	return found;
}
