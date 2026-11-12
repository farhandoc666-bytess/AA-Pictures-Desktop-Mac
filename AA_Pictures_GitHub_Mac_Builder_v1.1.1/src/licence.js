'use strict';
const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),cp=require('node:child_process'),crypto=require('node:crypto');
const ORIGIN='https://aapctrs.com';
const api=ORIGIN+'/wp-json/aadl/v1/';
const PINNED_FILE=path.join(__dirname,'../assets/trusted-public-key.pem');
function trustedKey(){try{return fs.readFileSync(PINNED_FILE,'utf8').trim();}catch{return '';}}
function hardware(){let id='';if(process.platform==='win32'){try{const text=cp.execFileSync('reg.exe',['query','HKLM\\SOFTWARE\\Microsoft\\Cryptography','/v','MachineGuid'],{encoding:'utf8',timeout:4000});id=text.match(/MachineGuid\s+REG_SZ\s+(\S+)/i)?.[1]||'';}catch{}}
 if(!id&&process.platform==='darwin'){try{const text=cp.execFileSync('/usr/sbin/ioreg',['-rd1','-c','IOPlatformExpertDevice'],{encoding:'utf8',timeout:4000});id=text.match(/\"IOPlatformUUID\"\s*=\s*\"([^\"]+)\"/)?.[1]||'';}catch{}}
 if(!id){const macs=[];for(const list of Object.values(os.networkInterfaces()))for(const n of list||[])if(n.mac && n.mac!=='00:00:00:00:00:00'&&!n.internal)macs.push(n.mac);id=os.hostname()+'|'+macs.sort().join('|')+'|'+os.arch();}
 return crypto.createHash('sha256').update((process.platform==='darwin'?'aadl-mac-v1|':'aadl-win-v1|')+id).digest('hex');}
function dec(x){return Buffer.from(x.replace(/-/g,'+').replace(/_/g,'/'),'base64');}
function verify(item,pem,app,device){try{const pin=trustedKey();if(!item||!pin||!pem||pin.trim()!==pem.trim())return null;const ok=crypto.verify('RSA-SHA256',Buffer.from(item.payload),pin,dec(item.signature));if(!ok)return null;const p=JSON.parse(dec(item.payload).toString('utf8'));if(p.v!==1||p.device!==crypto.createHash('sha256').update(device).digest('hex')||![app,'bundle'].includes(p.sku)||p.origin!==ORIGIN+'/')return null;return p;}catch{return null;}}
function saveData(dir,data){fs.mkdirSync(dir,{recursive:true});const file=path.join(dir,'licence.json');const tmp=file+'.tmp';fs.writeFileSync(tmp,JSON.stringify(data,null,2),{mode:0o600});fs.renameSync(tmp,file);}
function loadData(dir){try{return JSON.parse(fs.readFileSync(path.join(dir,'licence.json'),'utf8'));}catch{return null;}}
async function send(route,data,timeout=9000){const controller=new AbortController();const timer=setTimeout(()=>controller.abort(),timeout);try{const resp=await fetch(api+route,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(data),signal:controller.signal,redirect:'error'});const result=await resp.json().catch(()=>({message:'Unexpected website response'}));if(!resp.ok)throw Object.assign(new Error(result.message||'Server error '+resp.status),{status:resp.status});return result;}finally{clearTimeout(timer);}}
class Licence{
 constructor(dir,app){this.dir=dir;this.app=app;this.device=hardware();this.entry=loadData(dir);this.claim=this.entry?verify(this.entry.licence,this.entry.publicKey,app,this.device):null;this.status='offline';this.revoked=false;this.lastChecked=0;}
 get active(){return !!this.claim&&!this.revoked;}
 get info(){return {active:this.active,registeredName:this.claim?.name||'',email:this.claim?.email||'',sku:this.claim?.sku||'',deviceName:os.hostname(),online:this.status,revoked:this.revoked};}
 async activate(input){if(!trustedKey())throw new Error('This installer has not been configured with the official A&A public key. Contact support.');const body={app:this.app,device:this.device,deviceName:os.hostname(),username:input.username,email:input.email,key:input.key};const result=await send('activate',body,15000);const claim=verify(result.licence,result.publicKey,this.app,this.device);if(!claim)throw new Error('Server licence signature could not be verified. Activation stopped.');this.entry={licence:result.licence,publicKey:result.publicKey};saveData(this.dir,this.entry);this.claim=claim;this.revoked=false;this.status='online';this.lastChecked=Date.now();return this.info;}
 async refresh(){if(!this.claim)return this.info;
  try{const result=await send('status',{app:this.app,device:this.device,licence:this.entry.licence},6500);if(result.active!==true)throw Object.assign(new Error('Licence not active'),{status:403});this.status='online';this.lastChecked=Date.now();}
  catch(e){if(e.status===403||e.status===401){this.revoked=true;this.status='revoked';}else{this.status='offline';}}
  return this.info;
 }
 async ai(data){if(!this.active)throw Object.assign(new Error('Licence inactive'),{status:403});try{return await send('ai',{...data,device:this.device,licence:this.entry.licence},58000);}catch(e){if(!e.status)throw Object.assign(new Error('No internet connection or A&A server unreachable. A&A AI assistance requires internet; your local projects remain available.'),{status:503});throw e;}}
}
module.exports={Licence,verify,hardware,ORIGIN};
