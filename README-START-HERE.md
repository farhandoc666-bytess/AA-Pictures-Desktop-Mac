> **v1.1.1 build correction:** The WordPress plugin integration test no longer depends on a separately extracted sibling folder. No WordPress settings or licence keys need to be changed.

# A&A Pictures · Offline Script Studio & Media Production · Windows x64

## Status

This is the **buildable Electron desktop source** for two separate Windows 10/11 x64 installers, based on the two WordPress studio plugins supplied by the project owner. It is not a prebuilt EXE. The existing studio JS/CSS and their actual logos are included. A separate ZIP installs the WordPress licensing/download service. This desktop ZIP also includes a reference-only copy of the WordPress licensing plugin solely for the integration test; it does not update the live website.

The source has passed local Node integration tests; **it has not been run as a packaged Windows app or end-to-end tested with the live `aapctrs.com` website**. Do not publicly release binaries until those tests are complete. Windows may show SmartScreen warnings on unsigned executables; sign release binaries with your business code-signing certificate.

## Installation order

1. **Back up `aapctrs.com` WordPress files and SQL database**. Keep the live Customer Accounts v2.0.2, A&A App Store v0.9.0, Creative Studio v1.6.0 and Media Production v1.0.1 plugins installed.
2. Install `AA_Pictures_Desktop_Licensing_v1.1.0.zip` from WordPress → Plugins → Add New → Upload Plugin. Activate it. This **generates a unique RSA signing key pair on the WordPress server** and adds **A&A App Store → Desktop Licences**. It creates exactly 50 Script keys, 50 Media keys and 50 Bundle keys on first activation.
3. Open **A&A App Store → Desktop Licences**. Confirm inventory. Click **Download public key (.pem)**. Store it as `aa-desktop-public-key.pem` alongside `BUILD-WINDOWS.bat` in this desktop source folder. **Never put the private key in desktop code**. The server's WordPress option `aadl_signing_private_pem` is private and must be backed up with the database.
4. On a Windows 10/11 development PC, extract this ZIP, put `aa-desktop-public-key.pem` alongside `BUILD-WINDOWS.bat` and double-click the BAT. It uses Node.js LTS, installs dependencies (`npm install`), runs tests and builds **two Windows installers** in `dist\script\` and `dist\media\`.
5. Admin uploads the two EXE installers to **A&A App Store → Desktop Licences** (upload form). Large files may exceed WordPress PHP upload limits: alternatively use Hostinger File Manager or SFTP to place the EXEs in the **private folder shown on that admin page**, then set filenames there. The location must be outside the public website's document root. **Do not upload to public WordPress Media Library**.
6. A&A customer purchases a product in Live mode and the existing App Store marks the order `paid_verified` **after authenticated payment verification** (or an admin provides a clearly-labelled complimentary licence). When the customer opens **My Apps** (`/aa-apps/`), the addon issues one unused key for the product. Bundle issues one key usable by both desktop apps on **one registered PC**. The same page shows the registered name, email, key and authorised installer link.
7. On first launch the desktop customer enters **registered display name, account email, purchased key**. With internet available, the app requests signed activation. It binds the key to the Windows device fingerprint, then saves the signed offline entitlement locally. **First launch without activation will not open the editor.**
8. On later launches, the app contacts the WordPress licence API if internet is available. If offline/unreachable it permits work **only if an authentic licence was previously activated on this PC**. If the server explicitly says revoked, the local session is blocked. Thereafter it rechecks at intervals while open.
9. Gemini requests go through WordPress `/wp-json/aadl/v1/ai`, which reads the **current WordPress Creative Studio Gemini API key**. Customers cannot edit or view the provider key. With internet failure, the studio displays an error but saves local drafts/reports as usual.

## Desktop features

| Area | Offline behaviour |
|---|---|
| Script Studio interface | Original v1.6.0 HTML/JS/CSS-driven UI, story projects, scripts, poetry, templates, idea tools, story bible, writer preferences |
| Writing autosave and revisions | Local JSON atomic saves; up to 30 revisions per document; local daily backups |
| Script projects | Import previous **website-exported Creative Studio project JSON** using top `IMPORT PROJECT JSON` button |
| Media Studio interface | Original v1.0.1 HTML/JS/CSS-driven UI and data schema, scenes, schedules, callsheets, budget, shooting reports, postproduction records |
| Media previous projects | Existing Media Studio `AAMP-PORTABLE-1` JSON export/import workflow (project records imported into an opened project) |
| Media input | `.fountain`, `.fdx` scene import; cut approvals and issued call-sheet snapshot records |
| Documents & reports | Local printing/PDF via Windows print dialog; Script Studio basic DOCX/PDF export; offline project JSON backup |
| Attachments | Top `LOCAL FILES` copies files into per-app local storage, openable offline (up to 1 GB each); not yet linked to individual media records |
| User identity | Top ribbon displays registered A&A customer name and online/offline status |
| Google Gemini | **Online-only**; uses site's current key and model through a rate-limited website relay; explicit error if unreachable |
| Account & billing | Online customer portal required; desktop does not handle payment data |
| Collaboration, web sharing | Website only; disabled in offline copy |

### Limitations to address before commercial release

- **No precompiled EXEs** in these files, and no real Windows GUI test in this environment. Build on Windows after installing the website plugin and importing its public key. Run a complete acceptance test on real Windows PCs.
- Site projects **do not automatically sync** with offline projects. Creative web exports can be imported; Media portable JSON imports are supported. Future optional two-way sync needs a conflict-resolution design.
- Desktop Script Studio DOCX/PDF output uses a local simplified exporter; **advanced website cover placement, page numbering and typography are not yet 1:1**. Uploaded cover previews are supported; export parity is a separate QA task.
- The file library keeps copies of attachments in local per-app directories. Attachments are not yet linked to individual production records. Very large video files should be stored separately.
- Windows desktop project data is stored unencrypted under `%APPDATA%\A&A Pictures\Script Studio` or `%APPDATA%\A&A Pictures\Media Production Studio`. Restrict Windows account access and enable full-disk encryption when handling sensitive scripts.
- There is no practical way to instantly revoke an indefinitely offline licence on a PC that never reconnects. Hardware fingerprints and software can potentially be spoofed by a determined attacker. The app prevents normal copying to a different PC and validates signed device-bound licences; it cannot guarantee piracy-proof operation.
- A device reset by administrator allows replacement activation but cannot erase old offline app files. Document this in your licence terms.
- Bundle licenses are one PC shared across both apps, not two PC activations.
- This does not alter Worldpay Checkout settings. **No key is issued from a pending/Try payment.** Existing app-store status `paid_verified` or `complimentary` is required for automatic issue.
- Customers' product keys are sensitive; issue with proper purchase verification and keep installer downloads private.

## Development and tests

Install Node.js LTS (22+ recommended), then run:

```powershell
npm install
npm test
npm run build:script -- "C:\\path\\aa-desktop-public-key.pem"
npm run build:media -- "C:\\path\\aa-desktop-public-key.pem"
```

For local GUI development on Windows after downloading the public key:

```powershell
npm run dev:script -- "C:\\path\\aa-desktop-public-key.pem"
npm run dev:media -- "C:\\path\\aa-desktop-public-key.pem"
```

The developer scripts automatically copy the public key into the local assets. Never commit the private signing key, Gemini key, database backup or customer key inventory.

## Release acceptance checklist

- Website is using HTTPS and the A&A Customer Accounts + App Store integrations remain active.
- All three key pools contain 50 initial keys, and a purchase is assigned exactly once.
- Try/unpaid orders do not receive paid keys; verified Live orders do.
- Real customer can download only the purchased SKU; bundle can download both.
- First launch offline cannot open; first launch online with matching identity/key activates and displays user.
- The same key fails on a different Windows machine; both bundle apps work on the originally registered PC.
- Disconnect internet; save, close, reopen, restore writing, edit daily report, print and add a local file.
- Reconnect; Gemini succeeds using current WordPress key; change key in WP and retest Gemini.
- Revoke a key then launch while connected and verify access is blocked; understand offline revocation limitation.
- Test Word DOCX/PDF, cover uploads and printed call sheets against the hosted version; accept/repair output differences before sale.
- Test WordPress upload size limits and private installer downloads through independent customer sessions.
- Sign the Windows installers and independently scan/QA both builds before deployment.

## Windows folder names containing spaces

The v1.0.1 `BUILD-WINDOWS.bat` calls `node scripts\build.js` directly and
uses a shell-free electron-builder launcher to avoid broken paths when your
Windows user name or the extraction folder contains spaces.
