#!/usr/bin/env python3
from pathlib import Path
from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
PUBLIC = ROOT / "public"
SRC = PUBLIC / "logoFondation.png"

if not SRC.exists():
    raise SystemExit("Missing public/logoFondation.png")

img = Image.open(SRC).convert("RGBA")

# Lossless optimization for existing PNG fallback.
img.save(SRC, format="PNG", optimize=True, compress_level=9)

# Main lightweight format.
img.save(PUBLIC / "logoFondation.webp", format="WEBP", quality=82, method=6)

# White version keeping alpha (for dark backgrounds/icons).
white = Image.new("RGBA", img.size, (255, 255, 255, 0))
white_alpha = img.getchannel("A")
white.putalpha(white_alpha)
white.save(PUBLIC / "logoFondation-white.png", format="PNG", optimize=True, compress_level=9)
white.save(PUBLIC / "logoFondation-white.webp", format="WEBP", quality=82, method=6)

# Favicons: default and white variants.
def make_icons(source: Image.Image, stem: str) -> None:
    ico_sizes = [(16, 16), (32, 32), (48, 48)]
    png_sizes = [(16, 16), (32, 32), (180, 180)]

    source.save(PUBLIC / f"{stem}.ico", format="ICO", sizes=ico_sizes)

    for size in png_sizes:
        icon = source.copy()
        icon.thumbnail(size, Image.Resampling.LANCZOS)
        canvas = Image.new("RGBA", size, (0, 0, 0, 0))
        x = (size[0] - icon.width) // 2
        y = (size[1] - icon.height) // 2
        canvas.paste(icon, (x, y), icon)
        suffix = f"{size[0]}x{size[1]}"
        canvas.save(PUBLIC / f"{stem}-{suffix}.png", format="PNG", optimize=True, compress_level=9)

make_icons(img, "favicon")
make_icons(white, "favicon-white")

# iOS touch icon (uses colored variant by default).
(PUBLIC / "favicon-180x180.png").replace(PUBLIC / "apple-touch-icon.png")
