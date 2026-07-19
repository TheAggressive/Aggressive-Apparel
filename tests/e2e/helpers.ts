import { type Page } from '@playwright/test';

/* eslint-disable @typescript-eslint/no-explicit-any */
// These helpers run block-editor code inside the page, where `window.wp` is
// untyped; `any` is confined to this boundary.
declare global {
  interface Window {
    wp: any;
    wpApiSettings?: {
      nonce?: string;
    };
  }
}

const restNonces = new WeakMap<Page, string>();

/** Open a fresh page editor and wait for the block-editor data store. */
export async function openPageEditor(page: Page): Promise<void> {
  await page.goto('/wp-admin/post-new.php?post_type=page');
  await page.waitForSelector('iframe[name="editor-canvas"]', {
    timeout: 60_000,
  });
  await page.waitForFunction(() =>
    window.wp?.data?.select('core/block-editor')
  );

  const restNonce = await page.evaluate(
    () => window.wpApiSettings?.nonce ?? ''
  );
  if (!restNonce) {
    throw new Error('WordPress REST nonce was unavailable in the page editor.');
  }
  restNonces.set(page, restNonce);

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

    const deadline = Date.now() + 15_000;
    while (Date.now() < deadline) {
      const editor = select('core/editor');
      const id = Number(editor.getCurrentPostId());
      const url = editor.getPermalink() as string;
      const saveFinished = !editor.isSavingPost();
      const saveSucceeded = editor.didPostSaveRequestSucceed?.() !== false;

      if (id > 0 && /^https?:\/\//.test(url) && saveFinished && saveSucceeded) {
        return { id, url };
      }

      await new Promise(resolve => setTimeout(resolve, 50));
    }

    throw new Error('Timed out waiting for the page to finish publishing.');
  });
}

/**
 * Force-delete a page created during a test through the browser context's
 * authenticated REST client. This leaves the inspected page untouched so
 * failure screenshots and traces continue to describe the actual failure.
 */
export async function deletePage(page: Page, id: number): Promise<void> {
  if (!id) {
    return;
  }

  const nonce = restNonces.get(page);
  if (!nonce) {
    throw new Error('Cannot delete the E2E page without a REST nonce.');
  }

  const response = await page.request.delete(
    `/wp-json/wp/v2/pages/${id}?force=true`,
    {
      headers: { 'X-WP-Nonce': nonce },
    }
  );

  if (!response.ok() && response.status() !== 404) {
    throw new Error(
      `Failed to delete E2E page ${id}: ${response.status()} ${await response.text()}`
    );
  }
}
