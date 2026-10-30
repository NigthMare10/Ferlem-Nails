<script setup lang="ts">
withDefaults(defineProps<{ variant?: 'flat' | 'elevated' }>(), { variant: 'flat' });
</script>

<template>
    <section class="sl-section-surface" :class="`sl-section-surface--${variant}`"><slot /></section>
</template>
