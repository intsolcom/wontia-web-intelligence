/* WWI — Motor de instrumento: Guitarra eléctrica (Web Audio, sin dependencias).
   Contrato de motores: window.WWIInstruments[id] = { id,name,version,available,init(root),dispose() }.
   Audio perezoso (se crea al 1er gesto), 4 perfiles reales, 6 cuerdas, a11y teclado, dispose. */
(function () {
    if (!window.WWIInstruments) window.WWIInstruments = {};
    var NOTES = [82.41, 110.00, 146.83, 196.00, 246.94, 329.63]; // E2 A2 D3 G3 B3 E4
    var PROFILES = {
        clean:      { type: 'triangle', dist: 0.00, cut: 3400, q: 0.7, label: 'Clean' },
        overdrive:  { type: 'sawtooth', dist: 0.35, cut: 2600, q: 1.2, label: 'Overdrive' },
        distortion: { type: 'sawtooth', dist: 0.68, cut: 1800, q: 1.6, label: 'Distortion' },
        fuzz:       { type: 'square',   dist: 0.90, cut: 1200, q: 2.2, label: 'Fuzz' }
    };
    var ctx = null, master = null, live = [];

    function ensure() {
        if (ctx) return true;
        var AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return false;
        try { ctx = new AC(); } catch (e) { return false; }
        master = ctx.createGain(); master.gain.value = 0.55; master.connect(ctx.destination);
        return true;
    }
    function shaper(amount) {
        var n = 1024, ws = new Float32Array(n), k = amount * 120;
        for (var i = 0; i < n; i++) { var x = i * 2 / n - 1; ws[i] = (1 + k) * x / (1 + k * Math.abs(x)); }
        var w = ctx.createWaveShaper(); w.curve = ws; w.oversample = '2x'; return w;
    }
    function pluck(index, profile) {
        if (!ensure()) return false;
        if (ctx.state === 'suspended') ctx.resume();
        var pf = PROFILES[profile] || PROFILES.clean, f = NOTES[index] || NOTES[0], t = ctx.currentTime;
        var osc = ctx.createOscillator(); osc.type = pf.type; osc.frequency.value = f;
        var filt = ctx.createBiquadFilter(); filt.type = 'lowpass'; filt.frequency.value = pf.cut; filt.Q.value = pf.q;
        var g = ctx.createGain();
        g.gain.setValueAtTime(0.0001, t);
        g.gain.exponentialRampToValueAtTime(0.9, t + 0.008);
        g.gain.exponentialRampToValueAtTime(0.0001, t + 1.3);
        if (pf.dist > 0) { var s = shaper(pf.dist); osc.connect(s); s.connect(filt); } else { osc.connect(filt); }
        filt.connect(g); g.connect(master);
        osc.start(t); osc.stop(t + 1.4);
        live.push(osc);
        return true;
    }
    function dispose() { live.forEach(function (o) { try { o.stop(); } catch (e) { } }); live = []; }
    function track(ev, data) { try { if (window.dataLayer) window.dataLayer.push(Object.assign({ event: ev }, data || {})); } catch (e) { } }

    window.WWIInstruments.guitar = {
        id: 'guitar', name: 'Guitarra eléctrica', version: '1.0.0', available: true,
        init: function (root) {
            if (!root || root.__wwiGuitar) return; root.__wwiGuitar = 1;
            var profile = root.getAttribute('data-profile') || 'clean';
            var low = document.documentElement.getAttribute('data-cap') === 'low';
            if (low) { root.setAttribute('data-static', '1'); if (window.WWICap) WWICap.notice('Te estás perdiendo la mejor experiencia interactiva con este sitio.', 'Recomendado: un computador o un celular/tablet reciente'); }
            function hit(i, el) {
                if (pluck(i, profile) && el) { el.classList.remove('on'); void el.offsetWidth; el.classList.add('on'); setTimeout(function () { el.classList.remove('on'); }, 200); track('hero_instrument', { instrument: 'guitar', string: i, profile: profile }); }
            }
            root.querySelectorAll('[data-string]').forEach(function (b) {
                var i = parseInt(b.getAttribute('data-string'), 10);
                b.addEventListener('pointerdown', function (e) { e.preventDefault(); hit(i, b); });
                b.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); hit(i, b); } });
            });
            root.querySelectorAll('[data-profile]').forEach(function (pb) {
                pb.addEventListener('click', function () {
                    profile = pb.getAttribute('data-profile');
                    root.setAttribute('data-profile', profile);
                    root.querySelectorAll('[data-profile]').forEach(function (x) { x.classList.toggle('on', x === pb); });
                    if (ctx && ctx.state === 'suspended') ctx.resume();
                    track('hero_instrument_profile', { instrument: 'guitar', profile: profile });
                });
            });
            root.addEventListener('keydown', function (e) {
                var n = parseInt(e.key, 10);
                if (n >= 1 && n <= 6) { hit(n - 1, root.querySelector('[data-string="' + (n - 1) + '"]')); }
            });
        },
        dispose: dispose
    };
})();
