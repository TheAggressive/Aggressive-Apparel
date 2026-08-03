/**
 * CI and wp-env contracts.
 *
 * The only thing standing between "it passed locally" and "it passed in
 * Actions". Each module below is a side-effecting import: loading it runs its
 * assertions, and the first failure throws with a message naming the file and
 * value to fix.
 *
 * Split by responsibility rather than kept as one file, because it outgrew this
 * project's own 800-line budget. Add new assertions to the module that owns the
 * thing being asserted; add a new module when none of them does.
 */

import './contracts/toolchain.mjs';
import './contracts/wp-env.mjs';
import './contracts/workflows.mjs';

console.log('CI and wp-env contracts passed.');
