import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const repositoryRoot = path.resolve(scriptDirectory, '../..');
const packageJson = JSON.parse(
  readFileSync(path.join(repositoryRoot, 'package.json'), 'utf8')
);

const expectedNode = packageJson.engines?.node;
const expectedPnpm = packageJson.engines?.pnpm;
const actualNode = process.versions.node;
const pnpmMatch = process.env.npm_config_user_agent?.match(
  /(?:^|\s)pnpm\/([^\s]+)/u
);
const actualPnpm = pnpmMatch?.[1] ?? 'not launched by pnpm';

const mismatches = [];

if (actualNode !== expectedNode) {
  mismatches.push(`Node ${actualNode} (expected ${expectedNode})`);
}

if (actualPnpm !== expectedPnpm) {
  mismatches.push(`pnpm ${actualPnpm} (expected ${expectedPnpm})`);
}

if (mismatches.length > 0) {
  console.error('CI parity toolchain mismatch:');
  for (const mismatch of mismatches) {
    console.error(`  - ${mismatch}`);
  }
  console.error(
    'Activate the versions pinned by .node-version and package.json before running CI parity.'
  );
  process.exit(1);
}

console.log(`CI parity toolchain: Node ${actualNode}, pnpm ${actualPnpm}`);
