<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
const page = usePage();
const links = [
    { url: '/clientes', label: 'Inicio', icon: 'mdi-home-outline' },
    { url: '/clientes/citas', label: 'Mis citas', icon: 'mdi-calendar-blank-outline' },
    { url: '/clientes/perfil', label: 'Mi perfil', icon: 'mdi-account-outline' },
    { url: '/clientes/recompensas', label: 'Recompensas', icon: 'mdi-gift-outline' },
];
</script>
<template>
    <VApp class="customer-app">
        <main class="customer-shell">
            <header class="customer-brand">
                <Link href="/clientes">STUDIO LEMUS<small>UÑAS &amp; PESTAÑAS</small></Link
                ><Link href="/clientes/perfil" aria-label="Configurar perfil"><VIcon icon="mdi-cog-outline" /></Link>
            </header>
            <VAlert v-if="(page.props.flash as any)?.success" type="success" class="mb-4">{{
                (page.props.flash as any).success
            }}</VAlert>
            <slot />
        </main>
        <nav class="customer-nav" aria-label="Navegación de clientas">
            <Link
                v-for="link in links"
                :key="link.url"
                :href="link.url"
                :class="{ active: page.url.split('?')[0] === link.url }"
                ><VIcon :icon="link.icon" size="28" /><span>{{ link.label }}</span></Link
            >
        </nav>
    </VApp>
</template>
<style>
.customer-app {
    background: #faf7f2 !important;
    color: #25221f;
    font-family: Arial, sans-serif;
}
.customer-shell {
    width: 100%;
    max-width: 680px;
    margin: 0 auto;
    padding: 24px 22px calc(110px + env(safe-area-inset-bottom));
}
.customer-brand {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 12px 0 34px;
}
.customer-brand > a:first-child {
    font-family: Georgia, serif;
    font-size: clamp(23px, 6vw, 34px);
    text-align: center;
    letter-spacing: 1px;
}
.customer-brand a {
    color: inherit;
    text-decoration: none;
}
.customer-brand small {
    display: block;
    font:
        9px Arial,
        sans-serif;
    letter-spacing: 4px;
    margin-top: 7px;
}
.customer-nav {
    position: fixed;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100%;
    max-width: 720px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    padding: 12px 6px calc(12px + env(safe-area-inset-bottom));
    background: #faf7f2;
    border-top: 1px solid #e8dcd5;
    z-index: 20;
}
.customer-nav a {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    color: #413833;
    text-decoration: none;
    font-size: 11px;
}
.customer-nav a.active {
    color: #8a3437;
}
.customer-nav a.active .v-icon {
    background: #ead1cc;
    border-radius: 50%;
    box-sizing: content-box;
    padding: 5px;
    margin-top: -10px;
}
.customer-shell h1,
.customer-shell h2,
.customer-shell h3 {
    font-family: Georgia, serif;
    font-weight: 500;
}
.customer-shell h2 {
    font-size: 27px;
    margin: 24px 0 18px;
}
@media (min-width: 900px) {
    .customer-shell {
        padding-top: 36px;
    }
}
</style>
