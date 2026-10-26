import { cleanUpFixtures } from './fixture-cleanup';

/**
 * Remove everything the suite created and restore the options it changed, so
 * a persistent site (local Studio) ends a run as it started.
 */
export default function globalTeardown(): void {
  if (!process.env.WP_BASE_URL) {
    return;
  }
  const report = cleanUpFixtures();
  console.log(
    `E2E cleanup: ${report.posts} posts, ${report.terms} terms, ${report.attributes} attributes, ${report.shippingMethods} shipping methods removed; ${report.options} options restored.`
  );
}
