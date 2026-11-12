'use strict';
const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
class Storage{
 constructor(base,app){this.base=base;this.app=app;fs.mkdirSync(base,{recursive:true});this.file=path.join(base,app+'-projects.json');this.backups=path.join(base,'backups');fs.mkdirSync(this.backups,{recursive:true});this.data=this.load();}
 load(){try{const json=JSON.parse(fs.readFileSync(this.file,'utf8'));if(json&&Array.isArray(json.projects)&&Array.isArray(json.records)&&Array.isArray(json.documents))return json;}catch{}return {nextId:1,projects:[],records:[],documents:[],prefs:{},layout:{},covers:{}};}
 save(){this.data.savedAt=new Date().toISOString();if(fs.existsSync(this.file)){const dest=path.join(this.backups,`${this.app}-${new Date().toISOString().slice(0,10)}.json`);if(!fs.existsSync(dest))try{fs.copyFileSync(this.file,dest);}catch{}}const tmp=this.file+'.'+crypto.randomBytes(4).toString('hex')+'.tmp';fs.writeFileSync(tmp,JSON.stringify(this.data,null,2),{mode:0o600});fs.renameSync(tmp,this.file);}
 id(){return this.data.nextId++;}
 now(){return new Date().toISOString().replace('T',' ').replace(/\..*$/,'');}
 find(type,id){return this.data[type].find(r=>r.id===Number(id));}
 project(id){const p=this.find('projects',id);if(!p)throw Object.assign(new Error('Project not found'),{status:404});return p;}
 record(id){const p=this.find('records',id);if(!p)throw Object.assign(new Error('Record not found'),{status:404});return p;}
 document(id){const p=this.find('documents',id);if(!p)throw Object.assign(new Error('Document not found'),{status:404});return p;}
}
module.exports=Storage;
