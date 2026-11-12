const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const crypto=require('node:crypto');
const keyPath=path.resolve(__dirname,'../assets/trusted-public-key.pem');
const sourcePath=path.resolve(__dirname,'../aa-desktop-public-key.pem');
const expected='0b179e5049d9d322bac1ddb025fb02b016a5d316d6bd22737c9af9f240a825ce';
test('Build embeds correct aapctrs.com public signing key',()=>{
 const embedded=fs.readFileSync(keyPath);
 const supplied=fs.readFileSync(sourcePath);
 assert.deepEqual(embedded,supplied);
 assert.equal(crypto.createPublicKey(embedded).asymmetricKeyType,'rsa');
 assert.equal(crypto.createPublicKey(embedded).asymmetricKeyDetails.modulusLength,3072);
 assert.equal(crypto.createHash('sha256').update(embedded).digest('hex'),expected);
});
