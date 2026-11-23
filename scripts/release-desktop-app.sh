#!/usr/bin/env bash
# Builds, checks and packages a new version of the desktop app to share with
# other people: Part 2 of the "Updating the Daily Updates Mac App" guide, minus
# the one-time `xcrun notarytool store-credentials daily-updates` setup.
#
# Run with: composer desktop:release            (runs in the background)
#           composer desktop:release -- --no-dmg (zip only)
#           bash scripts/release-desktop-app.sh --foreground
#
# The quick checks run first, in the foreground, so a dirty folder or missing
# keychain profile fails right away. The build, notarization checks, stapling,
# zip, dmg and a git tag (v<version>, pushed to GitHub) then run in the
# background under `caffeinate`, logged to
# storage/logs/desktop-release-*.log, with a macOS notification at the end.
set -euo pipefail
self="$(cd "$(dirname "$0")" && pwd)/$(basename "$0")"
cd "$(dirname "$self")/.."

profile="daily-updates"
dist="nativephp/electron/dist"

foreground=false
background_child=false
make_dmg=true
for arg in "$@"; do
    case "$arg" in
        --foreground) foreground=true ;;
        --background-child) background_child=true ;;
        --no-dmg) make_dmg=false ;;
        *) echo "Unknown option: $arg" >&2; exit 2 ;;
    esac
done

case "$(uname -m)" in
    arm64) arch=arm64; app_dir="mac-arm64" ;;
    *) arch=x64; app_dir="mac" ;;
esac
app="$dist/$app_dir/Daily Updates.app"

notify() {
    osascript -e "display notification \"$2\" with title \"Daily Updates release\" subtitle \"$1\"" >/dev/null 2>&1 || true
}

# --- Quick checks, before anything slow -------------------------------------

preflight() {
    if [ -n "$(git status --porcelain)" ]; then
        echo "This folder has uncommitted changes. Commit them first, so the release matches GitHub." >&2
        git status --short >&2
        exit 1
    fi

    git fetch --quiet || echo "Note: couldn't reach GitHub to check that your commits are pushed."
    if ! upstream=$(git rev-parse --abbrev-ref --symbolic-full-name '@{u}' 2>/dev/null); then
        echo "This branch has no upstream on GitHub. Push it first." >&2
        exit 1
    fi
    if [ -n "$(git log --oneline "$upstream..HEAD")" ]; then
        echo "You have commits that aren't on $upstream yet. Push them first." >&2
        exit 1
    fi

    if ! xcrun notarytool history --keychain-profile "$profile" >/dev/null 2>&1; then
        echo "The keychain profile \"$profile\" doesn't work. Do the guide's one-time setup:" >&2
        echo "  xcrun notarytool store-credentials $profile --apple-id \"YOUR_APPLE_ID\" --team-id \"YOUR_TEAM_ID\"" >&2
        exit 1
    fi
}

if ! $background_child; then
    preflight
fi

if ! $foreground && ! $background_child; then
    mkdir -p storage/logs
    log="storage/logs/desktop-release-$(date +%Y%m%d-%H%M%S).log"
    args=(--background-child)
    $make_dmg || args+=(--no-dmg)
    # caffeinate keeps the Mac awake while notarytool waits on Apple.
    nohup caffeinate -i bash "$self" "${args[@]}" >"$log" 2>&1 </dev/null &
    echo "Release started in the background (process $!)."
    echo "Follow it with:"
    echo "  tail -f \"$PWD/$log\""
    echo "Check on it any time with: composer desktop:release-status"
    echo "You'll get a notification when it finishes or fails."
    exit 0
fi

# --- The release itself ------------------------------------------------------

lock="$dist/.release.lock"
mkdir -p "$dist"
if ! mkdir "$lock" 2>/dev/null; then
    echo "Another release seems to be running (found $lock). If not, delete that folder and try again." >&2
    exit 1
fi
staging=""
on_exit() {
    status=$?
    rmdir "$lock" 2>/dev/null || true
    [ -n "$staging" ] && rm -rf "$staging"
    if [ "$status" -ne 0 ]; then
        echo "Release failed (exit $status)." >&2
        notify "Failed" "See the log in storage/logs."
    fi
}
trap on_exit EXIT

echo "== $(date) =="
# Remember the commit now: the build takes a while, and you may commit more
# work while it runs. The tag goes on the commit that was built.
commit=$(git rev-parse HEAD)
echo "== 1. Build (commit ${commit:0:7}) =="
bash scripts/install-desktop-app.sh

version=$(/usr/libexec/PlistBuddy -c 'Print CFBundleShortVersionString' "$app/Contents/Info.plist")
echo "Version: $version"

echo "== 2. Check that Apple notarized the app =="
app_check=$(spctl -a -vvv -t exec "$app" 2>&1 || true)
echo "$app_check"
if [[ "$app_check" != *"source=Notarized Developer ID"* ]]; then
    echo "The app isn't notarized. Recent submissions:" >&2
    xcrun notarytool history --keychain-profile "$profile" 2>&1 | head -20 >&2 || true
    echo "See Troubleshooting in the guide: if the newest is Accepted, staple and check again; if Invalid, run" >&2
    echo "  xcrun notarytool log SUBMISSION_ID --keychain-profile $profile" >&2
    exit 1
fi

echo "== 3. Staple the approval to the app =="
xcrun stapler staple "$app"

echo "== 4. Make the zip =="
zip_name="Daily-Updates-$version-$arch.zip"
rm -f "$dist/$zip_name"
ditto -c -k --keepParent "$app" "$dist/$zip_name"
echo "Made $dist/$zip_name"

dmg_name=""
if $make_dmg; then
    echo "== 5. Make, sign and notarize the dmg =="
    dmg_name="Daily-Updates-$version-$arch.dmg"
    # Sign with the same Developer ID that signed the app.
    identity=$(codesign -dvv "$app" 2>&1 | sed -n 's/^Authority=\(Developer ID Application: .*\)$/\1/p' | head -1)
    if [ -z "$identity" ]; then
        echo "Couldn't find the Developer ID Application identity on the app." >&2
        exit 1
    fi

    staging="$dist/dmg-staging"
    rm -rf "$staging" "$dist/$dmg_name"
    mkdir "$staging"
    ditto "$app" "$staging/Daily Updates.app"
    ln -s /Applications "$staging/Applications"
    hdiutil create -volname "Daily Updates" -srcfolder "$staging" -fs HFS+ -format UDZO "$dist/$dmg_name"
    rm -rf "$staging"
    staging=""

    codesign --sign "$identity" --timestamp "$dist/$dmg_name"
    xcrun notarytool submit "$dist/$dmg_name" --keychain-profile "$profile" --wait
    xcrun stapler staple "$dist/$dmg_name"

    echo "Checking the dmg the way a downloaded file is checked..."
    dmg_check=$(spctl -a -vv -t open --context context:primary-signature "$dist/$dmg_name" 2>&1 || true)
    echo "$dmg_check"
    if [[ "$dmg_check" != *"source=Notarized Developer ID"* ]]; then
        echo "The dmg didn't pass the check. The zip is still fine to share." >&2
        exit 1
    fi
fi

echo "== 6. Tag the release on GitHub =="
# The files are already made, so a problem here is a warning, not a failure.
tag="v$version"
tag_note=""
if git rev-parse -q --verify "refs/tags/$tag" >/dev/null; then
    tag_note="Tag $tag already exists, so it was left alone."
elif ! git tag -a "$tag" -m "Daily Updates $version" "$commit"; then
    tag_note="Couldn't create tag $tag."
elif ! git push origin "refs/tags/$tag"; then
    tag_note="Tag $tag was made here but not pushed. Push it with: git push origin $tag"
else
    echo "Tagged ${commit:0:7} as $tag and pushed it."
fi
[ -n "$tag_note" ] && echo "Warning: $tag_note" >&2

echo "== Done =="
echo "Share these (not NativePHP's own \"Daily Updates-$version-*\" files):"
echo "  $PWD/$dist/$zip_name"
[ -n "$dmg_name" ] && echo "  $PWD/$dist/$dmg_name"
echo "Open the installed app and check it works before you send it."
notify "Daily Updates $version is ready" "${tag_note:-Files are in $dist}"
