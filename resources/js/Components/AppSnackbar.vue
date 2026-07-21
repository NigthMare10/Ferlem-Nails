<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
const open = ref(false); const flash = computed(() => usePage().props.flash as any);
watch(flash, value => { if (value?.success || value?.error) open.value = true; }, { deep: true, immediate: true });
</script>
<template><VSnackbar v-model="open" :color="flash?.error ? 'error' : 'success'" location="top end">{{ flash?.error || flash?.success }}</VSnackbar></template>
