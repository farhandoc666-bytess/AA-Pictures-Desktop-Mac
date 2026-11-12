@echo off
setlocal enableextensions
cd /d "%~dp0"
echo =========================================================
echo     A^&A PICTURES - WINDOWS DESKTOP INSTALLER BUILDER
 echo =========================================================
if not exist "aa-desktop-public-key.pem" (
 echo.
 echo ERROR: aa-desktop-public-key.pem is missing.
 echo Install the Desktop Licences WordPress plugin first.
 echo WordPress - A^&A App Store - Desktop Licences - Download public key.
 echo Copy that file into this folder and rename it aa-desktop-public-key.pem
 pause
 exit /b 1
)
where node >nul 2>nul
if errorlevel 1 (
 echo Node.js is not installed. Trying Windows Package Manager...
 where winget >nul 2>nul
 if errorlevel 1 (
   echo Please install Node.js LTS from https://nodejs.org/en/download/ and rerun.
   pause
   exit /b 1
 )
 winget install --id OpenJS.NodeJS.LTS --exact --accept-package-agreements --accept-source-agreements
 echo Node.js was installed or updated. Close this window and rerun after restart.
 pause
 exit /b 1
)
 echo Installing build tools and Electron. An internet connection is needed for building only...
call npm install
if errorlevel 1 goto failed
 echo Running desktop source tests...
call npm test
if errorlevel 1 goto failed
 echo Building Script Studio x64 installer...
node "%~dp0scripts\build.js" script "%~dp0aa-desktop-public-key.pem"
if errorlevel 1 goto failed
 echo Building Media Production x64 installer...
node "%~dp0scripts\build.js" media "%~dp0aa-desktop-public-key.pem"
if errorlevel 1 goto failed
 echo.
 echo SUCCESS. Installers are inside dist\script and dist\media
 echo Upload each installer through WordPress A^&A App Store - Desktop Licences.
 pause
 exit /b 0
:failed
 echo.
 echo Build failed. Check the error above. Existing WordPress plugins are unchanged.
 pause
 exit /b 1
