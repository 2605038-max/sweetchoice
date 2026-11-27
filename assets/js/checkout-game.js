/**
 * Sweet Choice - Checkout Mini-Game & Demo Rewarded Advertisement Controller
 *
 * Bridges the embedded existing 3D Sweet Run game (iframe) with server-side
 * attempt limits, Demo Rewarded Advertisement verification, and ¥200 discount tracking.
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('checkout-game-root');
    if (!root) return;

    const csrfToken = root.dataset.csrf || '';
    const i18n = JSON.parse(root.dataset.i18n || '{}');
    let state = JSON.parse(root.dataset.state || '{}');

    const iframe = document.getElementById('sweet-run-iframe');
    const stageSection = document.getElementById('minigame-stage-section');
    const stageFooter = document.getElementById('mg-stage-footer');
    const btnExitFullscreen = document.getElementById('btn-exit-fullscreen');
    const btnStart = document.getElementById('btn-start-attempt');
    const btnWatchAd = document.getElementById('btn-watch-ad');
    const btnContinue = document.getElementById('btn-continue-payment');
    const btnSkipTop = document.getElementById('btn-skip-to-payment');
    const feedbackBanner = document.getElementById('mg-feedback-banner');
    const stageFeedback = document.getElementById('mg-stage-feedback');
    const btnStageStart = document.getElementById('btn-stage-start');
    const btnStageWatchAd = document.getElementById('btn-stage-watch-ad');
    const btnStageContinue = document.getElementById('btn-stage-continue');
    const discountBox = document.getElementById('mg-discount-box');
    const discountValue = document.getElementById('mg-discount-value');
    const discountLabel = document.getElementById('mg-discount-label');
    const statAttempts = document.getElementById('mg-stat-attempts');
    const statAds = document.getElementById('mg-stat-ads');
    const statBest = document.getElementById('mg-stat-best');
    const historyEl = document.getElementById('mg-attempt-history');

    // Demo Ad modal elements
    const adModal = document.getElementById('demo-ad-modal');
    const adProgressBar = document.getElementById('demo-ad-progress-bar');
    const adCountdownEl = document.getElementById('demo-ad-countdown');
    const btnCancelAd = document.getElementById('btn-cancel-demo-ad');

    let activeAttemptToken = state.active_attempt ? state.active_attempt.attempt_token : null;
    let isBusy = false;
    let adTimerInterval = null;
    let activeAdTxnId = null;
    let gameIframeReady = false;
    let pendingStartAttemptNum = null;

    function revealGameStage() {
        if (stageSection) {
            stageSection.classList.add('is-open');
            stageSection.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }
        if (stageFooter) {
            stageFooter.style.display = 'none';
        }
        if (iframe && iframe.dataset.src && (!iframe.getAttribute('src') || iframe.getAttribute('src') === 'about:blank')) {
            gameIframeReady = false;
            iframe.src = iframe.dataset.src;
        }
    }

    function hideGameStage() {
        if (stageSection) {
            stageSection.classList.remove('is-open');
            stageSection.setAttribute('aria-hidden', 'true');
        }
        document.body.style.overflow = '';
    }

    function formatStr(template, val) {
        return (template || '').replace('%d', String(val)).replace('%s', String(val));
    }

    function showFeedback(type, message) {
        [feedbackBanner, stageFeedback].forEach((el) => {
            if (!el) return;
            el.className = 'mg-feedback-banner is-' + type;
            el.textContent = message;
            el.style.display = 'block';
        });
    }

    function sendToGame(type, data) {
        if (iframe && iframe.contentWindow) {
            iframe.contentWindow.postMessage(Object.assign({ type: type }, data || {}), window.location.origin);
        }
    }

    async function callApi(action, extraParams = {}) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('csrf_token', csrfToken);
        Object.keys(extraParams).forEach((k) => formData.append(k, extraParams[k]));

        const res = await fetch('api/checkout-game.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        return res.json();
    }

    function renderUI() {
        const maxAtt = state.max_attempts || 3;
        const maxAds = state.max_ads || 2;
        const used = state.attempts_used || 0;
        const adsGranted = state.ads_granted || 0;
        const best = state.best_score || 0;
        const disc = state.discount_amount || 0;

        if (statAttempts) statAttempts.textContent = `${used} / ${maxAtt}`;
        if (statAds) statAds.textContent = `${adsGranted} / ${maxAds}`;
        if (statBest) statBest.textContent = best.toLocaleString('en-US');

        if (discountBox && discountValue && discountLabel) {
            if (disc > 0) {
                discountBox.classList.add('is-earned');
                discountValue.textContent = `-¥${disc.toLocaleString('en-US')}`;
                discountLabel.textContent = i18n.discount_earned_badge || '¥200 Discount Earned!';
            } else {
                discountBox.classList.remove('is-earned');
                discountValue.textContent = '¥0';
                discountLabel.textContent = i18n.discount_not_earned || 'Not earned yet';
            }
        }

        // Render Attempt History
        if (historyEl) {
            const list = state.attempts || [];
            if (!list.length) {
                historyEl.innerHTML = `<p style="font-size:0.84rem; color:var(--color-text-secondary); margin:0;">${i18n.no_attempts_yet || ''}</p>`;
            } else {
                historyEl.innerHTML = list.map((a) => {
                    const typeLabel = a.attempt_type === 'free' ? i18n.attempt_free : i18n.attempt_ad;
                    const scoreStr = a.score !== null ? `${Number(a.score).toLocaleString('en-US')} pts` : '—';
                    const winBadge = a.is_winning ? `<span class="mg-win-pill">${i18n.won_tag}</span>` : '';
                    return `
                        <div class="mg-history-row ${a.is_winning ? 'is-win' : ''}">
                            <div>
                                <strong>#${a.attempt_number}</strong>
                                <span class="mg-history-type">${typeLabel}</span>
                            </div>
                            <div class="mg-history-right">
                                <span>${scoreStr}</span>
                                ${winBadge}
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }

        // Toggle primary action buttons based strictly on server state (both top invitation and post-round footer)
        const hasActive = !!state.active_attempt;
        const startBtns = [btnStart, btnStageStart].filter(Boolean);
        const adBtns = [btnWatchAd, btnStageWatchAd].filter(Boolean);
        const contBtns = [btnContinue, btnStageContinue].filter(Boolean);

        if (hasActive) {
            activeAttemptToken = state.active_attempt.attempt_token;
            if (stageFooter) stageFooter.style.display = 'none';
            startBtns.forEach((b) => {
                b.style.display = 'inline-flex';
                b.disabled = true;
                b.textContent = '🎮 ' + formatStr(i18n.round_in_progress, state.active_attempt.attempt_number);
            });
            adBtns.forEach((b) => { b.style.display = 'none'; });
            contBtns.forEach((b) => { b.style.display = 'none'; });
            return;
        }

        // When round is not active and fullscreen overlay is open, show the floating post-round retry/payment bar
        if (stageFooter && stageSection && stageSection.classList.contains('is-open')) {
            stageFooter.style.display = 'block';
        }

        activeAttemptToken = null;

        if (state.can_start_attempt) {
            const nextNum = used + 1;
            const label = '🎮 ' + (nextNum === 1 ? i18n.start_free : formatStr(i18n.start_unlocked, nextNum));
            startBtns.forEach((b) => {
                b.style.display = 'inline-flex';
                b.disabled = isBusy;
                b.textContent = label;
            });
        } else {
            startBtns.forEach((b) => { b.style.display = 'none'; });
        }

        if (state.can_watch_ad) {
            adBtns.forEach((b) => {
                b.style.display = 'inline-flex';
                b.disabled = isBusy;
                b.textContent = '🎬 ' + i18n.watch_ad_btn;
            });
        } else {
            adBtns.forEach((b) => { b.style.display = 'none'; });
        }

        // Show Continue to Payment button whenever customer has won OR finished all attempts OR played at least once
        if (state.has_won || used >= maxAtt || used > 0) {
            contBtns.forEach((b) => {
                b.style.display = 'inline-flex';
                b.disabled = isBusy;
            });
        } else {
            contBtns.forEach((b) => { b.style.display = 'none'; });
        }
    }

    async function handleStartAttempt() {
        if (isBusy) return;
        isBusy = true;
        renderUI();

        try {
            const res = await callApi('start_attempt');
            if (res.state) state = res.state;
            if (res.success && res.attempt_token) {
                activeAttemptToken = res.attempt_token;
                showFeedback('info', formatStr(i18n.round_in_progress, res.attempt_number));
                revealGameStage();
                if (gameIframeReady) {
                    sendToGame('sweetrun:authorize_and_start', { attempt_number: res.attempt_number });
                    if (iframe) iframe.focus();
                } else {
                    pendingStartAttemptNum = res.attempt_number;
                }
            } else {
                showFeedback('error', res.error || i18n.unexpected_err);
            }
        } catch (err) {
            showFeedback('error', i18n.unexpected_err);
        } finally {
            isBusy = false;
            renderUI();
        }
    }

    async function handleGameOver(finalScore) {
        if (!activeAttemptToken) return;
        const tokenToSubmit = activeAttemptToken;
        isBusy = true;

        try {
            const res = await callApi('finish_attempt', {
                attempt_token: tokenToSubmit,
                score: Math.floor(Number(finalScore) || 0)
            });
            if (res.state) state = res.state;
            activeAttemptToken = null;

            if (res.success) {
                if (res.is_winning) {
                    showFeedback('success', '🎉 ' + (res.message || ''));
                } else {
                    showFeedback('info', res.message || '');
                }
            } else {
                showFeedback('error', res.message || res.error || i18n.score_rejected);
            }
        } catch (err) {
            showFeedback('error', i18n.unexpected_err);
        } finally {
            isBusy = false;
            renderUI();
        }
    }

    function closeDemoAdModal() {
        if (adTimerInterval) {
            clearInterval(adTimerInterval);
            adTimerInterval = null;
        }
        activeAdTxnId = null;
        if (adModal) {
            adModal.classList.remove('is-open');
            adModal.setAttribute('aria-hidden', 'true');
        }
    }

    async function handleWatchDemoAd() {
        if (isBusy) return;
        isBusy = true;
        renderUI();

        try {
            const res = await callApi('ad_start');
            if (res.state) state = res.state;

            if (!res.success || !res.reward_txn_id) {
                showFeedback('error', res.error || i18n.unexpected_err);
                isBusy = false;
                renderUI();
                return;
            }

            activeAdTxnId = res.reward_txn_id;
            const totalSecs = Number(res.required_seconds) || 5;
            let remaining = totalSecs;

            // Open simulated advertisement overlay
            if (adModal) {
                adModal.classList.add('is-open');
                adModal.setAttribute('aria-hidden', 'false');
            }
            if (adProgressBar) adProgressBar.style.width = '0%';
            if (adCountdownEl) adCountdownEl.textContent = formatStr(i18n.ad_countdown_label, remaining);
            if (btnCancelAd) btnCancelAd.disabled = false;

            const startedMs = Date.now();
            // Wait slightly past 5.1s client-side so server TIMESTAMPDIFF(SECOND, started_at, NOW()) is guaranteed >= 5
            const targetMs = totalSecs * 1000 + 250;

            adTimerInterval = setInterval(async () => {
                const elapsedMs = Date.now() - startedMs;
                const pct = Math.min(100, Math.round((elapsedMs / targetMs) * 100));
                if (adProgressBar) adProgressBar.style.width = pct + '%';

                const secsLeft = Math.max(0, Math.ceil((targetMs - elapsedMs) / 1000));
                if (secsLeft > 0) {
                    if (adCountdownEl) adCountdownEl.textContent = formatStr(i18n.ad_countdown_label, secsLeft);
                    return;
                }

                // Countdown finished -> verify with server backend
                clearInterval(adTimerInterval);
                adTimerInterval = null;
                if (adCountdownEl) adCountdownEl.textContent = i18n.ad_unlocking;
                if (btnCancelAd) btnCancelAd.disabled = true;

                const txnToComplete = activeAdTxnId;
                try {
                    const comp = await callApi('ad_complete', { reward_txn_id: txnToComplete });
                    if (comp.state) state = comp.state;
                    closeDemoAdModal();
                    if (comp.success) {
                        showFeedback('success', '🎬 ' + (comp.message || i18n.ad_unlocked_success));
                    } else {
                        showFeedback('error', comp.error || i18n.unexpected_err);
                    }
                } catch (e) {
                    closeDemoAdModal();
                    showFeedback('error', i18n.unexpected_err);
                } finally {
                    isBusy = false;
                    renderUI();
                }
            }, 120);
        } catch (err) {
            closeDemoAdModal();
            showFeedback('error', i18n.unexpected_err);
            isBusy = false;
            renderUI();
        }
    }

    async function handleContinueToPayment() {
        if (isBusy) return;
        isBusy = true;
        try {
            const res = await callApi('continue_to_payment');
            window.location.href = (res && res.redirect) ? res.redirect : 'checkout.php';
        } catch (err) {
            window.location.href = 'checkout.php';
        }
    }

    // Listen to same-origin postMessage events from embedded Sweet Run iframe
    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin) return;
        const msg = event.data || {};
        if (msg.type === 'sweetrun:request_start') {
            if (state.can_start_attempt) {
                handleStartAttempt();
            } else if (state.can_watch_ad) {
                handleWatchDemoAd();
            }
        } else if (msg.type === 'sweetrun:game_over') {
            handleGameOver(msg.score);
        } else if (msg.type === 'sweetrun:ready') {
            gameIframeReady = true;
            const numToStart = pendingStartAttemptNum || (state.active_attempt ? state.active_attempt.attempt_number : null);
            if (numToStart) {
                pendingStartAttemptNum = null;
                sendToGame('sweetrun:authorize_and_start', { attempt_number: numToStart });
                if (iframe) iframe.focus();
            }
        }
    });

    if (btnStart) btnStart.addEventListener('click', handleStartAttempt);
    if (btnWatchAd) btnWatchAd.addEventListener('click', handleWatchDemoAd);
    if (btnContinue) btnContinue.addEventListener('click', handleContinueToPayment);
    if (btnStageStart) btnStageStart.addEventListener('click', handleStartAttempt);
    if (btnStageWatchAd) btnStageWatchAd.addEventListener('click', handleWatchDemoAd);
    if (btnStageContinue) btnStageContinue.addEventListener('click', handleContinueToPayment);
    if (btnSkipTop) btnSkipTop.addEventListener('click', handleContinueToPayment);
    if (btnExitFullscreen) btnExitFullscreen.addEventListener('click', hideGameStage);
    if (stageSection && stageSection.classList.contains('is-open')) {
        document.body.style.overflow = 'hidden';
    }
    if (btnCancelAd) {
        btnCancelAd.addEventListener('click', () => {
            closeDemoAdModal();
            isBusy = false;
            renderUI();
        });
    }

    // Initial render
    renderUI();
    if (state.has_won && state.discount_amount > 0) {
        showFeedback('success', '🎉 ' + (i18n.already_won_continue || i18n.discount_earned_badge));
    } else if ((state.attempts_used || 0) >= (state.max_attempts || 3)) {
        showFeedback('info', i18n.all_attempts_used || '');
    }
});
