#!/usr/bin/env bash
# Runs every shell test file (*.test.sh) of the repository: committed, staged or new, but never
# one in an ignored folder (node_modules, vendor). ci.yml and scripts/ci-local.sh both call this,
# so a new test file runs everywhere without being listed. Every file runs; the run is red when
# any file is red, and the red files are named at the end.
#
#   bash .github/scripts/run-tests.sh
set -uo pipefail

mapfile -t files < <(git ls-files --cached --others --exclude-standard -- '*.test.sh' | sort -u)
if [ "${#files[@]}" = 0 ]; then
    echo "No *.test.sh files found."
    exit 1
fi

failed=()
for file in "${files[@]}"; do
    echo "== $file"
    if ! bash "$file"; then
        failed+=("$file")
    fi
done

if [ "${#failed[@]}" -gt 0 ]; then
    printf 'FAILED %s\n' "${failed[@]}"
    exit 1
fi
echo "All ${#files[@]} shell test files passed."
