<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import CustomerLayout from '../../Layouts/CustomerLayout.vue';
import MemberHome from '../../Components/Customer/MemberHome.vue';
import MemberProfile from '../../Components/Customer/MemberProfile.vue';
import MemberRewards from '../../Components/Customer/MemberRewards.vue';
import MemberGifts from '../../Components/Customer/MemberGifts.vue';
import { useLoyaltyRefresh } from '../../composables/useLoyaltyRefresh';
const props = defineProps<{
    customer: any;
    balance: number;
    qualifying: number;
    visits: number;
    entries: any;
    rewards: any[];
    redemptions: any[];
    redemption_history?: any;
    gifts?: any;
    appointments: any[];
    levels: Array<{ name: string; points: number; benefits: string }>;
    favorites: any[];
    services: any[];
    section: string;
    has_avatar: boolean;
}>();
useLoyaltyRefresh(() => [
    ...props.redemptions,
    ...(props.gifts?.data ?? [])
        .filter((g: any) => g.status === 'available' && g.batch.expires_at)
        .map((g: any) => ({ status: 'reserved', expires_at: g.batch.expires_at })),
]);
const levels = computed(() => [...props.levels].sort((a, b) => a.points - b.points));
const level = computed(() => [...levels.value].reverse().find((l) => props.qualifying >= l.points) ?? levels.value[0]);
const nextLevel = computed(() => levels.value.find((l) => l.points > props.qualifying));
const progress = computed(() =>
    nextLevel.value && level.value
        ? Math.max(0, Math.min(100, (100 * (props.qualifying - level.value.points)) / (nextLevel.value.points - level.value.points)))
        : 100,
);
const nextReward = computed(() => props.rewards.find((r) => r.points_cost > props.balance) ?? props.rewards[0]);
const qrDialog = ref<HTMLDialogElement>();
const qrImage = ref('');
const qrCode = ref('');
const qrError = ref('');
const qrBusy = ref(false);
const remaining = ref(0);
const qrLifetime = ref(300);
const qrTime = computed(() => `${Math.floor(remaining.value / 60)}:${String(remaining.value % 60).padStart(2, '0')}`);
let timer: ReturnType<typeof setInterval> | undefined;
function closeQr() {
    qrDialog.value?.close();
    if (timer) clearInterval(timer);
    qrImage.value = '';
    qrCode.value = '';
    remaining.value = 0;
}
onUnmounted(closeQr);
watch(() => props.section, closeQr);
async function qr() {
    if (qrBusy.value) return;
    qrBusy.value = true;
    qrError.value = '';
    const requestedAt = Date.now();
    try {
        const response = await fetch('/clientes/qr', {
            method: 'POST',
            headers: {
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie
                        .split('; ')
                        .find((x) => x.startsWith('XSRF-TOKEN='))
                        ?.split('=')
                        .slice(1)
                        .join('=') ?? '',
                ),
                Accept: 'application/json',
            },
        });
        if (!response.ok) throw new Error();
        const data = await response.json();
        const QR = await import('qrcode');
        qrImage.value = await QR.toDataURL(data.token, { width: 560, margin: 4, errorCorrectionLevel: 'M', color: { dark: '#24122cff', light: '#ffffffff' } });
        qrCode.value = data.token;
        qrLifetime.value = data.expires_in;
        const expires = requestedAt + data.expires_in * 1000;
        remaining.value = Math.max(0, Math.ceil((expires - Date.now()) / 1000));
        if (timer) clearInterval(timer);
        timer = setInterval(() => {
            remaining.value = Math.max(0, Math.ceil((expires - Date.now()) / 1000));
            if (!remaining.value) {
                qrImage.value = '';
                qrCode.value = '';
                if (timer) clearInterval(timer);
            }
        }, 1000);
        qrDialog.value?.showModal();
    } catch {
        qrDialog.value?.close();
        qrError.value = 'No pudimos generar tu QR. Verifica tu correo y vuelve a intentarlo.';
    } finally {
        qrBusy.value = false;
    }
}
const date = (value: string) => new Intl.DateTimeFormat('es-HN', { dateStyle: 'medium', timeZone: 'America/Tegucigalpa' }).format(new Date(value));
</script>
<template>
    <Head :title="section === 'rewards' ? 'Recompensas' : section === 'profile' ? 'Mi perfil' : 'Mi espacio Lemus'" />
    <CustomerLayout>
        <div v-if="!customer.email_verified_at" class="feedback" role="status">
            Verifica tu correo para mostrar tu QR y reservar recompensas.
            <button type="button" class="verification-button" @click="router.post('/clientes/verificar-email')">Reenviar correo</button>
        </div>
        <p v-if="qrError" class="feedback feedback-error" role="alert">{{ qrError }}</p>
        <MemberGifts v-if="section === 'rewards'" :gifts="gifts" />
        <p v-if="section === 'home' && gifts?.data?.length" class="feedback">
            <Link href="/clientes/recompensas#gift-heading">Consulta tus regalos en Recompensas →</Link>
        </p>
        <MemberHome
            v-if="section === 'home'"
            :customer="customer"
            :balance="balance"
            :qualifying="qualifying"
            :visits="visits"
            :level="level"
            :next-level="nextLevel"
            :progress="progress"
            :reward="nextReward"
            :qr-busy="qrBusy"
            @qr="qr"
        />
        <MemberProfile v-else-if="section === 'profile'" :customer="customer" :has-avatar="has_avatar" :level="level" :qr-busy="qrBusy" @qr="qr" />
        <MemberRewards
            v-else-if="section === 'rewards'"
            :customer="customer"
            :balance="balance"
            :rewards="rewards"
            :redemptions="redemptions"
            :history-pagination="redemption_history"
        />
        <section v-else-if="section === 'progress'" class="progress-page">
            <Link href="/clientes">← Volver a inicio</Link>
            <h1>Mi progreso</h1>
            <p>{{ qualifying }} puntos calificables · {{ balance }} disponibles para canje</p>
            <article v-for="l in levels" :key="l.name" class="progress-level" :class="{ reached: qualifying >= l.points }">
                <div>
                    <strong>{{ l.name }}</strong
                    ><span>{{ l.points }} puntos calificables</span>
                </div>
                <p>{{ l.benefits }}</p>
            </article>
            <h2>Mis movimientos</h2>
            <article v-for="entry in entries.data" :key="entry.id" class="point-entry">
                <div>
                    <strong>{{ entry.note ?? entry.type }}</strong>
                    <p>{{ date(entry.created_at) }}</p>
                </div>
                <strong>{{ entry.points > 0 ? '+' : '' }}{{ entry.points }} pts</strong>
            </article>
            <p v-if="!entries.data.length">Tu historial comenzará con tu primera visita.</p>
            <nav v-if="entries.last_page > 1" aria-label="Páginas de movimientos" class="entry-pagination">
                <button
                    v-for="p in entries.last_page"
                    :key="p"
                    :aria-current="p === entries.current_page ? 'page' : undefined"
                    type="button"
                    @click="router.get('/clientes/movimientos', { page: p })"
                >
                    {{ p }}
                </button>
            </nav>
        </section>
        <section v-else class="progress-page"><Link href="/clientes">Volver a mi inicio →</Link></section>
        <dialog
            ref="qrDialog"
            class="member-qr"
            aria-labelledby="qr-heading"
            aria-describedby="qr-instructions"
            @cancel="closeQr"
            @close="closeQr"
            @click="$event.target === qrDialog && closeQr()"
        >
            <div class="qr-sheet">
                <header>
                    <span class="qr-pass-brand"><span class="material-symbols-outlined" aria-hidden="true">workspace_premium</span> STUDIO LEMUS PASS</span
                    ><button type="button" aria-label="Cerrar QR" @click="closeQr"><span class="material-symbols-outlined">close</span></button>
                </header>
                <p class="qr-invitation">Tu próxima experiencia empieza aquí</p>
                <h2 id="qr-heading">{{ customer.name }}</h2>
                <p class="qr-member-level">{{ level?.name }} · {{ balance }} pts disponibles</p>
                <div v-if="qrImage" class="qr-stage">
                    <span class="qr-orbit" aria-hidden="true" />
                    <div class="qr-image"><img :src="qrImage" alt="QR temporal de fidelidad" width="240" height="240" /></div>
                </div>
                <p v-else class="qr-expired" role="status">Tu QR ha vencido. Genera uno nuevo para identificarte.</p>
                <div class="qr-validity">
                    <p class="qr-caption">
                        <span class="material-symbols-outlined" aria-hidden="true">{{ remaining ? 'verified_user' : 'timer_off' }}</span
                        >{{ remaining ? `Válido durante ${qrTime}` : 'Código vencido' }}
                    </p>
                    <div class="qr-time-track" aria-hidden="true"><span :style="{ transform: `scaleX(${remaining / Math.max(1, qrLifetime)})` }" /></div>
                </div>
                <p id="qr-instructions" class="qr-note">Presenta este código al personal.<br />Es personal y temporal. No lo compartas.</p>
                <details v-if="qrCode">
                    <summary>Código alternativo</summary>
                    <code>{{ qrCode }}</code>
                </details>
                <button v-if="!remaining" class="qr-regenerate" type="button" :disabled="qrBusy" @click="qr">Generar nuevo QR</button>
            </div>
        </dialog>
    </CustomerLayout>
</template>
<style scoped>
.verification-button {
    display: block;
    color: #ffaedc;
    text-decoration: underline;
    margin-top: 8px;
}
.member-qr {
    max-width: 360px;
    width: calc(100% - 32px);
    margin: auto;
    color: #f5daf6;
    background: transparent;
    border: 0;
    padding: 0;
    max-height: calc(100dvh - 24px);
    overflow-y: auto;
    overscroll-behavior: contain;
    border-radius: 32px;
}
.member-qr::backdrop {
    background: #1d0e21cc;
    backdrop-filter: blur(16px);
}
.qr-sheet {
    padding: clamp(16px, 5vw, 28px);
    border-radius: 32px;
    border: 1px solid #f3f0f838;
    background: radial-gradient(ellipse at 0 0, #8a3b6e66, transparent 65%), linear-gradient(155deg, #411a4e, #24122c);
    box-shadow:
        inset 0 1px 2px #ffffff40,
        0 20px 48px -12px #120516cc;
    text-align: center;
    backdrop-filter: blur(36px);
}
.qr-sheet header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.qr-sheet header > span {
    font-size: 10px;
    letter-spacing: 0.1em;
    color: #ccc2db;
}
.qr-sheet header button {
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    border-radius: 50%;
    background: #402f4466;
    display: grid;
    place-items: center;
}
.qr-sheet header .material-symbols-outlined {
    font-size: 18px;
}
.qr-sheet h2 {
    font:
        500 24px/32px 'Syne',
        sans-serif;
    overflow-wrap: anywhere;
}
.qr-member-level {
    color: #ffaedc;
    font-size: 12px;
    margin-bottom: 20px;
}
.qr-image {
    position: relative;
    width: 256px;
    max-width: 100%;
    margin: auto;
    background: #fff;
    padding: 8px;
    border-radius: 20px;
}
.qr-image img {
    width: 100%;
    height: auto;
}
.qr-caption {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 12px;
}
.qr-note {
    font-size: 12px;
    line-height: 1.7;
    color: #d6c1ca;
    margin-top: 4px;
}
.qr-sheet details {
    margin-top: 14px;
    font-size: 11px;
}
.qr-sheet summary {
    min-height: 44px;
    padding: 14px 0;
    cursor: pointer;
    color: #ccc2db;
}
.qr-pass-brand {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.qr-pass-brand .material-symbols-outlined {
    font-size: 20px;
    color: #ffaedc;
}
.qr-invitation {
    color: #d6c1ca;
    font-size: 12px;
    margin-bottom: 8px;
}
.qr-stage {
    position: relative;
    padding: 10px;
    margin: auto;
    max-width: 280px;
    overflow: hidden;
    border-radius: 28px;
    background: #ffaedc26;
}
.qr-orbit {
    position: absolute;
    inset: -45%;
    background: conic-gradient(from 0deg, transparent 60%, #ffaedc, #ccc2db, transparent 85%);
    animation: lemus-qr-orbit 9s linear infinite;
    pointer-events: none;
}
.qr-validity {
    margin: 20px auto 12px;
    max-width: 240px;
}
.qr-caption .material-symbols-outlined {
    font-size: 18px;
    color: #ffaedc;
}
.qr-time-track {
    height: 3px;
    margin-top: 12px;
    border-radius: 999px;
    overflow: hidden;
    background: #ffaedc26;
}
.qr-time-track span {
    display: block;
    height: 100%;
    background: #ffaedc;
    transform-origin: left;
    transition: transform 1s linear;
}
@keyframes lemus-qr-orbit {
    to {
        transform: rotate(360deg);
    }
}
@media (max-height: 650px) {
    .qr-sheet header {
        margin-bottom: 8px;
    }
    .qr-stage {
        max-width: 220px;
    }
    .qr-member-level {
        margin-bottom: 12px;
    }
}
.qr-sheet code {
    display: block;
    overflow-wrap: anywhere;
    margin-top: 8px;
}
.qr-regenerate {
    margin-top: 16px;
    padding: 12px;
    width: 100%;
    border-radius: 999px;
    background: #8a3b6e;
}
.qr-expired {
    padding: 24px;
    font-size: 14px;
}
.progress-page {
    display: flex;
    flex-direction: column;
    gap: 18px;
}
.progress-page h1 {
    font:
        600 30px/36px 'Syne',
        sans-serif;
}
.progress-page h2 {
    font:
        500 24px/32px 'Syne',
        sans-serif;
}
.progress-page p {
    font-size: 13px;
    color: #d6c1ca;
}
.progress-page > a {
    color: #ffaedc;
    font-size: 12px;
}
.progress-level {
    padding: 20px;
    border: 1px solid #52434a;
    border-radius: 24px;
    background: #2d123673;
}
.progress-level.reached {
    border-color: #ffaedc66;
}
.progress-level > div {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
}
.progress-level span {
    font-size: 11px;
    color: #ccc2db;
}
.progress-level p {
    margin-top: 8px;
}
.point-entry {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    border-bottom: 1px solid #52434a66;
    padding-bottom: 16px;
    font-size: 13px;
}
.entry-pagination {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.entry-pagination button {
    padding: 10px 14px;
    border: 1px solid #52434a;
    border-radius: 8px;
}
.entry-pagination [aria-current] {
    background: #8a3b6e;
}
</style>
