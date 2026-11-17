# Reidel CRM Hub

**Version:** 0.3.0
**Author:** Schuyler "Rocky" Reidel, Reidel Law Firm (schuyler@reidellawfirm.com)
**License:** GPL-3.0-or-later

Reidel CRM Hub is a lightweight CRM for Nextcloud. It links Contacts, Files, Deck, Mail, and Activity into unified client and project records without replacing those apps.

## Main Features

- **Unified Client Records:** Local contact records linked to Nextcloud Contacts vCards.
- **Project Workspaces:** Projects attached to contacts, with case folders in Files and cards in Deck.
- **Activity Timeline:** A unified activity feed plus a Nextcloud Dashboard widget.
- **Tasks and Notes:** Contact- and project-scoped work items and notes.
- **Reports and Exports:** CRM analytics and CSV exports.
- **Automation:** Windmill REST integration and Groundhogg contact synchronization.
- **Nextcloud Integration:** Contacts panels, unified search, notifications, and file/Deck/Mail activity hooks.

## Requirements

- Nextcloud 34 or 35.
- PHP 8.1 or later.
- Nextcloud Contacts is recommended; Files, Deck, Mail, Calendar, Windmill, and Groundhogg integrations are optional.

## Installation

Install the app archive into `apps/` or `custom_apps/`, then enable **Reidel CRM Hub** in Nextcloud Apps. The app icon opens the CRM overview at `/apps/crmhub_nc_app/`.

## Hugo Preview separation

Hugo Preview is no longer bundled with CRM Hub as of version 0.3.0. It is now the independent `hugopreview_nc_app` application, maintained at `https://github.com/reidellawfirm/hugopreview-nc-app`.

If you use Hugo Preview, install that app before upgrading CRM Hub. Existing Hugo project configuration is not automatically migrated; back up CRM Hub's legacy `projects` application configuration key and recreate the project list in Hugo Preview. Rebuild previews from the original Nextcloud folders after installing the standalone app.

## Development

```bash
npm ci --legacy-peer-deps
npm run build
```

The app ID is `crmhub_nc_app`. The public repository is `https://github.com/reidellawfirm/crmhub-nc-app`.
