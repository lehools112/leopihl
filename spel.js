/* ==========================================================
   PIXEL HOPP – minispelet som startar med "PRESS START"
   Hoppa över blocken och samla mynt.
   Styr: tryck på skärmen, klicka, mellanslag eller pil upp.
   ========================================================== */
(function () {
  // ---- Inställningar du kan leka med ----
  var GRAVITY = 1500;       // hur snabbt figuren faller
  var JUMP = 520;           // hur högt figuren hoppar
  var START_SPEED = 150;    // hastighet i början
  var MAX_SPEED = 380;      // högsta hastighet
  var COIN_POINTS = 5;      // poäng per mynt

  // Färger (samma som style.css)
  var C = {
    bg: "#12102a", ground: "#3a3570", green: "#37f2c4",
    pink: "#ff5ea8", yellow: "#ffd23f", text: "#f1efff", muted: "#a9a4d6"
  };

  var W = 320, H = 180, GROUND = 150;   // spelets storlek i "pixlar"

  // ---- Bygg spelrutan ----
  var overlay = document.createElement("div");
  overlay.className = "game-overlay";
  overlay.hidden = true;
  overlay.innerHTML =
    '<div class="game-box" role="dialog" aria-label="Pixel Hopp">' +
    '  <div class="game-top"><span class="pixel">PIXEL HOPP</span>' +
    '  <button class="game-close" type="button" aria-label="Stäng spelet">✕</button></div>' +
    '  <canvas width="' + W + '" height="' + H + '"></canvas>' +
    '  <p class="game-help">Tryck, klicka eller mellanslag för att hoppa</p>' +
    '</div>';
  document.body.appendChild(overlay);

  var canvas = overlay.querySelector("canvas");
  var ctx = canvas.getContext("2d");
  ctx.imageSmoothingEnabled = false;

  // ---- Rekord sparas i webbläsaren ----
  var best = 0;
  try { best = parseInt(localStorage.getItem("pixelhopp-best") || "0", 10) || 0; } catch (e) {}
  function saveBest() {
    try { localStorage.setItem("pixelhopp-best", String(best)); } catch (e) {}
  }

  // ---- Spelets tillstånd ----
  var state, player, blocks, coins, stars, speed, distance, coinScore, spawnIn, coinIn, last, raf;

  function reset() {
    state = "ready";
    player = { x: 40, y: GROUND - 16, w: 12, h: 16, vy: 0, onGround: true };
    blocks = [];
    coins = [];
    speed = START_SPEED;
    distance = 0;
    coinScore = 0;
    spawnIn = 1.2;
    coinIn = 0.8;
    stars = [];
    for (var i = 0; i < 30; i++) {
      stars.push({ x: Math.random() * W, y: Math.random() * (GROUND - 20), s: Math.random() < 0.3 ? 2 : 1 });
    }
  }

  function score() { return Math.floor(distance / 10) + coinScore; }

  // ---- Styrning ----
  function press() {
    if (state === "ready") { state = "play"; jump(); }
    else if (state === "play") { jump(); }
    else if (state === "over") { reset(); }
  }
  function jump() {
    if (player.onGround) { player.vy = -JUMP; player.onGround = false; }
  }

  canvas.addEventListener("pointerdown", function (e) { e.preventDefault(); press(); });
  document.addEventListener("keydown", function (e) {
    if (overlay.hidden) return;
    if (e.code === "Space" || e.code === "ArrowUp" || e.code === "KeyW") { e.preventDefault(); press(); }
    if (e.code === "Escape") close();
  });
  overlay.querySelector(".game-close").addEventListener("click", close);
  overlay.addEventListener("click", function (e) { if (e.target === overlay) close(); });

  // ---- Öppna / stäng ----
  function open() {
    reset();
    overlay.hidden = false;
    document.body.style.overflow = "hidden";
    last = performance.now();
    raf = requestAnimationFrame(loop);
    canvas.focus && canvas.focus();
  }
  function close() {
    overlay.hidden = true;
    document.body.style.overflow = "";
    cancelAnimationFrame(raf);
  }

  var startBtn = document.getElementById("press-start");
  if (startBtn) startBtn.addEventListener("click", open);

  // ---- Spelloopen ----
  function loop(now) {
    var dt = Math.min((now - last) / 1000, 0.05);
    last = now;
    update(dt);
    draw(now);
    raf = requestAnimationFrame(loop);
  }

  function hit(a, b) {
    return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;
  }

  function update(dt) {
    // Stjärnorna rör sig sakta även i startläget
    var starSpeed = state === "play" ? speed * 0.15 : 10;
    stars.forEach(function (s) { s.x -= starSpeed * dt; if (s.x < 0) s.x += W; });
    if (state !== "play") return;

    speed = Math.min(MAX_SPEED, speed + 6 * dt);
    distance += speed * dt;

    // Figuren
    player.vy += GRAVITY * dt;
    player.y += player.vy * dt;
    if (player.y >= GROUND - player.h) { player.y = GROUND - player.h; player.vy = 0; player.onGround = true; }

    // Nya block
    spawnIn -= dt;
    if (spawnIn <= 0) {
      var tall = Math.random() < 0.35;
      var h = tall ? 22 : 12 + Math.floor(Math.random() * 6);
      var w = 10 + Math.floor(Math.random() * 3) * 6;
      blocks.push({ x: W + 10, y: GROUND - h, w: w, h: h });
      spawnIn = 0.9 + Math.random() * 0.9 - Math.min(0.4, (speed - START_SPEED) / 600);
    }

    // Nya mynt
    coinIn -= dt;
    if (coinIn <= 0) {
      coins.push({ x: W + 10, y: GROUND - 30 - Math.random() * 40, w: 8, h: 8, t: 0 });
      coinIn = 0.7 + Math.random() * 1.2;
    }

    blocks.forEach(function (b) { b.x -= speed * dt; });
    coins.forEach(function (c) { c.x -= speed * dt; c.t += dt; });
    blocks = blocks.filter(function (b) { return b.x + b.w > -10; });

    // Krockar (lite förlåtande ruta runt figuren)
    var body = { x: player.x + 2, y: player.y + 2, w: player.w - 4, h: player.h - 3 };
    for (var i = 0; i < blocks.length; i++) {
      if (hit(body, blocks[i])) { gameOver(); return; }
    }
    coins = coins.filter(function (c) {
      if (hit(player, c)) { coinScore += COIN_POINTS; return false; }
      return c.x + c.w > -10;
    });
  }

  function gameOver() {
    state = "over";
    if (score() > best) { best = score(); saveBest(); }
  }

  // ---- Rita ----
  function draw(now) {
    ctx.fillStyle = C.bg;
    ctx.fillRect(0, 0, W, H);

    ctx.fillStyle = "#ffffff55";
    stars.forEach(function (s) { ctx.fillRect(Math.round(s.x), Math.round(s.y), s.s, s.s); });

    // Mark
    ctx.fillStyle = C.ground;
    ctx.fillRect(0, GROUND, W, H - GROUND);
    ctx.fillStyle = C.green;
    var off = Math.floor(distance) % 16;
    for (var x = -off; x < W; x += 16) ctx.fillRect(x, GROUND, 8, 2);

    // Block
    blocks.forEach(function (b) {
      ctx.fillStyle = C.pink;
      ctx.fillRect(Math.round(b.x), b.y, b.w, b.h);
      ctx.fillStyle = "#00000044";
      ctx.fillRect(Math.round(b.x) + b.w - 3, b.y, 3, b.h);
    });

    // Mynt (blinkar lite)
    coins.forEach(function (c) {
      var x = Math.round(c.x), y = Math.round(c.y + Math.sin(c.t * 6) * 2);
      var thin = Math.floor(c.t * 8) % 4 === 0;
      ctx.fillStyle = C.yellow;
      if (thin) ctx.fillRect(x + 3, y, 2, 8);
      else { ctx.fillRect(x + 1, y, 6, 8); ctx.fillRect(x, y + 1, 8, 6); }
    });

    drawPlayer(now);

    // Poäng
    ctx.fillStyle = C.text;
    ctx.font = "8px 'Press Start 2P', monospace";
    ctx.textAlign = "left";
    ctx.fillText("POÄNG " + String(score()).padStart(4, "0"), 8, 16);
    ctx.textAlign = "right";
    ctx.fillStyle = C.yellow;
    ctx.fillText("REKORD " + String(best).padStart(4, "0"), W - 8, 16);

    ctx.textAlign = "center";
    if (state === "ready") {
      ctx.fillStyle = C.green;
      ctx.fillText("TRYCK FÖR ATT STARTA", W / 2, 70);
    } else if (state === "over") {
      ctx.fillStyle = "#12102acc";
      ctx.fillRect(40, 48, W - 80, 58);
      ctx.fillStyle = C.pink;
      ctx.font = "12px 'Press Start 2P', monospace";
      ctx.fillText("GAME OVER", W / 2, 70);
      ctx.font = "8px 'Press Start 2P', monospace";
      ctx.fillStyle = C.text;
      ctx.fillText(score() >= best && score() > 0 ? "NYTT REKORD!" : "POÄNG " + score(), W / 2, 86);
      ctx.fillStyle = C.muted;
      ctx.fillText("TRYCK FÖR NYTT FÖRSÖK", W / 2, 100);
    }
  }

  // Figuren: en liten pixelrobot med visir
  function drawPlayer(now) {
    var x = Math.round(player.x), y = Math.round(player.y);
    ctx.fillStyle = C.green;
    ctx.fillRect(x + 1, y, 10, 11);              // kropp
    ctx.fillStyle = C.bg;
    ctx.fillRect(x + 3, y + 3, 7, 3);            // visir
    ctx.fillStyle = C.pink;
    ctx.fillRect(x + 7, y + 4, 2, 1);            // öga
    ctx.fillStyle = C.green;
    ctx.fillRect(x + 5, y - 3, 1, 3);            // antenn
    ctx.fillStyle = C.yellow;
    ctx.fillRect(x + 4, y - 4, 3, 2);
    // Ben som springer
    ctx.fillStyle = C.green;
    var step = state === "play" && player.onGround ? Math.floor(now / 90) % 2 : 0;
    if (!player.onGround) { ctx.fillRect(x + 2, y + 11, 3, 3); ctx.fillRect(x + 7, y + 11, 3, 3); }
    else if (step) { ctx.fillRect(x + 1, y + 11, 3, 5); ctx.fillRect(x + 8, y + 11, 3, 3); }
    else { ctx.fillRect(x + 2, y + 11, 3, 3); ctx.fillRect(x + 7, y + 11, 3, 5); }
  }
})();
