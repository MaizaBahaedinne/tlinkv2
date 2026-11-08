#!/usr/bin/env python3
"""Genere icones et ecrans de demarrage des 4 apps mobiles a partir de mobile/icons/.

Usage : python3 scripts/generate_mobile_assets.py   (necessite Pillow)
Chaque icone source est un carre plein (idealement 1024x1024, sans transparence) ;
la couleur de fond est lue dans le coin de l'image.
"""
from pathlib import Path
import shutil

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parent.parent / "mobile"
WEB_ICONS = Path(__file__).resolve().parent.parent / "public" / "app-icons"
SOUNDS = ROOT / "sounds"
WEB_SOUNDS = Path(__file__).resolve().parent.parent / "public" / "sounds"
SOURCES = {
    "link": "icon Tlink mobile.png",
    "store": "icon store mobile.png",
    "academy": "icon mobile academy.png",
    "messenger": "icone mobile Messenger.png",
}

ICON_SIZES = {"mdpi": 48, "hdpi": 72, "xhdpi": 96, "xxhdpi": 144, "xxxhdpi": 192}
FOREGROUND_SIZES = {"mdpi": 108, "hdpi": 162, "xhdpi": 216, "xxhdpi": 324, "xxxhdpi": 432}
SPLASH_PORT = {"mdpi": (320, 480), "hdpi": (480, 800), "xhdpi": (720, 1280), "xxhdpi": (960, 1600), "xxxhdpi": (1280, 1920)}
SPLASH_LOGO_RATIO = 0.4  # part de la plus petite dimension occupee par le logo


def resize(img: Image.Image, size: int) -> Image.Image:
    return img.resize((size, size), Image.LANCZOS)


def splash(master: Image.Image, bg: tuple, width: int, height: int) -> Image.Image:
    canvas = Image.new("RGB", (width, height), bg)
    logo = resize(master, int(min(width, height) * SPLASH_LOGO_RATIO))
    canvas.paste(logo, ((width - logo.width) // 2, (height - logo.height) // 2))
    return canvas


def round_icon(master: Image.Image, size: int) -> Image.Image:
    scale = 4
    mask = Image.new("L", (size * scale, size * scale), 0)
    ImageDraw.Draw(mask).ellipse((0, 0, size * scale - 1, size * scale - 1), fill=255)
    mask = mask.resize((size, size), Image.LANCZOS)
    out = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    out.paste(resize(master, size).convert("RGBA"), (0, 0), mask)
    return out


def foreground(master: Image.Image, bg: tuple, size: int) -> Image.Image:
    canvas = Image.new("RGBA", (size, size), bg + (255,))
    logo = resize(master, int(size * 0.66))
    canvas.paste(logo, ((size - logo.width) // 2, (size - logo.height) // 2))
    return canvas


def build(app: str, source: str) -> None:
    master = Image.open(ROOT / "icons" / source).convert("RGB")
    master = master.resize((1024, 1024), Image.LANCZOS)
    bg = master.getpixel((2, 2))
    hex_bg = "#%02X%02X%02X" % bg

    # Android
    res = ROOT / app / "android/app/src/main/res"
    if res.exists():
        for density, size in ICON_SIZES.items():
            resize(master, size).save(res / f"mipmap-{density}/ic_launcher.png")
            round_icon(master, size).save(res / f"mipmap-{density}/ic_launcher_round.png")
        for density, size in FOREGROUND_SIZES.items():
            foreground(master, bg, size).save(res / f"mipmap-{density}/ic_launcher_foreground.png")
        (res / "values/ic_launcher_background.xml").write_text(
            '<?xml version="1.0" encoding="utf-8"?>\n<resources>\n'
            f'    <color name="ic_launcher_background">{hex_bg}</color>\n</resources>\n'
        )
        for density, (w, h) in SPLASH_PORT.items():
            splash(master, bg, w, h).save(res / f"drawable-port-{density}/splash.png")
            splash(master, bg, h, w).save(res / f"drawable-land-{density}/splash.png")
        splash(master, bg, 480, 320).save(res / "drawable/splash.png")

        # Sons des notifications push (canaux Android : res/raw)
        (res / "raw").mkdir(exist_ok=True)
        for sound in SOUNDS.glob("*.*"):
            shutil.copy(sound, res / "raw" / sound.name)

    # iOS
    assets = ROOT / app / "ios/App/App/Assets.xcassets"
    if assets.exists():
        master.save(assets / "AppIcon.appiconset/AppIcon-512@2x.png")
        big = splash(master, bg, 2732, 2732)
        for name in ("splash-2732x2732.png", "splash-2732x2732-1.png", "splash-2732x2732-2.png"):
            big.save(assets / "Splash.imageset" / name)

    # Web : favicons par application (public/app-icons/<app>/)
    web = WEB_ICONS / app
    web.mkdir(parents=True, exist_ok=True)
    for size in (16, 32):
        resize(master, size).save(web / f"favicon-{size}x{size}.png")
    resize(master, 180).save(web / "apple-touch-icon.png")
    resize(master, 192).save(web / "icon-192.png")
    resize(master, 512).save(web / "icon-512.png")
    master.save(web / "favicon.ico", sizes=[(16, 16), (32, 32), (48, 48)])

    print(f"{app}: fond {hex_bg}")


if __name__ == "__main__":
    WEB_SOUNDS.mkdir(parents=True, exist_ok=True)
    for sound in SOUNDS.glob("*.*"):
        shutil.copy(sound, WEB_SOUNDS / sound.name)
    for app, source in SOURCES.items():
        build(app, source)
