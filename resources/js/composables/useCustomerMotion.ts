import { nextTick, onMounted, onUnmounted, ref, watch, type Ref, type WatchSource } from 'vue';

/** Finite entrances plus visibility/control for CSS light loops. Content is visible without motion. */
export function useCustomerMotion(root: Ref<HTMLElement | undefined>, route: WatchSource<unknown>) {
    const running = new Set<Animation>();
    const seen = new WeakSet<Element>();
    let observer: IntersectionObserver | undefined;
    let loopObserver: IntersectionObserver | undefined;
    const loopSeen = new WeakSet<Element>();
    const paused = ref(false);
    let mutations: MutationObserver | undefined;
    let media: MediaQueryList;
    let disposed = false;
    function play(element: Element, frames: Keyframe[], duration: number, delay = 0) {
        if (!element.animate || media?.matches || document.hidden || paused.value) return;
        const animation = element.animate(frames, { duration, delay, easing: 'cubic-bezier(.16,1,.3,1)', fill: 'none' });
        running.add(animation);
        animation.finished.then(
            () => running.delete(animation),
            () => running.delete(animation),
        );
    }
    function reveal(element: Element, index = 0) {
        if (seen.has(element)) return;
        seen.add(element);
        if (element.getAttribute('data-motion-kind') === 'unlock') {
            play(
                element,
                [
                    { clipPath: 'inset(0 100% 0 0 round 999px)', opacity: 0.4 },
                    { clipPath: 'inset(0 0 0 0 round 999px)', opacity: 1 },
                ],
                380,
            );
            return;
        }
        play(
            element,
            [
                { opacity: 0.45, transform: 'translateY(16px) scale(.985)' },
                { opacity: 1, transform: 'none' },
            ],
            520,
            Math.min(index * 45, 135),
        );
        const progress = element.querySelector('[data-motion-progress]');
        if (progress)
            play(
                progress,
                [
                    { transform: 'scaleX(0)', transformOrigin: 'left' },
                    { transform: 'scaleX(1)', transformOrigin: 'left' },
                ],
                780,
            );
        const sheen = element.querySelector('[data-motion-sheen]');
        if (sheen && !root.value?.classList.contains('customer-world'))
            play(
                sheen,
                [
                    { transform: 'translateX(-120%) rotate(-18deg)', opacity: 0 },
                    { opacity: 0.6, offset: 0.45 },
                    { transform: 'translateX(360%) rotate(-18deg)', opacity: 0 },
                ],
                1000,
            );
    }
    function scan() {
        if (disposed || !root.value) return;
        root.value.querySelectorAll('[data-motion-sheen]').forEach((element) => {
            if (loopSeen.has(element)) return;
            loopSeen.add(element);
            loopObserver?.observe(element.parentElement!);
        });
        root.value.querySelectorAll('[data-motion-reveal]').forEach((element) => {
            if (seen.has(element)) return;
            if (media.matches) {
                seen.add(element);
                return;
            }
            observer?.observe(element);
        });
    }
    function press(event: PointerEvent) {
        const button = (event.target as Element).closest('button:not(:disabled), a');
        if (button && root.value?.contains(button))
            play(button, [{ transform: 'scale(1)' }, { transform: 'scale(.965)', offset: 0.35 }, { transform: 'scale(1)' }], 220);
    }
    function stop() {
        running.forEach((a) => a.cancel());
        running.clear();
    }
    function preference() {
        if (media.matches) stop();
        scan();
    }
    function visibility() {
        root.value?.toggleAttribute('data-motion-paused', document.hidden);
        if (document.hidden) stop();
    }
    function toggleMotion() {
        paused.value = !paused.value;
        root.value?.toggleAttribute('data-motion-user-paused', paused.value);
        if (paused.value) stop();
        try {
            localStorage.setItem('lemus-motion-paused', String(paused.value));
        } catch {
            /* Storage can be unavailable. */
        }
    }
    onMounted(() => {
        media = matchMedia('(prefers-reduced-motion: reduce)');
        try {
            paused.value = localStorage.getItem('lemus-motion-paused') === 'true';
        } catch {
            /* Use default. */
        }
        root.value?.toggleAttribute('data-motion-user-paused', paused.value);
        visibility();
        loopObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => entry.target.toggleAttribute('data-loop-visible', entry.isIntersecting));
        });
        observer = new IntersectionObserver(
            (entries) => {
                entries
                    .filter((e) => e.isIntersecting)
                    .forEach((entry, i) => {
                        reveal(entry.target, i);
                        observer?.unobserve(entry.target);
                    });
            },
            { threshold: 0.08 },
        );
        mutations = new MutationObserver(scan);
        if (root.value) {
            mutations.observe(root.value, { childList: true, subtree: true });
            root.value.addEventListener('pointerdown', press, { passive: true });
        }
        media.addEventListener('change', preference);
        document.addEventListener('visibilitychange', visibility);
        scan();
    });
    watch(route, async () => {
        stop();
        await nextTick();
        scan();
    });
    onUnmounted(() => {
        disposed = true;
        stop();
        observer?.disconnect();
        loopObserver?.disconnect();
        mutations?.disconnect();
        root.value?.removeEventListener('pointerdown', press);
        media?.removeEventListener('change', preference);
        document.removeEventListener('visibilitychange', visibility);
    });
    return { play, paused, toggleMotion };
}
