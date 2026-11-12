'use strict';
const fs = require('node:fs');
const path = require('node:path');
const cp = require('node:child_process');
const crypto = require('node:crypto');

const app = process.argv[2];
const platform = process.argv[4] || 'win';
if (!['win','mac'].includes(platform)) {console.error('Select win or mac'); process.exit(1);}
if (platform==='mac' && process.platform!=='darwin') {console.error('macOS builds must run on a Mac.');process.exit(1);}
if (!['script', 'media'].includes(app)) {
  console.error('Usage: node scripts/build.js <script|media> "C:\\path with spaces\\aa-desktop-public-key.pem"');
  process.exit(1);
}

const pub = process.argv[3] || process.env.AAD_PUBLIC_KEY_FILE;
if (!pub || !fs.existsSync(pub)) {
  console.error('Public key file required. Download it from WordPress > A&A App Store > Desktop Licences.');
  process.exit(1);
}

const pem = fs.readFileSync(pub, 'utf8').trim();
try {
  const key = crypto.createPublicKey(pem);
  if (key.asymmetricKeyType !== 'rsa') throw new Error('Expected RSA signing key');
} catch (error) {
  console.error('Invalid public signing key:', error.message);
  process.exit(1);
}

const projectRoot = path.resolve(__dirname, '..');
const trustedKey = path.join(projectRoot, 'assets', 'trusted-public-key.pem');
fs.writeFileSync(trustedKey, pem + '\n');

// Launch the electron-builder CLI with Node directly, rather than its .cmd
// shim. Windows npm command forwarding and cmd.exe shell parsing can both
// split paths that contain spaces. shell:false keeps each argument intact.
const builderCli = path.join(projectRoot, 'node_modules', 'electron-builder', 'out', 'cli', 'cli.js');
if (!fs.existsSync(builderCli)) {
  console.error('Missing electron-builder. Run npm install first.');
  process.exit(1);
}
const options = [builderCli, platform==='mac'?'--mac':'--win', '--config', 'build.config.js', '--publish', 'never'];
const child = cp.spawnSync(process.execPath, options, {
  cwd: projectRoot,
  env: { ...process.env, AAD_APP: app, AAD_REQUIRE_MAC_SIGNING: platform==='mac' && process.env.AAD_ALLOW_UNSIGNED_MAC!=='1'?'1':'0' },
  stdio: 'inherit',
  shell: false,
});
if (child.error) console.error('Failed to launch electron-builder:', child.error.message);
if (child.signal) console.error('electron-builder ended with signal:', child.signal);
process.exit(child.status ?? 1);
