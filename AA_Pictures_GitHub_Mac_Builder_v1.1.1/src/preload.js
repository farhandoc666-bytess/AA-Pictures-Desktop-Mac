'use strict';
const {contextBridge,ipcRenderer}=require('electron');
contextBridge.exposeInMainWorld('aaDesktop',{
 info:()=>ipcRenderer.invoke('aad:info'),listFiles:()=>ipcRenderer.invoke('aad:list-files'),addFile:()=>ipcRenderer.invoke('aad:add-file'),openFile:name=>ipcRenderer.invoke('aad:open-file',name),backup:()=>ipcRenderer.invoke('aad:backup'),pickImport:()=>ipcRenderer.invoke('aad:import-file')
});
