# Custom Error Pages

A plugin for **OJS 3.4 and 3.5** that replaces OJS' bare `404 Not Found` message with a friendly error page in your journal's own theme, with its header, footer and menu. It works with any theme and needs no changes to OJS.

The themed page is shown for unknown pages, deleted articles, missing issues and missing files. For a missing file, it links back to the article or issue.

A URL whose journal path does not exist (for example `/index.php/no-such-journal`) is the one exception: OJS 3.4 and 3.5 answer it before any plugin is loaded, so it keeps OJS' plain 404.

## Choose a style

Each journal picks how its error page looks from a list of 12 styles in the plugin settings:

![Style list in the plugin settings](screenshots/settings.png)

Photo styles load one small image (about 100 KB); the colour styles load nothing extra. With *Your own image* (style 12) you can use any image, for example one uploaded to *Settings → Workflow → Publisher Library* with *Public access* ticked.

### Examples

The styles in OJS' default theme:

![The styles in the default theme](screenshots/styles-default-theme.png)

The same styles in a custom theme:

![The styles in a custom theme](screenshots/styles-custom-theme.png)

## Installation

1. Download `customErrorPages-ojs3.4-3.5-<version>.tar.gz` (version 4.x) from [Releases](../../releases). The same package is for OJS 3.4 and 3.5.
2. In OJS, go to *Settings → Website → Plugins → Upload a new plugin* and choose the file.
3. Enable **Custom Error Pages** in each journal where you want it.
4. Optional: open *Custom Error Pages → Settings* and pick a style.

**OJS 3.3:** use the `customErrorPages-ojs3.3-<version>.tar.gz` package (version 3.0.0.x). The plugin name and its settings are the same on every OJS line.

## Requirements

- OJS 3.4.x or 3.5.x (the OJS 3.4 and 3.5 packages have the same content)
- The PHP version your OJS needs (8.0 or later for OJS 3.4, 8.2 or later for OJS 3.5)

## Licence

GNU General Public License v3. See [LICENSE](LICENSE).

© OJS Services — info@ojs-services.com
