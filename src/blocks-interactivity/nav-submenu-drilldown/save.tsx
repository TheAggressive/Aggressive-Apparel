/**
 * Nav Drilldown Block Save
 *
 * Dynamic block — PHP renders the wrapper. We save inner block content only.
 *
 * @package Aggressive_Apparel
 */

import { InnerBlocks } from '@wordpress/block-editor';

export default function Save(): JSX.Element {
  return <InnerBlocks.Content />;
}
