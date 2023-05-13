#!/usr/bin/env bash
set -o errexit -o nounset -o pipefail
cd "$(dirname "$0")"

./dictaphone-to-email "$@"
echo
read -p 'Press any key to exit.'
