/* ==========================================================
   8-BITLJUD – skapas med webbläsarens inbyggda synt (Web Audio).
   Inga ljudfiler behövs.

   Använd så här i annan kod:
     Ljud.hopp()  Ljud.mynt()  Ljud.start()  Ljud.over()
     Ljud.klick() Ljud.levelup()

   Knappar med attributet data-ljud-knapp slår av och på ljudet.
   ========================================================== */
(function () {
  var VOLYM = 0.07;   // 0.0 = tyst, 0.2 = högt

  var ctx = null;
  var avstangt = false;
  try { avstangt = localStorage.getItem("leo-ljud") === "av"; } catch (e) {}

  function synt() {
    if (!ctx) {
      var A = window.AudioContext || window.webkitAudioContext;
      if (!A) return null;
      ctx = new A();
    }
    if (ctx.state === "suspended") ctx.resume();
    return ctx;
  }

  // En ton: frekvens (Hz), längd (s), vågform, när (s), glid till frekvens
  function ton(frekvens, langd, vagform, nar, glidTill) {
    if (avstangt) return;
    var c = synt();
    if (!c) return;
    var t = c.currentTime + (nar || 0);
    var osc = c.createOscillator();
    var gain = c.createGain();
    osc.type = vagform || "square";            // square = klassiskt 8-bitljud
    osc.frequency.setValueAtTime(frekvens, t);
    if (glidTill) osc.frequency.exponentialRampToValueAtTime(glidTill, t + langd);
    gain.gain.setValueAtTime(VOLYM, t);
    gain.gain.exponentialRampToValueAtTime(0.0001, t + langd);
    osc.connect(gain);
    gain.connect(c.destination);
    osc.start(t);
    osc.stop(t + langd + 0.02);
  }

  function melodi(toner, steg, vagform, langd) {
    toner.forEach(function (f, i) { ton(f, langd || steg * 1.2, vagform, i * steg); });
  }

  var Ljud = {
    hopp:    function () { ton(260, 0.16, "square", 0, 720); },
    mynt:    function () { ton(988, 0.07, "square"); ton(1319, 0.2, "square", 0.07); },
    klick:   function () { ton(660, 0.05, "square"); },
    start:   function () { melodi([523, 659, 784, 1047], 0.09); },
    levelup: function () { melodi([523, 659, 784, 1047, 784, 1047, 1319], 0.07); },
    over:    function () { melodi([392, 330, 262, 196], 0.16, "triangle", 0.22); },

    avstangt: function () { return avstangt; },
    vaxla: function () {
      avstangt = !avstangt;
      try { localStorage.setItem("leo-ljud", avstangt ? "av" : "pa"); } catch (e) {}
      Ljud.uppdatera();
      Ljud.klick();
    },
    uppdatera: function () {
      document.querySelectorAll("[data-ljud-knapp]").forEach(function (b) {
        b.textContent = avstangt ? "♪ LJUD AV" : "♪ LJUD PÅ";
        b.setAttribute("aria-pressed", avstangt ? "false" : "true");
      });
    }
  };

  // Alla ljudknappar, även de som skapas senare (som den i spelet)
  document.addEventListener("click", function (e) {
    var knapp = e.target.closest && e.target.closest("[data-ljud-knapp]");
    if (knapp) Ljud.vaxla();
  });

  window.Ljud = Ljud;
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", Ljud.uppdatera);
  else Ljud.uppdatera();
})();
