/**
 * E2E for the choice-control spacing contract.
 *
 * Every checkbox and radio sits one choice gap (`--aa-field-choice-gap`, the
 * checkout's spacing) from its text, whatever markup renders it. Each
 * structure reaches that gap through a different rule — the label margin plus
 * its space character, a sibling label, a flex gap, Fluent Forms' table
 * cells — and a plugin's unlayered CSS can silently cancel the layered
 * baseline, so this measures the rendered gap, not a computed margin.
 *
 * Fluent Forms is rendered for real: the fixture builds a form from the
 * plugin's own field defaults, so a markup change in a plugin update fails
 * here instead of on a live form.
 */

import { test, expect, type Page } from '@playwright/test';
import {
  CHOICE_GAP_TOLERANCE_PX,
  choiceGapPx,
  measureChoiceGaps,
  type ChoiceGap,
} from './choice-gap';
import { wpCli } from './wp-cli';

const FIXTURE_OWNER_META = '_aa_e2e_fixture_owner';
const FIXTURE_OWNER = 'form-choice-spacing';

/** Every Fluent Forms element that renders a labelled checkbox or radio. */
const FLUENT_CHOICE_ELEMENTS = [
  'input_checkbox',
  'input_radio',
  'terms_and_condition',
  'gdpr_agreement',
];

let fluentFormId = 0;
let fluentPageUrl = '';

test.beforeAll(() => {
  const output = wpCli([
    'eval',
    `if (!defined('FLUENTFORM_DIR_PATH')) {
       echo wp_json_encode(array('error' => 'Fluent Forms is not active.'));
       return;
     }
     include FLUENTFORM_DIR_PATH . 'app/Services/FormBuilder/DefaultElements.php';
     $defaults = array_merge(...array_values($fluentformDefaultElements));
     $form = (new \\FluentForm\\App\\Services\\Form\\FormService())->store(
       array('predefined' => 'blank_form', 'type' => 'form', 'title' => 'AA E2E choice spacing')
     );
     $structure = json_decode($form->form_fields, true);
     $structure['fields'] = array();
     foreach (array(${FLUENT_CHOICE_ELEMENTS.map(element => `'${element}'`).join(', ')}) as $i => $element) {
       $field = $defaults[$element];
       unset($field['editor_options']);
       $field['index'] = $i;
       $field['uniqElKey'] = 'el_aa_e2e_' . $i;
       $structure['fields'][] = $field;
     }
     $form->form_fields = wp_json_encode($structure);
     $form->save();
     $page_id = wp_insert_post(array(
       'post_type' => 'page',
       'post_status' => 'publish',
       'post_title' => 'AA E2E choice spacing',
       'post_content' => '<!-- wp:shortcode -->[fluentform id="' . (int) $form->id . '" theme="ffs_inherit_theme"]<!-- /wp:shortcode -->',
     ));
     update_post_meta($page_id, '${FIXTURE_OWNER_META}', '${FIXTURE_OWNER}');
     echo wp_json_encode(array('formId' => (int) $form->id, 'url' => get_permalink($page_id)));`,
  ]);
  const fixture = JSON.parse(output) as {
    formId?: number;
    url?: string;
    error?: string;
  };
  expect(fixture.error).toBeUndefined();
  fluentFormId = fixture.formId ?? 0;
  fluentPageUrl = fixture.url ?? '';
});

test.afterAll(() => {
  wpCli([
    'eval',
    `foreach (get_posts(array('post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '${FIXTURE_OWNER_META}', 'meta_value' => '${FIXTURE_OWNER}')) as $id) { wp_delete_post($id, true); }
     if (${fluentFormId} && class_exists('\\FluentForm\\App\\Models\\Form')) { \\FluentForm\\App\\Models\\Form::remove(${fluentFormId}); }`,
  ]);
});

function expectChoiceGaps(gaps: ChoiceGap[], expected: number): void {
  for (const { name, gap } of gaps) {
    expect
      .soft(Math.abs(gap - expected), `${name}: ${gap}px from its text`)
      .toBeLessThanOrEqual(CHOICE_GAP_TOLERANCE_PX);
  }
}

async function injectChoices(page: Page): Promise<void> {
  await page.evaluate(() => {
    const host = document.createElement('div');
    host.id = 'aa-e2e-choices';
    // Markup copied from each renderer: plain labels, a label with a link,
    // the core comment form's sibling label, WooCommerce's classic login,
    // and the theme's field primitive (back-in-stock consent).
    host.innerHTML = `
      <label><input name="plain" type="checkbox"> I agree</label>
      <label><input name="radio" type="radio"> Email</label>
      <label><input name="linked" type="checkbox"> I accept the <a href="#">terms</a></label>
      <p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"> <label for="wp-comment-cookies-consent">Save my name, email, and website in this browser for the next time I comment.</label></p>
      <form class="woocommerce-form woocommerce-form-login"><label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme"><input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" value="forever"> <span>Remember me</span></label></form>
      <label class="aa-bis__consent aggressive-apparel-field aggressive-apparel-field--checkbox"><input name="field" type="checkbox" required> I agree to receive a one-time email when this product is restocked.</label>`;
    document.body.appendChild(host);
  });
}

test.describe('form choice spacing', () => {
  test('native, WordPress, WooCommerce classic and field checkboxes keep the choice gap', async ({
    page,
  }) => {
    await page.goto('/');
    await injectChoices(page);

    const expected = await choiceGapPx(page);
    expect(expected).toBeGreaterThan(0);

    const gaps = await measureChoiceGaps(page, '#aa-e2e-choices');
    expect(gaps.map(gap => gap.name)).toEqual([
      'plain',
      'radio',
      'linked',
      'wp-comment-cookies-consent',
      'rememberme',
      'field',
    ]);
    expectChoiceGaps(gaps, expected);
  });

  test('Fluent Forms "Inherit Theme Style" choices keep the choice gap', async ({
    page,
  }) => {
    await page.goto(fluentPageUrl);
    await page.waitForSelector('.fluentform.ff-inherit-theme-style');

    const expected = await choiceGapPx(page);
    const gaps = await measureChoiceGaps(
      page,
      '.fluentform.ff-inherit-theme-style'
    );

    // One measurement per element type at least: options, radios, terms, GDPR.
    expect(gaps.length).toBeGreaterThanOrEqual(FLUENT_CHOICE_ELEMENTS.length);
    expect(gaps.map(gap => gap.name)).toEqual(
      expect.arrayContaining(['terms-n-condition', 'gdpr-agreement'])
    );
    expectChoiceGaps(gaps, expected);
  });
});
