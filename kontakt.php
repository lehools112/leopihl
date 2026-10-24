<?php
/*
 * Tar emot kontaktformuläret på kontakt.html och skickar det som mejl.
 *
 * Mottagarens adress står INTE här (koden ligger öppet på GitHub).
 * Den står i kontakt-config.php, som ligger en nivå ovanför public_html på servern:
 *   /domains/leopihl.com/kontakt-config.php
 */

// Bara POST, annars tillbaka till formuläret.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kontakt.html', true, 303);
    exit;
}

function tillbaka($status) {
    header('Location: kontakt.html?status=' . $status, true, 303);
    exit;
}

// ---- Inställningar ----
$configFil = __DIR__ . '/../kontakt-config.php';
if (!is_file($configFil)) {
    tillbaka('server');
}
$config = require $configFil;
$till = $config['till'] ?? '';
$fran = $config['fran'] ?? 'noreply@leopihl.com';
if (!filter_var($till, FILTER_VALIDATE_EMAIL)) {
    tillbaka('server');
}

// ---- Skydd mot spamrobotar ----
// 1. Fällan: ett dolt fält som bara robotar fyller i.
if (!empty($_POST['webbplats'])) {
    tillbaka('skickat'); // låtsas att det gick bra, så roboten inte försöker igen
}
// 2. Tid: skickat mindre än 3 sekunder efter att sidan laddades = robot.
$t = (int)($_POST['t'] ?? 0);
if ($t > 0 && time() - $t < 3) {
    tillbaka('skickat');
}
// 3. Max 5 meddelanden per timme från samma adress.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'okand';
$logg = sys_get_temp_dir() . '/leopihl-kontakt-' . md5($ip);
$tider = is_file($logg) ? array_filter(explode("\n", (string)file_get_contents($logg))) : [];
$tider = array_filter($tider, fn($x) => (int)$x > time() - 3600);
if (count($tider) >= 5) {
    tillbaka('vanta');
}

// ---- Läs och kontrollera fälten ----
$namn = trim((string)($_POST['namn'] ?? ''));
$epost = trim((string)($_POST['epost'] ?? ''));
$meddelande = trim((string)($_POST['meddelande'] ?? ''));

// Inga radbrytningar i namn/e-post (skyddar mejlets rubriker).
$namn = str_replace(["\r", "\n"], ' ', $namn);
$epost = str_replace(["\r", "\n"], '', $epost);

if ($namn === '' || $meddelande === ''
    || mb_strlen($namn) > 80 || mb_strlen($meddelande) > 2000
    || !filter_var($epost, FILTER_VALIDATE_EMAIL)) {
    tillbaka('fel');
}

// ---- Skicka mejlet ----
$amne = 'Nytt meddelande från leopihl.com: ' . $namn;
$text = "Namn: $namn\n"
      . "E-post: $epost\n"
      . "Skickat: " . date('Y-m-d H:i') . "\n"
      . "----------------------------------------\n\n"
      . $meddelande . "\n";

$rubriker = [
    'From: Leos hemsida <' . $fran . '>',
    'Reply-To: ' . $epost,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];

$ok = mail(
    $till,
    mb_encode_mimeheader($amne, 'UTF-8'),
    $text,
    implode("\r\n", $rubriker),
    '-f' . $fran
);

if (!$ok) {
    tillbaka('server');
}

$tider[] = time();
@file_put_contents($logg, implode("\n", $tider));
tillbaka('skickat');
