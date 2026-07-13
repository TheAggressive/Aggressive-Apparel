/**
 * Shared heart silhouette path (matches Icons::heart_path()).
 *
 * Wishlist toggles use a dual-layer SVG (fill + stroke). Icon-block
 * consumers can still render a simple filled or outlined glyph.
 */

export const HEART_PATH =
  'm7 3c-1.5355 0-3.0784 0.5-4.25 1.7-2.3431 2.4-2.2788 6.1 0 8.5l9.25 9.8 9.25-9.8c2.279-2.4 2.343-6.1 0-8.5-2.343-2.3-6.157-2.3-8.5 0l-0.75 0.8-0.75-0.8c-1.172-1.2-2.7145-1.7-4.25-1.7z';

export const HEART_VIEWBOX = '1.75 2.25 20.5 20.5';

export const HeartIcon = ({
  className,
  outlined = true,
  size = 22,
}: {
  className?: string;
  outlined?: boolean;
  size?: number;
}): JSX.Element => {
  if (!outlined) {
    return (
      <svg
        className={className}
        viewBox={HEART_VIEWBOX}
        width={size}
        height={size}
        fill='currentColor'
        aria-hidden='true'
      >
        <path d={HEART_PATH} />
      </svg>
    );
  }

  return (
    <svg
      className={className}
      viewBox={HEART_VIEWBOX}
      width={size}
      height={size}
      aria-hidden='true'
    >
      <path className='aggressive-apparel-wishlist__icon-fill' d={HEART_PATH} />
      <path
        className='aggressive-apparel-wishlist__icon-stroke'
        d={HEART_PATH}
      />
    </svg>
  );
};
