'use strict';

/**
 * Jest treats skipped tests as a successful run by default. Release QA must
 * instead prove that every discovered test executed.
 */
class NoSkippedTestsReporter {
  onRunComplete(_contexts, results) {
    if (results.numPendingTests > 0) {
      this.error = new Error(
        `Release gate rejected ${results.numPendingTests} skipped Jest test(s).`
      );
    }
  }

  getLastError() {
    return this.error;
  }
}

module.exports = NoSkippedTestsReporter;
