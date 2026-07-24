# Compartir Studio Lemus mediante ngrok

Esta vista previa publica unicamente Laravel. El navegador remoto utiliza los assets compilados de `public/build` y no necesita acceder al servidor local de Vite.

## Configuracion local

En el `.env` local, habilita estas variables solo mientras compartes la aplicacion mediante ngrok:

```dotenv
TRUST_PROXIES=*
NGROK_PREVIEW=true
SESSION_DOMAIN=
SESSION_PATH=/
SESSION_SAME_SITE=lax
SESSION_SECURE_COOKIE=true
```

`TRUST_PROXIES` esta vacio y `NGROK_PREVIEW` esta desactivado por defecto. La aplicacion solo acepta `TRUST_PROXIES=*` cuando ambos `APP_ENV=local` y `NGROK_PREVIEW=true`; no uses `*` en produccion y confia solamente en las direcciones reales de los proxies de esa infraestructura. No escribas la URL gratuita de ngrok en `APP_URL` ni `ASSET_URL`; cambia cada vez que reinicias el tunel.

`SESSION_DOMAIN=` crea una cookie host-only para el dominio que atiende la peticion, sin atarla a `127.0.0.1`. `SESSION_SECURE_COOKIE=true` protege la sesion publica por HTTPS, pero el navegador no enviara esa cookie si accedes directamente por `http://127.0.0.1:8000`; usa `false` cuando vuelvas al acceso HTTP local.

## Preparar la vista previa

Desde la raiz del proyecto ejecuta:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/prepare-ngrok-preview.ps1
```

El script compila el frontend, elimina cualquier `public/hot` residual, limpia las caches de Laravel y comprueba el manifest y todos sus assets. No inicia servidores, ejecuta migraciones ni modifica datos, contrasenas o `APP_KEY`.

## Diagnosticar los assets publicos

Con Laravel y ngrok activos, ejecuta en otra terminal usando la URL HTTPS vigente:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/diagnose-ngrok-preview.ps1 -PublicUrl "https://dominio-ngrok"
```

El diagnostico solicita `/login`, descubre los scripts, estilos y fuentes de `/build/assets/`, y muestra URL, estado, `Content-Type`, tamano y SHA-256. Tambien compara cada respuesta publica con el archivo local correspondiente y falla si un asset esta vacio, devuelve HTML, tiene un MIME incorrecto o no coincide con `public/build`.

Para revisar manualmente el MIME, abre DevTools, selecciona **Network**, recarga `/login`, abre el JavaScript de `app-*.js` y comprueba `Status: 200` y `Content-Type: application/javascript` o `text/javascript`. El CSS debe usar `text/css` y las fuentes `font/woff2`, `font/woff` o el tipo correspondiente; una respuesta `text/html` indica que no se recibio el asset.

## Iniciar Laravel

En una terminal deja ejecutando:

```powershell
php artisan serve
```

## Iniciar ngrok

En otra terminal deja ejecutando:

```powershell
ngrok http 8000
```

Abre la URL HTTPS entregada por ngrok. La URL gratuita cambia cuando se reinicia el tunel.

## Cambios frontend

Despues de cada cambio frontend ejecuta nuevamente:

```powershell
npm run build
```

No ejecutes `npm run dev` mientras la clienta usa el enlace. Vite vuelve a crear `public/hot` y Laravel intentara cargar `127.0.0.1:5173`. El desarrollo con HMR remoto requiere un tunel separado y una configuracion especifica para Vite.

## Pantalla blanca

1. Abre la URL en una ventana privada, sin una sesion autenticada.
2. Abre DevTools y revisa **Console** y las peticiones fallidas en **Network**.
3. Revisa el HTML de `/login` y confirma que los assets usan `https`.
4. Ejecuta `scripts/diagnose-ngrok-preview.ps1` con la URL publica.
5. Confirma que `public/hot` no existe.
6. Ejecuta `php artisan optimize:clear`.
7. En DevTools, activa **Disable cache** y haz `Ctrl+Shift+R`.

Confirma tambien que no aparezcan `127.0.0.1:5173`, `@vite/client` ni `resources/js/app.ts` como URL de script, y que los scripts y estilos usen `/build/assets/` y respondan 200.

El HTML correcto por si solo contiene un elemento `#app` y los datos de la pagina Inertia. Vue esta montado solamente cuando el DOM inspeccionado dentro de `#app` contiene la interfaz generada, por ejemplo los campos `input[name="email"]`, `input[name="password"]` y el boton **Iniciar sesion**. **View source** muestra la respuesta original y no demuestra que JavaScript se haya ejecutado; usa la pestana **Elements**.

Si funciona en Chrome o Chromium limpio y falla solo en Brave, abre el icono de Shields para el dominio temporal de ngrok y desactiva Shields unicamente para ese sitio. Recarga con `Ctrl+Shift+R` y revisa en **Network** cual recurso cambia de bloqueado a 200. No desactives Shields globalmente. Si no hay recursos bloqueados, elimina los datos del sitio temporal desde DevTools, **Application > Storage > Clear site data**, y vuelve a abrir el enlace. La aplicacion no registra service workers ni necesita funciones PWA.

Con `APP_ENV=local`, `NGROK_PREVIEW=true` y `TRUST_PROXIES=*`, Laravel respeta `X-Forwarded-Proto`, `X-Forwarded-Host`, `X-Forwarded-Port` y `X-Forwarded-For` enviados por ngrok. Si cambias estas variables, reinicia `php artisan serve`; un proceso que ya esta activo conserva su configuracion anterior.
