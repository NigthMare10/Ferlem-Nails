<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import '../../css/stitch/base.css';
import '../../css/stitch/home.css';
import '../../css/stitch/rewards.css';
import '../../css/stitch/profile.css';
import '../../css/stitch/motion.css';
import { useCustomerMotion } from '../composables/useCustomerMotion';
const page = usePage();
const motionRoot = ref<HTMLElement>();
const { paused, toggleMotion } = useCustomerMotion(motionRoot, () => page.url);
const activity = ref(false);
const section = computed(() => (page.props.section === 'rewards' ? 'rewards' : page.props.section === 'profile' ? 'profile' : 'home'));
const customer = computed(() => page.props.customer as { name: string });
const links = [
    { url: '/clientes', label: 'Inicio', icon: 'arrow_back_ios_new', section: 'home' },
    { url: '/clientes/recompensas', label: 'Recompensas', icon: 'workspace_premium', section: 'rewards' },
    { url: '/clientes/perfil', label: 'Mi perfil', icon: 'person', section: 'profile' },
];
</script>
<template>
    <div ref="motionRoot" :class="['stitch-screen customer-world dark', `stitch-${section}`]">
        <div class="customer-ambient" aria-hidden="true"><i /><i /><i /></div>
        <div class="customer-canvas">
            <header class="customer-header">
                <template v-if="section === 'profile'"
                    ><Link href="/clientes" class="profile-logo"><span class="logo-initial">S</span><span>Studio Lemus</span></Link
                    ><span class="atelier-tag">Atelier Privé</span></template
                >
                <template v-else>
                    <Link href="/clientes/perfil" class="header-member"
                        ><span class="mini-avatar"
                            ><img v-if="page.props.has_avatar" src="/clientes/foto" alt="" /><span v-else>{{ customer.name.slice(0, 1) }}</span
                            ><i /></span
                        ><span v-if="section === 'home'"
                            ><small>Atelier Member</small><strong>{{ customer.name }}</strong></span
                        ><span v-else><small>ATELIER PRIVÉ</small><b>Studio Lemus</b></span></Link
                    >
                    <div class="header-trailing">
                        <span v-if="section === 'home'" class="header-brand">Studio Lemus</span
                        ><button
                            type="button"
                            aria-label="Ver actividad de puntos"
                            class="activity-trigger"
                            :aria-expanded="activity"
                            @click="activity = !activity"
                        >
                            <span class="material-symbols-outlined">notifications</span>
                        </button>
                    </div>
                </template>
            </header>
            <div class="motion-controls">
                <button type="button" :aria-pressed="paused" @click="toggleMotion">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path v-if="paused" d="m9 5 11 7-11 7Z" />
                        <path v-else d="M8 5v14M16 5v14" />
                    </svg>
                    {{ paused ? 'Activar animaciones' : 'Pausar animaciones' }}
                </button>
            </div>
            <div v-if="activity" class="feedback activity-feedback" role="status">
                Consulta tus movimientos y puntos en <Link href="/clientes/progreso">Mi progreso →</Link>
            </div>
            <main class="customer-content">
                <p v-if="(page.props.flash as any)?.success" class="feedback" role="status">{{ (page.props.flash as any).success }}</p>
                <slot />
            </main>
        </div>
        <nav class="customer-bottom-nav" aria-label="Navegación de clientas">
            <div>
                <Link
                    v-for="link in links"
                    :key="link.url"
                    :href="link.url"
                    :class="{ selected: section === link.section }"
                    :aria-current="section === link.section ? 'page' : undefined"
                    ><span class="material-symbols-outlined" aria-hidden="true">{{ link.icon }}</span
                    ><span>{{ link.label }}</span></Link
                >
            </div>
        </nav>
    </div>
</template>
<style scoped>
.customer-world {
    min-height: 100dvh;
    background: #1d0e21;
    color: #f5daf6;
    position: relative;
    overflow-x: clip;
    isolation: isolate;
}
.customer-ambient {
    position: fixed;
    inset: 0;
    max-width: 448px;
    margin: auto;
    overflow: hidden;
    pointer-events: none;
    z-index: -1;
}
.customer-ambient i {
    position: absolute;
    display: block;
    border-radius: 50%;
}
.customer-ambient i:nth-child(1) {
    top: -96px;
    left: -48px;
    width: 320px;
    height: 320px;
    background: radial-gradient(ellipse, #a5508840, transparent 70%);
}
.customer-ambient i:nth-child(2) {
    top: 33%;
    right: -80px;
    width: 288px;
    height: 288px;
    background: radial-gradient(ellipse, #ccc2db26, transparent 70%);
}
.customer-ambient i:nth-child(3) {
    bottom: 112px;
    left: 40px;
    width: 256px;
    height: 256px;
    background: radial-gradient(ellipse, #a5508840, transparent 70%);
}
.customer-canvas {
    position: relative;
    width: 100%;
    max-width: 390px;
    margin: auto;
    padding-bottom: calc(112px + env(safe-area-inset-bottom));
}
.stitch-profile .customer-canvas {
    max-width: 448px;
}
.customer-header {
    position: sticky;
    top: 0;
    z-index: 40;
    min-height: 68px;
    padding: 14px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    background: #1d0e21b3;
    backdrop-filter: blur(24px);
    box-shadow: 0 1px 2px #0000000d;
}
.header-member {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}
.mini-avatar {
    position: relative;
    width: 40px;
    height: 40px;
    flex: none;
    border-radius: 50%;
    padding: 2px;
    background: linear-gradient(45deg, #ffaedc66, #52434a80, #ffaedccc);
}
.mini-avatar > span,
.mini-avatar img {
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    object-fit: cover;
    border-radius: 50%;
    background: #352438;
    border: 1px solid #d6c1ca4d;
}
.mini-avatar i {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ffaedc;
    border: 2px solid #1d0e21;
}
.header-member small {
    display: block;
    font-size: 10px;
    line-height: 14px;
    color: #d6c1ca;
    letter-spacing: 0.08em;
}
.header-member strong {
    display: block;
    font-size: 12px;
    line-height: 16px;
    font-weight: 600;
    letter-spacing: 0.06em;
    max-width: 110px;
    overflow-wrap: break-word;
}
.header-member b {
    font:
        500 24px/32px 'Syne',
        sans-serif;
    letter-spacing: -0.025em;
}
.header-brand {
    font:
        500 24px/32px 'Syne',
        sans-serif;
    white-space: nowrap;
    letter-spacing: -0.025em;
}
.header-trailing {
    display: flex;
    align-items: center;
    gap: 12px;
}
.activity-trigger {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: #402f444d;
    border: 1px solid #52434a66;
    color: #ffaedc;
}
.activity-trigger .material-symbols-outlined {
    font-size: 20px;
}
.customer-content {
    padding: 16px 20px 0;
    overflow-wrap: anywhere;
    min-width: 0;
}
.stitch-profile .customer-content {
    padding-top: 24px;
}
.activity-feedback {
    margin: 10px 20px;
}
.profile-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    font:
        600 20px/28px 'Syne',
        sans-serif;
}
.logo-initial {
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    border: 1px solid #ffaedc66;
    color: #ffaedc;
    background: #8a3b6e33;
}
.atelier-tag {
    font-size: 10px;
    line-height: 14px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #9e8c94;
    padding: 2px 8px;
    border: 1px solid #52434a66;
    border-radius: 999px;
    background: #17081b99;
}
.customer-bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 50;
    background: #251629bf;
    backdrop-filter: blur(40px);
    border-radius: 16px 16px 0 0;
    box-shadow: 0 -8px 32px #1d0e2199;
}
.customer-bottom-nav > div {
    display: flex;
    justify-content: space-around;
    align-items: center;
    padding: 8px 16px max(24px, env(safe-area-inset-bottom));
    max-width: 448px;
    margin: auto;
}
.customer-bottom-nav a {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 6px 12px;
    color: #d6c1ca;
    font-size: 10px;
    line-height: 14px;
    letter-spacing: 0.08em;
    border-radius: 999px;
}
.customer-bottom-nav a.selected {
    padding: 6px 16px;
    background: #8a3b6e66;
    color: #ffbae0;
}
.customer-bottom-nav .material-symbols-outlined {
    font-size: 20px;
}
.stitch-profile .customer-bottom-nav .material-symbols-outlined {
    font-size: 22px;
}
@media (min-width: 600px) {
    .customer-bottom-nav {
        max-width: 448px;
        margin: auto;
    }
}
@media (max-width: 360px) {
    .header-brand {
        font-size: 19px;
    }
    .header-member strong {
        max-width: 90px;
    }
}
.header-member > span:nth-child(2) {
    min-width: 0;
}
.header-member strong {
    max-width: 100%;
}
.header-trailing {
    flex-shrink: 0;
}
.motion-controls {
    display: flex;
    justify-content: flex-end;
    padding: 0 20px;
}
.motion-controls button {
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #ccc2db;
    font-size: 11px;
}
.motion-controls .material-symbols-outlined {
    font-size: 16px;
}
.customer-world :focus-visible {
    outline: 2px solid #ffaedc;
    outline-offset: 4px;
}
.customer-world :is(button, a, summary, input) {
    scroll-margin-block: 110px 130px;
}
@media (max-width: 380px) {
    .customer-header {
        flex-wrap: wrap;
        gap: 8px;
        padding-inline: 16px;
    }
    .stitch-home .header-member {
        flex: 1 1 100%;
    }
    .stitch-home .header-trailing {
        width: 100%;
        justify-content: space-between;
    }
    .stitch-profile .profile-logo {
        font-size: 20px;
        gap: 8px;
    }
}
.stitch-profile .customer-header {
    min-height: 60px;
    border-bottom: 1px solid #52434a4d;
}
.stitch-profile .logo-initial {
    width: 28px;
    height: 28px;
    font-size: 12px;
}
.stitch-profile .profile-logo {
    gap: 12px;
    font-size: 24px;
    line-height: 32px;
    font-weight: 500;
    letter-spacing: -0.025em;
}
.stitch-rewards .customer-header {
    min-height: 76px;
    border-bottom: 1px solid #52434a4d;
}
.stitch-rewards .mini-avatar {
    width: 36px;
    height: 36px;
}
.stitch-rewards .mini-avatar > span,
.stitch-rewards .mini-avatar img {
    width: 32px;
    height: 32px;
}
.stitch-profile .customer-ambient {
    max-width: none;
}
.stitch-profile .customer-ambient i:nth-child(1) {
    width: 384px;
    height: 384px;
    top: 0;
    left: calc(50% - 192px);
    background: radial-gradient(ellipse, #a5508833, transparent 70%);
}
.stitch-profile .customer-ambient i:nth-child(2) {
    width: 320px;
    height: 320px;
    top: auto;
    bottom: 96px;
    right: 0;
    background: radial-gradient(ellipse, #ccc2db26, transparent 70%);
}
.stitch-profile .customer-ambient i:nth-child(3) {
    display: none;
}
.customer-bottom-nav a.selected .material-symbols-outlined {
    font-variation-settings: 'FILL' 1;
}
</style>
