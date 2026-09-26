# Releasing Custom Error Pages

These rules apply to every release. This file is not part of the release package.

## Version numbers

- There is one major version per OJS series:

  | OJS | Plugin versions | Branch |
  |---|---|---|
  | 3.3 | 1.x.x.x | `stable-3_3_0` |
  | 3.4 | 2.x.x.x | `stable-3_4_0` |
  | 3.5 | 3.x.x.x | `stable-3_5_0` / `main` |

- Version numbers only go up. A published tag or package is never rewritten or re-tagged. Every fix gets a new version number.
- OJS compares `versions` rows case-insensitively. If a published version is re-installed under different content or a different folder name, OJS can reactivate the old row instead of adding a new one (see CHANGELOG 1.4.1.0).

## Single source of truth

- The version is the `<release>` value in `version.xml`.
- The following must all equal it:
  - `CustomErrorPagesPlugin::PLUGIN_VERSION`
  - the first `## x.y.z.w` heading in `CHANGELOG.md`
  - the package file name
- `tools/package.sh` stops if any of them differ.

## Names that never change

- The folder and product name is `customErrorPages` in every OJS series.
- Class names, `getName()` (`customerrorpagesplugin`) and setting keys stay as they are, because OJS stores plugin settings under the class name.
- If one of these ever has to change, the release must ship a migration and a tested upgrade path.

## Package

Build it with the script:

```bash
tools/package.sh            # builds from HEAD
tools/package.sh v1.4.1.0   # builds from a tag
```

The script runs `git archive` on the given ref, removes everything that does not ship, runs the release checks, and writes the package **outside the repository** (default: the parent directory; set `OUT_DIR` to override):

```
tar --format=ustar -czf customErrorPages-<x_y_z_w>.tar.gz customErrorPages
```

- The top-level directory is exactly `customErrorPages`.
- Never shipped: `.git`, `docs/`, `screenshots/`, `tools/`, `RELEASING.md`, `.gitignore`, `.gitattributes`, `*.tar.gz`, temporary and test files. `.gitattributes` also marks these `export-ignore`, so GitHub's source archives leave them out too.
- Tarballs are never committed. They are attached to the GitHub release.

## Checks for every release

`tools/package.sh` runs these checks and fails on any error:

1. `php -l` on every PHP file with PHP 7.4, 8.0, 8.1 and 8.2. Set `PHP_BINS` to the interpreters to use; when it is unset, the script uses `php` from `PATH`.
2. Locale parity: all five locales (`en_US`, `tr_TR`, `es_ES`, `ru_RU`, `ar_IQ`) have the same set of keys and no empty values.
3. `PLUGIN_VERSION` = `version.xml` = the CHANGELOG heading = the package name.
4. No leaks in shipped text files: internal notes, personal names, local paths, `localhost`, AI or tool attribution.
5. For the 3.3 series: no PHP 7.4+ syntax (arrow functions, `??=`, `match`, `?->`, typed properties, union types, named arguments, `str_contains` and related functions).

Manual checks:

- Test on a local OJS of the target series.
- Record a field test in `docs/FIELD_TEST.md` before starting work on the next series.

## Commits and public content

- Commit author: `OJS Services <info@ojs-services.com>`.
- Commits, release notes and other public content carry no AI or tool attribution.
- Sprint and test reports live in `docs/`, which git ignores.

## GitHub release

1. Push the branch and the tag: `git push origin stable-3_3_0 v<x.y.z.w>`.
2. Create the release from the tag:
   - Title: `Custom Error Pages <x.y.z.w>`.
   - Notes: the release's CHANGELOG entry, in English.
   - Attachment: the package, with its `sha1sum` in the notes.
