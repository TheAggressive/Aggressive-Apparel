/**
 * Plain-text guarantees for block labels.
 *
 * getBlockLabel reduces block content to a label. It carried its own single-
 * pass tag strip — duplicated across the paragraph and heading branches — which
 * CodeQL flagged as incomplete multi-character sanitization: removing the inner
 * tag from `<scr<script>ipt>` splices the remainder back into `<script>`, so a
 * single pass can build the tag it was meant to delete.
 *
 * Labels are short strings rendered as text in the editor UI, so the job is to
 * leave no parseable tag — not to delete every angle bracket, which would turn
 * a heading reading "5 > 3" into "5  3".
 */

import type { EditorBlock } from '../types';
import { getBlockLabel } from '../utils/getBlockLabel';

/** Minimal block fixture. */
const block = (
  name: string,
  attributes: Record<string, unknown>
): EditorBlock =>
  ({ clientId: 'test-client-id', name, attributes }) as EditorBlock;

describe('getBlockLabel', () => {
  it('leaves no angle bracket in a paragraph label', () => {
    const payloads = [
      '<scr<script>ipt>alert(1)</scr</script>ipt>',
      '<<div>img src=x onerror=alert(1)>',
      '<img src=x onerror="alert(1)">',
    ];

    for (const content of payloads) {
      expect(getBlockLabel(block('core/paragraph', { content }))).not.toMatch(
        /<[a-z!/]/iu
      );
    }
  });

  it('leaves no angle bracket in a heading label', () => {
    expect(
      getBlockLabel(
        block('core/heading', {
          content: '<scr<script>ipt>alert(1)</scr</script>ipt>',
        })
      )
    ).not.toMatch(/<[a-z!/]/iu);
  });

  it('keeps the readable text of ordinary markup', () => {
    expect(
      getBlockLabel(block('core/paragraph', { content: '<b>Hello</b> world' }))
    ).toBe('Hello world');
  });

  it('truncates long content to a label-sized string', () => {
    const label = getBlockLabel(
      block('core/paragraph', { content: 'x'.repeat(120) })
    );

    expect(label).toHaveLength(33);
    expect(label.endsWith('...')).toBe(true);
  });

  it('falls back when content is empty or the block is missing', () => {
    expect(getBlockLabel(block('core/paragraph', { content: '' }))).toBe(
      'Paragraph'
    );
    expect(getBlockLabel(block('core/heading', { content: '<p></p>' }))).toBe(
      'Heading'
    );
    expect(getBlockLabel(null)).toBe('');
  });
});
