<?php
// Familjens mat – API för inköpslista och recept.
// Datan sparas i DATA_DIR/mat.php (utanför public_html, och med en exit-rad överst som extra skydd).

declare(strict_types=1);
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

const DATA_FILE = DATA_DIR . '/mat.php';
const SEED_FILE = __DIR__ . '/seed.json';
const GUARD     = "<?php exit; ?>\n";
const COOKIE    = 'mat_auth';

const CATEGORIES = ['Frukt & grönt', 'Kött & chark', 'Fisk & skaldjur', 'Kyl & ägg',
                    'Bröd', 'Skafferi', 'Fryst', 'Godis & snacks', 'Hushåll', 'Övrigt'];
const RECIPE_CATS = ['Varmt', 'Kallt', 'Sött', 'Bröd & bak', 'Övrigt'];

function out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function fail(string $msg, int $code = 400): void { out(['error' => $msg], $code); }

function token(): string { return hash_hmac('sha256', 'familjens-mat-v1', FAMILY_PASSWORD . '|' . SECRET_KEY); }
function isLoggedIn(): bool {
    return IS_CONFIGURED && isset($_COOKIE[COOKIE]) && is_string($_COOKIE[COOKIE]) && hash_equals(token(), $_COOKIE[COOKIE]);
}
function setAuthCookie(string $value, int $expires): void {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/';
    setcookie(COOKIE, $value, ['expires' => $expires, 'path' => $path, 'secure' => $https,
                               'httponly' => true, 'samesite' => 'Lax']);
}

function uid(): string { return bin2hex(random_bytes(6)); }
function clean($v, int $max): string {
    $s = is_string($v) ? $v : (is_numeric($v) ? (string)$v : '');
    $s = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '');
    return mb_substr($s, 0, $max);
}
function cleanLines($v, int $maxLines, int $maxLen): array {
    if (is_string($v)) $v = preg_split('/\r?\n/', $v);
    if (!is_array($v)) return [];
    $outLines = [];
    foreach ($v as $line) {
        $l = clean($line, $maxLen);
        if ($l !== '') $outLines[] = $l;
        if (count($outLines) >= $maxLines) break;
    }
    return $outLines;
}
function cat($v, array $allowed, string $default): string {
    return in_array($v, $allowed, true) ? $v : $default;
}

function emptyState(): array {
    if (is_file(SEED_FILE)) {
        $seed = json_decode((string)file_get_contents(SEED_FILE), true);
        if (is_array($seed)) {
            return ['rev' => 1, 'items' => $seed['items'] ?? [], 'recipes' => $seed['recipes'] ?? []];
        }
    }
    return ['rev' => 1, 'items' => [], 'recipes' => []];
}
function decode(string $raw): array {
    if (strncmp($raw, GUARD, strlen(GUARD)) === 0) $raw = substr($raw, strlen(GUARD));
    $d = json_decode($raw, true);
    return is_array($d) ? $d : emptyState();
}

/** Läser data under delat lås. */
function readState(): array {
    if (!is_file(DATA_FILE)) return emptyState();
    $fh = fopen(DATA_FILE, 'r');
    if (!$fh) fail('Kunde inte läsa datafilen.', 500);
    flock($fh, LOCK_SH);
    $raw = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return ($raw === false || $raw === '') ? emptyState() : decode($raw);
}

/** Ändrar data under exklusivt lås. $fn får state som referens. */
function mutate(callable $fn): array {
    if (!is_dir(DATA_DIR) && !@mkdir(DATA_DIR, 0755, true)) fail('Kunde inte skapa mappen mat-data på servern. Skapa den via FTP bredvid public_html.', 500);
    $fh = @fopen(DATA_FILE, 'c+');
    if (!$fh) fail('Mappen mat-data är skrivskyddad. Ge den rättigheten 755 via FTP.', 500);
    flock($fh, LOCK_EX);
    $raw = stream_get_contents($fh);
    $state = ($raw === false || $raw === '') ? emptyState() : decode($raw);
    $fn($state);
    $state['rev'] = (int)($state['rev'] ?? 0) + 1;
    $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, GUARD . $json);
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $state;
}

function findIndex(array $list, string $id): int {
    foreach ($list as $i => $x) if (($x['id'] ?? '') === $id) return $i;
    return -1;
}

// ---------------------------------------------------------------- routes
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $a = $_GET['a'] ?? 'state';
    if ($a === 'ping') out(['ok' => true, 'loggedIn' => IS_CONFIGURED && isLoggedIn(), 'configured' => IS_CONFIGURED]);
    if (!isLoggedIn()) fail('Inte inloggad', 401);
    $state = readState();
    if (isset($_GET['since']) && (int)$_GET['since'] === (int)$state['rev']) out(['unchanged' => true, 'rev' => $state['rev']]);
    out($state);
}

if ($method !== 'POST') fail('Fel metod', 405);
// Enkelt skydd mot förfalskade anrop från andra sidor.
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'mat-app') fail('Ogiltigt anrop', 403);

$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) fail('Ogiltig data');
$a = $body['a'] ?? '';

if ($a === 'login') {
    if (!IS_CONFIGURED) fail('Lägg upp mat-config.php på servern först.', 403);
    $pw = is_string($body['password'] ?? null) ? $body['password'] : '';
    if (!hash_equals(FAMILY_PASSWORD, $pw)) { sleep(1); fail('Fel lösenord', 401); }
    setAuthCookie(token(), time() + LOGIN_DAYS * 86400);
    out(['ok' => true]);
}
if ($a === 'logout') { setAuthCookie('', time() - 3600); out(['ok' => true]); }

if (!isLoggedIn()) fail('Inte inloggad', 401);

$state = mutate(function (array &$s) use ($a, $body) {
    $s['items'] = $s['items'] ?? [];
    $s['recipes'] = $s['recipes'] ?? [];
    switch ($a) {
        case 'addItems':
            $new = is_array($body['items'] ?? null) ? array_slice($body['items'], 0, 100) : [];
            foreach ($new as $it) {
                $text = clean($it['text'] ?? '', 200);
                if ($text === '') continue;
                $s['items'][] = ['id' => uid(), 'text' => $text,
                                 'cat' => cat($it['cat'] ?? '', CATEGORIES, 'Övrigt'),
                                 'done' => false, 'added' => time()];
            }
            if (count($s['items']) > 1000) fail('Listan är full (max 1000 varor).');
            break;
        case 'toggleItem':
            $i = findIndex($s['items'], (string)($body['id'] ?? ''));
            if ($i >= 0) $s['items'][$i]['done'] = (bool)($body['done'] ?? !$s['items'][$i]['done']);
            break;
        case 'editItem':
            $i = findIndex($s['items'], (string)($body['id'] ?? ''));
            if ($i >= 0) {
                $text = clean($body['text'] ?? '', 200);
                if ($text !== '') $s['items'][$i]['text'] = $text;
                $s['items'][$i]['cat'] = cat($body['cat'] ?? '', CATEGORIES, $s['items'][$i]['cat']);
            }
            break;
        case 'deleteItem':
            $i = findIndex($s['items'], (string)($body['id'] ?? ''));
            if ($i >= 0) array_splice($s['items'], $i, 1);
            break;
        case 'clearDone':
            $s['items'] = array_values(array_filter($s['items'], fn($x) => empty($x['done'])));
            break;
        case 'saveRecipe':
            $r = is_array($body['recipe'] ?? null) ? $body['recipe'] : [];
            $title = clean($r['title'] ?? '', 120);
            if ($title === '') fail('Receptet behöver ett namn.');
            $rec = ['title' => $title,
                    'cat' => cat($r['cat'] ?? '', RECIPE_CATS, 'Övrigt'),
                    'info' => clean($r['info'] ?? '', 120),
                    'source' => clean($r['source'] ?? '', 500),
                    'ingredients' => cleanLines($r['ingredients'] ?? [], 80, 200),
                    'steps' => cleanLines($r['steps'] ?? [], 60, 1000),
                    'notes' => clean($r['notes'] ?? '', 2000),
                    'updated' => time()];
            if ($rec['source'] !== '' && !preg_match('#^https?://#i', $rec['source'])) $rec['source'] = '';
            $i = findIndex($s['recipes'], (string)($r['id'] ?? ''));
            if ($i >= 0) $s['recipes'][$i] = array_merge($s['recipes'][$i], $rec);
            else {
                if (count($s['recipes']) >= 500) fail('Max 500 recept.');
                $s['recipes'][] = ['id' => uid()] + $rec;
            }
            break;
        case 'deleteRecipe':
            $i = findIndex($s['recipes'], (string)($body['id'] ?? ''));
            if ($i >= 0) array_splice($s['recipes'], $i, 1);
            break;
        default:
            fail('Okänd åtgärd');
    }
});
out($state);
