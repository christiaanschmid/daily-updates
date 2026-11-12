#!/bin/sh
# Renders resources/icons/desktop-icon.svg to the desktop app icons NativePHP
# copies into a build: public/icon.png (1024px) and public/icon.icns (macOS).
# Uses only tools that ship with macOS and Xcode's command line tools.
set -e
cd "$(dirname "$0")/../.."

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

cat > "$work/render.swift" <<'SWIFT'
import AppKit

let args = CommandLine.arguments
guard args.count == 4, let size = Int(args[3]),
      let image = NSImage(contentsOf: URL(fileURLWithPath: args[1])) else {
    FileHandle.standardError.write("Could not load the SVG.\n".data(using: .utf8)!)
    exit(1)
}
let rep = NSBitmapImageRep(bitmapDataPlanes: nil, pixelsWide: size, pixelsHigh: size,
                           bitsPerSample: 8, samplesPerPixel: 4, hasAlpha: true, isPlanar: false,
                           colorSpaceName: .deviceRGB, bytesPerRow: 0, bitsPerPixel: 0)!
NSGraphicsContext.saveGraphicsState()
NSGraphicsContext.current = NSGraphicsContext(bitmapImageRep: rep)
NSGraphicsContext.current?.imageInterpolation = .high
image.draw(in: NSRect(x: 0, y: 0, width: size, height: size))
NSGraphicsContext.restoreGraphicsState()
try rep.representation(using: .png, properties: [:])!.write(to: URL(fileURLWithPath: args[2]))
SWIFT

swiftc -O "$work/render.swift" -o "$work/render" 2>/dev/null

"$work/render" resources/icons/desktop-icon.svg public/icon.png 1024

mkdir "$work/icon.iconset"
for size in 16 32 128 256 512; do
    "$work/render" resources/icons/desktop-icon.svg "$work/icon.iconset/icon_${size}x${size}.png" "$size"
    "$work/render" resources/icons/desktop-icon.svg "$work/icon.iconset/icon_${size}x${size}@2x.png" "$((size * 2))"
done
iconutil -c icns "$work/icon.iconset" -o public/icon.icns

echo "Wrote public/icon.png and public/icon.icns"
