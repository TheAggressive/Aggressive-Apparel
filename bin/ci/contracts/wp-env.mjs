/** Contracts separating Docker-free Studio development from CI wp-env lanes. */

import {
  artifactWpEnv,
  betaUpdater,
  betaWorkflow,
  check,
  packageJson,
  wpEnv,
} from '../lib/contract-inputs.mjs';

const stablePluginPattern =
  /^https:\/\/downloads\.wordpress\.org\/plugin\/woocommerce\.\d+\.\d+\.\d+\.zip$/;
const stableCorePattern =
  /^https:\/\/wordpress\.org\/wordpress-\d+\.\d+\.\d+\.zip$/;
const developmentPlugins = wpEnv.env?.development?.plugins;
const testPlugins = wpEnv.env?.tests?.plugins;

check(
  stableCorePattern.test(wpEnv.core),
  `bin/ci/.wp-env.json must pin stable WordPress (found "${wpEnv.core}").`
);
check(
  developmentPlugins?.length === 1 &&
    stablePluginPattern.test(developmentPlugins[0]),
  'bin/ci/.wp-env.json must pin exactly one stable WooCommerce archive.'
);
check(
  JSON.stringify(developmentPlugins) === JSON.stringify(testPlugins),
  'CI development and test environments must install identical plugins.'
);
check(
  wpEnv.port === 9930 && wpEnv.testsPort === 9931,
  'The isolated CI environment must retain ports 9930 and 9931.'
);
check(
  !JSON.stringify(wpEnv).includes('lifecycleScripts'),
  'The CI parity environment must be reproducible without lifecycle hooks.'
);

for (const environment of ['development', 'tests']) {
  const mapping =
    wpEnv.env?.[environment]?.mappings?.[
      'wp-content/themes/aggressive-apparel'
    ];
  check(
    mapping === '../..',
    `bin/ci/.wp-env.json ${environment} must map the canonical theme slug to ../.. (found ${JSON.stringify(mapping)}).`
  );
}

check(
  wpEnv.env.development.mappings['wp-content/mu-plugins'] ===
    '../wp-env/mu-plugins',
  'The CI development environment must map the E2E mu-plugin directory.'
);
check(
  artifactWpEnv.core === wpEnv.core &&
    artifactWpEnv.phpVersion === wpEnv.phpVersion,
  'Artifact acceptance must use the release gate WordPress and PHP versions.'
);
check(
  JSON.stringify(artifactWpEnv.env?.development?.plugins) ===
    JSON.stringify(developmentPlugins),
  'Artifact acceptance must install the release gate WooCommerce version.'
);
check(
  artifactWpEnv.port === 9940 && artifactWpEnv.testsPort === 9941,
  'Artifact acceptance must retain isolated ports 9940 and 9941.'
);
check(
  !JSON.stringify(artifactWpEnv).includes(
    'wp-content/themes/aggressive-apparel'
  ) &&
    artifactWpEnv.env?.development?.mappings?.['wp-content/aa-artifacts'] ===
      '../../../.cache/ci/artifact-files',
  'Artifact acceptance must install the ZIP without mapping source as the theme.'
);

const localScripts = [
  'env:start',
  'env:stop',
  'env:status',
  'env:check',
  'db:local',
  'cli',
  'dev',
  'setup',
  'test:php',
  'test:e2e',
  'phpstan',
];

for (const name of localScripts) {
  check(
    packageJson.scripts[name] &&
      !packageJson.scripts[name].includes('wp-env') &&
      !packageJson.scripts[name].includes('docker'),
    `The local "${name}" script must not invoke wp-env or Docker.`
  );
}

check(
  packageJson.scripts['env:start'] === 'node bin/local/studio.mjs start' &&
    packageJson.scripts.cli === 'node bin/local/studio.mjs wp',
  'Local lifecycle and WP-CLI commands must route through WordPress Studio.'
);
check(
  packageJson.scripts['ci:env:beta'] ===
    'bash bin/wp-env/update-beta-channel.sh' &&
    packageJson.scripts['ci:env:check'] === 'bash bin/ci/check-wp-env.sh',
  'Beta compatibility must use explicit ci:env:* commands.'
);
check(
  betaWorkflow.includes('pnpm ci:env:reset') &&
    betaWorkflow.includes('pnpm ci:env:beta') &&
    betaWorkflow.includes('pnpm ci:env:check') &&
    !betaWorkflow.includes('pnpm env:start'),
  'The beta workflow must never call local Studio lifecycle commands.'
);
check(
  betaUpdater.includes('/../ci/wp-env.sh') &&
    betaUpdater.includes('files_before') &&
    betaUpdater.includes('files_after'),
  'The beta updater must use the isolated CI wrapper and verify wp-content restore.'
);
