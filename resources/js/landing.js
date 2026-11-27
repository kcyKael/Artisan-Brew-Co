import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { createCupScene } from './landing-cup-scene';

gsap.registerPlugin(ScrollTrigger);

const initLanding = () => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const finePointer = window.matchMedia('(pointer: fine)').matches;

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

    const stage = document.getElementById('hero-cup-stage');
    const canvasHost = document.getElementById('hero-cup-canvas');
    const staticFallback = document.getElementById('hero-cup-fallback');
    let cupScene = null;

    if (stage && canvasHost) {
        cupScene = createCupScene(canvasHost, {
            logoUrl: stage.dataset.logoUrl || '',
            reducedMotion,
        });

        if (cupScene) {
            canvasHost.classList.remove('opacity-0');
            canvasHost.classList.add('opacity-100');
            staticFallback?.classList.add('opacity-0', 'pointer-events-none');
        }
    }

    const heroSection = document.getElementById('hero');
    if (cupScene && heroSection && finePointer && !reducedMotion) {
        heroSection.addEventListener(
            'pointermove',
            (event) => {
                const rect = heroSection.getBoundingClientRect();
                const nx = ((event.clientX - rect.left) / Math.max(rect.width, 1)) * 2 - 1;
                const ny = ((event.clientY - rect.top) / Math.max(rect.height, 1)) * 2 - 1;
                cupScene.setPointer(nx, ny);
            },
            { passive: true }
        );
        heroSection.addEventListener(
            'pointerleave',
            () => {
                cupScene.setPointer(0, 0);
            },
            { passive: true }
        );
    }

    const heroReveals = document.querySelectorAll('[data-hero-reveal]');
    if (!reducedMotion) {
        if (heroReveals.length > 0) {
            gsap.fromTo(
                heroReveals,
                { y: 24, opacity: 0 },
                {
                    y: 0,
                    opacity: 1,
                    duration: 0.95,
                    stagger: 0.11,
                    ease: 'power3.out',
                    clearProps: 'transform,opacity',
                }
            );
        }

        if (cupScene) {
            const entranceState = { value: 0 };
            gsap.to(entranceState, {
                value: 1,
                duration: 1.25,
                ease: 'power2.out',
                onUpdate: () => cupScene.setEntranceProgress(entranceState.value),
            });

            if (heroSection) {
                ScrollTrigger.create({
                    trigger: heroSection,
                    start: 'top top',
                    end: 'bottom top',
                    scrub: 1.1,
                    onUpdate: (self) => {
                        cupScene.setScrollProgress(self.progress);
                    },
                });
            }
        }

        document.querySelectorAll('[data-reveal]').forEach((item) => {
            gsap.fromTo(
                item,
                { y: 28, opacity: 0 },
                {
                    y: 0,
                    opacity: 1,
                    duration: 0.85,
                    ease: 'power3.out',
                    clearProps: 'transform,opacity',
                    scrollTrigger: {
                        trigger: item,
                        start: 'top 86%',
                        once: true,
                    },
                }
            );
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
    } else if (cupScene) {
        cupScene.setEntranceProgress(1);
    }

    window.addEventListener('pagehide', () => {
        window.removeEventListener('scroll', syncNavScroll);
        ScrollTrigger.getAll().forEach((trigger) => trigger.kill());
        cupScene?.dispose();
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLanding);
} else {
    initLanding();
}
