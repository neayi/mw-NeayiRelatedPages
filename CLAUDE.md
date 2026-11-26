# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this extension is

**NeayiRelatedPages** is a MediaWiki extension used on **Triple Performance** (wiki.tripleperformance.fr).
It shows a list of pages related to the current page: in the right column on desktop, and in the
footer on mobile. Related pages are grouped by page type, with a row of type filter buttons
("Tout", "Fiche technique", "Portail thématique", ...).

- Upstream repo: https://github.com/neayi/mw-NeayiRelatedPages (own git repository, branch `main`).
- The repo is checked out at `~/3perf-mw1.43/html/extensions/NeayiRelatedPages` by the parent Docker stack
  (`~/3perf-mw1.43`, see its own `CLAUDE.md`). The checkout is declared in `getWikiComponents()` in
  `~/3perf-mw1.43/bin/build_project.php` and loaded with `wfLoadExtension( 'NeayiRelatedPages' )` in
  `~/3perf-mw1.43/config/LocalSettings.php`.
- Commits go to **this** repo, not to the parent stack repo.
- Requires MediaWiki >= 1.39 (it runs on 1.43) and **Semantic MediaWiki**: all the logic relies on SMW properties.

## How it works

The extension has three parts:

1. **`NeayiRelatedPages.hooks.php`**: the `BeforePageDisplay` hook adds the `ext.neayiRelatedPages` RL module on every page.
2. **`modules/ext.neayiRelatedPages.js`** (client): if `wgArticleId != 0`, it calls
   `api.php?action=query&prop=relatedpages&titles=<wgPageName>`. It then renders into every `div.related-pages`:
   - a wrapping list of type filter tags (`.related-tags` > `button.related-tag`, each with a `.related-tag-count`).
     Several tags can be active at once, and a card is shown if it has **any** selected type (OR).
     `.related-tag-all` ("Tout") clears the selection and is active whenever nothing else is selected.
   - one `.very-small-card` per page. The filter reads its types from `data-types` (a JSON array of
     singular type names). The `related-page-<type slug>` classes are kept, but the filter no longer uses them.
   Cards that would make `.rightSide` taller than its initial height are added hidden (`display:none`).
   Pages are sorted by `Priority` (desc), then by `SortIndex`.
3. **`src/ApiRelatedPages.php`** (`APIRelatedPages`, registered as the `relatedpages` API **prop** module).
   For each page in the query's page set, it collects candidate titles from these sources, in this order
   (PHP `+` on arrays keyed by page ID, so the first source to add a page wins):
   1. `getTitlesFromTagsOf`: the pages named in the current page's `A un mot-clé` values (tags), except those that are
      also a `A un type de production`. These pages get **Priority = 1**.
   2. `getTitlesThatHaveTheSameTags`: weighted similarity scoring on SMW properties (`$propertiesToMatch`:
      mot-clé, agriculteur, intervenant, objectif, département, culture principale, Régule, portail).
      `$bonusPropertiesToMatch` only adds weight to pages that are already scored. It keeps the top 50.
   3. `getTitlesWithTag`: pages tagged with the current page (when the current page is itself a tag).
   4. Backlinks (`getLinksTo`, redirects resolved one level) and outgoing links (`getLinksFrom`, redirect targets resolved).

   It then drops the page itself and drops pages that have no `A un type de page`. It caps each type at
   15 pages and adds thumbnails (SMW `Page Image`, 200px). Pages in `NS_TRAINING` (3002, "Formation",
   defined in the stack's LocalSettings) also get Priority 1.
   Type labels are turned into plurals by parsing `Template:TranslationArray` as JSON (`[{singular, plural}, ...]`).

   Output per related page, under `query.pages.<id>.relatedpages`:
   `Title`, `URL` (prefixed URL), `Page types` (`[[singular, plural], ...]`), `ImageURL`, `SortIndex`, `Priority`.

## Things to know before editing

- **SMW property names are hard-coded in French** (`A un mot-clé`, `A un type de page`, ...). The same code runs on
  every language wiki (en, de, ...), so these labels must exist as SMW properties there too. Don't rename them in
  the code without checking the wikis.
- The skin builds the HTML containers, not this extension: `div.related-pages` is in the Chameleon layouts
  `~/3perf-mw1.43/html/skins/skin-neayi/layout.<lang>.xml`. There is one inside `.rightSide` (desktop) and one in
  the mobile footer block. Styles are in `skin-neayi/_relatedpages.scss`. If you change markup or class names
  in the JS, update the skin as well (it is a separate repo).
- `WANObjectCache` is injected and `CACHE_VERSION` exists, but **no caching is implemented yet**: each page view
  runs all the SMW queries. Keep performance in mind when adding sources or properties.
- `getCategoriesPlurals()` calls `die()` when `Template:TranslationArray` is missing. Be careful on fresh or empty DBs.
- The JS builds HTML with template strings and doesn't escape the values (titles come from the wiki).
- `NeayiRelatedPages.php` (legacy entry point), `NeayiRelatedPages.i18n.magic.php`, the `NeayiRelatedPagesEnableFoo`
  config and the "Parser hook" mention in the README are leftovers from the extension boilerplate. They aren't used.
- i18n: only `neayirelatedpages-all` and `neayirelatedpages-desc` are used by the JS. Add new keys to `i18n/en.json`,
  `i18n/fr.json` and `qqq.json`, and declare them in `extension.json` under `ResourceModules.messages`.

## Testing locally

The stack in `~/3perf-mw1.43` must be running (`docker compose up -d` there). There's no local PHP toolchain:
PHP runs in the `web` container, and the extension directory is mounted at `/var/www/html/extensions/NeayiRelatedPages`
(see `.vscode/launch.json` for Xdebug on port 9003).

- Page to test with: https://wiki.dev.tripleperformance.fr/wiki/Agroforesterie
  (the "related pages" block is in the right column, and at the bottom of the page on a narrow viewport).
- Raw API output (fastest way to check PHP changes):
  ```
  curl -sk "https://wiki.dev.tripleperformance.fr/api.php?action=query&prop=relatedpages&titles=Agroforesterie&format=json"
  ```
- PHP changes apply on the next request. For JS changes, ResourceLoader may serve a cached bundle: reload with
  `?debug=1` or do a hard refresh.
- Errors: look at the `web` container logs (`docker compose logs -f web` from `~/3perf-mw1.43`). The API catches
  exceptions and returns them as API errors.

Lint tooling (`Makefile`, `Gruntfile.js`, `composer.json`, `phpcs.xml`) comes from the old boilerplate, with outdated
dev dependencies. There are no automated tests.
