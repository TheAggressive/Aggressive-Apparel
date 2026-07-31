import jestConfig from '@wordpress/scripts/config/jest-unit.config.js';

export default {
  ...jestConfig,
  // Unit tests belong to source. Keeping an explicit ownership boundary stops
  // generated builds and isolated wp-env dependencies from becoming test input.
  roots: ['<rootDir>/src'],
  reporters: ['default', '<rootDir>/bin/ci/jest-no-skips-reporter.cjs'],
};
