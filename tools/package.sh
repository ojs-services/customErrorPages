#!/usr/bin/env bash
# Build the release package for Custom Error Pages and run the release checks.
#
# Usage:  tools/package.sh [git-ref]        (default: HEAD)
# Env:    OUT_DIR   where to write the package (default: the repository's parent directory)
#         PHP_BINS  space-separated PHP interpreters for `php -l` (default: php)
#
# Copyright (c) 2026 OJS Services. Distributed under the GNU GPL v3.
# For full terms see the file LICENSE.
set -euo pipefail

REF="${1:-HEAD}"
REPO="$(cd "$(dirname "$0")/.." && pwd)"
NAME="customErrorPages"
OUT_DIR="${OUT_DIR:-$(dirname "$REPO")}"
PHP_BINS="${PHP_BINS:-php}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
fail() { echo "FAIL: $*" >&2; exit 1; }

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
CONST="$(sed -n "s:.*const PLUGIN_VERSION = '\([0-9.]*\)';.*:\1:p" "$P/CustomErrorPagesPlugin.inc.php")"
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
LOCALES="en_US tr_TR es_ES ru_RU ar_IQ"
ref_keys="$(grep -h '^msgid "plugins' "$P/locale/en_US/locale.po" | sort)"
for loc in $LOCALES; do
    po="$P/locale/$loc/locale.po"
    [ -f "$po" ] || fail "missing locale $loc"
    keys="$(grep -h '^msgid "plugins' "$po" | sort)"
    [ "$keys" = "$ref_keys" ] || fail "locale $loc: key set differs from en_US"
    ! grep -A1 '^msgid "plugins' "$po" | grep -q '^msgstr ""$' || fail "locale $loc: empty msgstr"
done
echo "ok   locales: $(echo "$ref_keys" | wc -l) keys in each of $LOCALES"

# --- 4. leaks in shipped text files -------------------------------------------
LEAKS='localhost|127\.0\.0\.1|wamp|[A-Za-z]:\\|/home/|/Users/|scratchpad|claude|anthropic|chatgpt|openai|copilot|generated with|co-authored-by|kerim|maria|dergiadmin|izahost|izadigital|tinisos|\bTODO\b|\bFIXME\b|\bXXX\b'
if grep -rIniE "$LEAKS" "$P" --exclude=LICENSE; then fail "leak pattern found (see above)"; fi
echo "ok   no leaks"

# --- 5. no PHP 7.4+ syntax (OJS 3.3 series) -----------------------------------
SYNTAX='\bfn\s*\(|\?\?=|\bmatch\s*\(|\?->|str_contains|str_starts_with|str_ends_with|(private|public|protected)\s+(static\s+)?\??(int|string|bool|array|float|object|iterable|self|[A-Z][A-Za-z_\\]*)\s+\$|\(\s*[a-zA-Z_]+:\s'
if grep -rnE "$SYNTAX" "$P" --include='*.php'; then fail "PHP 7.4+ syntax (see above)"; fi
echo "ok   no PHP 7.4+ syntax"

# --- package -----------------------------------------------------------------
PKG="$NAME-ojs3.3-$REL.tar.gz"
case "$(realpath -m "$OUT_DIR")/" in "$REPO/"*) fail "OUT_DIR is inside the repository; tarballs are never committed";; esac
mkdir -p "$OUT_DIR"
( cd "$STAGE" && tar --format=ustar -czf "$PKG" "$NAME" )
mv "$STAGE/$PKG" "$OUT_DIR/$PKG"
echo "ok   $(tar -tzf "$OUT_DIR/$PKG" | wc -l) entries, top dir $(tar -tzf "$OUT_DIR/$PKG" | head -1)"
echo
echo "package: $OUT_DIR/$PKG"
(cd "$OUT_DIR" && sha1sum "$PKG" && sha256sum "$PKG")
