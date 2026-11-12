'use strict';
const path=require('node:path'),fs=require('node:fs');
const date=()=>new Date().toISOString().replace('T',' ').split('.')[0];
function err(msg,status=400){throw Object.assign(new Error(msg),{status});}
function words(html){return String(html||'').replace(/<[^>]+>/g,' ').trim().split(/\s+/).filter(Boolean).length;}
const fmtDoc=d=>({...d,wordCount:words(d.content)});
function creative(store,licence){const db=store.data;
 function projects(){return db.projects.slice().sort((a,b)=>b.updated.localeCompare(a.updated));}
 function docs(pid){return db.documents.filter(d=>d.projectId===pid).map(fmtDoc);}
 function touch(p){p.updated=date();}
 function mkProject(input){if(!String(input.title||'').trim())err('Project title required');const item={id:store.id(),title:String(input.title).slice(0,200),description:String(input.description||''),type:String(input.type||'novel'),role:'owner',members:[],created:date(),updated:date()};db.projects.push(item);store.save();return item;}
 function mkDoc(input){const pid=Number(input.projectId);store.project(pid);const item={id:store.id(),projectId:pid,title:String(input.title||'Untitled').slice(0,200),kind:String(input.kind||'chapter'),content:String(input.content||''),revisions:[],created:date(),updated:date()};db.documents.push(item);store.save();return fmtDoc(item);}
 async function api(endpoint,method,body){const pieces=endpoint.split('/').filter(Boolean);const p=pieces[0],id=Number(pieces[1]);
  if(p==='bootstrap'&&method==='GET')return {user:{id:licence.claim.customer_id,name:licence.claim.name,email:licence.claim.email},projects:projects(),prefs:db.prefs,aiReady:true,aiProvider:'aap',dailyLimit:30,exportLayout:db.layout,covers:{front:!!db.covers.front,back:!!db.covers.back},adminSettings:null,adminSummary:null};
  if(p==='projects'){
   if(!pieces[1]&&method==='POST')return mkProject(body||{});
   const proj=store.project(id);
   if(method==='GET')return {project:proj,documents:docs(id)};
   if(method==='POST'){if('title'in body)proj.title=String(body.title||'').slice(0,200);if('description'in body)proj.description=String(body.description||'');touch(proj);store.save();return proj;}
   if(method==='DELETE'){db.projects=db.projects.filter(p=>p.id!==id);db.documents=db.documents.filter(d=>d.projectId!==id);store.save();return {deleted:true};}
  }
  if(p==='documents'){
   if(!pieces[1]&&method==='POST')return mkDoc(body||{});
   const doc=store.document(id);store.project(doc.projectId);
   if(pieces[2]==='revisions'&&method==='GET')return (doc.revisions||[]).slice(-30).reverse();
   if(pieces[2]==='restore'&&method==='POST'){const rev=(doc.revisions||[]).find(r=>r.id===Number(body.revisionId));if(!rev)err('Revision not found');doc.revisions.push({id:store.id(),date:date(),title:doc.title,content:doc.content});doc.title=rev.title;doc.content=rev.content;touch(doc);store.save();return fmtDoc(doc);}
   if(method==='GET')return fmtDoc(doc);
   if(method==='POST'){if('title'in body||'content'in body){doc.revisions=doc.revisions||[];doc.revisions.push({id:store.id(),date:date(),title:doc.title,content:doc.content});doc.revisions=doc.revisions.slice(-30);}if('title'in body)doc.title=String(body.title||'').slice(0,200);if('content'in body)doc.content=String(body.content||'').slice(0,1500000);touch(doc);store.save();return fmtDoc(doc);}
   if(method==='DELETE'){db.documents=db.documents.filter(d=>d.id!==id);store.save();return {deleted:true};}
  }
  if(p==='prefs'&&method==='POST'){for(const [k,v]of Object.entries(body||{}))if(['language','font','fontSize','goal','autosave','focus','aiStyle','theme','penName','writingGenre','bio','suggestions','reduceMotion','highContrast','lineHeight','showWordCount','spellcheck','aiLanguage','coachingDetail','saveReminder'].includes(k))db.prefs[k]=String(v).slice(0,1000);store.save();return db.prefs;}
  if(p==='layout'&&method==='POST'){db.layout={...db.layout,...body};store.save();return db.layout;}
  if(p==='covers'&&['front','back'].includes(pieces[1])){
   const side=pieces[1];
   if(method==='GET'){const row=db.covers[side];if(!row)return {image:''};if(typeof row==='string')return {image:row};const img=path.join(store.base,row.filename);return {image:fs.existsSync(img)?'data:'+row.mime+';base64,'+fs.readFileSync(img).toString('base64'):''};}
   if(method==='DELETE'){const row=db.covers[side];if(row&&row.filename)try{fs.unlinkSync(path.join(store.base,row.filename));}catch{}delete db.covers[side];store.save();return {deleted:true};}
  }
  if(p==='export'&&method==='GET'){const proj=store.project(id);return {format:'aacs-project-v1',exported:new Date().toISOString(),project:proj,documents:docs(id)};}
  if(p==='ai'&&method==='POST')return licence.ai(body);
  if(p==='collaborators')err('Collaboration requires the online website. Offline project sharing is unavailable.',503);
  if(p==='admin')err('Studio administration is managed on the website.',403);
  err('Unsupported local Creative Studio request: '+endpoint,404);
 }
 function importProject(data){if(!data||data.format!=='aacs-project-v1'||!data.project||!Array.isArray(data.documents))err('Import a Creative Studio project JSON backup');if(data.documents.length>2000)err('Project contains too many documents');const p=mkProject(data.project);for(const d of data.documents)mkDoc({...d,projectId:p.id});return {project:p,count:data.documents.length};}
 return {api,importProject};}
module.exports=creative;
