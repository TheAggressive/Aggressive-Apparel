/**
 * Cart savings total — pure logic for the cart/checkout "You save" line.
 *
 * @package Aggressive_Apparel
 */

/** The slice of a Store API cart item the savings sum needs. */
export interface SavingsCartItem {
  quantity: number;
  prices: {
    price: string;
    regular_price: string;
  };
}

/**
 * Sum of (regular − current price) × quantity across the cart, in the
 * currency's minor units, as the Store API reports prices. Coupons are left
 * out: WooCommerce already lists them as their own discount line.
 *
 * @param items Store API cart items.
 * @return Savings in minor units; 0 when nothing is on sale.
 */
export function getCartSavings(items: readonly SavingsCartItem[]): number {
  return items.reduce((total, item) => {
    const regular = parseInt(item.prices.regular_price, 10);
    const price = parseInt(item.prices.price, 10);

    if (!Number.isFinite(regular) || !Number.isFinite(price)) {
      return total;
    }

    return total + Math.max(0, regular - price) * item.quantity;
  }, 0);
}
