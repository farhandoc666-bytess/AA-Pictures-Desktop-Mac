'use strict';
(()=>{
const cfg=window.DESKTOP;
const headers={'X-AAD-Nonce':cfg.nonce,'Content-Type':'application/json'};
async function get(path){const r=await fetch(path,{headers});const v=await r.json();if(!r.ok)throw Error(v.message||'Unable to open local data');return v;}
async function post(path,data){const r=await fetch(path,{method:'POST',headers,body:JSON.stringify(data)});const v=await r.json();if(!r.ok)throw Error(v.message||'Request failed');return v;}
function escape(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));}
function show(message){const p=document.getElementById('activate-message');if(p)p.textContent=message;else alert(message);}
async function status(){try{const x=await get('/local/info');const el=document.querySelector('#desktop-state');if(el){el.textContent=x.active?`Registered: ${x.registeredName} · ${x.online==='online'?'● Online':x.online==='revoked'?'⛔ Licence revoked':'○ Offline-ready'}`:'Licence required';el.className=x.online==='online'?'online':x.online==='revoked'?'revoked':'offline';}if(!x.active&&cfg.enabled){window.location.reload();}}catch{}}
const form=document.getElementById('activate-form');if(form)form.addEventListener('submit',async e=>{e.preventDefault();const b=form.querySelector('button');b.disabled=true;show('Checking your account and registering this PC…');try{const raw=Object.fromEntries(new FormData(form).entries());await post('/local/register',raw);show('Activation successful. Opening offline workspace…');location.reload();}catch(e){show(e.message);}finally{b.disabled=false;}});
async function files(){if(!cfg.enabled){show('Activate this PC before using its file library');return;}const modal=document.getElementById('desktop-files-modal');modal.hidden=false;modal.innerHTML='<div class="df-inner"><h2>Offline file library</h2><p>Files are copied to this computer. Large video files should remain in the production storage system.</p><div class="df-actions"><button id="df-add">+ Attach local file</button><button id="df-close">Close</button></div><div id="df-list">Loading…</div></div>';
 modal.querySelector('#df-close').onclick=()=>{modal.hidden=true;};modal.querySelector('#df-add').onclick=async()=>{try{await window.aaDesktop.addFile();await refresh();}catch(e){alert(e.message);}};
 async function refresh(){try{const list=await window.aaDesktop.listFiles();const div=modal.querySelector('#df-list');div.innerHTML=list.length?list.map(x=>`<button class="df-row" data-open="${escape(x.name)}"><strong>${escape(x.name.replace(/^\d+-[a-z0-9]+-/,''))}</strong><span>${(x.size/1024/1024).toFixed(1)} MB · Open</span></button>`).join(''):'<p>No attachments saved yet.</p>';div.querySelectorAll('[data-open]').forEach(b=>b.onclick=()=>window.aaDesktop.openFile(b.dataset.open).catch(e=>alert(e.message)));}catch(e){modal.querySelector('#df-list').textContent=e.message;}}
 await refresh();}
document.getElementById('desktop-files')?.addEventListener('click',files);
document.getElementById('desktop-backup')?.addEventListener('click',async()=>{try{const ok=await window.aaDesktop.backup();if(ok)alert('Project data backup saved. Back up attached files from the computer’s A&A Desktop documents folder separately.');}catch(e){alert(e.message);}});
if(cfg.enabled&&cfg.app==='script'){
 const importBtn=document.createElement('button');importBtn.textContent='IMPORT PROJECT JSON';importBtn.id='desktop-import';document.getElementById('desktop-backup')?.after(importBtn);
 importBtn.addEventListener('click',async()=>{try{const raw=await window.aaDesktop.pickImport();if(!raw)return;const result=await post('/local/import-project',JSON.parse(raw));alert(`Imported ${result.count} documents from your Creative Studio JSON backup.`);location.reload();}catch(e){alert(e.message);}});
}
status();if(cfg.enabled)setInterval(status,40*1000);
})();
