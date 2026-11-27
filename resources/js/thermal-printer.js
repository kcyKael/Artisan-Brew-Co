const printerControllers = new WeakMap();

function prefersReducedMotion() {
    return typeof window !== 'undefined'
        && typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function buildFeedKeyframes(paperHeight) {
    const steps = Math.max(10, Math.min(22, Math.round(paperHeight / 34)));
    const frames = [];

    for (let i = 0; i <= steps; i++) {
        const t = i / steps;
        const pct = (1 - t) * 100;
        const microJitter = i > 0 && i < steps && i % 2 === 1 ? 0.35 : 0;
        frames.push({
            transform: `translate3d(0, ${(pct + microJitter).toFixed(2)}%, 0)`,
            offset: t,
        });
    }

    return frames;
}

export function cleanupThermalPrinter(stageEl) {
    if (!stageEl) return;
    const state = printerControllers.get(stageEl);
    if (!state) return;

    state.timers.forEach((id) => window.clearTimeout(id));
    state.timers.clear();

    if (state.animation) {
        try {
            state.animation.cancel();
        } catch {
        }
        state.animation = null;
    }

    state.runId += 1;
}

export function playThermalPrinter(stageEl, options = {}) {
    if (!stageEl) return;

    const paper = stageEl.querySelector('.receipt-paper');
    const statusEl = stageEl.querySelector('[data-printer-status]');
    if (!paper) return;

    cleanupThermalPrinter(stageEl);

    let state = printerControllers.get(stageEl);
    if (!state) {
        state = { timers: new Set(), animation: null, runId: 0 };
        printerControllers.set(stageEl, state);
    }

    const currentRun = ++state.runId;

    const setStatus = (mode, text) => {
        stageEl.dataset.state = mode;
        if (statusEl && statusEl.textContent !== text) {
            statusEl.textContent = text;
        }
    };

    const forceImmediate = Boolean(options.immediate)
        || prefersReducedMotion()
        || stageEl.dataset.animate === 'false'
        || paper.classList.contains('receipt-a4');

    if (forceImmediate) {
        paper.style.transform = paper.classList.contains('receipt-a4')
            ? 'translate3d(0, 0, 0)'
            : 'translate3d(0, -12px, 0)';
        setStatus('done', 'Receipt ready');
        return;
    }

    setStatus('starting', 'Preparing...');
    paper.style.transform = 'translate3d(0, 100%, 0)';

    const startTimer = window.setTimeout(() => {
        state.timers.delete(startTimer);
        if (state.runId !== currentRun) return;

        setStatus('printing', 'Printing receipt...');

        const paperHeight = Math.max(220, paper.scrollHeight || 340);
        const feedDuration = Math.min(3400, Math.max(950, Math.round((paperHeight / 240) * 1000)));

        if (typeof paper.animate !== 'function') {
            paper.style.transform = 'translate3d(0, 0, 0)';
            setStatus('done', 'Receipt ready');
            return;
        }

        const feedAnim = paper.animate(buildFeedKeyframes(paperHeight), {
            duration: feedDuration,
            easing: 'linear',
            fill: 'forwards',
        });
        state.animation = feedAnim;

        feedAnim.onfinish = () => {
            if (state.runId !== currentRun) return;

            setStatus('cutting', 'Cutting & releasing...');

            const cutTimer = window.setTimeout(() => {
                state.timers.delete(cutTimer);
                if (state.runId !== currentRun) return;

                const settleAnim = paper.animate(
                    [
                        { transform: 'translate3d(0, 0, 0)' },
                        { transform: 'translate3d(0, -19px, 0)' },
                        { transform: 'translate3d(0, -12px, 0)' },
                    ],
                    {
                        duration: 340,
                        easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                        fill: 'forwards',
                    }
                );
                state.animation = settleAnim;

                settleAnim.onfinish = () => {
                    if (state.runId !== currentRun) return;
                    paper.style.transform = 'translate3d(0, -12px, 0)';
                    setStatus('done', 'Receipt ready');
                    state.animation = null;
                };
            }, 440);

            state.timers.add(cutTimer);
        };
    }, 240);

    state.timers.add(startTimer);
}

export function bindThermalPrinterControls(stageEl) {
    if (!stageEl || stageEl.dataset.printerBound === 'true') return;
    stageEl.dataset.printerBound = 'true';

    const replayBtn = stageEl.querySelector('[data-replay-printer]');
    replayBtn?.addEventListener('click', () => {
        playThermalPrinter(stageEl, { immediate: false });
    });
}
