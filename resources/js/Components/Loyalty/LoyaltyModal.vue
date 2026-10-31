<script setup lang="ts">
import { nextTick, ref, watch, onUnmounted } from 'vue';
const props = defineProps<{ open: boolean; title: string; drawer?: boolean; busy?: boolean }>();
const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement>();
watch(
    () => props.open,
    async (open) => {
        await nextTick();
        if (open && !dialog.value?.open) dialog.value?.showModal();
        else if (!open) dialog.value?.close();
    },
    { immediate: true },
);
onUnmounted(() => dialog.value?.close());
</script>
<template>
    <Teleport to="body">
        <dialog
            ref="dialog"
            class="loyalty-v2 loyalty-modal"
            :class="{ 'loyalty-drawer': drawer }"
            :aria-label="title"
            @cancel.prevent="!busy && emit('close')"
            @click="$event.target === dialog && !busy && emit('close')"
        >
            <section class="modal-surface">
                <header>
                    <h2>{{ title }}</h2>
                    <button type="button" :disabled="busy" aria-label="Cerrar ventana" @click="emit('close')">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="m6 6 12 12M18 6 6 18" />
                        </svg>
                    </button>
                </header>
                <div class="modal-body"><slot /></div>
                <footer v-if="$slots.footer"><slot name="footer" /></footer>
            </section>
        </dialog>
    </Teleport>
</template>
