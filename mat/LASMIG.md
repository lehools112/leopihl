# Familjens mat

Inköpslista och receptsamling för familjen på **leopihl.com/mat**.
Mappen kopieras som den är av `bygg.py` och laddas upp till Inleed av GitHub, precis som resten av sidan.

## Engångsinställning på servern

Lösenordet ligger inte i den här koden (repot är öppet). Lägg i stället en fil
**en nivå ovanför public_html** via FTP, bredvid `kontakt-config.php`:

`/domains/leopihl.com/mat-config.php`

```php
<?php
return [
    'losenord'      => 'familjens-losenord-har',
    'hemlig_nyckel' => 'en-lang-slumpmassig-text-minst-16-tecken',
];
```

Familjens lista och recept sparas automatiskt i `/domains/leopihl.com/mat-data/`.
Mappen skapas första gången någon ändrar något. Går det inte, skapa den själv via FTP.

## Filer

| Fil | Vad den gör |
| --- | --- |
| `index.html` | Själva appen |
| `api.php` | Sparar och hämtar listan och recepten |
| `config.php` | Läser inställningarna från `mat-config.php` |
| `seed.json` | Recept och inköpslista som finns med från start |
| `manifest.json`, `icon-*.png` | Ikonen när appen läggs på hemskärmen |

## Säkerhetskopia

All data finns i `/domains/leopihl.com/mat-data/mat.php`. Ladda ner den via FTP ibland.
