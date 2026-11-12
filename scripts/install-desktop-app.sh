#!/usr/bin/env bash
# Builds the desktop app from this folder and installs it in /Applications,
# replacing the installed copy. Your data is kept: it lives in
# ~/Library/Application Support/daily-updates, not inside the app.
#
# Run with: composer desktop:install
set -euo pipefail
cd "$(dirname "$0")/.."

installed="/Applications/Daily Updates.app"

case "$(uname -m)" in
    arm64) arch=arm64; built="nativephp/electron/dist/mac-arm64/Daily Updates.app" ;;
    *) arch=x64; built="nativephp/electron/dist/mac/Daily Updates.app" ;;
esac

# A version that always goes up (year.month.dayHHMM, e.g. 2026.11.121105), so
# the installed app sees a new version and runs any new database migrations.
read -r year month day hourmin <<<"$(date +'%Y %m %d %H%M')"
version="$year.$((10#$month)).$((10#$day * 10000 + 10#$hourmin))"

if [ -n "$(git status --porcelain)" ]; then
    echo "Note: this folder has uncommitted changes, and they will be in the build."
fi

echo "Building Daily Updates $version..."
NATIVEPHP_APP_VERSION="$version" php artisan native:build mac "$arch" --no-interaction

if [ ! -d "$built" ]; then
    echo "The build did not produce $built" >&2
    exit 1
fi

# Quit the installed app by its bundle ID, so a Safari web app with the same
# name is left alone.
bundle_id=$(/usr/libexec/PlistBuddy -c 'Print CFBundleIdentifier' "$built/Contents/Info.plist")
if pgrep -f "$installed/Contents/MacOS/" >/dev/null; then
    echo "Quitting the installed app..."
    osascript -e "tell application id \"$bundle_id\" to quit"
    for _ in $(seq 1 20); do
        pgrep -f "$installed/Contents/MacOS/" >/dev/null || break
        sleep 1
    done
    if pgrep -f "$installed/Contents/MacOS/" >/dev/null; then
        echo "Daily Updates didn't quit. Quit it yourself and run this again." >&2
        exit 1
    fi
fi

echo "Installing to $installed..."
rm -rf "$installed"
ditto "$built" "$installed"

open "$installed"
echo "Daily Updates $version is installed and open."
