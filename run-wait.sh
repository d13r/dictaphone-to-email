#!/usr/bin/env bash
set -o errexit -o nounset -o pipefail
cd "$(dirname "$0")"

# Make KeePass auto-type work for when we run 'sudo'
echo -ne "\e]2;"
echo -n "$USER@$(~/.bin/get-full-hostname):$PWD"
echo -ne "\a"

./dictaphone-to-email "$@"
echo
read -p 'Press any key to exit.'
