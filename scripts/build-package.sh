#!/usr/bin/env bash
# Package the committed production files of the current branch.
set -euo pipefail

usage() {
    echo "Usage: bash $0 [--with-docker] [output-directory]"
    echo 'Builds pays_ps-VERSION.zip (or pays_ps-VERSION-docker.zip with --with-docker) from HEAD.'
}

with_docker=false
output_arg=""
for arg in "$@"; do
    case "$arg" in
        -h|--help)
            usage
            exit 0
            ;;
        --with-docker)
            with_docker=true
            ;;
        -*)
            usage >&2
            exit 2
            ;;
        *)
            if [ -n "$output_arg" ]; then
                usage >&2
                exit 2
            fi
            output_arg="$arg"
            ;;
    esac
done

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
archive_paths=(
    pays_ps.php index.php logo.png settings-template.php LICENSE changelog.txt
    controllers model install uninstall upgrade translations views
)
suffix=""
if [ "$with_docker" = true ]; then
    archive_paths+=(docker ':(exclude)docker/.env')
    suffix="-docker"
fi

output_dir="${output_arg:-$repository/dist}"
mkdir -p -- "$output_dir"
output_dir="$(cd -- "$output_dir" && pwd)"
archive="$output_dir/pays_ps-$version$suffix.zip"
temporary_archive="$(mktemp "$output_dir/.pays_ps-XXXXXX.zip")"
trap 'rm -f -- "$temporary_archive"' EXIT

git -C "$repository" archive --format=zip --prefix=pays_ps/ \
    --output="$temporary_archive" "$revision" -- "${archive_paths[@]}"
mv -f -- "$temporary_archive" "$archive"
printf 'Created %s\nSource commit: %s\n' "$archive" "$revision"
