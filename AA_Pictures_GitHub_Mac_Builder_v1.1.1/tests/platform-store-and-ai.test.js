'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const root=path.resolve(__dirname,'..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8');

test('Customer-visible writing-coach UI uses A&A branding and third-party AI disclosure',()=>{
 const js=read('assets/creative-app.js');
 const side=js.substring(js.indexOf('function aiSide(){'),js.indexOf('function writingTab(){'));
 assert.match(side,/A&A AI READY/);
 assert.match(side,/third-party AI service/);
 assert.doesNotMatch(side,/Google Gemini|GEMINI READY|OPENAI READY/);
 assert.match(js,/A&A AI Writing Coach: Your request and selected writing context/);
 assert.doesNotMatch(js,/AI privacy: this sends the request and selected writing context to Google Gemini/);
 const licence=read('src/licence.js');
 assert.match(licence,/A&A AI assistance requires internet/);
 assert.doesNotMatch(licence,/Gemini requires internet/);
});

test('Mac packaging has universal DMG target, stable Mac fingerprint, and platform-specific builder',()=>{
 const conf=read('build.config.js');
 assert.match(conf,/target:'dmg',arch:\['universal'\]/);
 assert.match(conf,/forceCodeSigning: process.env.AAD_REQUIRE_MAC_SIGNING/);
 assert.ok(fs.existsSync(path.join(root,'BUILD-MAC.command')));
 const mac=read('BUILD-MAC.command');
 assert.match(mac,/Developer ID Application/);
 assert.match(mac,/APPLE_API_KEY/);
 const licence=read('src/licence.js');
 assert.match(licence,/IOPlatformUUID/);
 assert.match(licence,/aadl-mac-v1/);
 const script=read('scripts/build.js');
 assert.match(script,/platform==='mac' && process.platform!=='darwin'/);
});

test('WordPress plugin extension has four platform slots and purchase-checked download handler',()=>{
 const php=fs.readFileSync(path.join(root,'wordpress-plugin-reference/aap-desktop-licensing.php'),'utf8');
 assert.match(php,/Version: 1.1.0/);
 assert.match(php,/function aadl_file_option/);
 assert.match(php,/function aadl_storefront_section/);
 assert.match(php,/function aadl_customer_apps/);
 assert.match(php,/if\(!isset\(\$apps\[\$app\]\)\)wp_die\('No valid desktop licence/);
 assert.match(php,/store_page_id/);
 assert.match(php,/add_shortcode\('aap_desktop_store'/);
 assert.match(php,/\$os==='mac'\?'_mac':'/);
});
