/**
 * Prebuild script: generate wayfinder route files to resources/js/routes_temp/.
 *
 * The app's vite.config.ts aliases @/routes, @/actions and @/wayfinder to
 * routes_temp/ because the canonical resources/js/{routes,actions,wayfinder}
 * copies are gitignored and can be locked on Windows (EPERM from a crashed
 * wayfinder process). This script regenerates routes_temp before each build
 * so fresh-clone environments work out of the box.
 *
 * PHP resolution:
 *   1. Try `php` in PATH (macOS / Linux / Windows cmd.exe).
 *   2. Fall back to Laravel Herd's php.bat on Windows.
 *   3. Skip generation if no PHP is found — but ONLY when routes_temp already
 *      exists from a previous run (stale routes still build).
 *
 * Failure behaviour: when routes_temp does not exist at all there is nothing
 * the vite aliases can resolve, and `vite build` dies with a wall of cryptic
 * "Could not load resources/js/routes_temp/…" errors. To save everyone that
 * debugging session, this script exits 1 up front with the exact remediation.
 */

import { execSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(__dirname, '..');
const targetPath = 'resources/js/routes_temp';
const targetIndex = resolve(projectRoot, targetPath, 'routes/index.ts');

// ── Helpers ───────────────────────────────────────────────────────────
const routesExist = () => existsSync(targetIndex);

function fail(reason, detail = '') {
  console.error('');
  console.error('[generate-routes] ERROR: could not generate wayfinder routes.');
  console.error(`[generate-routes] ${reason}`);
  if (detail) {
    console.error('[generate-routes] Command output (tail):');
    console.error(
      detail
        .split('\n')
        .slice(-12)
        .map((l) => `[generate-routes]   ${l}`)
        .join('\n'),
    );
  }
  console.error('');
  console.error('[generate-routes] The build cannot proceed because nothing');
  console.error(`[generate-routes] exists at "${targetPath}/" yet.`);
  console.error('[generate-routes] Fix it with:');
  console.error('[generate-routes]   1. composer install        (wayfinder needs vendor/)');
  console.error('[generate-routes]   2. php artisan wayfinder:generate --path=' + targetPath);
  console.error('[generate-routes]   3. re-run your build');
  console.error('');
  process.exit(1);
}

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
  if (routesExist()) {
    console.warn(
      '[generate-routes] PHP not found — keeping the previously generated ' +
        `routes in ${targetPath}/ (they may be stale).`,
    );
    process.exit(0);
  }
  fail(
    'PHP was not found in PATH (and no Laravel Herd install was detected), ' +
      'so the wayfinder route files cannot be generated.',
  );
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
  if (routesExist()) {
    // The command may fail if locked files block overwriting some routes.
    // That's OK — previously generated routes still satisfy the aliases.
    const msg = (e.stderr || e.message || '').slice(0, 300);
    console.warn(
      '[generate-routes] Generation failed, but previously generated routes ' +
        `exist in ${targetPath}/ — continuing. (${msg})`,
    );
    process.exit(0);
  }
  fail(
    '`php artisan wayfinder:generate` exited with an error ' +
      '(often a missing vendor/ directory or a broken .env).',
    String(e.stderr || e.stdout || e.message || ''),
  );
}

if (!routesExist()) {
  fail(
    '`php artisan wayfinder:generate` reported success but ' +
      `${targetPath}/routes/index.ts is missing.`,
  );
}

console.log('[generate-routes] Done.');
