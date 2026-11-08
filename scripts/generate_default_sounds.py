#!/usr/bin/env python3
"""Synthétise les sons de notification par défaut dans mobile/sounds/ (WAV mono 22,05 kHz).

  message.wav       T.Link Messenger : deux notes montantes courtes.
  notification.wav  Notifications T.Link : carillon doux à trois notes.

Pour utiliser vos propres sons, remplacez ces fichiers (WAV ou OGG pour Android, même nom) puis lancez `npm run cap:assets`.
"""
import math
import struct
import wave
from pathlib import Path

RATE = 22050
OUT = Path(__file__).resolve().parent.parent / "mobile" / "sounds"


def tone(freq: float, seconds: float, volume: float = 0.5) -> list[float]:
    count = int(RATE * seconds)
    samples = []
    for i in range(count):
        t = i / RATE
        attack = min(1.0, i / (RATE * 0.005))
        decay = math.exp(-5.0 * t / seconds)
        wave_ = math.sin(2 * math.pi * freq * t) + 0.25 * math.sin(2 * math.pi * freq * 2 * t)
        samples.append(volume * attack * decay * wave_ / 1.25)
    return samples


def mix(parts: list[tuple[float, list[float]]]) -> list[float]:
    end = max(int(RATE * start) + len(samples) for start, samples in parts)
    out = [0.0] * end
    for start, samples in parts:
        offset = int(RATE * start)
        for i, value in enumerate(samples):
            out[offset + i] += value
    return out


def save(name: str, samples: list[float]) -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    with wave.open(str(OUT / name), "wb") as f:
        f.setnchannels(1)
        f.setsampwidth(2)
        f.setframerate(RATE)
        f.writeframes(b"".join(struct.pack("<h", int(max(-1.0, min(1.0, s)) * 32767)) for s in samples))


if __name__ == "__main__":
    save("message.wav", mix([(0.0, tone(880.0, 0.18)), (0.11, tone(1318.5, 0.3))]))
    save("notification.wav", mix([(0.0, tone(659.3, 0.35)), (0.16, tone(830.6, 0.35)), (0.32, tone(987.8, 0.55))]))
    print("Sons écrits dans", OUT)
