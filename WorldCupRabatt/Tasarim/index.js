/**
 * World Cup Rabatt – Index-Entwicklung (Startseite)
 * Nach Freigabe nach templates/js/worldcup-rabatt.js kopieren.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'wcr_played_v3';
    var AUTOOPEN_KEY = 'wcr_autoopen_seen_v1';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function init() {
        var root = document.getElementById('worldcup-rabatt-root');
        if (!root || root.getAttribute('data-wcr-ready') === '1') {
            return;
        }
        root.setAttribute('data-wcr-ready', '1');

        var cfg = {
            attempts: Math.max(1, Math.min(10, parseInt(root.getAttribute('data-wcr-attempts'), 10) || 3)),
            codes: {
                5:  (root.getAttribute('data-wcr-code5') || '').trim(),
                10: (root.getAttribute('data-wcr-code10') || '').trim(),
                15: (root.getAttribute('data-wcr-code15') || '').trim()
            },
            autoOpen: root.getAttribute('data-wcr-autoopen') === '1'
        };

        var el = {
            launch:       root.querySelector('.wcr-launch'),
            overlay:      root.querySelector('.wcr-overlay'),
            close:        root.querySelector('.wcr-close'),
            stage:        root.querySelector('.wcr-stage'),
            keeper:       root.querySelector('.wcr-keeper'),
            ball:         root.querySelector('.wcr-ball'),
            flash:        root.querySelector('.wcr-flash'),
            confetti:     root.querySelector('.wcr-confetti'),
            targets:      Array.prototype.slice.call(root.querySelectorAll('.wcr-target')),
            attemptsLeft: root.querySelector('.wcr-attempts-left'),
            bestWrap:     root.querySelector('.wcr-best-wrap'),
            best:         root.querySelector('.wcr-best'),
            result:       root.querySelector('.wcr-result'),
            resultDisc:   root.querySelector('.wcr-result-discount'),
            upgradeNote:  root.querySelector('.wcr-upgrade-note'),
            codeBtn:      root.querySelector('.wcr-code'),
            codeValue:    root.querySelector('.wcr-code-value'),
            lose:         root.querySelector('.wcr-lose')
        };

        var state = {
            attemptsLeft: cfg.attempts,
            bestDiscount: 0,
            animating: false,
            finished: false
        };

        function getPlayedState() {
            try {
                return JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
            } catch (e) {
                return null;
            }
        }

        function setPlayedState(payload) {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
            } catch (e) {
                // ignore
            }
        }

        function targetSide(target) {
            if (target.classList.contains('wcr-target-top-left') || target.classList.contains('wcr-target-left') || target.classList.contains('wcr-target-bottom-left')) {
                return 'left';
            }
            if (target.classList.contains('wcr-target-top-right') || target.classList.contains('wcr-target-right') || target.classList.contains('wcr-target-bottom-right')) {
                return 'right';
            }
            return 'center';
        }

        function sideToKeeperClass(side) {
            if (side === 'left') {
                return 'dive-left';
            }
            if (side === 'right') {
                return 'dive-right';
            }
            return 'dive-center';
        }

        function targetRow(target) {
            if (target.classList.contains('wcr-target-top-left') || target.classList.contains('wcr-target-top-right')) {
                return 'top';
            }
            if (target.classList.contains('wcr-target-bottom-left') || target.classList.contains('wcr-target-bottom-right')) {
                return 'bottom';
            }
            return 'mid';
        }

        function clearKeeperDive() {
            el.keeper.classList.remove('dive-left', 'dive-right', 'dive-center', 'dive-up', 'dive-down');
        }

        function applyKeeperDive(side, row) {
            clearKeeperDive();
            el.keeper.classList.add(sideToKeeperClass(side));
            if (row === 'top') {
                el.keeper.classList.add('dive-up');
            } else if (row === 'bottom') {
                el.keeper.classList.add('dive-down');
            }
        }

        function resetBall() {
            el.ball.style.transition = 'none';
            el.ball.style.left = '50%';
            el.ball.style.bottom = '4%';
            el.ball.style.transform = 'translateX(-50%)';
            void el.ball.offsetWidth;
            el.ball.style.transition = '';
        }

        function moveBallToTarget(target) {
            var stageRect = el.stage.getBoundingClientRect();
            var tRect = target.getBoundingClientRect();
            var cx = tRect.left + tRect.width / 2 - stageRect.left;
            var cy = tRect.top + tRect.height / 2 - stageRect.top;
            var leftPct = (cx / stageRect.width) * 100;
            var bottomPct = ((stageRect.height - cy) / stageRect.height) * 100;
            el.ball.style.left = leftPct + '%';
            el.ball.style.bottom = bottomPct + '%';
            el.ball.style.transform = 'translateX(-50%) scale(0.58)';
        }

        function pickShotTarget() {
            var available = el.targets.filter(function (t) {
                return parseInt(t.getAttribute('data-discount'), 10) > 0;
            });
            if (available.length === 0) {
                return null;
            }
            return available[Math.floor(Math.random() * available.length)];
        }

        /** 3. Schuss: Ziel nach Rabatt gewichtet (15 % am häufigsten). */
        function pickWeightedShotTarget() {
            var pool = [];
            var total = 0;
            el.targets.forEach(function (t) {
                var d = parseInt(t.getAttribute('data-discount'), 10) || 0;
                var w = d === 15 ? 5 : (d === 10 ? 2 : 1);
                pool.push({ target: t, weight: w });
                total += w;
            });
            if (pool.length === 0) {
                return null;
            }
            var r = Math.random() * total;
            var acc = 0;
            for (var i = 0; i < pool.length; i++) {
                acc += pool[i].weight;
                if (r <= acc) {
                    return pool[i].target;
                }
            }
            return pool[pool.length - 1].target;
        }

        function getShotNumber() {
            return cfg.attempts - state.attemptsLeft + 1;
        }

        function pickKeeperMissSide(shotSide) {
            if (shotSide === 'left') {
                return 'right';
            }
            if (shotSide === 'right') {
                return 'left';
            }
            return Math.random() < 0.5 ? 'left' : 'right';
        }

        function showFlash(text, color) {
            el.flash.textContent = text;
            el.flash.style.color = color || '#fff';
            el.flash.classList.remove('show');
            void el.flash.offsetWidth;
            el.flash.classList.add('show');
        }

        function updateStatus() {
            if (el.attemptsLeft) {
                el.attemptsLeft.textContent = String(state.attemptsLeft);
            }
            if (state.bestDiscount > 0) {
                el.bestWrap.hidden = false;
                el.best.innerHTML = state.bestDiscount + '&nbsp;%';
            }
        }

        function setGameEnabled(enabled) {
            if (el.ball) {
                el.ball.disabled = !enabled;
            }
        }

        var preshotAudio = null;
        var PRESHOT_VOL_NORMAL = 1;
        var PRESHOT_VOL_KICK   = 0.28;
        var PRESHOT_VOL_GOAL   = 0.1;

        function getPreshotAudio() {
            if (preshotAudio) {
                return preshotAudio;
            }
            var src = (root.getAttribute('data-wcr-preshot-sound') || '').trim();
            if (!src) {
                return null;
            }
            preshotAudio = new Audio(src);
            preshotAudio.preload = 'auto';
            preshotAudio.volume = PRESHOT_VOL_NORMAL;
            return preshotAudio;
        }

        function setPreshotVolume(vol) {
            var audio = getPreshotAudio();
            if (audio) {
                audio.volume = vol;
            }
        }

        function duckPreshotOnKick() {
            setPreshotVolume(PRESHOT_VOL_KICK);
        }

        function duckPreshotForGoalSong() {
            var audio = getPreshotAudio();
            if (!audio) {
                return;
            }
            audio.volume = PRESHOT_VOL_GOAL;
            audio.pause();
        }

        function restorePreshotVolume() {
            setPreshotVolume(PRESHOT_VOL_NORMAL);
        }

        function playPreshotSound(done) {
            var audio = getPreshotAudio();
            if (!audio) {
                if (typeof done === 'function') {
                    done();
                }
                return;
            }
            audio.onended = null;
            audio.onerror = null;
            var finished = false;
            function complete() {
                if (finished) {
                    return;
                }
                finished = true;
                audio.onended = null;
                audio.onerror = null;
                if (typeof done === 'function') {
                    done();
                }
            }
            if (typeof done === 'function') {
                audio.onended = complete;
                audio.onerror = complete;
            }
            audio.volume = PRESHOT_VOL_NORMAL;
            audio.pause();
            try {
                audio.currentTime = 0;
            } catch (e) {
                // ignore
            }
            var playPromise = audio.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(complete);
            }
        }

        function stopPreshotSound() {
            var audio = getPreshotAudio();
            if (!audio) {
                return;
            }
            audio.pause();
            audio.onended = null;
            audio.onerror = null;
        }

        var kickAudio = null;

        function getKickAudio() {
            if (kickAudio) {
                return kickAudio;
            }
            var src = (root.getAttribute('data-wcr-kick-sound') || '').trim();
            if (!src) {
                return null;
            }
            kickAudio = new Audio(src);
            kickAudio.preload = 'auto';
            return kickAudio;
        }

        function playKickSound() {
            var audio = getKickAudio();
            if (!audio) {
                return;
            }
            audio.pause();
            try {
                audio.currentTime = 0;
            } catch (e) {
                // ignore
            }
            var playPromise = audio.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(function () { /* ignore */ });
            }
        }

        var goalSongAudio = null;

        function getGoalSongAudio() {
            if (goalSongAudio) {
                return goalSongAudio;
            }
            var src = (root.getAttribute('data-wcr-goal-song') || '').trim();
            if (!src) {
                return null;
            }
            goalSongAudio = new Audio(src);
            goalSongAudio.preload = 'auto';
            goalSongAudio.volume = 1;
            return goalSongAudio;
        }

        function playGoalSong() {
            duckPreshotForGoalSong();
            var audio = getGoalSongAudio();
            if (!audio) {
                return;
            }
            audio.volume = 1;
            audio.pause();
            try {
                audio.currentTime = 0;
            } catch (e) {
                // ignore
            }
            var playPromise = audio.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(function () { /* ignore */ });
            }
        }

        function launchConfetti() {
            if (!el.confetti) {
                return;
            }
            el.confetti.innerHTML = '';
            var colors = ['#facc15', '#22c55e', '#38bdf8', '#fb7185', '#a78bfa', '#f97316'];
            for (var i = 0; i < 70; i += 1) {
                var piece = document.createElement('span');
                piece.className = 'wcr-confetti-piece';
                piece.style.left = (Math.random() * 100) + '%';
                piece.style.background = colors[i % colors.length];
                piece.style.animationDelay = (Math.random() * 0.25) + 's';
                piece.style.setProperty('--wcr-x', ((Math.random() * 180) - 90) + 'px');
                piece.style.transform = 'rotate(' + (Math.random() * 180) + 'deg)';
                el.confetti.appendChild(piece);
            }
            window.setTimeout(function () {
                el.confetti.innerHTML = '';
            }, 1900);
        }

        function continueAfterSave() {
            state.animating = false;
            clearKeeperDive();
            restorePreshotVolume();
            resetBall();
            setGameEnabled(true);
            el.result.hidden = true;
            el.lose.hidden = true;
        }

        function runShot() {
            var shotNumber = getShotNumber();
            var isFinalShot = shotNumber >= cfg.attempts;
            var target = isFinalShot ? pickWeightedShotTarget() : pickShotTarget();
            if (!target) {
                state.animating = false;
                setGameEnabled(true);
                return;
            }
            duckPreshotOnKick();
            playKickSound();
            el.targets.forEach(function (t) { t.classList.remove('is-aimed', 'is-hit'); });
            target.classList.add('is-aimed');

            var discount = parseInt(target.getAttribute('data-discount'), 10) || 0;
            var shotSide = targetSide(target);
            var saved;
            var keeperTargetSide;
            var keeperRow;
            if (!isFinalShot) {
                saved = true;
                keeperTargetSide = shotSide;
                keeperRow = targetRow(target);
            } else {
                saved = false;
                keeperTargetSide = pickKeeperMissSide(shotSide);
                keeperRow = 'mid';
            }

            el.ball.classList.add('is-shooting');
            applyKeeperDive(keeperTargetSide, keeperRow);
            moveBallToTarget(target);

            window.setTimeout(function () {
                if (saved) {
                    showFlash('GEHALTEN! 🧤', '#ffe08a');
                } else {
                    target.classList.add('is-hit');
                    state.bestDiscount = discount;
                    showFlash('GOOOL! ' + discount + '%', '#ffffff');
                    playGoalSong();
                    launchConfetti();
                }
                state.attemptsLeft -= 1;
                updateStatus();

                window.setTimeout(function () {
                    el.ball.classList.remove('is-shooting');
                    target.classList.remove('is-aimed');
                    if (saved && state.attemptsLeft > 0) {
                        continueAfterSave();
                    } else {
                        finishGame();
                    }
                }, 1050);
            }, 540);
        }

        function shoot() {
            if (state.animating || state.finished || state.attemptsLeft <= 0) {
                return;
            }
            state.animating = true;
            setGameEnabled(false);
            runShot();
        }

        function finishGame() {
            state.finished = true;
            state.animating = false;
            root.classList.add('wcr-finished');
            setGameEnabled(false);
            clearKeeperDive();

            if (state.bestDiscount > 0) {
                var wonDiscount = state.bestDiscount;
                var effectiveDiscount = wonDiscount === 5 ? 10 : wonDiscount;
                var code = cfg.codes[effectiveDiscount] || '';
                el.resultDisc.innerHTML = effectiveDiscount + '&nbsp;%';
                el.codeValue.textContent = code;
                el.codeBtn.setAttribute('data-wcr-code', code);
                if (el.upgradeNote) {
                    el.upgradeNote.hidden = wonDiscount !== 5;
                }
                el.result.hidden = false;
                el.lose.hidden = true;
                setPlayedState({ played: true, won: true, d: effectiveDiscount, c: code, originalD: wonDiscount, upgraded: wonDiscount === 5 });
                markLaunchWon(effectiveDiscount);
            } else {
                el.result.hidden = true;
                el.lose.hidden = false;
                setPlayedState({ played: true, won: false });
                markLaunchPlayed();
            }
        }

        function showPlayedState() {
            var played = getPlayedState();
            if (!played || !played.played) {
                return false;
            }
            state.finished = true;
            state.attemptsLeft = 0;
            root.classList.add('wcr-finished');
            setGameEnabled(false);
            updateStatus();

            if (played.won && played.c && played.d) {
                state.bestDiscount = played.d;
                el.resultDisc.innerHTML = played.d + '&nbsp;%';
                el.codeValue.textContent = played.c;
                el.codeBtn.setAttribute('data-wcr-code', played.c);
                if (el.upgradeNote) {
                    el.upgradeNote.hidden = played.upgraded !== true;
                }
                el.result.hidden = false;
                el.lose.hidden = true;
                markLaunchWon(played.d);
            } else {
                el.result.hidden = true;
                el.lose.hidden = false;
                if (el.upgradeNote) {
                    el.upgradeNote.hidden = true;
                }
                markLaunchPlayed();
            }
            return true;
        }

        function markLaunchWon(discount) {
            if (!el.launch) { return; }
            var label = el.launch.querySelector('.wcr-launch-label');
            if (label) {
                label.textContent = discount + '% Code anzeigen';
            }
        }

        function markLaunchPlayed() {
            if (!el.launch) { return; }
            var label = el.launch.querySelector('.wcr-launch-label');
            if (label) {
                label.textContent = 'Oyun oynandı';
            }
        }

        function openOverlay() {
            el.overlay.hidden = false;
            document.body.style.overflow = 'hidden';
            if (!state.finished) {
                playPreshotSound();
                window.setTimeout(resetBall, 30);
            }
        }

        function closeOverlay() {
            el.overlay.hidden = true;
            document.body.style.overflow = '';
            stopPreshotSound();
        }

        function copyCode(btn) {
            var code = (btn.getAttribute('data-wcr-code') || '').trim();
            if (!code) { return; }
            var done = function () {
                btn.classList.add('is-copied');
                var hint = btn.querySelector('.wcr-code-hint');
                if (hint) {
                    if (!hint.getAttribute('data-orig')) {
                        hint.setAttribute('data-orig', hint.textContent || '');
                    }
                    hint.textContent = 'Kopiert!';
                }
                window.setTimeout(function () {
                    btn.classList.remove('is-copied');
                    if (hint && hint.getAttribute('data-orig')) {
                        hint.textContent = hint.getAttribute('data-orig');
                    }
                }, 2200);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(done).catch(function () { fallbackCopy(code, done); });
            } else {
                fallbackCopy(code, done);
            }
        }

        function fallbackCopy(text, done) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try { if (document.execCommand('copy')) { done(); } } catch (e) { /* ignore */ }
            document.body.removeChild(ta);
        }

        if (el.launch) {
            el.launch.addEventListener('click', openOverlay);
        }
        if (el.close) {
            el.close.addEventListener('click', closeOverlay);
        }
        if (el.ball) {
            el.ball.addEventListener('click', shoot);
        }
        el.overlay.addEventListener('click', function (ev) {
            if (ev.target === el.overlay) {
                closeOverlay();
            }
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && !el.overlay.hidden) {
                closeOverlay();
            }
        });
        if (el.codeBtn) {
            el.codeBtn.addEventListener('click', function () { copyCode(el.codeBtn); });
        }

        if (!showPlayedState()) {
            updateStatus();
            resetBall();
            setGameEnabled(true);
        }

        if (cfg.autoOpen) {
            var seen = false;
            try { seen = sessionStorage.getItem(AUTOOPEN_KEY) === '1'; } catch (e) { seen = false; }
            if (!seen) {
                try { sessionStorage.setItem(AUTOOPEN_KEY, '1'); } catch (e) { /* ignore */ }
                window.setTimeout(openOverlay, 1200);
            }
        }
    }

    ready(init);
})();
