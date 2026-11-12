'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
test('Windows build batch safely passes paths containing spaces', () => {
 const bat = fs.readFileSync(path.join(root, 'BUILD-WINDOWS.bat'), 'utf8');
 assert.match(bat, /node "%~dp0scripts\\build\.js" script "%~dp0aa-desktop-public-key\.pem"/);
 assert.match(bat, /node "%~dp0scripts\\build\.js" media "%~dp0aa-desktop-public-key\.pem"/);
 assert.doesNotMatch(bat, /npm run build:(?:script|media) --/);
});
test('Electron builder launches through Node without Windows command shell', () => {
 const script = fs.readFileSync(path.join(root, 'scripts', 'build.js'), 'utf8');
 assert.match(script, /spawnSync\(process\.execPath, options,/);
 assert.match(script, /shell: false/);
});
