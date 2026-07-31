import type {
  FullConfig,
  FullResult,
  Reporter,
  Suite,
  TestCase,
} from '@playwright/test/reporter';

/**
 * Skipped or flaky browser tests are incomplete release evidence.
 */
export default class NoSkipsReporter implements Reporter {
  private readonly listOnly = process.argv.includes('--list');
  private tests: TestCase[] = [];

  onBegin(_config: FullConfig, suite: Suite): void {
    this.tests = suite.allTests();
  }

  async onEnd(_result: FullResult): Promise<{ status: 'failed' } | undefined> {
    if (this.listOnly) {
      return undefined;
    }

    const rejectedTests = this.tests.filter(test =>
      ['skipped', 'flaky'].includes(test.outcome())
    );

    if (rejectedTests.length === 0) {
      return undefined;
    }

    console.error(
      `\nRelease gate rejected ${rejectedTests.length} skipped or flaky Playwright test(s):`
    );
    for (const test of rejectedTests) {
      console.error(
        `- [${test.outcome()}] ${test.titlePath().filter(Boolean).join(' › ')}`
      );
    }

    return { status: 'failed' };
  }

  printsToStdio(): boolean {
    return false;
  }
}
