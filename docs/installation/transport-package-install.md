# Transport Package Installation

Status: Draft

## Build

The package is built from `_build/build.php` and follows the MODX Transport Package convention.

The generated artifact is expected to have the form:

`modx-ai-bridge-VERSION-RELEASE.transport.zip`

## Install in Manager

1. Open MODX Manager.
2. Open **Extras → Installer**.
3. Add/upload the Bridge Transport Package.
4. Select the package and choose **Install**.
5. Review the installation summary and resolvers.
6. Complete installation.
7. Clear MODX cache.
8. Open **Components → AI Bridge** and run the health check.

## Post-install verification

The installation is not considered successful merely because the package installer reports success. Verify:

- namespace `aibridge` exists;
- component files exist under `core/components/aibridge/`;
- generated xPDO model metadata is available;
- Manager controller loads;
- AI Bridge menu entry is present;
- health processor returns an `ok` status;
- default security switches remain disabled for REST/MCP;
- no secret values were written to logs.
