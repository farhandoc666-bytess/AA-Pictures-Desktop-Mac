'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

// Pin the actual public RSA key (SubjectPublicKeyInfo DER), not PEM formatting.
// This accepts LF / CRLF differences without accepting a different signing key.
const expectedSpkiSha256 = '8dbff148977a9f8b762a9b7e013ff6b9cba52252e245fda56e027093623c45cb';
const embeddedPath = path.resolve(__dirname, '../assets/trusted-public-key.pem');
const downloadedPath = path.resolve(__dirname, '../aa-desktop-public-key.pem');

function validatePublicKey(filePath) {
  const pem = fs.readFileSync(filePath, 'utf8');
  const key = crypto.createPublicKey(pem);
  assert.equal(key.asymmetricKeyType, 'rsa');
  assert.equal(key.asymmetricKeyDetails.modulusLength, 3072);
  const der = key.export({type: 'spki', format: 'der'});
  const fingerprint = crypto.createHash('sha256').update(der).digest('hex');
  assert.equal(fingerprint, expectedSpkiSha256, `${path.basename(filePath)} is not the approved A&A signing key`);
  return der;
}

test('Build embeds the approved aapctrs.com RSA public signing key', () => {
  const embedded = validatePublicKey(embeddedPath);
  const downloaded = validatePublicKey(downloadedPath);
  assert.deepEqual(embedded, downloaded);
});
