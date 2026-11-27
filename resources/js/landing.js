import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const INTRO_STORAGE_KEY = 'abc_intro_seen';

const readIntroSeen = () => {
    try {
        return window.sessionStorage.getItem(INTRO_STORAGE_KEY) === '1';
    } catch {
        return false;
    }
};

const writeIntroSeen = () => {
    try {
        window.sessionStorage.setItem(INTRO_STORAGE_KEY, '1');
    } catch {
        return;
    }
};

const preloadImages = (urls, timeoutMs) => {
    const unique = [...new Set(urls.filter(Boolean))];
    if (unique.length === 0) {
        return Promise.resolve(true);
    }

    const loadAll = Promise.all(
        unique.map(
            (src) =>
                new Promise((resolve, reject) => {
                    const img = new Image();
                    img.onload = () => resolve(true);
                    img.onerror = () => reject(new Error('load-failed'));
                    img.src = src;
                    if (img.complete && img.naturalWidth > 0) {
                        resolve(true);
                    }
                })
        )
    )
        .then(() => true)
        .catch(() => false);

    const timer = new Promise((resolve) => {
        window.setTimeout(() => resolve(false), timeoutMs);
    });

    return Promise.race([loadAll, timer]);
};

const setShellInert = (locked) => {
    document.querySelectorAll('[data-page-shell]').forEach((el) => {
        if ('inert' in el) {
            el.inert = locked;
        }
        if (locked) {
            el.setAttribute('aria-hidden', 'true');
        } else {
            el.removeAttribute('aria-hidden');
        }
    });
    document.body.classList.toggle('overflow-hidden', locked);
};

const initLanding = () => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const desktopPointer = window.matchMedia('(pointer: fine) and (hover: hover)').matches;

    const nav = document.getElementById('landing-nav');
    const syncNavScroll = () => {
        if (!nav) {
            return;
        }
        nav.classList.toggle('is-scrolled', window.scrollY > 28);
    };
    syncNavScroll();
    window.addEventListener('scroll', syncNavScroll, { passive: true });

    const mobileDrawer = document.getElementById('landing-mobile-drawer');
    const openMenuBtn = document.getElementById('landing-menu-open');
    const closeMenuBtns = document.querySelectorAll('[data-landing-menu-close]');

    const setMobileMenu = (open) => {
        if (!mobileDrawer) {
            return;
        }
        mobileDrawer.classList.toggle('hidden', !open);
        document.body.classList.toggle('overflow-hidden', open);
        openMenuBtn?.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            const firstTarget = mobileDrawer.querySelector('a, button');
            firstTarget?.focus();
        } else {
            openMenuBtn?.focus();
        }
    };

    openMenuBtn?.addEventListener('click', () => setMobileMenu(true));
    closeMenuBtns.forEach((el) => el.addEventListener('click', () => setMobileMenu(false)));
    mobileDrawer?.querySelectorAll('a[href^="#"]').forEach((link) => {
        link.addEventListener('click', () => setMobileMenu(false));
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobileDrawer && !mobileDrawer.classList.contains('hidden')) {
            setMobileMenu(false);
        }
    });

    const heroReveals = document.querySelectorAll('[data-hero-reveal]');
    const cupStage = document.getElementById('hero-cup-stage');
    const cupEntrance = document.getElementById('hero-cup-entrance');
    const cupFloat = document.getElementById('hero-cup-float');
    const cupParallax = document.getElementById('hero-cup-parallax');
    const cupShadow = document.getElementById('hero-cup-shadow');
    const cupContactShadow = document.getElementById('hero-cup-contact-shadow');
    const cupSteam = document.getElementById('hero-cup-steam');
    const steamWisps = document.querySelectorAll('[data-hero-steam-wisp]');

    const heroIdleTweens = [];

    const startHeroIdleMotion = () => {
        if (reducedMotion || heroIdleTweens.length > 0) {
            return;
        }

        document.body.classList.add('studio-bg-active');

        if (cupFloat) {
            heroIdleTweens.push(
                gsap.to(cupFloat, {
                    y: -10,
                    duration: 3.4,
                    repeat: -1,
                    yoyo: true,
                    ease: 'sine.inOut',
                })
            );
        }

        if (cupShadow) {
            heroIdleTweens.push(
                gsap.to(cupShadow, {
                    scaleX: 0.91,
                    scaleY: 0.90,
                    opacity: 0.48,
                    duration: 3.4,
                    repeat: -1,
                    yoyo: true,
                    ease: 'sine.inOut',
                })
            );
        }

        if (cupContactShadow) {
            heroIdleTweens.push(
                gsap.to(cupContactShadow, {
                    scaleX: 0.86,
                    scaleY: 0.84,
                    opacity: 0.42,
                    duration: 3.4,
                    repeat: -1,
                    yoyo: true,
                    ease: 'sine.inOut',
                })
            );
        }

        steamWisps.forEach((wisp, index) => {
            const sway = index % 2 === 0 ? 7 : -7;
            const duration = 5.6 + index * 0.7;
            const delay = index * 1.15;
            gsap.set(wisp, { y: 12, x: -sway * 0.4, opacity: 0, scaleY: 0.88 });
            const tl = gsap.timeline({ repeat: -1, delay });
            tl.to(wisp, {
                y: -26,
                x: sway,
                opacity: index < 2 ? 0.42 : 0.28,
                scaleY: 1.06,
                duration: duration * 0.45,
                ease: 'sine.out',
            }).to(wisp, {
                y: -58,
                x: -sway * 0.5,
                opacity: 0,
                scaleY: 1.16,
                duration: duration * 0.55,
                ease: 'sine.in',
            });
            heroIdleTweens.push(tl);
        });
    };

    const runHeroEntrance = (skipCupLift = false) => {
        if (reducedMotion) {
            gsap.set(heroReveals, { clearProps: 'all' });
            if (cupEntrance) {
                gsap.set(cupEntrance, { clearProps: 'all' });
            }
            return;
        }

        if (heroReveals.length > 0) {
            gsap.fromTo(
                heroReveals,
                { y: 20, opacity: 0 },
                {
                    y: 0,
                    opacity: 1,
                    duration: 0.82,
                    stagger: 0.1,
                    ease: 'power3.out',
                    clearProps: 'transform,opacity',
                }
            );
        }

        if (cupEntrance && !skipCupLift) {
            gsap.fromTo(
                cupEntrance,
                { y: 18, opacity: 0 },
                {
                    y: 0,
                    opacity: 1,
                    duration: 0.95,
                    ease: 'power2.out',
                    clearProps: 'transform,opacity',
                    onComplete: startHeroIdleMotion,
                }
            );
        } else {
            if (cupEntrance) {
                gsap.set(cupEntrance, { opacity: 1, y: 0, clearProps: 'transform,opacity' });
            }
            startHeroIdleMotion();
        }
    };

    if (cupStage && cupParallax && desktopPointer && !reducedMotion) {
        const cupX = gsap.quickTo(cupParallax, 'x', { duration: 0.7, ease: 'power3.out' });
        const cupY = gsap.quickTo(cupParallax, 'y', { duration: 0.7, ease: 'power3.out' });
        const steamX = cupSteam ? gsap.quickTo(cupSteam, 'x', { duration: 0.85, ease: 'power3.out' }) : null;
        const steamY = cupSteam ? gsap.quickTo(cupSteam, 'y', { duration: 0.85, ease: 'power3.out' }) : null;
        const shadowX = cupShadow ? gsap.quickTo(cupShadow, 'x', { duration: 0.75, ease: 'power3.out' }) : null;
        const contactShadowX = cupContactShadow ? gsap.quickTo(cupContactShadow, 'x', { duration: 0.72, ease: 'power3.out' }) : null;

        cupStage.addEventListener(
            'pointermove',
            (event) => {
                if (event.pointerType && event.pointerType !== 'mouse') {
                    return;
                }
                const rect = cupStage.getBoundingClientRect();
                const nx = ((event.clientX - rect.left) / Math.max(rect.width, 1)) * 2 - 1;
                const ny = ((event.clientY - rect.top) / Math.max(rect.height, 1)) * 2 - 1;
                cupX(nx * 9);
                cupY(ny * 6);
                steamX?.(nx * 12);
                steamY?.(ny * 4);
                shadowX?.(-nx * 5);
                contactShadowX?.(-nx * 3);
            },
            { passive: true }
        );

        cupStage.addEventListener(
            'pointerleave',
            () => {
                cupX(0);
                cupY(0);
                steamX?.(0);
                steamY?.(0);
                shadowX?.(0);
                contactShadowX?.(0);
            },
            { passive: true }
        );
    }

    if (!reducedMotion) {
        document.querySelectorAll('[data-reveal]').forEach((item) => {
            const tween = gsap.fromTo(
                item,
                { y: 26, opacity: 0 },
                {
                    y: 0,
                    opacity: 1,
                    duration: 0.82,
                    ease: 'power3.out',
                    clearProps: 'transform,opacity',
                    scrollTrigger: {
                        trigger: item,
                        start: 'top 92%',
                        once: true,
                    },
                }
            );
            if (item.getBoundingClientRect().top <= window.innerHeight * 0.95) {
                tween.progress(1);
            }
        });

        document.querySelectorAll('[data-parallax-img]').forEach((media) => {
            gsap.fromTo(
                media,
                { yPercent: -5 },
                {
                    yPercent: 5,
                    ease: 'none',
                    scrollTrigger: {
                        trigger: media.parentElement || media,
                        start: 'top bottom',
                        end: 'bottom top',
                        scrub: true,
                    },
                }
            );
        });
    }

    const introOverlay = document.getElementById('abc-intro-overlay');
    const skipBtn = document.getElementById('abc-intro-skip');
    const replayRequested = new URLSearchParams(window.location.search).get('replay-intro') === '1';

    let introTimeline = null;
    let introFinished = false;

    const finishIntro = (immediate = false) => {
        if (introFinished) {
            return;
        }
        introFinished = true;
        writeIntroSeen();

        if (introTimeline) {
            introTimeline.kill();
            introTimeline = null;
        }

        setShellInert(false);
        if (introOverlay) {
            introOverlay.classList.add('hidden');
            gsap.set(introOverlay, { clearProps: 'all' });
        }

        if (immediate) {
            gsap.set(heroReveals, { opacity: 1, y: 0, clearProps: 'transform,opacity' });
            if (cupEntrance) {
                gsap.set(cupEntrance, { opacity: 1, y: 0, clearProps: 'transform,opacity' });
            }
            startHeroIdleMotion();
        } else {
            runHeroEntrance(true);
        }
        ScrollTrigger.refresh();
    };

    const onIntroKeyDown = (event) => {
        if (event.key === 'Escape' && introOverlay && !introOverlay.classList.contains('hidden')) {
            finishIntro(true);
        }
    };

    skipBtn?.addEventListener('click', () => finishIntro(true));
    document.addEventListener('keydown', onIntroKeyDown);

    const hasAnchorHash = window.location.hash.length > 1;
    const shouldPlayIntro = Boolean(introOverlay) && !reducedMotion && !hasAnchorHash && (!readIntroSeen() || replayRequested);

    if (shouldPlayIntro) {
        const assetList = [
            introOverlay.dataset.cupSrc || '',
            introOverlay.dataset.logoSrc || '',
            ...(introOverlay.dataset.beanSrcs || '').split('|'),
        ];

        preloadImages(assetList, 900).then((ready) => {
            if (!ready || introFinished) {
                writeIntroSeen();
                runHeroEntrance(false);
                return;
            }

            const beans = introOverlay.querySelectorAll('[data-intro-bean]');
            const introCupWrap = document.getElementById('intro-cup-wrap');
            const introCupVisual = document.getElementById('intro-cup-visual');
            const introBrandMark = document.getElementById('intro-brand-mark');
            const introSteamPaths = introOverlay.querySelectorAll('[data-intro-steam]');
            const heroCupImg = document.getElementById('hero-cup-img');

            introOverlay.classList.remove('hidden');
            setShellInert(true);
            gsap.set(heroReveals, { opacity: 0, y: 20 });
            if (cupEntrance) {
                gsap.set(cupEntrance, { opacity: 0 });
            }
            skipBtn?.focus();

            introTimeline = gsap.timeline({
                onComplete: () => finishIntro(false),
            });

            beans.forEach((bean, idx) => {
                const depth = bean.dataset.depth || 'mid';
                const fallDuration = depth === 'near' ? 1.15 : depth === 'mid' ? 1.32 : 1.48;
                const startAt = idx * 0.095;
                const startRot = idx % 2 === 0 ? -18 : 22;
                const endRot = startRot + (idx % 2 === 0 ? 52 : -46);
                const driftX = (idx % 2 === 0 ? 1 : -1) * (14 + idx * 4);

                gsap.set(bean, { y: '-12vh', x: 0, rotation: startRot, opacity: 0 });

                introTimeline.to(
                    bean,
                    {
                        opacity: depth === 'far' ? 0.55 : depth === 'mid' ? 0.82 : 1,
                        duration: 0.22,
                        ease: 'power1.out',
                    },
                    startAt
                );

                introTimeline.to(
                    bean,
                    {
                        y: '126vh',
                        x: driftX,
                        rotation: endRot,
                        duration: fallDuration,
                        ease: 'power1.in',
                    },
                    startAt
                );

                introTimeline.to(
                    bean,
                    {
                        opacity: 0,
                        duration: 0.26,
                        ease: 'power1.in',
                    },
                    startAt + fallDuration - 0.28
                );
            });

            if (introCupWrap) {
                introTimeline.fromTo(
                    introCupWrap,
                    { opacity: 0, y: 28, scale: 0.94 },
                    { opacity: 1, y: 0, scale: 1, duration: 0.95, ease: 'power3.out' },
                    1.05
                );
            }

            introSteamPaths.forEach((path, idx) => {
                gsap.set(path, { y: 18, opacity: 0 });
                introTimeline.to(
                    path,
                    {
                        y: -24,
                        opacity: 0.42,
                        duration: 1.15,
                        ease: 'sine.out',
                    },
                    1.35 + idx * 0.12
                );
            });

            if (introBrandMark) {
                introTimeline.fromTo(
                    introBrandMark,
                    { opacity: 0, y: 12 },
                    { opacity: 1, y: 0, duration: 0.65, ease: 'power2.out' },
                    1.75
                );
            }

            introTimeline.add(() => {
                if (introBrandMark) {
                    gsap.to(introBrandMark, { opacity: 0, y: -8, duration: 0.35, ease: 'power2.in' });
                }
                if (introCupWrap && introCupVisual && heroCupImg && window.innerWidth >= 1024) {
                    const fromRect = introCupVisual.getBoundingClientRect();
                    const toRect = heroCupImg.getBoundingClientRect();
                    if (fromRect.width > 0 && toRect.width > 0) {
                        const dx = toRect.left + toRect.width / 2 - (fromRect.left + fromRect.width / 2);
                        const dy = toRect.top + toRect.height / 2 - (fromRect.top + fromRect.height / 2);
                        const ratio = Math.max(0.75, Math.min(1.25, toRect.width / fromRect.width));
                        gsap.to(introCupWrap, {
                            x: dx,
                            y: dy,
                            scale: ratio,
                            duration: 0.72,
                            ease: 'power3.inOut',
                        });
                    }
                }
            }, 2.85);

            if (cupEntrance) {
                introTimeline.to(cupEntrance, { opacity: 1, duration: 0.45, ease: 'power2.out' }, 3.22);
            }

            introTimeline.to(introOverlay, { opacity: 0, duration: 0.48, ease: 'power2.out' }, 3.32);
        });
    } else {
        runHeroEntrance(false);
    }

    window.addEventListener('pagehide', () => {
        window.removeEventListener('scroll', syncNavScroll);
        document.removeEventListener('keydown', onIntroKeyDown);
        heroIdleTweens.forEach((tween) => tween.kill());
        introTimeline?.kill();
        ScrollTrigger.getAll().forEach((trigger) => trigger.kill());
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLanding);
} else {
    initLanding();
}
