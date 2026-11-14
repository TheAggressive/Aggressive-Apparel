/** @jest-environment jsdom */

// A module, so its helpers don't collide with other script-style suites.
export {};

interface MockStore {
	state: { cartApiUrl: string; cartCount: number };
	actions: { refreshCartCount: () => void };
}

// Captured by the module's top-level store registration.
var mockStore: MockStore;

jest.mock(
	'@wordpress/interactivity',
	() => ( {
		store: ( _name: string, config: MockStore ) => {
			mockStore = config;
			return config;
		},
		getElement: () => ( { ref: null } ),
	} ),
	{ virtual: true }
);
jest.mock(
	'@aggressive-apparel/use-overlay',
	() => ( {
		activateOverlayFocus: jest.fn(),
		closeOverlay: jest.fn(),
		prepareOverlayOpen: jest.fn(),
	} ),
	{ virtual: true }
);

type Reply = { ok: boolean; status: number; body: unknown };

function respond( { ok, status, body }: Reply ): Promise< Response > {
	return Promise.resolve( {
		ok,
		status,
		json: () => Promise.resolve( body ),
	} as Response );
}

/** Let queued fetch/json promise chains settle. */
async function settle(): Promise< void > {
	for ( let i = 0; i < 5; i++ ) {
		await Promise.resolve();
	}
}

describe( 'bottom nav cart count', () => {
	beforeAll( () => {
		require( '../bottom-nav' );
	} );

	beforeEach( () => {
		mockStore.state.cartApiUrl = '/wp-json/wc/store/v1/cart';
		mockStore.state.cartCount = 3;
	} );

	it( 'takes the count from a successful cart response', async () => {
		window.fetch = jest.fn( () =>
			respond( { ok: true, status: 200, body: { items_count: 5 } } )
		) as unknown as typeof fetch;

		mockStore.actions.refreshCartCount();
		await settle();

		expect( mockStore.state.cartCount ).toBe( 5 );
	} );

	it( 'keeps the last known count when the request fails', async () => {
		window.fetch = jest.fn( () =>
			respond( {
				ok: false,
				status: 403,
				body: { code: 'woocommerce_rest_cart_error' },
			} )
		) as unknown as typeof fetch;

		mockStore.actions.refreshCartCount();
		await settle();

		expect( mockStore.state.cartCount ).toBe( 3 );
	} );

	it( 'ignores a response without a usable count', async () => {
		window.fetch = jest.fn( () =>
			respond( { ok: true, status: 200, body: {} } )
		) as unknown as typeof fetch;

		mockStore.actions.refreshCartCount();
		await settle();

		expect( mockStore.state.cartCount ).toBe( 3 );
	} );

	it( 'lets only the newest of overlapping refreshes write', async () => {
		let releaseOld!: () => void;
		const replies = [
			new Promise< Response >( ( resolve ) => {
				releaseOld = () =>
					resolve(
						respond( {
							ok: true,
							status: 200,
							body: { items_count: 1 },
						} )
					);
			} ),
			respond( { ok: true, status: 200, body: { items_count: 2 } } ),
		];
		window.fetch = jest.fn( () =>
			replies.shift()
		) as unknown as typeof fetch;

		mockStore.actions.refreshCartCount();
		mockStore.actions.refreshCartCount();
		await settle();
		releaseOld();
		await settle();

		expect( mockStore.state.cartCount ).toBe( 2 );
	} );
} );
