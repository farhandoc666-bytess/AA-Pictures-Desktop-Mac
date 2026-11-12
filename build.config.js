'use strict';
const mode=process.env.AAD_APP;
if(!['script','media'].includes(mode))throw Error('AAD_APP must be script or media');
const script=mode==='script';
module.exports={
 appId:script?'com.aapictures.scriptstudio':'com.aapictures.mediaproduction',
 productName:script?'AA Script Studio':'AA Media Production Studio',
 artifactName:script?'AA-Script-Studio-Setup-${version}.${ext}':'AA-Media-Production-Setup-${version}.${ext}',
 directories:{output:'dist/'+mode,buildResources:'assets'},
 files:['src/**/*','assets/**/*','package.json'],
 asar:true,
 extraMetadata:{aadProduct:mode,name:script?'aa-script-studio':'aa-media-production-studio'},
 win:{target:[{target:'nsis',arch:['x64']}],icon:'assets/'+(script?'script.ico':'media.ico')},
 mac:{target:[{target:'dmg',arch:['universal']}],category:script?'public.app-category.productivity':'public.app-category.business',icon:'assets/'+(script?'script-mac.png':'media-mac.png'),hardenedRuntime:process.env.AAD_ALLOW_UNSIGNED_MAC!=='1'},
 dmg:{title:script?'A&A Script Studio':'A&A Media Production Studio'},
 forceCodeSigning: process.env.AAD_REQUIRE_MAC_SIGNING==='1',
 nsis:{oneClick:false,perMachine:false,allowElevation:true,allowToChangeInstallationDirectory:true,createDesktopShortcut:true,createStartMenuShortcut:true,shortcutName:script?'AA Script Studio':'AA Media Production Studio'},
 publish:null
};
