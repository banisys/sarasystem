# 0065 — Help on admin panel pages

- **Date:** 2026-11-06
- **Status:** accepted

## Context
The user wants a help system in the admin panel: an icon beside a page's title opens a modal that explains the page. People with permission edit the text in a textarea; everyone else reads it as plain text. It starts with «انواع قرارداد», and the user names further pages during development.

## Options considered
- **Where the default text lives**
  - A seeder or migration per page: a fresh or newly enabled module has no text until it runs, and a disabled module leaves rows behind.
  - **In the page's code, with a database row only for an edited text (chosen):** it needs no seeding, goes away with its module, and an unedited page gets improved text with the next release.
- **Scope of an edited text**
  - Per tenant: each branch would keep its own copy of text that describes the same screen.
  - **One per page for the installation (chosen):** the organization writes it once. So `page_helps` has no `tenant_id` and is not tenant-owned.
- **Format**
  - A rich editor: more than was asked, and the HTML would need sanitizing.
  - **Plain text in a textarea (chosen):** shown escaped, with line breaks kept.
- **How the icon reaches the title**
  - The render hook after the heading: it renders below the `h1`, so it needs CSS to sit beside the title, and it would have to find out for itself which pages have help.
  - **A trait that returns the heading as HTML with the action inside (chosen):** a page opts in with one line and a key.

## Decision
- The trait [HasPageHelp](../../app/Filament/Concerns/HasPageHelp.php) adds the action `pageHelp` to a page's heading. A page implements `pageHelpKey()` and `defaultPageHelp()`.
- The permission `Update:PageHelp` decides the form. It is built per request, so someone without it has neither the form nor a save handler on the server. It is a Shield custom permission in `config/filament-shield.php`, with that tab turned on in the role editor. By default `org_admin` has it, and not `tenant_manager`, because the text is shared by the whole organization.
- [PageHelp](../../app/Models/PageHelp.php) keeps a row only for a text that differs from the default: saving an empty text or the default itself removes the row, and the page follows its code again.

## Consequences
- An edited text no longer follows changes to the default in code, so it can go stale after a page changes.
- Pages join one at a time, on request; a page without the trait has no icon.
- The permission counts in the current tenant, as all permissions do: someone who holds it only in the head office reads the text on a branch's pages.
- Plain text only: no images or links, and no history of edits.
