#!/usr/bin/env bash
# Build the release package for Custom Error Pages and run the release checks.
#
# Usage:  tools/package.sh [git-ref] [series ...]   (default: HEAD, series 3.4 3.5)
#         One package per series, same content: this branch is the code for OJS 3.4 and 3.5.
# Env:    OUT_DIR   where to write the package (default: the repository's parent directory)
#         PHP_BINS  space-separated PHP interpreters for `php -l` (default: php)
#
# Copyright (c) 2026 OJS Services. Distributed under the GNU GPL v3.
# For full terms see the file LICENSE.
set -euo pipefail

REF="${1:-HEAD}"
[ $# -gt 0 ] && shift
SERIES_LIST="${*:-3.4 3.5}"
REPO="$(cd "$(dirname "$0")/.." && pwd)"
NAME="customErrorPages"
OUT_DIR="${OUT_DIR:-$(dirname "$REPO")}"
PHP_BINS="${PHP_BINS:-php}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
fail() { echo "FAIL: $*" >&2; exit 1; }
for s in $SERIES_LIST; do case "$s" in 3.4|3.5) ;; *) fail "unknown series $s (this branch builds OJS 3.4 and 3.5)";; esac; done

# --- stage the ref -----------------------------------------------------------
git -C "$REPO" rev-parse --verify --quiet "$REF^{commit}" >/dev/null || fail "unknown ref $REF"
git -C "$REPO" archive --format=tar --prefix="$NAME/" "$REF" | tar -x -C "$STAGE"
P="$STAGE/$NAME"
# Anything that must never ship (export-ignore covers most; this is the backstop).
rm -rf "$P/.git" "$P/docs" "$P/screenshots" "$P/tools" "$P/tmp" \
       "$P/RELEASING.md" "$P/.gitignore" "$P/.gitattributes"
find "$P" \( -name '*.tar.gz' -o -name '*.tmp' -o -name '*~' -o -name '.DS_Store' -o -name 'Thumbs.db' \) -delete

# --- 1. version: version.xml = PLUGIN_VERSION = CHANGELOG heading ------------
REL="$(sed -n 's:.*<release>\([0-9.]*\)</release>.*:\1:p' "$P/version.xml")"
CONST="$(sed -n "s:.*const PLUGIN_VERSION = '\([0-9.]*\)';.*:\1:p" "$P/CustomErrorPagesPlugin.php")"
CHLOG="$(git -C "$REPO" show "$REF:CHANGELOG.md" | sed -n 's/^## \([0-9][0-9.]*\) .*/\1/p' | head -1)"
[ -n "$REL" ] || fail "no <release> in version.xml"
[ "$REL" = "$CONST" ] || fail "version.xml $REL != PLUGIN_VERSION $CONST"
[ "$REL" = "$CHLOG" ] || fail "version.xml $REL != CHANGELOG heading $CHLOG"
APP="$(sed -n 's:.*<application>\(.*\)</application>.*:\1:p' "$P/version.xml")"
[ "$APP" = "$NAME" ] || fail "version.xml <application> is '$APP', expected '$NAME'"
echo "ok   version $REL (version.xml = PLUGIN_VERSION = CHANGELOG)"

# --- 2. php -l on every PHP file, every interpreter --------------------------
for bin in $PHP_BINS; do
    ver="$("$bin" -r 'echo PHP_VERSION;')"
    while IFS= read -r f; do
        "$bin" -l "$f" >/dev/null 2>&1 || fail "php -l ($ver): ${f#$STAGE/}"
    done < <(find "$P" -name '*.php')
    echo "ok   php -l with PHP $ver"
done

# --- 3. locale parity ---------------------------------------------------------
LOCALES="en tr es ru ar"
ref_keys="$(grep -h '^msgid "plugins' "$P/locale/en/locale.po" | sort)"
for loc in $LOCALES; do
    po="$P/locale/$loc/locale.po"
    [ -f "$po" ] || fail "missing locale $loc"
    keys="$(grep -h '^msgid "plugins' "$po" | sort)"
    [ "$keys" = "$ref_keys" ] || fail "locale $loc: key set differs from en"
    ! grep -A1 '^msgid "plugins' "$po" | grep -q '^msgstr ""$' || fail "locale $loc: empty msgstr"
done
echo "ok   locales: $(echo "$ref_keys" | wc -l) keys in each of $LOCALES"

# --- 4. leaks in shipped text files -------------------------------------------
LEAKS='localhost|127\.0\.0\.1|wamp|[A-Za-z]:\\|/home/|/Users/|scratchpad|claude|anthropic|chatgpt|openai|copilot|generated with|co-authored-by|kerim|maria|dergiadmin|izahost|izadigital|tinisos|\bTODO\b|\bFIXME\b|\bXXX\b'
if grep -rIniE "$LEAKS" "$P" --exclude=LICENSE; then fail "leak pattern found (see above)"; fi
echo "ok   no leaks"

# --- 5. no OJS 3.3 leftovers (OJS 3.4 / 3.5 series) ---------------------------
# 3.3 file names, the 3.3 class loader and registries, and the global constants
# that became class constants. Class-qualified uses (Foo::ASSOC_TYPE_ISSUE) pass.
[ -z "$(find "$P" -name '*.inc.php')" ] || fail "*.inc.php file shipped (OJS 3.3 naming)"
LEGACY="\\bimport\\(['\"]|HookRegistry::|AppLocale::|(^|[^:A-Za-z_])(CONTEXT_SITE|CONTEXT_ID_NONE|ASSOC_TYPE_[A-Z_]+|STATUS_PUBLISHED|FORM_VALIDATOR_[A-Z_]+|CACHEABILITY_[A-Z_]+)\\b"
if grep -rnE "$LEGACY" "$P" --include='*.php'; then fail "OJS 3.3 leftover (see above)"; fi
grep -q '^namespace APP\\plugins\\generic\\customErrorPages' "$P/CustomErrorPagesPlugin.php" || fail "plugin class is not namespaced"
# PHP 8.1+ only functions (OJS 3.4 runs on PHP 8.0; php -l does not catch these)
if grep -rnE '\barray_is_list\s*\(|\benum_exists\s*\(|\bfsync\s*\(' "$P" --include='*.php'; then fail "PHP 8.1+ function (see above)"; fi
echo "ok   no OJS 3.3 leftovers, no PHP 8.1+ functions"

# --- package -----------------------------------------------------------------
case "$(realpath -m "$OUT_DIR")/" in "$REPO/"*) fail "OUT_DIR is inside the repository; tarballs are never committed";; esac
mkdir -p "$OUT_DIR"
for SERIES in $SERIES_LIST; do
    PKG="$NAME-ojs$SERIES-$REL.tar.gz"
    ( cd "$STAGE" && tar --format=ustar -czf "$PKG" "$NAME" )
    mv "$STAGE/$PKG" "$OUT_DIR/$PKG"
    echo "ok   $PKG: $(tar -tzf "$OUT_DIR/$PKG" | wc -l) entries, top dir $(tar -tzf "$OUT_DIR/$PKG" | head -1)"
    echo "package: $OUT_DIR/$PKG"
    (cd "$OUT_DIR" && sha1sum "$PKG" && sha256sum "$PKG")
done
