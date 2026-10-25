#!/usr/bin/env python3
"""Bygger Leos sida.

Texterna ligger i innehall/sida.json (redigeras i Pages CMS).
Utseendet ligger i mallar/*.html och style.css.
Resultatet hamnar i mappen public/, som GitHub laddar upp till Inleed.

Köra själv på datorn:  pip install jinja2  och sedan  python3 bygg.py
"""
import json
import os
import shutil
import time

from jinja2 import Environment, FileSystemLoader, select_autoescape

ROT = os.path.dirname(os.path.abspath(__file__))
UT = os.path.join(ROT, "public")

# Menyn: sidorna i den ordning de visas
MENY = [
    {"fil": "index.html", "namn": "Start"},
    {"fil": "bilder.html", "namn": "Bilder"},
    {"fil": "om-mig.html", "namn": "Om mig"},
    {"fil": "kontakt.html", "namn": "Kontakt"},
]

# Filer och mappar som kopieras som de är
KOPIERA = ["style.css", "spel.js", "ljud.js", "kontakt.php", "bilder"]


def main():
    with open(os.path.join(ROT, "innehall", "sida.json"), encoding="utf-8") as f:
        data = json.load(f)

    # Nytt versionsnummer vid varje bygge, så webbläsare hämtar nya filer
    version = (os.environ.get("GITHUB_SHA") or str(int(time.time())))[:8]

    env = Environment(
        loader=FileSystemLoader(os.path.join(ROT, "mallar")),
        autoescape=select_autoescape(["html"]),
        trim_blocks=False,
    )

    if os.path.exists(UT):
        shutil.rmtree(UT)
    os.makedirs(UT)

    for sida in MENY:
        mall = env.get_template(sida["fil"])
        html = mall.render(meny=MENY, aktiv=sida["fil"], version=version, **data)
        with open(os.path.join(UT, sida["fil"]), "w", encoding="utf-8") as f:
            f.write(html)

    for namn in KOPIERA:
        kalla = os.path.join(ROT, namn)
        if os.path.isdir(kalla):
            shutil.copytree(kalla, os.path.join(UT, namn))
        elif os.path.exists(kalla):
            shutil.copy(kalla, UT)

    print(f"Byggde {len(MENY)} sidor till public/ (version {version})")


if __name__ == "__main__":
    main()
