<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ConfigurationLayout from '../../Layouts/ConfigurationLayout.vue';
import RewardsPanel from '../../Components/Loyalty/RewardsPanel.vue';
import HistoryPanel from '../../Components/Loyalty/HistoryPanel.vue';
import LevelsPanel from '../../Components/Loyalty/LevelsPanel.vue';
import GiftsPanel from '../../Components/Loyalty/GiftsPanel.vue';
import '../../../css/stitch/base.css';
import '../../../css/loyalty-v2.css';
const props = defineProps<{
    rewards: any[];
    levels: any[];
    redemptions: any;
    services: any[];
    settings_version: number;
    gift_batches: any;
    filters: any;
    status: string;
}>();
const page = usePage();
const tab = computed(() => props.filters?.tab ?? 'rewards');
const tabs = [
    { value: 'rewards', label: 'Premios' },
    { value: 'history', label: 'Historial' },
    { value: 'levels', label: 'Niveles' },
    { value: 'gifts', label: 'Regalos' },
];
</script>
<template>
    <Head title="Fidelidad y recompensas" />
    <ConfigurationLayout
        ><div class="loyalty-v2">
            <h1>Fidelidad y recompensas</h1>
            <p class="muted">Servicios que se convierten en premios y atenciones para tus clientas.</p>
            <nav class="tabs" aria-label="Secciones de fidelidad">
                <Link
                    v-for="t in tabs"
                    :key="t.value"
                    :href="`/configuration/loyalty/rewards?tab=${t.value}`"
                    :aria-current="tab === t.value ? 'page' : undefined"
                    >{{ t.label }}</Link
                >
            </nav>
            <p v-if="(page.props.flash as any)?.success" class="notice" role="status">{{ (page.props.flash as any).success }}</p>
            <RewardsPanel v-if="tab === 'rewards'" :rewards="rewards" :services="services" />
            <HistoryPanel v-else-if="tab === 'history'" :redemptions="redemptions" :filters="filters" />
            <LevelsPanel v-else-if="tab === 'levels'" :levels="levels" :version="settings_version" />
            <GiftsPanel v-else-if="tab === 'gifts'" :batches="gift_batches" :services="services" :filters="filters" />
            <p v-else class="notice">Sección no encontrada. <Link href="/configuration/loyalty/rewards">Volver a premios</Link></p>
        </div></ConfigurationLayout
    >
</template>
