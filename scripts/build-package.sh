#!/usr/bin/env bash
# Package the committed production files of the current branch.
set -euo pipefail

if [ "$#" -gt 1 ] || [ "${1:-}" = '--help' ] || [ "${1:-}" = '-h' ]; then
    echo "Usage: bash $0 [output-directory]"
    echo 'Builds pays_ps-VERSION.zip from HEAD (uncommitted changes are excluded).'
    if [ "$#" -gt 1 ]; then exit 2; fi
    exit 0
fi

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repository="$(git -C "$script_dir/.." rev-parse --show-toplevel)"
revision="$(git -C "$repository" rev-parse --verify HEAD)"
version="$(git -C "$repository" show "$revision:pays_ps.php" |
    awk -F "'" '/[$]this->version = / && !found {print $2; found = 1}')"
if [[ ! "$version" =~ ^[0-9]+(\.[0-9]+){2}([+-][0-9A-Za-z.-]+)?$ ]]; then
    echo 'Cannot read a valid module version from committed pays_ps.php.' >&2
    exit 1
fi

# Explicit list: development files and local settings never enter the archive.
production_paths=(
    pays_ps.php index.php logo.png settings-template.php LICENSE changelog.txt
    controllers model install uninstall upgrade translations views
)

output_dir="${1:-$repository/dist}"
mkdir -p -- "$output_dir"
output_dir="$(cd -- "$output_dir" && pwd)"
archive="$output_dir/pays_ps-$version.zip"
temporary_archive="$(mktemp "$output_dir/.pays_ps-XXXXXX.zip")"
trap 'rm -f -- "$temporary_archive"' EXIT

git -C "$repository" archive --format=zip --prefix=pays_ps/ \
    --output="$temporary_archive" "$revision" -- "${production_paths[@]}"
mv -f -- "$temporary_archive" "$archive"
printf 'Created %s\nSource commit: %s\n' "$archive" "$revision"
