<script setup lang="ts">
import { computed } from 'vue';
defineProps<{ label: string; disabled?: boolean }>();
const model = defineModel<string>({ required: true });
const rawHour = computed(() => Number(model.value?.slice(0, 2) ?? 0));
const hour = computed({
    get: () => rawHour.value % 12 || 12,
    set: (value: number) => write(value, minute.value, period.value),
});
const minute = computed({
    get: () => model.value?.slice(3, 5) || '00',
    set: (value: string) => write(hour.value, value, period.value),
});
const period = computed({
    get: () => (rawHour.value >= 12 ? 'PM' : 'AM'),
    set: (value: string) => write(hour.value, minute.value, value),
});
function write(h: number, m: string, p: string) {
    model.value = String((h % 12) + (p === 'PM' ? 12 : 0)).padStart(2, '0') + ':' + m;
}
const hours = Array.from({ length: 12 }, (_, i) => i + 1);
const minutes = Array.from({ length: 60 }, (_, i) => String(i).padStart(2, '0'));
</script>
<template>
    <fieldset class="am-pm-field" :disabled="disabled">
        <legend>{{ label }}</legend>
        <div>
            <VSelect
                v-model="hour"
                :items="hours"
                :label="`${label} · hora`"
                :disabled="disabled"
                hide-details
                density="compact"
            />
            <VSelect
                v-model="minute"
                :items="minutes"
                :label="`${label} · minutos`"
                :disabled="disabled"
                hide-details
                density="compact"
            />
            <VSelect
                v-model="period"
                :items="['AM', 'PM']"
                :label="`${label} · AM/PM`"
                :disabled="disabled"
                hide-details
                density="compact"
            />
        </div>
    </fieldset>
</template>
<style scoped>
.am-pm-field {
    border: 0;
    padding: 0;
    min-width: 0;
    margin: 0 0 20px;
}
.am-pm-field legend {
    font-size: 0.85rem;
    margin-bottom: 8px;
}
.am-pm-field > div {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}
</style>
