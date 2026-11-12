# A&A Pictures — GitHub macOS DMG builder (v1.1.1)

This ZIP contains the **repository root**, ready to upload into a **PRIVATE** GitHub repository.
It produces one universal DMG per application: Script Studio and Media Production Studio.
Universal means built for Intel and Apple Silicon processors. GitHub uses a hosted Mac;
you do **not** need to own a Mac to compile these packages.

## Quick start on Windows — test builds (no Apple Developer account required)

1. Go to https://github.com/new and create a **Private** repository named `aa-pictures-desktop-mac`.
   Leave the initial README/`.gitignore`/license options unchecked.
2. Extract this ZIP. Open the resulting folder. Upload **the contents**, not a folder
   containing them, to the repository using **Add file > Upload files**. Include the
   hidden `.github` folder, which contains `.github/workflows/build-macos.yml`.
   To avoid web uploader issues, GitHub Desktop can also be used to clone the empty
   repository, copy the extracted contents inside it, commit, and push.
3. Check that `package.json`, `scripts/`, `assets/`, and `.github/workflows/build-macos.yml`
   are at the TOP LEVEL of the repository. Don't let everything sit under `aa-desktop-suite/`.
4. Open repository **Actions** -> **Build A&A Pictures macOS DMG installers**.
   Choose **Run workflow** -> **test_unsigned** -> **Run workflow**.
5. When the run completes successfully, open it and download the artifact named
   `AA-Pictures-macOS-test_unsigned-DMGs` from the **Artifacts** section.
6. Extract the artifact. It contains the two `.dmg` files (one from `dist/script/`
   and one from `dist/media/`). **These are unsigned TEST builds only. Do NOT upload
   them to your public store.** macOS Gatekeeper may refuse to open them by default.

## Signed and notarized public builds

For public downloads on aapctrs.com you need an Apple Developer Program membership,
a *Developer ID Application* certificate in `.p12` format, and Apple notarization
credentials. Configure these GitHub **repository secrets** under Settings > Secrets
and variables > Actions > New repository secret:

- `AA_MAC_CSC_LINK`: base64 representation of the Developer ID Application `.p12` file
- `AA_MAC_CSC_PASSWORD`: password protecting the `.p12`
- `AA_APPLE_ID`: Apple account email used for notarization
- `AA_APPLE_APP_PASSWORD`: Apple app-specific password (not the account password)
- `AA_APPLE_TEAM_ID`: 10-character Apple Developer Team ID

Once correctly configured, run the workflow again in **signed_release** mode.
This mode enforces code signing and supplies notarization credentials to electron-builder.
Validate signing, notarization, Gatekeeper behavior, licensing, local project storage,
opening on an actual Intel and an Apple Silicon Mac, and both private download routes
before publishing to paying users.

Never put the `.p12` certificate, its password, an Apple credential, WordPress database
backup, WordPress RSA **private** key, Gemini key, customer list, or real customer licence
keys into the repository (or a chat message). The public `.pem` key is safe to embed.

## Why this package differs from the prior source ZIP

- Fixes the original macOS workflow's `cache: npm` failure without a lockfile.
- Adds a `test_unsigned` mode, to compile development DMGs before you have Apple secrets.
- Preserves a distinct `signed_release` mode for paid customers.
- Generates `assets/trusted-public-key.pem` in CI from the public key before testing.
  The generated file is `.gitignore`d; without this step, a GitHub push can omit it.
- Makes test-only builds disable hardened runtime when unsigned; signed builds retain it.
- Keeps the `.github/workflows` file at the root of this upload-ready archive.

## Important constraints

- GitHub does not install the apps on your personal Mac or perform user-facing acceptance
  tests. Running CI creates build artifacts; you must test the actual binaries.
- Github Actions macOS minutes in a private repository can consume included usage or
  result in charges. Check your GitHub billing settings.
- An already-used **Windows** one-device licence generally cannot activate an additional
  Mac simultaneously; admin-approved device transfer is needed for the same key.
- The WordPress plugin already provides separate Windows and Mac installer slots; leave
  the plugin and issued licences unchanged during this build.
- Run `npm audit` and resolve security issues before public distribution.
