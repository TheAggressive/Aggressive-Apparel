/**
 * wp-env contracts: the development and CI parity environments.
 *
 * These exist because a wp-env misconfiguration is not a build failure — it is
 * a developer losing their site content. wp-env re-provisions WordPress when
 * its config changes, clearing wp-content, and WordPress will drift off a
 * pinned version on its own if left able to. Each assertion below closes one
 * route to that outcome.
 */

import {
  artifactWpEnv,
  betaUpdater,
  betaWorkflow,
  check,
  developmentWpEnv,
  packageJson,
  readText,
  wpEnv,
  wpEnvBackup,
  wpEnvRestore,
} from '../lib/contract-inputs.mjs';

const stablePluginPattern =
  /^https:\/\/downloads\.wordpress\.org\/plugin\/woocommerce\.\d+\.\d+\.\d+\.zip$/;
const stableCorePattern =
  /^https:\/\/wordpress\.org\/wordpress-\d+\.\d+\.\d+\.zip$/;
const developmentPlugins = wpEnv.env?.development?.plugins;
const testPlugins = wpEnv.env?.tests?.plugins;

check(
  developmentPlugins?.length === 1,
  'bin/ci/.wp-env.json must declare exactly one development plugin (the ' +
    `pinned WooCommerce archive), found ${developmentPlugins?.length ?? 0}.`
);

check(
  stablePluginPattern.test(developmentPlugins[0]),
  `The CI WooCommerce archive must be a version-pinned stable release URL ` +
    `(found "${developmentPlugins[0]}"). A floating "latest" makes the lane ` +
    "fail on someone else's release schedule."
);

check(
  JSON.stringify(developmentPlugins) === JSON.stringify(testPlugins),
  'The CI development and tests environments must install identical plugins, ' +
    'or the suite tests a different WooCommerce than the one E2E drives.'
);

check(
  !Object.hasOwn(developmentWpEnv.env?.development ?? {}, 'plugins') &&
    !Object.hasOwn(developmentWpEnv.env?.tests ?? {}, 'plugins'),
  'The local environments must not declare WooCommerce as a wp-env plugin ' +
    'source. Remote plugin sources become bind mounts that the normal ' +
    'WordPress updater cannot replace.'
);

check(
  !wpEnvBackup.includes('--exclude="wp-content/plugins/woocommerce"') &&
    !betaUpdater.includes('--exclude="wp-content/plugins/woocommerce"') &&
    !betaUpdater.includes(
      '-path /var/www/html/wp-content/plugins/woocommerce -prune'
    ),
  'WooCommerce is a normal, dashboard-updatable local plugin, so recovery ' +
    'and beta-update archives must preserve its installed files.'
);

check(
  stableCorePattern.test(wpEnv.core),
  `bin/ci/.wp-env.json must pin a stable WordPress release (found ` +
    `"${wpEnv.core}"). Beta coverage belongs in the scheduled beta workflow.`
);

check(
  artifactWpEnv.core === wpEnv.core &&
    artifactWpEnv.phpVersion === wpEnv.phpVersion,
  'The artifact-acceptance environment must use the same pinned WordPress and ' +
    'PHP versions as the release gate.'
);

check(
  JSON.stringify(artifactWpEnv.env?.development?.plugins) ===
    JSON.stringify(developmentPlugins),
  "The artifact-acceptance environment must install the release gate's pinned " +
    'WooCommerce archive.'
);

check(
  artifactWpEnv.port === 9940 && artifactWpEnv.testsPort === 9941,
  'The artifact-acceptance environment must stay isolated on ports 9940/9941.'
);

check(
  !JSON.stringify(artifactWpEnv).includes(
    'wp-content/themes/aggressive-apparel'
  ) &&
    artifactWpEnv.env?.development?.mappings?.['wp-content/aa-artifacts'] ===
      '../../../.cache/ci/artifact-files',
  'Artifact acceptance must install the ZIP without mapping the source tree as ' +
    'the active theme.'
);

// Dedicated ports: the parity environment must never bind the development one,
// whose database holds the customised footer and seeded products.
const CI_PORTS = [
  ['port', wpEnv.port, 9930],
  ['testsPort', wpEnv.testsPort, 9931],
];

for (const [key, actual, expected] of CI_PORTS) {
  check(
    actual === expected,
    `bin/ci/.wp-env.json ${key} must be ${expected} (found ${actual}) so the ` +
      'parity environment never collides with development on 9910/9920.'
  );
}

// Both wp-env configs must mount the theme at an EXPLICIT lowercase path.
//
// `"themes": ["."]` looks equivalent and is not: wp-env names the mount after
// path.basename() of the resolved source (parse-source-string.js), so the theme
// directory becomes whatever the checkout is called. That is
// "aggressive-apparel" on a developer's machine and "Aggressive-Apparel" on a
// GitHub runner, where the repository name supplies the directory. Everything
// keyed to the theme path — bin/wp-env/lib.sh's --env-cwd, and the slug
// Core\Theme_Updates ships under — then works locally and fails in CI. That is
// exactly how the WordPress beta job broke.
// Every wp-env config in the repository, named for error messages, with the
// relative path each must map the theme from. The paths differ only because
// bin/ci/.wp-env.json sits two directories deeper. Assertions that hold for
// both configs iterate this rather than restating the pair.
const WP_ENV_CONFIGS = [
  ['bin/ci/.wp-env.json', wpEnv, '../..'],
  ['.wp-env.json', developmentWpEnv, '.'],
];

for (const [source, config, expected] of WP_ENV_CONFIGS) {
  check(
    !Object.hasOwn(config, 'themes'),
    `${source} must not use a "themes" array — wp-env would name the mount ` +
      'after the checkout directory, which differs between a developer machine ' +
      'and a CI runner. Use an explicit mappings entry instead.'
  );

  for (const environment of ['development', 'tests']) {
    const themeMapping =
      config.env?.[environment]?.mappings?.[
        'wp-content/themes/aggressive-apparel'
      ];

    check(
      themeMapping === expected,
      `${source} ${environment} must map ` +
        `"wp-content/themes/aggressive-apparel" to "${expected}" (found ` +
        `${JSON.stringify(themeMapping)}), so the theme directory name is the ` +
        'canonical lowercase slug everywhere it runs.'
    );
  }
}

// Mapping the directory (not individual files) keeps Docker from creating
// root-owned placeholder files the host user then cannot delete.
const MU_PLUGIN_MAPPINGS = [
  [
    'bin/ci/.wp-env.json',
    wpEnv.env.development.mappings['wp-content/mu-plugins'],
    '../wp-env/mu-plugins',
  ],
  [
    '.wp-env.json',
    developmentWpEnv.env.development.mappings['wp-content/mu-plugins'],
    './bin/wp-env/mu-plugins',
  ],
];

for (const [source, actual, expected] of MU_PLUGIN_MAPPINGS) {
  check(
    actual === expected,
    `${source} must map wp-content/mu-plugins to "${expected}" (found ` +
      `"${actual}") — mapping individual files leaves Docker-owned placeholders.`
  );
}

for (const [name, script] of [
  ['backup', wpEnvBackup],
  ['restore', wpEnvRestore],
  ['beta update', betaUpdater],
]) {
  check(
    script.includes('--exclude="wp-content/mu-plugins"'),
    `The ${name} path must not archive or extract the repository-mapped ` +
      'mu-plugin directory.'
  );
}

// The beta upgrade replaces wp-content. It must never run as a wp-env lifecycle
// hook: as an afterStart hook it fired on every `pnpm env:start` anywhere,
// including a developer's environment, where it destroyed uploaded media the
// first time it actually completed. It belongs to a disposable runner, invoked
// by name so a failure is attributable.
check(
  !JSON.stringify(developmentWpEnv).includes('update-beta-channel'),
  '.wp-env.json must not run bin/wp-env/update-beta-channel.sh from ' +
    'lifecycleScripts — it replaces wp-content, and a developer environment is ' +
    'not disposable. The beta workflow invokes it explicitly instead.'
);

check(
  !JSON.stringify(wpEnv).includes('lifecycleScripts'),
  'bin/ci/.wp-env.json must not declare lifecycleScripts — the parity ' +
    'environment must be reproducible from its lanes alone.'
);

check(
  betaWorkflow.includes('pnpm env:beta'),
  'wordpress-beta-compatibility.yml must invoke `pnpm env:beta` explicitly. ' +
    'That upgrade is the entire purpose of the job; if no step runs it, the ' +
    'suite silently tests the stable WordPress it already tests everywhere else.'
);

check(
  packageJson.scripts['env:beta'] === 'bash bin/wp-env/update-beta-channel.sh',
  'The env:beta script must run bin/wp-env/update-beta-channel.sh.'
);

// Both halves of the same failure. WordPress auto-updates core in the
// background, and this repository's cron loopback mu-plugin makes that fire;
// the Beta Tester plugin aims those updates at a beta. Either way the installed
// core stops matching the pinned archive, and the next `wp-env start`
// re-provisions /var/www/html — taking wp-content, uploads included, with it.
//
// Explicit `wp core update` is unaffected: WP_AUTO_UPDATE_CORE is read only by
// Core_Upgrader::should_update_to_version(), whose sole caller is
// WP_Automatic_Updater, so the beta lane still upgrades on demand.
for (const [source, config] of WP_ENV_CONFIGS) {
  check(
    !JSON.stringify(config).includes('wordpress-beta-tester'),
    `${source} must not install wordpress-beta-tester. It aims background ` +
      'updates at a beta, drifting the site off the pinned core. The beta ' +
      'lane installs it on a disposable runner instead.'
  );

  check(
    config.config?.WP_AUTO_UPDATE_CORE === false,
    `${source} must set config.WP_AUTO_UPDATE_CORE to false. Without it ` +
      'WordPress silently updates itself off the pinned version, and the next ' +
      'wp-env start wipes wp-content restoring the pin.'
  );
}

check(
  betaUpdater.includes('wp plugin install wordpress-beta-tester'),
  'bin/wp-env/update-beta-channel.sh must install wordpress-beta-tester ' +
    'itself, now that it is no longer a declared wp-env plugin.'
);

// wp-env's `"themes"` entry both mounted and ACTIVATED the theme. Replacing it
// with an explicit mapping kept the canonical directory name but dropped the
// activation, so a fresh clone came up on a default WordPress theme.
check(
  packageJson.scripts['env:start'] === 'bash bin/wp-env/start.sh',
  'env:start must go through bin/wp-env/start.sh, which takes a recovery ' +
    'point before a config change re-provisions WordPress and activates the ' +
    'theme afterwards. Calling `wp-env start` directly loses both.'
);

const wpEnvStart = readText('bin/wp-env/start.sh');
const wpEnvLifecycle = readText('bin/wp-env/lifecycle.sh');
const wpEnvWooCommerce = readText('bin/wp-env/ensure-woocommerce.sh');

// The two things start.sh exists to guarantee. Losing either turns a routine
// config edit back into silent, permanent loss of a developer's site content.
check(
  wpEnvStart.includes('backup.sh') && wpEnvStart.includes('restore.sh'),
  'bin/wp-env/start.sh must both create a recovery point before a changed ' +
    'config re-provisions WordPress and restore it when content is cleared.'
);

check(
  wpEnvStart.includes('ensure-theme.sh'),
  'bin/wp-env/start.sh must activate the mounted theme. The explicit path ' +
    'mapping that replaced the wp-env "themes" entry does not activate it.'
);

check(
  wpEnvStart.includes('ensure-woocommerce.sh') &&
    wpEnvStart.includes('check.sh') &&
    wpEnvRestore.includes('ensure-woocommerce.sh') &&
    wpEnvWooCommerce.includes(developmentPlugins[0]),
  'bin/wp-env/start.sh must validate WooCommerce after startup and repair it ' +
    'from the same pinned archive used by CI before startup or restore reports ' +
    'success.'
);

check(
  !wpEnvWooCommerce.includes('installed_version} ==') &&
    wpEnvWooCommerce.includes('wp plugin install'),
  'The local WooCommerce guard must seed a missing installation without ' +
    'downgrading a version installed later through the WordPress updater.'
);

check(
  wpEnvStart.includes('aa_acquire_environment_lock') &&
    wpEnvLifecycle.includes('aa_acquire_environment_lock'),
  'Development wp-env start and lifecycle operations must share a non-blocking ' +
    'lock so concurrent refreshes cannot corrupt downloaded source mounts.'
);

check(
  wpEnvLifecycle.includes('ensure-theme.sh') &&
    wpEnvLifecycle.includes('ensure-woocommerce.sh'),
  'Database clean/reset operations must reactivate the mapped theme and the ' +
    'normal WordPress-managed WooCommerce plugin before running health checks.'
);

// Every route into the development environment has to go through that guard.
// A script that calls `wp-env start` directly skips the recovery point and
// re-provisions straight over the developer's content.
for (const name of ['dev', 'setup']) {
  check(
    packageJson.scripts[name]?.includes('env:start'),
    `The "${name}" script must start the environment via env:start, not by ` +
      'invoking wp-env directly, or it bypasses the backup/restore guard.'
  );

  check(
    !/(^|\s|")wp-env start/u.test(packageJson.scripts[name] ?? ''),
    `The "${name}" script must not call \`wp-env start\` directly — that ` +
      'skips the recovery point taken before a config change re-provisions.'
  );
}

// The restore is what failed silently and cost a developer their uploads.
check(
  betaUpdater.includes('files_after') && betaUpdater.includes('files_before'),
  'bin/wp-env/update-beta-channel.sh must verify that its wp-content restore ' +
    'returned as many files as it archived. An unverified restore turns data ' +
    'loss into a passing run.'
);
