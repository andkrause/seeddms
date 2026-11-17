#!/usr/bin/env bash
set -euo pipefail

root=$(cd "$(dirname "$0")/.." && pwd)
output=${1:-"$root/dist"}
mkdir -p "$output"
output=$(cd "$output" && pwd)
staging=$(mktemp -d "$output/.llmclassifier-build.XXXXXX")
trap 'rm -rf "$staging"' EXIT
mkdir "$staging/llmclassifier"

for file in class.llmclassifier.php conf.php lang.php icon.svg README.md changelog.md composer.json composer.lock; do
    cp "$root/ext/llmclassifier/$file" "$staging/llmclassifier/"
done

docker run --rm -v "$staging/llmclassifier:/app" -w /app composer:2 \
    install --no-dev --prefer-dist --no-interaction --optimize-autoloader

docker run --rm -v "$staging:/package:ro" -v "$output:/output" composer:2 \
    tar -czf /output/llmclassifier-1.1.0.tar.gz -C /package llmclassifier
echo "$output/llmclassifier-1.1.0.tar.gz"
