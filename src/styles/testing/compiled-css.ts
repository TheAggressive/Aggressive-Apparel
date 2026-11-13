/**
 * Compiled-CSS queries for contract tests.
 *
 * Compiles a stylesheet through the theme's real PostCSS pipeline
 * (postcss.config.cjs: import inlining, nesting, Lightning CSS) and answers
 * "which declarations does this selector get, and how?" — so tests assert what
 * ships, independent of how the source is formatted or nested. Selectors are
 * matched in Lightning's canonical form (an expected selector is compiled the
 * same way), and :is() lists are expanded, so `:is(.a,.b) .c` matches `.a .c`.
 *
 * @package Aggressive_Apparel
 */

import fs from 'node:fs';
import path from 'node:path';
import postcss, { type AtRule, type Root, type Rule } from 'postcss';
import selectorParser from 'postcss-selector-parser';

// CommonJS config shared with the build.
const config = require( '../../../postcss.config.cjs' ) as {
	plugins: postcss.AcceptedPlugin[];
};

export interface CompiledDeclaration {
	value: string;
	important: boolean;
	/** Innermost @layer block, or null when unlayered. */
	layer: string | null;
	/** Enclosing conditional rules, outermost first, e.g. `@media (hover:hover)`. */
	conditions: string[];
}

const THEME_ROOT = path.resolve( __dirname, '../../..' );

async function compile( css: string, from: string ): Promise< Root > {
	const result = await postcss( config.plugins ).process( css, { from } );
	return postcss.parse( result.css );
}

/** Every alternative a selector stands for, with :is() lists expanded. */
function expand( selector: string ): string[] {
	const out: string[] = [];
	selectorParser( ( selectors ) => {
		selectors.each( ( complex ) => {
			const queue = [ complex ];
			while ( queue.length ) {
				const current = queue.shift()!;
				let isNode: selectorParser.Pseudo | undefined;
				current.walkPseudos( ( p ) => {
					if (
						! isNode &&
						p.value === ':is' &&
						p.parent === current
					) {
						isNode = p;
					}
				} );
				if ( ! isNode ) {
					out.push( current.toString().trim() );
					continue;
				}
				const index = current.index( isNode );
				for ( const alternative of isNode.nodes ) {
					const clone = current.clone() as selectorParser.Selector;
					clone.nodes[ index ].replaceWith(
						...( alternative.clone() as selectorParser.Selector )
							.nodes
					);
					queue.push( clone );
				}
			}
		} );
	} ).processSync( selector );
	return out;
}

function enclosing(
	rule: Rule
): Pick< CompiledDeclaration, 'layer' | 'conditions' > {
	let layer: string | null = null;
	const conditions: string[] = [];
	for (
		let node = rule.parent;
		node && node.type === 'atrule';
		node = node.parent
	) {
		const at = node as AtRule;
		if ( at.name === 'layer' ) {
			layer ??= at.params;
		} else {
			conditions.unshift( `@${ at.name } ${ at.params }` );
		}
	}
	return { layer, conditions };
}

export class CompiledCss {
	private constructor( private readonly root: Root ) {}

	/** Compile a theme stylesheet (path relative to the theme root). */
	static async load( relativePath: string ): Promise< CompiledCss > {
		const file = path.join( THEME_ROOT, relativePath );
		return new CompiledCss(
			await compile( fs.readFileSync( file, 'utf8' ), file )
		);
	}

	/** Lightning's canonical spelling of each selector alternative. */
	private static async canonical( selector: string ): Promise< string[] > {
		const probe = await compile(
			`${ selector }{--probe:0}`,
			path.join( THEME_ROOT, 'probe.css' )
		);
		const rules: string[] = [];
		probe.walkRules( ( rule ) => {
			rules.push( ...expand( rule.selector ) );
		} );
		return rules;
	}

	/** Every declaration of `prop` on rules matching `selector` exactly. */
	async declarations(
		selector: string,
		prop: string
	): Promise< CompiledDeclaration[] > {
		const wanted = new Set( await CompiledCss.canonical( selector ) );
		const found: CompiledDeclaration[] = [];
		this.root.walkRules( ( rule ) => {
			if (
				rule.parent?.type === 'atrule' &&
				/keyframes$/.test( ( rule.parent as AtRule ).name )
			) {
				return;
			}
			if ( ! expand( rule.selector ).some( ( s ) => wanted.has( s ) ) ) {
				return;
			}
			rule.each( ( node ) => {
				if ( node.type === 'decl' && node.prop === prop ) {
					found.push( {
						value: node.value,
						important: node.important === true,
						...enclosing( rule ),
					} );
				}
			} );
		} );
		return found;
	}

	/** Every declaration, with the selector and context it sits in. */
	all(): Array< CompiledDeclaration & { selector: string; prop: string } > {
		const found: Array<
			CompiledDeclaration & { selector: string; prop: string }
		> = [];
		this.root.walkDecls( ( decl ) => {
			const rule = decl.parent;
			if ( rule?.type !== 'rule' ) {
				return;
			}
			found.push( {
				selector: ( rule as Rule ).selector,
				prop: decl.prop,
				value: decl.value,
				important: decl.important === true,
				...enclosing( rule as Rule ),
			} );
		} );
		return found;
	}

	/** Every selector alternative in the stylesheet. */
	selectors(): string[] {
		const all: string[] = [];
		this.root.walkRules( ( rule ) => {
			all.push( ...expand( rule.selector ) );
		} );
		return all;
	}

	/** Every declaration value of `prop`, anywhere. */
	values( prop?: string ): string[] {
		const all: string[] = [];
		this.root.walkDecls( ( decl ) => {
			if ( ! prop || decl.prop === prop ) {
				all.push( decl.value );
			}
		} );
		return all;
	}

	/** Cascade layers in the order the browser fixes them: first appearance. */
	layerOrder(): string[] {
		const order: string[] = [];
		this.root.walkAtRules( 'layer', ( at ) => {
			for ( const name of at.params
				.split( ',' )
				.map( ( n ) => n.trim() ) ) {
				if ( name && ! order.includes( name ) ) {
					order.push( name );
				}
			}
		} );
		return order;
	}

	/** Names declared by @keyframes. */
	keyframes(): string[] {
		const names: string[] = [];
		this.root.walkAtRules( /keyframes$/, ( at ) => {
			names.push( at.params );
		} );
		return names;
	}
}
