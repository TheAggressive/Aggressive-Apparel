#!/usr/bin/env node

import fs from 'fs';
import { ASSET_BUILD_OUTPUT_DIRS } from './lib/build-manifest.mjs';

ASSET_BUILD_OUTPUT_DIRS.forEach(dir => {
  fs.rmSync(dir, { recursive: true, force: true });
});
