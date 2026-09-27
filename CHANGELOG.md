# Changelog — Custom Error Pages

## 3.0.0.2 — 2026-09-27
- No change for OJS 3.3. This release fixes the language menu and the RSS and Atom feeds on OJS 3.5; the OJS 3.3 package gets the same number so that every OJS series stays on one version. Sites on 3.0.0.1 can upgrade or stay; both behave the same.

## 3.0.0.1 — 2026-09-26
- One version number for every OJS series: this release is 3.0.0.1 for OJS 3.3, 3.4 and 3.5, and the package name says which OJS it is for (`customErrorPages-ojs3.3-3.0.0.1.tar.gz`).
- No functional change from 1.5.0.1. Sites on 1.5.x upgrade to this release and keep their settings.

## 1.5.0.1 — 2026-09-26
- Fixed: with a theme that draws its own card on the error page (Pampas), the dark colour and photo styles put white text on a white card, so the message could not be read. Styles without a card now remove the theme's card and centre the text. Other themes look the same as before.

## 1.5.0.0 — 2026-09-26
- New: choose the error page style from 12 options in the plugin settings, each with a preview: the theme's own colours, four sandstone photo styles, six colour styles, or your own image.
- Existing installs keep their current look after upgrading.
- The error message and button are centred on every theme.
- All new texts are available in English, Turkish, Spanish, Russian and Arabic.
