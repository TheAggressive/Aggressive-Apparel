/**
 * Every test must assert something: a test whose expectations never run
 * passes vacuously (an early return, a callback that never fires, an
 * un-awaited promise). Mirrors PHPUnit's failOnRisky.
 */
beforeEach( () => {
	expect.hasAssertions();
} );
