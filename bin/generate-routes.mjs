/**
 * Prebuild script: generate wayfinder route files to resources/js/routes_temp/.
 *
 * The app's vite.config.ts has aliases pointing @/routes/{assignments,exams,profile}
 * to routes_temp/ because the originals are locked on Windows (EPERM from a
 * crashed wayfinder process).  This script regenerates routes_temp before each
 * build so fresh-clone environments work out of the box.
 *
 * PHP resolution:
 *   1. Try `php` in PATH (macOS / Linux / Windows cmd.exe).
 *   2. Fall back to Laravel Herd's php.bat on Windows.
 *   3. Skip generation if no PHP is found (non-fatal — stale routes may still work).
 */

import { execSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(__dirname, '..');
const targetPath = 'resources/js/routes_temp';

// ── Resolve PHP binary ────────────────────────────────────────────────
function resolvePhp() {
  if (process.platform === 'win32') {
    // Try php via cmd.exe (honours Windows PATH)
    const comspec = process.env.COMSPEC || 'cmd.exe';
    try {
      execSync(`"${comspec}" /c "php -v"`, { stdio: 'ignore' });
      return 'php';
    } catch {
      // Laravel Herd
      const herd = `${process.env.USERPROFILE || ''}\\.config\\herd\\bin\\php.bat`;
      if (existsSync(herd)) return herd;
    }
  } else {
    // macOS / Linux
    try {
      execSync('php -v', { stdio: 'ignore' });
      return 'php';
    } catch {
      for (const p of ['/opt/homebrew/bin/php', '/usr/local/bin/php']) {
        try {
          execSync(`${p} -v`, { stdio: 'ignore' });
          return p;
        } catch { /* skip */ }
      }
    }
  }
  return null;
}

// ── Main ──────────────────────────────────────────────────────────────
const php = resolvePhp();
if (!php) {
  console.warn(
    '[generate-routes] PHP not found — skipping route generation. ' +
      'If the build fails, run "php artisan wayfinder:generate --path=' +
      targetPath +
      '" manually.',
  );
  process.exit(0);
}

const cmd = `${php} artisan wayfinder:generate --path=${targetPath}`;
console.log(`[generate-routes] Running: ${cmd}`);

try {
  const opts = { cwd: projectRoot, encoding: 'utf8', timeout: 60_000 };
  if (process.platform === 'win32') opts.shell = 'cmd.exe';

  const out = execSync(cmd, opts);
  const lines = out.split('\n').filter((l) => l.includes('[Wayfinder]'));
  lines.forEach((l) => console.log('  ' + l.trim()));
} catch (e) {
  // The command may fail if locked files block overwriting some routes.
  // That's OK — the aliases in vite.config.ts handle the locked ones.
  const msg = (e.stderr || e.message || '').slice(0, 300);
  console.warn('[generate-routes] Generation completed with warnings:', msg);
}

console.log('[generate-routes] Done.');
