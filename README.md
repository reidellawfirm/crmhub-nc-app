# Reidel CRM Hub

**Version:** 0.2.1
**Author:** Schuyler "Rocky" Reidel, Reidel Law Firm (schuyler@reidellawfirm.com)
**License:** GPL-3.0-or-later

## Project Overview

Reidel CRM Hub is a lightweight CRM application designed to integrate deeply with Nextcloud. Instead of acting as a standalone application, it turns Nextcloud into a relational CRM for client and project work by seamlessly linking existing Nextcloud apps (Contacts, Files, Deck, Mail, and Activity) into a unified client record.

## Main Features

- **Unified Client Records:** Maintains a local cache of contact records linked to Nextcloud Contacts vCards.
- **Project Workspaces:** Attaches projects directly to a contact, linking a specific case folder in Nextcloud Files and a project board in Nextcloud Deck.
- **Hugo Preview Integration:** Fully integrated Hugo Static Site Generator preview tool. Pick a folder from Nextcloud Files containing markdown and attachments, and dynamically build, preview, and serve a Hugo static site within the CRM!
- **Activity Timeline:** Records a unified activity timeline and exposes a Dashboard widget for at-a-glance reviews of CRM activities.
- **Third-Party Automations:** Offloads heavy email/funnel automation to Windmill via a lightweight REST API surface.
- **Groundhogg Import:** Keeps existing CRM contacts in sync with Groundhogg.
- **Modern UI:** Features a sleek Vue 3 interface matching the Nextcloud 28+ design system, with dark mode support and keyboard accessibility.

## Application Workflow

1. **Contacts:** Users manage their clients via the standard Nextcloud Contacts interface or the CRM Hub dashboard.
2. **Projects:** Each client can have associated projects. A project has a dedicated folder in the Nextcloud Files app.
3. **Hugo Previews (Websites):** 
   - A user navigates to the **Hugo Preview** app (or uses the sidebar integration in Nextcloud Files).
   - They map an existing Nextcloud folder to a Hugo Project.
   - When the user triggers a build, the app dynamically parses the folder's markdown, resolves Nextcloud attachment links, and executes the `hugo` binary.
   - The compiled static site is served securely via an internal Nextcloud route.
4. **Deletions & Cleanups:** When a project is removed or deleted in the Nextcloud virtual file system, background hooks automatically clean up the built artifacts.

## Repository Structure & Development

This repository contains everything needed for both running the app in production and for future maintenance.

- **Runtime Files**: The core Nextcloud PHP backend (`lib/`, `appinfo/`, `templates/`) and the compiled frontend assets (`js/`, `css/`). These are the only files actively executed or served by Nextcloud in production.
- **Development Source Files**: Files such as the `src/` directory (Vue.js frontend source), `package.json`, and `webpack.config.js`. While these are **not required for runtime**, they are intentionally preserved in the repository so your IT team can maintain, debug, or extend the frontend application in the future.

For detailed setup instructions, please see [INSTALLATION.md](INSTALLATION.md).
