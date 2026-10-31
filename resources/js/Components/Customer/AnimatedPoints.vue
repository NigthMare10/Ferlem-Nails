<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
const props = defineProps<{ value: number }>();
const shown = ref(props.value);
const format = (v: number) => new Intl.NumberFormat('en-US').format(v);
const label = computed(() => format(props.value));
let frame = 0;
let media: MediaQueryList;
function finish() {
    cancelAnimationFrame(frame);
    frame = 0;
    shown.value = props.value;
}
function animate(from: number) {
    finish();
    if (media?.matches || document.hidden || !Number.isFinite(props.value)) return;
    const target = props.value;
    const start = performance.now();
    const tick = (now: number) => {
        const progress = Math.min(1, (now - start) / 620);
        shown.value = Math.round(from + (target - from) * (1 - Math.pow(1 - progress, 3)));
        if (progress < 1) frame = requestAnimationFrame(tick);
        else frame = 0;
    };
    frame = requestAnimationFrame(tick);
}
function visibility() {
    if (document.hidden) finish();
}
onMounted(() => {
    media = matchMedia('(prefers-reduced-motion: reduce)');
    media.addEventListener('change', finish);
    document.addEventListener('visibilitychange', visibility);
    animate(0);
});
watch(
    () => props.value,
    (value, old) => {
        if (value !== old) animate(shown.value);
    },
);
onUnmounted(() => {
    cancelAnimationFrame(frame);
    media?.removeEventListener('change', finish);
    document.removeEventListener('visibilitychange', visibility);
});
</script>
<template>
    <span :aria-label="label"
        ><span aria-hidden="true">{{ format(shown) }}</span></span
    >
</template>
