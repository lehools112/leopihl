<?php
/*
 * Inställningar för Familjens mat.
 *
 * Lösenordet står INTE här (koden ligger öppet på GitHub).
 * Det står i mat-config.php, som ligger en nivå ovanför public_html på servern:
 *   /domains/leopihl.com/mat-config.php
 * Se mat/LASMIG.md för hur filen ska se ut.
 *
 * Familjens lista och recept sparas också utanför public_html:
 *   /domains/leopihl.com/mat-data/
 * så de varken syns på webben eller skrivs över när sidan laddas upp på nytt.
 */

$__cfgFile = __DIR__ . '/../../mat-config.php';
$__cfg = is_file($__cfgFile) ? (require $__cfgFile) : [];
if (!is_array($__cfg)) $__cfg = [];

define('FAMILY_PASSWORD', (string)($__cfg['losenord'] ?? ''));
define('SECRET_KEY', (string)($__cfg['hemlig_nyckel'] ?? ''));
define('DATA_DIR', (string)($__cfg['datamapp'] ?? (__DIR__ . '/../../mat-data')));
define('LOGIN_DAYS', 365);
define('IS_CONFIGURED', FAMILY_PASSWORD !== '' && strlen(SECRET_KEY) >= 16);

unset($__cfgFile, $__cfg);
