<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import CustomerLayout from '../../Layouts/CustomerLayout.vue';
import {redemptionLabels} from '../../utils/loyalty';
const props = defineProps<{
    customer: any;
    balance: number;
    qualifying: number;
    visits: number;
    entries: any;
    rewards: any[];
    redemptions: any[];
    appointments: any[];
    levels: Array<{ name: string; points: number; benefits: string }>;
    favorites: any[];
    services: any[];
    section: string;
    has_avatar: boolean;
}>();
const level = computed(() => [...props.levels].reverse().find((l) => props.qualifying >= l.points) ?? props.levels[0]);
const nextLevel = computed(() => props.levels.find((l) => l.points > props.qualifying));
const progress = computed(() =>
    nextLevel.value
        ? Math.min(100, (100 * (props.qualifying - level.value.points)) / (nextLevel.value.points - level.value.points))
        : 100,
);
const profile = useForm({
    name: props.customer.name,
    phone: props.customer.phone ?? '',
    birthday: props.customer.birthday ?? '',
});
const avatar = useForm({ photo: null as File | null });
const avatarUrl = ref('/clientes/foto?v=' + Date.now());
const canje = useForm({ operation_token: crypto.randomUUID() });
const qrImage = ref('');
const qrCode = ref('');
const qrError = ref('');
const qrBusy = ref(false);
const settings = ref(false);
const date = (value: string) =>
    new Intl.DateTimeFormat('es-HN', { dateStyle: 'medium', timeZone: 'America/Tegucigalpa' }).format(new Date(value));
async function qr() {
    qrBusy.value = true;
    qrError.value = '';
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
        if (!response.ok) throw new Error('Verifica tu correo e inténtalo de nuevo.');
        const data = await response.json();
        const QR = await import('qrcode');
        qrImage.value = await QR.toDataURL(data.token, { width: 280, margin: 2 });
        qrCode.value = data.token;
    } catch (error) {
        qrError.value = String(error);
    } finally {
        qrBusy.value = false;
    }
}
function reserve(reward: any) {
    if (canje.processing) return;
    canje.operation_token = crypto.randomUUID();
    canje.post(`/clientes/recompensas/${reward.id}/canjes`, { preserveScroll: true });
}
</script>
<template>
    <Head title="Mi perfil Lemus" /><CustomerLayout>
        <VAlert v-if="!customer.email_verified_at" type="info" class="mb-5"
            >Verifica tu correo para mostrar tu QR y canjear recompensas.<VBtn
                variant="text"
                @click="router.post('/clientes/verificar-email')"
                >Reenviar correo</VBtn
            ></VAlert
        >
        <template v-if="['home', 'profile'].includes(section)">
            <section class="member">
                <img
                    v-if="has_avatar"
                    class="member-avatar"
                    :src="avatarUrl"
                    alt="Mi foto de perfil"
                    style="object-fit: cover"
                />
                <div v-else class="member-avatar">{{ customer.name.slice(0, 1) }}</div>
                <div>
                    <h1>{{ customer.name }}</h1>
                    <p>Clienta desde {{ date(customer.created_at) }}</p>
                    <span class="member-badge">♥ Miembro Lemus</span>
                </div>
            </section>
            <Link href="/clientes/progreso" class="level-card"
                ><div class="level-top">
                    <VIcon icon="mdi-crown" size="43" color="#bd9c65" /><span
                        >TU NIVEL ACTUAL<strong>{{ level.name }}</strong></span
                    ><b>{{ balance }} puntos</b>
                </div>
                <div class="progress"><i :style="{ width: progress + '%' }" /></div>
                <p v-if="nextLevel">
                    {{ Math.max(0, nextLevel.points - qualifying) }} puntos más para llegar a
                    <strong>Nivel {{ nextLevel.name }} →</strong>
                </p>
                <p v-else>Disfruta de tu nivel más alto.</p></Link
            >
            <div class="member-stats">
                <div>
                    <VIcon icon="mdi-calendar-heart" /><strong>{{ visits }}</strong
                    ><span>Visitas</span>
                </div>
                <div>
                    <VIcon icon="mdi-star-outline" /><strong data-testid="loyalty-balance">{{ balance }}</strong
                    ><span>Puntos</span>
                </div>
                <div>
                    <VIcon icon="mdi-gift-outline" /><strong>{{
                        redemptions.filter((r) => r.status === 'confirmed').length
                    }}</strong
                    ><span>Recompensas</span>
                </div>
                <div>
                    <VIcon icon="mdi-heart-outline" /><strong>{{ favorites.length }}</strong
                    ><span>Favoritos</span>
                </div>
            </div>
            <VBtn block variant="tonal" :loading="qrBusy" @click="qr">Mostrar mi QR de fidelidad</VBtn>
            <p class="text-error">{{ qrError }}</p>
            <div class="section-heading">
                <h2>Mis recompensas</h2>
                <Link href="/clientes/recompensas">Ver todas →</Link>
            </div>
            <div class="reward-grid">
                <article v-for="reward in rewards.slice(0, 3)" :key="reward.id">
                    <VIcon icon="mdi-gift-outline" size="32" />
                    <h3>{{ reward.name }}</h3>
                    <p>{{ reward.points_cost }} puntos</p>
                    <VIcon v-if="balance < reward.points_cost" icon="mdi-lock-outline" />
                    <div class="progress">
                        <i :style="{ width: Math.min(100, (Math.max(0, balance) / reward.points_cost) * 100) + '%' }" />
                    </div>
                </article>
            </div>
            <div v-if="!rewards.length" class="soft-panel">Pronto encontrarás nuevas recompensas aquí.</div>
            <section class="beauty-banner">
                <VIcon icon="mdi-flower-outline" size="60" />
                <div>
                    <h2>Cada visita te acerca a más belleza ♥</h2>
                    <p>Gracias por ser parte de Studio Lemus.</p>
                </div>
            </section>
            <VBtn variant="text" @click="settings = !settings">Editar mi perfil</VBtn>
            <VForm
                v-if="settings"
                @submit.prevent="
                    avatar.post('/clientes/foto', {
                        forceFormData: true,
                        onSuccess: () => (avatarUrl = '/clientes/foto?v=' + Date.now()),
                    })
                "
                ><VFileInput
                    v-model="avatar.photo"
                    label="Foto de perfil"
                    accept="image/jpeg,image/png,image/webp"
                    :error-messages="avatar.errors.photo"
                /><VBtn type="submit" :disabled="!avatar.photo" :loading="avatar.processing"
                    >Actualizar foto</VBtn
                ></VForm
            >
            <VForm v-if="settings" @submit.prevent="profile.patch('/clientes/perfil')"
                ><VTextField v-model="profile.name" label="Nombre" :error-messages="profile.errors.name" /><VTextField
                    v-model="profile.phone"
                    label="Teléfono"
                    :error-messages="profile.errors.phone"
                /><VTextField
                    v-model="profile.birthday"
                    label="Fecha de nacimiento"
                    type="date"
                    :disabled="!!customer.birthday"
                    :error-messages="profile.errors.birthday"
                /><VBtn type="submit" :loading="profile.processing">Guardar</VBtn></VForm
            >
            <h2>Servicios favoritos</h2>
            <VChip
                v-for="service in services"
                :key="service.id"
                class="ma-1"
                :color="favorites.some((f) => f.id === service.id) ? 'primary' : undefined"
                @click="
                    favorites.some((f) => f.id === service.id)
                        ? router.delete(`/clientes/favoritos/${service.id}`, { preserveScroll: true })
                        : router.put(`/clientes/favoritos/${service.id}`, {}, { preserveScroll: true })
                "
                >{{ service.name }}</VChip
            >
            <VBtn block variant="text" class="mt-6" @click="router.post('/clientes/logout')">Cerrar sesión</VBtn>
        </template>
        <template v-else-if="section === 'progress'">
            <div class="section-heading">
                <h2>Mi progreso</h2>
                <b>{{ balance }} puntos</b>
            </div>
            <div class="level-track">
                <div v-for="l in levels" :key="l.name" :class="{ reached: qualifying >= l.points }">
                    <VIcon icon="mdi-crown-outline" /><strong>{{ l.name }}</strong
                    ><small>{{ l.points }}+ pts</small>
                </div>
            </div>
            <div class="soft-panel">
                {{
                    nextLevel
                        ? `Te faltan ${nextLevel.points - qualifying} puntos para ${nextLevel.name}`
                        : '¡Alcanzaste el nivel más alto!'
                }}
            </div>
            <h2>Historial de visitas y puntos</h2>
            <article v-for="entry in entries.data" :key="entry.id" class="history-row">
                <VIcon icon="mdi-flower-outline" />
                <div>
                    <small>{{ date(entry.created_at) }}</small>
                    <p>{{ entry.note ?? entry.type }}</p>
                </div>
                <b>{{ entry.points > 0 ? '+' : '' }}{{ entry.points }} pts</b>
            </article>
            <p v-if="!entries.data.length">Tu historial comenzará con tu primera visita.</p>
            <VPagination
                v-if="entries.last_page > 1"
                :model-value="entries.current_page"
                :length="entries.last_page"
                @update:model-value="router.get('/clientes/movimientos', { page: $event })"
            />
            <section class="soft-panel">
                <h2>Beneficios de tu nivel</h2>
                <p>{{ level.benefits }}</p>
            </section>
        </template>
        <template v-else-if="section === 'rewards'">
            <h1>Mis recompensas</h1>
            <p class="my-4">{{ balance }} puntos disponibles</p>
            <VAlert v-if="canje.hasErrors" type="error">{{ Object.values(canje.errors).join(' ') }}</VAlert>
            <article v-for="reward in rewards" :key="reward.id" class="soft-panel mb-4">
                <h2>{{ reward.name }}</h2>
                <p>{{ reward.description }}</p>
                <p>{{ reward.points_cost }} puntos</p>
                <VBtn
                    class="mt-3"
                    :disabled="balance < reward.points_cost || !customer.email_verified_at"
                    :loading="canje.processing"
                    @click="reserve(reward)"
                    >Reservar recompensa</VBtn
                >
            </article>
            <h2>Mis canjes</h2>
            <article v-for="r in redemptions" :key="r.id" class="history-row">
                <div>
                    <strong>{{ r.reward_snapshot.name }}</strong>
                    <p>Código {{ r.id }} · {{ redemptionLabels[r.status] }}</p>
                    <small>Vence: {{ date(r.expires_at) }}</small>
                </div>
            </article>
        </template>
        <template v-else-if="section === 'appointments'"
            ><h1>Mis citas</h1>
            <p class="my-4">Solo se muestran citas vinculadas de forma verificada a tu cuenta.</p>
            <article v-for="a in appointments" :key="a.id" class="soft-panel mb-4">
                <strong>{{ date(a.scheduled_start) }}</strong>
                <p>
                    {{
                        new Intl.DateTimeFormat('es-HN', {
                            timeStyle: 'short',
                            hour12: true,
                            timeZone: 'America/Tegucigalpa',
                        }).format(new Date(a.scheduled_start))
                    }}
                    · {{ a.status }}
                </p>
            </article>
            <p v-if="!appointments.length">Aún no tienes citas vinculadas.</p></template
        >
        <VDialog
            :model-value="!!qrImage"
            max-width="360"
            @update:model-value="
                qrImage = '';
                qrCode = '';
            "
            ><VCard class="pa-5 text-center"
                ><h2>Mi QR Lemus</h2>
                <img
                    :src="qrImage"
                    alt="QR temporal de fidelidad"
                    width="280"
                    height="280"
                    style="max-width: 100%; height: auto"
                />
                <p>Válido durante 5 minutos. No compartas este código.</p>
                <details>
                    <summary>Código alternativo</summary>
                    <code style="overflow-wrap: anywhere">{{ qrCode }}</code>
                </details>
                <VBtn
                    @click="
                        qrImage = '';
                        qrCode = '';
                    "
                    >Cerrar</VBtn
                ></VCard
            ></VDialog
        >
    </CustomerLayout>
</template>
<style scoped>
.member {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 26px;
}
.member-avatar {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: #e6d2c6;
    display: grid;
    place-items: center;
    font: 44px Georgia;
    flex-shrink: 0;
}
.member h1 {
    font-size: 28px;
}
.member p {
    font-size: 13px;
    color: #777;
    margin: 8px 0;
}
.member-badge {
    background: #ebd2cd;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
}
.level-card {
    display: block;
    color: inherit;
    text-decoration: none;
    padding: 24px;
    background: linear-gradient(110deg, #e9cec7, #efddd6);
    border-radius: 22px;
}
.level-top {
    display: flex;
    align-items: center;
    gap: 14px;
}
.level-top > span {
    font-size: 11px;
    letter-spacing: 1px;
    flex: 1;
}
.level-top strong {
    display: block;
    font: 30px Georgia;
    letter-spacing: 0;
}
.level-top b,
.section-heading b {
    font-size: 12px;
    border-radius: 20px;
    padding: 7px;
    background: #e7c4bf;
    white-space: nowrap;
}
.progress {
    height: 12px;
    background: #faf1ec;
    border-radius: 10px;
    margin: 18px 0 12px;
    overflow: hidden;
}
.progress i {
    display: block;
    height: 100%;
    background: #c88d8b;
    border-radius: 10px;
}
.level-card p {
    font-size: 14px;
    color: #6c625e;
}
.level-card p strong {
    display: block;
    font: 22px Georgia;
    color: #292520;
    margin-top: 6px;
}
.member-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    margin: 30px 0;
    gap: 8px;
}
.member-stats > div {
    display: flex;
    flex-direction: column;
    text-align: center;
    align-items: center;
    gap: 8px;
}
.member-stats > div + div {
    border-left: 1px solid #e7dcd5;
}
.member-stats .v-icon,
.reward-grid .v-icon {
    color: #b98077;
}
.member-stats strong {
    font: 25px Georgia;
}
.member-stats span {
    font-size: 12px;
    color: #756e66;
}
.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.section-heading a {
    color: #b67c76;
    font-size: 13px;
    text-decoration: none;
}
.reward-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}
.reward-grid article {
    text-align: center;
    background: #f0e8e1;
    border-radius: 16px;
    padding: 20px 10px;
}
.reward-grid h3 {
    font: 14px Arial;
    margin: 16px 0 10px;
    overflow-wrap: anywhere;
}
.reward-grid p {
    font-size: 12px;
}
.reward-grid .progress {
    height: 6px;
    margin-bottom: 0;
}
.beauty-banner {
    display: flex;
    align-items: center;
    gap: 20px;
    margin: 24px 0;
    background: #ead2ca;
    border-radius: 16px;
    padding: 24px;
}
.beauty-banner h2 {
    font-size: 24px;
    margin: 0 0 12px;
}
.soft-panel {
    padding: 22px;
    background: #f1e2dd;
    border-radius: 16px;
}
.soft-panel h2 {
    margin-top: 0;
}
.level-track {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin: 25px 0;
}
.level-track > div {
    display: flex;
    align-items: center;
    flex-direction: column;
    text-align: center;
    gap: 8px;
    font-size: 12px;
    color: #aaa;
    flex: 1;
}
.level-track .reached {
    color: #b77473;
}
.level-track .v-icon {
    border: 1px solid;
    padding: 10px;
    box-sizing: content-box;
    border-radius: 50%;
}
.history-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px 0;
    border-bottom: 1px solid #e7dcd5;
}
.history-row > div {
    flex: 1;
}
.history-row small {
    color: #777;
}
.history-row b {
    font-size: 13px;
    color: #b45b60;
    background: #f2dfda;
    padding: 8px;
    border-radius: 20px;
}
@media (max-width: 360px) {
    .member {
        gap: 12px;
    }
    .member-avatar {
        width: 65px;
        height: 65px;
    }
    .member h1 {
        font-size: 24px;
    }
    .level-card {
        padding: 17px;
    }
    .level-top {
        gap: 8px;
    }
    .level-top strong {
        font-size: 25px;
    }
    .reward-grid {
        grid-template-columns: 1fr;
    }
    .beauty-banner {
        padding: 18px;
    }
    .beauty-banner > .v-icon {
        display: none;
    }
}
</style>
