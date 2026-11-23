#!/usr/bin/env bash
# Shows whether a `composer desktop:release` is running, which step it's on,
# and how the latest one ended.
#
# Run with: composer desktop:release-status
set -euo pipefail
cd "$(dirname "$0")/.."

lock="nativephp/electron/dist/.release.lock"

# The release script itself: a process whose command is `bash .../release-desktop-app.sh`.
# Matching only that leaves out the caffeinate wrapper, editors, and anything
# else that merely mentions the file name.
process=$(ps -ax -o pid=,etime=,command= | awk '$3 == "bash" && $4 ~ /(^|\/)release-desktop-app\.sh$/ { print; exit }' || true)
log=$(ls -t storage/logs/desktop-release-*.log 2>/dev/null | head -1 || true)

if [ -n "$process" ]; then
    read -r pid elapsed _ <<<"$process"
    echo "Running: yes (process $pid, for $elapsed)"
else
    echo "Running: no"
fi

if [ -z "$log" ]; then
    echo "No release logs yet in storage/logs."
    exit 0
fi

step=$(grep '^== [0-9]' "$log" | tail -1 | sed 's/^== //; s/ ==$//' || true)

if [ -n "$process" ]; then
    echo "Step:    ${step:-starting}"
    case "$step" in
        "1. Build"*|"5."*) echo "         (this step waits on Apple, so it can take a while)" ;;
    esac
elif grep -q '^== Done ==' "$log"; then
    echo "Latest:  finished"
    sed -n '/^Share these/,/^Open the installed app/p' "$log" | sed '$d'
elif grep -q '^Release failed' "$log"; then
    echo "Latest:  failed during ${step:-the checks}"
    echo "Last lines of the log:"
    tail -8 "$log" | sed 's/^/  /'
else
    echo "Latest:  stopped during ${step:-the start} without finishing"
    echo "         (the process was killed, or the Mac restarted)"
fi

grep '^Warning:' "$log" | sed 's/^/         /' || true

if [ -z "$process" ] && [ -d "$lock" ]; then
    echo "Note:    a leftover lock will stop the next release. Nothing is running, so it's safe to remove:"
    echo "  rmdir \"$PWD/$lock\""
fi

echo "Log:     $PWD/$log"
[ -n "$process" ] && echo "Watch:   tail -f \"$PWD/$log\""
exit 0
