# Changelog — Custom Error Pages

## 3.0.0.2 — 2026-09-27
- Fixed (OJS 3.5): with the plugin enabled, the language menu led to a "page not found" error on every multilingual site, and the language did not change. OJS 3.5 changes the language in its router, not in a page handler, and the plugin took the language link for a page that does not exist. The plugin now leaves these links to OJS.
- Fixed (OJS 3.5): with the plugin enabled, the journal's RSS and Atom feeds and every other page under `/gateway/` (LOCKSS, CLOCKSS, gateway plugins) ended in a server error. To tell a real page from a missing one, the plugin loads the page's `index.php` the way OJS does. OJS 3.5's gateway page needs the `$request` variable that OJS gives it, and the plugin did not provide it. The plugin now provides it. If loading a page file fails for any other reason, the plugin steps aside and OJS handles the request as usual.
- OJS 3.4 was not affected by either problem: it changes the language in a page handler, and its page files do not use `$request`. The OJS 3.4 and OJS 3.5 packages are identical, as always.

## 3.0.0.1 — 2026-09-26
- One version number for every OJS series: this release is 3.0.0.1 for OJS 3.3, 3.4 and 3.5. The package name says which OJS it is for (`customErrorPages-ojs3.4-3.0.0.1.tar.gz`, `customErrorPages-ojs3.5-3.0.0.1.tar.gz`); the OJS 3.4 and OJS 3.5 packages are identical.
- No functional change from 2.0.0.0 (OJS 3.4) and 3.0.0.0 (OJS 3.5). Both upgrade to this release and keep their settings.

## 3.0.0.0 — 2026-09-26
- First release for OJS 3.5. The code is the same as 2.0.0.0 (OJS 3.4); only the version number differs, because each OJS series has its own major version.
- A site that moves from OJS 3.4 to 3.5 upgrades from 2.x to this release and keeps its settings.

## 2.0.0.0 — 2026-09-26
- First release for OJS 3.4 and 3.5. It has the same features, styles and settings as 1.5.0.0; the 1.x line stays for OJS 3.3.
- One code base for both OJS versions: it checks what the running OJS offers, never its version number.
- Fixed: with a theme that draws its own card on the error page (Pampas), the dark colour and photo styles put white text on a white card, so the message could not be read. Styles without a card now remove the theme's card and centre the text. The same fix is in 1.5.0.1 for OJS 3.3.
- Known limit: on OJS 3.4 and 3.5, a URL whose journal path does not exist is answered by OJS before any plugin loads, so it keeps OJS' plain 404.

## 1.5.0.0 — 2026-09-26
- New: choose the error page style from 12 options in the plugin settings, each with a preview: the theme's own colours, four sandstone photo styles, six colour styles, or your own image.
- Existing installs keep their current look after upgrading.
- The error message and button are centred on every theme.
- All new texts are available in English, Turkish, Spanish, Russian and Arabic.
