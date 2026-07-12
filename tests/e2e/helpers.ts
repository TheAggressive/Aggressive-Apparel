import { type Page } from '@playwright/test';

/* eslint-disable @typescript-eslint/no-explicit-any */
// These helpers run block-editor code inside the page, where `window.wp` is
// untyped; `any` is confined to this boundary.
declare global {
  interface Window {
    wp: any;
  }
}

/** Open a fresh page editor and wait for the block-editor data store. */
export async function openPageEditor(page: Page): Promise<void> {
  await page.goto('/wp-admin/post-new.php?post_type=page');
  await page.waitForSelector('iframe[name="editor-canvas"]', {
    timeout: 60_000,
  });
  await page.waitForFunction(() => window.wp?.data?.select('core/block-editor'));
  // Dismiss the welcome guide if present.
  await page.evaluate(() => {
    window.wp.data
      .dispatch('core/preferences')
      ?.set('core/edit-post', 'welcomeGuide', false);
  });
}

/** Publish the current post and return its id + permalink. */
export async function publishAndGetUrl(
  page: Page
): Promise<{ id: number; url: string }> {
  return page.evaluate(async () => {
    const { dispatch, select } = window.wp.data;
    dispatch('core/editor').editPost({
      status: 'publish',
      title: `E2E ${Date.now()}`,
    });
    await dispatch('core/editor').savePost();
    await new Promise(resolve => setTimeout(resolve, 1200));
    return {
      id: select('core/editor').getCurrentPostId(),
      url: select('core/editor').getPermalink() as string,
    };
  });
}

/**
 * Force-delete a page created during a test. Runs from an admin screen so
 * `wp.apiFetch` (with its REST nonce) is available — the front end has neither.
 */
export async function deletePage(page: Page, id: number): Promise<void> {
  if (!id) {
    return;
  }
  await page.goto('/wp-admin/post-new.php?post_type=page');
  await page.waitForFunction(() => window.wp?.apiFetch);
  await page.evaluate(async postId => {
    await window.wp.apiFetch({
      path: `/wp/v2/pages/${postId}?force=true`,
      method: 'DELETE',
    });
  }, id);
}
