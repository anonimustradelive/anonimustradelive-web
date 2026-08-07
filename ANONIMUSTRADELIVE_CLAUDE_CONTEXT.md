# AnonimusTrade Live — Contexto para Claude Code

Este documento contiene todo lo necesario para continuar el desarrollo del sitio y bots de **AnonimusTrade Live** sin perder contexto entre sesiones.

> ⚠️ **Actualizar este archivo después de cada cambio importante.**

---

## Sobre el proyecto

**AnonimusTrade Live** es una comunidad de trading en vivo dominicana fundada por **Richard Cejas** y **Ridolfi Mosquea**. Anti-gurú, sin filtros, análisis real con entradas en vivo.

- **Sitio principal:** https://anonimustradelive.com
- **Bio/Linktree:** https://anonimustradelive.com/bio/
- **Panel administrativo:** https://panel.anonimustradelive.com (Facturación + Registros — reemplazó a reg.anonimustradelive.com, ver sección dedicada abajo)
- **Servidor:** cPanel compartido
- **Deploy:** Git via `.cpanel.yml` → push a GitHub → deploy manual desde cPanel Git Version Control
- **Repositorio:** https://github.com/anonimustradelive/anonimustradelive-web.git (rama `main`)

---

## Stack técnico

| Componente | Tecnología |
|---|---|
| Backend | PHP plano (sin framework) |
| Frontend | HTML/CSS/JS vanilla |
| Base de datos | MySQL (en cPanel) |
| Bot de Telegram | PHP + Telegram Bot API (webhooks) |
| Crypto monitoring | Moralis API |
| Donaciones fiat | Ko-fi webhooks |
| Autenticación | JWT manual (links mágicos) + sesiones Telegram |
| Deploy | cPanel Git Version Control |

---

## Estructura de archivos

```
anonimustradelive/
├── CLAUDE_CONTEXT.md          ← este archivo, mantener actualizado
├── .cpanel.yml                ← config de deploy automático
├── config.php                 ← ⛔ GITIGNOREADO — tokens, credenciales, DB
├── index.html                 ← página principal del sitio
├── anonimustrade_live.BACKUP.html  ← backup de la página principal
├── gate.php                   ← guard de sesión (protege páginas premium)
├── verify.php                 ← verifica tokens mágicos de acceso
├── kofi-webhook.php           ← recibe pagos Ko-fi, guarda en donors.json
├── telegram-notify.php        ← cron job: detecta donaciones crypto via Moralis
├── crypto-donors.php          ← API que retorna donantes crypto (caché 5 min)
├── donors.json                ← base de datos de donantes Ko-fi
├── auth-tokens.json           ← tokens mágicos activos (generados por el bot)
├── crypto_seen_txs.json       ← hashes de transacciones ya notificadas
├── notify-lisnelly.php        ← script de un solo uso ya ejecutado (ignorar)
│
├── telegram-auth-webhook.php  ← 🤖 ROUTER del bot de herramientas/libros
├── commands/                  ← comandos del bot (uno por archivo)
│   ├── start.php              ← /start, /inicio
│   ├── acceso.php             ← /acceso (link mágico herramientas premium)
│   └── libros.php             ← /libros /librosp /toplibros /toplibrosp
│
├── panel/                     ← 🖥️ Panel administrativo (panel.anonimustradelive.com)
│   ├── config.php             ← ⛔ GITIGNOREADO (mismos valores que el viejo reg/config.php)
│   ├── login.php / logout.php ← login único compartido por todos los módulos
│   ├── index.php              ← redirige a /facturas/
│   ├── setup.php              ← migraciones DB de facturación (ejecutar una vez)
│   ├── includes/              ← auth.php, db.php, nav.php (tabs compartidos), footer.php
│   ├── assets/style.css       ← tema compartido (dark/purple)
│   ├── facturas/              ← 🧾 Generador de facturas para clientes de ads/contenido
│   │   ├── index.php          ← listado + filtros + acciones (marcar pagada, anular…)
│   │   ├── nueva.php          ← crear/editar factura (líneas dinámicas, ITBIS opcional)
│   │   └── ver.php            ← factura imprimible (tema claro, Ctrl+P → PDF)
│   └── registros/             ← 🤖 Bot de registro a la comunidad (migrado de reg/ el 2026-07-17)
│       ├── webhook.php        ← bot de registro (máquina de estados)
│       ├── index.php          ← admin de registros (chat, notas, KYC, migración)
│       └── setup.php          ← migraciones DB (ejecutar una vez)
│
├── bio/
│   └── index.html             ← página tipo linktree
├── libros/
│   └── index.html             ← biblioteca de libros recomendados (Top 5 + lista completa por categoría)
├── utilidades/
│   └── index.php              ← herramientas premium (protegidas por gate.php)
├── herramientas/
│   └── index.html             ← página pública de herramientas
├── images/                    ← logos, favicon, fotos
├── ads/
│   ├── index.html             ← landing page de publicidad (estática, sin backend)
│   └── ads_img/               ← assets visuales de la landing
│       ├── hero_img_1.png     ← foto alternativa del live (no usada actualmente)
│       ├── hero_img_2.png     ← foto del live — fondo actual del hero
│       ├── live_capture_1.png ← captura del stream (stream mock)
│       ├── reel_1.png         ← top reel IG (32.7K views)
│       ├── reel_2.png         ← top reel IG (60.9K views)
│       ├── reel_3.png         ← top reel IG (2,667 views)
│       ├── cintillo_pana.gif  ← cintillo animado cliente Pana (1920×216)
│       ├── cintillo_pp.gif    ← cintillo animado cliente PepperStone (convertido de MP4, 960×108, CSS stretch)
│       └── cintillo_prado.png ← cintillo estático cliente Prado (1920×216)
├── Asistente/
│   └── contexto-anonimustradelive.md  ← contexto del show (no tocar)
└── Live Rundown/               ← 🎛️ App interna de rundown de producción (React/TS/Vite, ver su propio README.md)
    └── (proyecto Node separado, con su package.json — no pasa por .cpanel.yml ni el deploy del sitio)
```

---

## Variables de config.php (NUNCA subir a GitHub)

El archivo `config.php` está en `.gitignore`. Contiene:

```php
// Bot de herramientas/libros (@AnonimusTradeLiveDonBot)
$TG_TOKEN      // token del bot
$TG_CHAT_ID    // ID del grupo privado (-1001517888411)
$TG_THREAD_ID  // ID del hilo/topic en el grupo (para crypto notify)
$SITE_URL      // https://anonimustradelive.com
$KOFI_TOKEN    // token de verificación de Ko-fi
$MORALIS_KEY   // API key de Moralis para detectar transacciones crypto

// Panel administrativo (panel/config.php — también gitignoreado, reemplaza a reg/config.php)
BOT_TOKEN          // token del bot de registro
DB_HOST/NAME/USER/PASS  // credenciales MySQL (compartida por Facturación y Registros)
ADMIN_TG_ID        // Telegram ID del admin (Richard)
COMMUNITY_CHAT_ID  // ID del grupo para invitar usuarios
ADMIN_PASS         // contraseña de acceso al panel (único login para todos los módulos)
```

---

## Bots de Telegram

### 1. Bot de herramientas + libros (`telegram-auth-webhook.php`)

**Estructura modular — router + commands/:**
- Agregar comando nuevo: crear `commands/nuevo.php` + línea en router.
- Las funciones `sendMessage()`, `sendToGroup()`, `getMemberStatus()` están definidas en el router y disponibles en todos los comandos.

| Comando | Archivo | Acción |
|---|---|---|
| `/start`, `/inicio` | `commands/start.php` | Bienvenida + lista de comandos |
| `/acceso` | `commands/acceso.php` | Verifica membresía → link mágico 15 min |
| `/libros` | `commands/libros.php` | Lista completa → publica en el grupo |
| `/librosp` | `commands/libros.php` | Lista completa → envía por DM privado |
| `/toplibros` | `commands/libros.php` | Top 5 → publica en el grupo |
| `/toplibrosp` | `commands/libros.php` | Top 5 → envía por DM privado |

### 2. Bot de registro (`panel/registros/webhook.php`)

Máquina de estados en MySQL (`sessions` table). Flujo:

```
/start → awaiting_type → awaiting_email → awaiting_platform_id
       → awaiting_confirm → (reg_confirmar) → completed
```

**Estados:**
- `awaiting_type` — elige perfil: principiante / trader
- `awaiting_asset` — elige mercado: crypto / forex (solo traders)
- `awaiting_crypto_platform` — elige exchange: BingX / Bitunix
- `awaiting_email` — escribe email de la plataforma
- `awaiting_platform_id` — escribe ID de usuario numérico
- `awaiting_confirm` — confirma o corrige los datos
- `awaiting_migration_confirm` — esperando que envíe correo a Pepperstone
- `completed` — registro enviado (verifica status real en DB)

**Callbacks:**
`tipo_aprender`, `tipo_trader`, `asset_crypto`, `asset_tradicional`,
`platform_bingx`, `platform_bitunix`, `platform_zoomex`, `ya_tengo_pepperstone`,
`migr_confirmado`, `reg_confirmar`, `reg_corregir`,
`kyc_done`, `migr_notify`

**Plataformas soportadas:**
- Pepperstone (broker Forex, requisito de acceso a la comunidad)
- BingX (exchange crypto, requiere KYC)
- Bitunix (exchange crypto, sin KYC)
- Zoomex (exchange crypto, sin KYC — agregado 2026-07-15)

**Regla crítica de MarkdownV2:**
Solo escapar estos caracteres: `_ * [ ] ( ) ~ \` > # + - = | { } . !`
Escapar otros (ej. `$`, em-dash) causa fallos silenciosos.
Siempre usar `mdEscape()` para contenido dinámico (nombres, usernames).

### 3. Cron job crypto (`telegram-notify.php`)

Script PHP que corre periódicamente (cron en cPanel).
- Consulta Moralis API por transferencias a la wallet
- Wallets monitoreadas: USDT BSC, USDC Base, USDT ETH, USDC ETH
- Wallet: `0x20332BD20d55cc85282AFFe05BcC473bb8D18D91`
- Detecta desde: `2026-05-23T00:00:00Z`
- Guarda hashes ya notificados en `crypto_seen_txs.json`

---

## Base de datos MySQL (compartida por panel/facturas y panel/registros)

### Tabla `registrations`
| Campo | Tipo | Notas |
|---|---|---|
| id | INT AUTO_INCREMENT | |
| telegram_user_id | BIGINT | |
| telegram_name | VARCHAR(255) | |
| telegram_username | VARCHAR(255) | |
| profile_type | ENUM('principiante','trader') | |
| asset_type | ENUM('crypto','tradicional') | NULL para principiantes |
| platform | ENUM('pepperstone','bingx','bitunix','zoomex') | |
| email | VARCHAR(255) | |
| is_migration | TINYINT(1) | 1 = cuenta existente a migrar |
| platform_user_id | VARCHAR(255) | ID numérico en la plataforma |
| status | ENUM('pending','accepted','rejected') | |
| invite_link | VARCHAR(500) | link de un solo uso al aceptar |
| kyc_status | ENUM('none','pending','completed') | solo BingX |
| kyc_attempts | INT | máximo 3, luego rechazo automático |
| migration_status | ENUM('none','pending','notified') | |
| patience_sent | TINYINT(1) | 1 = mensaje de paciencia ya enviado |
| created_at / updated_at | TIMESTAMP | |

### Tabla `sessions`
| Campo | Tipo | Notas |
|---|---|---|
| user_id | BIGINT PRIMARY KEY | Telegram user ID |
| state | VARCHAR(50) | estado actual del flujo |
| data | JSON | datos acumulados del flujo |
| updated_at | TIMESTAMP | |

### Tabla `registration_notes`
| Campo | Tipo | Notas |
|---|---|---|
| id | INT AUTO_INCREMENT | |
| registration_id | INT | FK a registrations |
| note | TEXT | historial append-only |
| created_at | TIMESTAMP | |

---

## Panel Admin — módulo Registros (`panel/registros/index.php`)

**URL:** https://panel.anonimustradelive.com/registros/

**Funcionalidades:**
- Filtros por estado (pending/accepted/rejected) y plataforma
- Búsqueda en tiempo real
- Columnas: #, Nombre, Telegram, Perfil, Plataforma, Email, ID, Estado, Fechas, Acciones
- Filas en amarillo = sin actividad 48h+ (stale)
- Notas internas append-only con modal AJAX (no recarga página)

**Botones de acción por registro pendiente:**
| Botón | Acción |
|---|---|
| ✅ Aceptar | Crea invite link único → notifica usuario |
| ❌ Rechazar | Rechaza con mensaje genérico |
| ⛔ UID no referido | Solo exchanges — rechaza explicando que el ID no está bajo el referido + link para crear cuenta nueva |
| 📋 KYC | Solo BingX — avisa que falta KYC (máx 3 intentos, luego rechazo auto) |
| 🔄 Migración | Solo Pepperstone migración — envía mensaje de paciencia (un solo uso) |

---

## ✅ Runbook de corte: reg.anonimustradelive.com → panel.anonimustradelive.com (COMPLETADO 2026-07-18)

Ejecutado y verificado end-to-end el 2026-07-18: subdominio `reg.` eliminado, `panel.` creado y en producción, webhook de Telegram re-registrado y confirmado (`getWebhookInfo` mostró la URL correcta, `pending_update_count:0`, sin `last_error_message`), bot probado con `/start`, panel probado (Facturación + Registros con los 74 registros existentes, chat y notas funcionando), scripts `setup.php` eliminados del servidor. Queda documentado el procedimiento por si se repite un corte similar en el futuro:

1. **Eliminar el subdominio `reg.anonimustradelive.com`** en cPanel (Dominios → Subdominios) para liberar el slot. *(A partir de aquí el bot deja de responder — es el punto de no retorno, hay que completar los siguientes pasos con calma pero sin demorar demasiado.)*
2. **Crear el subdominio `panel.anonimustradelive.com`**, con document root `/home/ntpsrnlfrg/panel.anonimustradelive.com`.
3. **Deploy** vía cPanel Git Version Control (pull del último commit) — ahora sí, la carpeta del subdominio ya existe (limpia, recién creada por cPanel), así que el deploy la llena sin ambigüedad.
4. **Crear `panel/config.php` directamente en el servidor** (por FTP o el Administrador de Archivos de cPanel — nunca por git) con los mismos valores que tenía `reg/config.php` (BOT_TOKEN, DB_HOST/NAME/USER/PASS, ADMIN_TG_ID, COMMUNITY_CHAT_ID) más un `ADMIN_PASS` para el panel. Plantilla en `panel/config.example.php`.
5. **Re-registrar el webhook de Telegram** — este es el paso que restaura el bot. Pegar esta URL en el navegador (reemplazando `<TU_BOT_TOKEN>` por el valor real):
   ```
   https://api.telegram.org/bot<TU_BOT_TOKEN>/setWebhook?url=https://panel.anonimustradelive.com/registros/webhook.php
   ```
   Debe responder `{"ok":true,"result":true,"description":"Webhook was set"}`.
6. **Verificar** que el webhook quedó bien apuntado, visitando (sin token visible en el chat, correrlo tú mismo):
   ```
   https://api.telegram.org/bot<TU_BOT_TOKEN>/getWebhookInfo
   ```
   Debe mostrar `"url":"https://panel.anonimustradelive.com/registros/webhook.php"` y `"pending_update_count":0` (o bajo).
7. **Correr `panel/setup.php`** una vez (tablas de Facturación) y **`panel/registros/setup.php`** una vez (tablas de Registros — ya deberían existir de antes, pero confirma que el ENUM de `platform` sigue teniendo `zoomex`).
8. **Probar el bot**: escribirle `/start` desde Telegram y confirmar que responde. Probar también el panel completo entrando a `panel.anonimustradelive.com` con el nuevo login.
9. Una vez todo confirmado, `panel/setup.php` y `panel/registros/setup.php` se pueden eliminar del servidor (scripts de un solo uso).

---

## Bio page (`bio/index.html`)

**Orden actual de links:**
1. Comunidad — bot de Telegram
2. Mentoría — Calendly 1:1
3. Correo — anonimustradelive@outlook.com
4. Cuentas de fondeo — FundingPips · The5ers
5. Apóyanos — Pepperstone, Zoomex, BingX, Bitunix, Pana
6. Donar — crypto · Ko-fi
7. Sesiones en vivo — YouTube · TikTok · Instagram
8. Herramientas y recursos — tier lists, simulador, reloj sesiones

**Cuenta regresiva live:**
- Inicio: 8:30 AM hora de Nueva York
- Fin: 12:00 PM hora de Nueva York
- L-V solamente
- Fuera del horario → cuenta regresiva; dentro → banner "EN VIVO"

---

## Referidos activos

| Plataforma | URL | Tipo |
|---|---|---|
| Pepperstone | https://trk.pepperstonepartners.com/aff_c?offer_id=367&aff_id=45363 | Broker Forex (requisito comunidad) |
| BingX | https://bingxdao.com/partner/AnonimusTrade/ | Exchange crypto con KYC |
| Bitunix | https://www.bitunix.com/register?vipCode=KMrN | Exchange crypto sin KYC |
| Zoomex | https://partner.zoomex.com/aff/ZX904826 | Exchange crypto sin KYC |
| Pana | https://pana.go.link/am7IP | Fintech para retiros USDC |
| FundingPips | https://app.fundingpips.com/register?referral_code=f225e2bb | Prop firm |
| The5ers | https://www.the5ers.com/?afmc=xmw | Prop firm |
| Surfshark | https://surfshark.club/friend/2EHfq785 | VPN (recomendado para EE.UU./UE) |

---

## Reglas de desarrollo

1. **NUNCA subir a GitHub:** `config.php`, `panel/config.php`, `auth-tokens.json`, `donors.json`, `crypto_seen_txs.json`
2. **Siempre hacer commit + push + deploy en cPanel** tras cada cambio
3. **MarkdownV2 en Telegram:** usar siempre `mdEscape()` para contenido dinámico; nunca `htmlspecialchars()`
4. **Estructura modular del bot:** nunca poner lógica de negocio en el router; usar `commands/`
5. **Actualizar este archivo** (`CLAUDE_CONTEXT.md`) después de cada cambio relevante

---

## Historial de cambios recientes

### 2026-08-02 (6) — Módulo "Concurso" en el panel admin (ver/exportar participaciones)
- **Motivo:** `concurso/submissions.json` está bloqueado por `.htaccess` (a propósito, son nombres de participantes). El usuario necesita poder ver las participaciones y descargar todo organizado para pasárselo a una IA que arme el ranking. Primer intento fue una página de admin standalone dentro de `concurso/` con su propio login — el usuario pidió mejor reutilizar el panel admin ya existente (`panel.anonimustradelive.com`), así que se revirtió ese enfoque y se migró todo ahí
- **`panel/includes/concurso_data.php`** (nuevo): lee `concurso/submissions.json` y sirve de base para resolver la carpeta del concurso — mismo patrón de "docroots hermanos bajo el mismo home de cPanel" que ya usa `ads_pricing.php` (candidatos: constante `CONCURSO_DIR` opcional, mismo árbol local, o `../../anonimustradelive.com/concurso` en producción). `panel.anonimustradelive.com` y `anonimustradelive.com` son subdominios con docroots separados pero pueden leerse el filesystem entre sí porque son carpetas hermanas en el mismo hosting
- **`panel/concurso/index.php`** (nuevo módulo, tab "🏆 Concurso" agregado a `panel/includes/nav.php` junto a Facturación/Registros): lista cada participación (nombre + apellido, fecha, nota, miniaturas de las capturas enlazando a `https://anonimustradelive.com/concurso/uploads/<archivo>` en tamaño completo). Usa el mismo `includes/auth.php` que el resto del panel — sin login duplicado
- **`panel/concurso/export_csv.php`**: descarga un CSV (nombre, apellido, nota, fecha, cantidad de imágenes, nombres de archivo) — texto organizado para pegar o pasarle a una IA
- **`panel/concurso/export_zip.php`**: arma un ZIP en el momento (vía `ZipArchive`) con una carpeta por participante (`01_Nombre_Apellido/`, con sus imágenes dentro) + un `resumen.tsv` en la raíz — así se puede subir el ZIP completo a una IA con todo el contexto de quién es cada imagen
- **`panel/assets/style.css`**: nuevas clases `.concurso-entry`, `.concurso-entry-note`, `.concurso-thumbs`, `.concurso-thumb` (tarjetas con miniaturas 96×96, mismo lenguaje visual que el resto del panel)
- **`.cpanel.yml`**: deploy de `panel/concurso/*.php` (el `.htaccess`/`uploads/` de `concurso/` en el docroot principal ya estaban cubiertos desde antes)
- Verificado con un mock estático en navegador: tab activo, tarjetas con nombre/fecha/nota (incluyendo el caso de nota vacía → "Sin nota." en cursiva) y miniaturas con el layout correcto

### 2026-08-02 (5) — Concurso: desglose de premios por lugar
- La franja genérica "🏆 4 cuentas de fondeo en juego" se reemplazó por un bloque **"Premios en juego"** con el detalle por posición (medallas 🥇🥈🥉): 1er lugar $50,000 (Futuros), 2do lugar $10,000 (CFDs), 3er y 4to lugar $5,000 (CFDs) cada una — mismo estilo visual que el resto de la página (fondo morado translúcido, filas separadas por línea sutil)
- Verificado visualmente en navegador (mobile 375px)

### 2026-08-02 (4) — Concurso: Nombre/Apellido separados (bloquean el botón) + nota opcional
- **Motivo:** con un solo campo "Nombre completo" es fácil que alguien solo ponga el nombre y se olvide del apellido — con nombres comunes, después es difícil identificar a la persona en vivo entre varios candidatos con el mismo nombre
- **`concurso/index.php`**: campo único reemplazado por dos campos lado a lado (`first_name` / `last_name`), ambos obligatorios y con **mínimo 3 caracteres** (server-side vía `MIN_NAME_LEN`, y `minlength="3"` en el HTML) — evita que alguien ponga un punto o una letra suelta para saltarse la validación
- **Botón "Enviar participación" empieza deshabilitado** (`disabled` en el HTML) y se habilita en vivo por JS (`updateSubmitState()`) solo cuando *ambos* campos tienen 3+ caracteres sin espacios en blanco — probado en navegador: con 2 caracteres o con un solo campo lleno el botón sigue bloqueado; al completar ambos se habilita inmediatamente
- **Nota opcional**: nuevo `<textarea name="note" maxlength="500">` debajo de las capturas, con contador de caracteres en vivo ("X/500") y tope real server-side (`MAX_NOTE_LEN`) que rechaza el envío si se supera (por si alguien manipula el HTML)
- `submissions.json` ahora guarda `first_name`, `last_name` y `note` por separado (antes era un solo `full_name`)
- Verificado en navegador: los dos campos se ven lado a lado en mobile (375px), el botón queda gris/bloqueado por defecto, se desbloquea correctamente al llenar Nombre + Apellido con 3+ caracteres cada uno, y el contador de la nota actualiza en vivo

### 2026-08-02 (3) — Ajustes al formulario del concurso: sin correo, próximos pasos, multi-imagen
- **Mensaje de éxito:** se quitó el link de `mailto:` (para no abrir la puerta a que la gente empiece a escribir por temas ajenos al concurso). En su lugar, un nuevo bloque **"Para poder ganar"** (mismo estilo que las reglas) recordando: seguir en TikTok y/o Instagram, suscribirse al canal de YouTube, y estar presente en el chat en vivo el día del anuncio — si resulta ganador, se lo invita a una videollamada privada donde debe mostrar su dashboard y refrescar la página en vivo para verificar que los datos no están falseados
- **Multi-imagen:** el dashboard no cabe en una sola captura, así que el input pasó de un solo archivo a `screenshots[]` con `multiple`, tope de **4 imágenes** (5 MB cada una, mismo límite de antes mantenido por archivo). Validación **todo o nada**: si alguna imagen falla (tipo, tamaño o cantidad), no se guarda ninguna y se muestra el error — evita registros a medias con solo algunas capturas guardadas
- `submissions.json` ahora guarda cada envío como `{full_name, files: [{filename, size}, ...], submitted_at}` en vez de un solo `filename`
- JS del selector de archivo actualizado para validar cada imagen del lote y mostrar cuántas/cuáles se eligieron
- Verificado de nuevo con mocks estáticos en navegador: formulario con hint "Hasta 4 imágenes", estado con 3 archivos seleccionados, y el mensaje de éxito sin correo con el bloque de próximos pasos

### 2026-08-02 (2) — Nuevo `/concurso/`: formulario de participación del concurso de trading
- **Qué es:** concurso interno con 4 cuentas de fondeo en juego, rankeadas manualmente por el equipo a partir de capturas de dashboard que suben los participantes. Esta página **solo captura los datos** (nombre + imagen) — no calcula el ranking
- **`concurso/index.php`** (nuevo, sin base de datos, mismo patrón de archivo plano que `donors.json`):
  - Formulario: nombre completo + subida de imagen (drag-friendly, con preview del nombre de archivo elegido)
  - Reglas mostradas en la página: solo cuenta trading entre **lunes 20 de julio y viernes 7 de agosto**; cuentas iniciadas antes del 20 de julio quedan descalificadas; cuentas que se queman durante el proceso quedan descalificadas automáticamente
  - Validación de imagen en dos capas: cliente (JS, feedback inmediato) y servidor (autoritativa — nunca confía en el tipo/tamaño que reporta el navegador): máximo 5 MB, y el tipo real se verifica con `getimagesize()` sobre el archivo ya subido (no por extensión ni `Content-Type` del navegador) — solo JPG, PNG o WebP pasan
  - Cada envío se guarda como `{full_name, filename, size, submitted_at}` en `concurso/submissions.json` (append), y la imagen se renombra a `YYYYMMDD_HHMMSS_<random>.<ext>` en `concurso/uploads/` — nunca se usa el nombre de archivo original del usuario
  - Patrón POST-redirect-GET (`Location: /concurso/?ok=1`) para evitar reenvíos duplicados al refrescar
- **Seguridad de la carpeta de uploads:** `concurso/uploads/.htaccess` bloquea la ejecución de scripts (`.php`, `.phtml`, etc.) y desactiva el listado de directorio — defensa en profundidad además de la validación real de contenido de imagen. `concurso/.htaccess` bloquea el acceso directo a `submissions.json` (contiene nombres de participantes, no debe ser público) y también desactiva el listado
- **`.gitignore`**: agregado `concurso/submissions.json` y `concurso/uploads/*` (con excepción de `.htaccess`) — son datos generados en runtime por los usuarios, no código fuente, mismo criterio que `crypto_seen_txs.json`/`auth-tokens.json`
- **`.cpanel.yml`**: deploy de `concurso/index.php`, `concurso/.htaccess`, `concurso/uploads/.htaccess` (con `mkdir -p` de ambas carpetas)
- Verificado con un mock estático en navegador (sin PHP local disponible, mismo flujo que en Facturación): diseño mobile (375px), estado inicial del formulario, estado "archivo elegido" y estado de éxito tras enviar — los tres se ven y funcionan correctamente
- ⚠️ **Pendiente:** hacer commit + push, y confirmar en producción que `concurso/uploads/` queda con permisos de escritura para PHP (debería heredarlos del `mkdir -p` del deploy, pero conviene comprobar con un envío de prueba real)

### 2026-08-02 — Ruleta promocional Zoomex en la bio (campaña temporal)
- **Motivo:** campaña de referidos de Zoomex con premios (bonos $5–$500, descuento de tarifa, vales de posición 10U–200U). El usuario pidió promoverla en `bio/index.html` mientras dure, con la condición explícita de poder revertir al estado anterior cuando termine
- **`bio/index.html`**:
  - Nuevo bloque `.zoomex-wheel-promo`: ruleta 100% CSS (sin imágenes ni librerías) con los 12 premios de la campaña, `conic-gradient` de 12 segmentos alternados en tonos teal/negro (marca Zoomex), rotación continua infinita (`@keyframes wheelSpin`, 18s/vuelta) que acelera a 0.7s al presionar (`:active`), borde con glow pulsante (`@keyframes wheelPromoGlow`), puntero fijo arriba y logo de Zoomex en el centro. Es un `<a target="_blank">` completo — toda la tarjeta es clickeable y lleva a `https://www.zoomex.com/es-MX/welcome/AnonimusTradeEvent`
  - El botón "Abrir cuenta en Zoomex" se movió a la **primera posición** de la sección "Apóyanos" (antes de Pepperstone) mientras dure la campaña
  - **Reversión al terminar la campaña:** hay comentarios HTML en el archivo (junto a la ruleta y junto al botón de Zoomex en Apóyanos) documentando el orden default y los pasos exactos para revertir. También guardado en memoria (`project_anonimustrade_zoomex_campaign.md`) para que quede aunque se pierda el contexto de la sesión
- Verificado en navegador (mobile 375px): la ruleta gira (confirmado vía `getComputedStyle`), los 12 labels están en el orden correcto, el link y `target="_blank"` apuntan bien, y Zoomex aparece antes que Pepperstone en el listado
- **Mismo día, dos ajustes de ubicación pedidos por el usuario:**
  1. La ruleta se movió de la sección Apóyanos a **justo debajo del botón "Únete a la comunidad"** (sección Comunidad, mucho más arriba en la página) para darle más visibilidad
  2. El botón **"Abrir cuenta en Zoomex" se movió junto a la ruleta** (justo debajo), para acercar el flujo de "ver la ruleta → abrir cuenta". La sección Apóyanos quedó en su orden 100% default sin Zoomex: Pepperstone, BingX, Bitunix, Pana
- Memoria (`project_anonimustrade_zoomex_campaign.md`) y comentarios HTML en el archivo actualizados con la ubicación final y los pasos exactos para revertir todo (ruleta + botón) cuando termine la campaña

### 2026-07-29 — Nueva carpeta `Live Rundown/`: app de rundown de producción (React/TS/Vite)
- **Motivo:** reemplazar el prototipo de un solo archivo HTML (rundown de producción del show en vivo — planificador semanal + cronómetro en vivo con drift + integración OBS) por una app profesional con persistencia robusta, motor de cronómetro testeado y funcionalidad nueva (historial de shows, modo ensayo, aviso de patrocinador obligatorio)
- Proyecto Node **completamente independiente** dentro de `Live Rundown/` (propio `package.json`, `node_modules`, etc.) — no toca `.cpanel.yml` ni el pipeline de deploy del sitio principal, es una herramienta interna que corre local (`npm run dev`) en la laptop de producción, no algo que se publique en el dominio
- Stack: React 19 + TypeScript + Vite 8, Tailwind v4, Zustand, dnd-kit (drag-and-drop del planificador), Dexie/IndexedDB (reemplaza el `localStorage` del prototipo), Vitest (40 tests — motor del cronómetro + auth de OBS)
- Ver `Live Rundown/README.md` para instalación, desarrollo y cómo conectar OBS
- `.claude/launch.json` (raíz): agregada config `rundown-dev` (puerto 5173) junto a `ads-preview`

### 2026-07-18 (8) — Botón "Contacto" en la barra superior (sitio principal)
- **Motivo:** el correo solo aparecía como una tarjeta más dentro de la sección Comunidad, poco intuitivo para quien busca contacto por negocios/colaboraciones
- **`index.html`**: nuevo estilo `.nav-cta-outline` (borde, fondo transparente, hover en morado) para diferenciarlo visualmente de "🤍 donar" (rojo) y "únete" (morado sólido). Botón **"📧 contacto"** (`mailto:anonimustradelive@outlook.com?subject=Contacto%20desde%20la%20web`) agregado al `.nav-actions` de escritorio (antes de donar/únete) y al `.nav-mobile-actions` del menú móvil (fila completa arriba, con donar/únete debajo). Se probó primero con el emoji ✉️ pero no renderizaba bien (glifo faltante) — se cambió a 📧, más compatible
- Se **quitó** la tarjeta "Email" del `.social-grid` en `#comunidad` (quedan Telegram, YouTube, Instagram, X, TikTok) — el correo de contacto para negocios ahora vive únicamente en el botón de la barra superior. El mailto del footer del sitio no se tocó (es la firma de contacto general, no la sección Comunidad)
- Verificado visualmente en navegador: desktop (1440px), mobile (375px, menú hamburguesa) y sección Comunidad sin la tarjeta de email

### 2026-07-18 (7) — Eliminar cualquier factura/comprobante (con triple confirmación)
- **`panel/facturas/index.php`**: la acción `delete` ya no está restringida a `status='draft'` (antes solo se podían borrar borradores) — ahora se puede eliminar cualquier factura o comprobante, en cualquier estado, incluyendo pagadas/comprobantes
- Botón **"🗑️ Eliminar"** unificado (antes solo existía dentro del bloque de Borradores) — ahora aparece para todas las filas del listado, con `onsubmit` encadenando **3 `confirm()` de JS** (cortan si el admin cancela cualquiera): 1) confirmación general con el número de factura, 2) advertencia de que es irreversible, 3) confirmación final repitiendo el número de factura
- El mensaje flash tras eliminar distingue "Factura" vs "Comprobante" según `doc_type`
- Verificado visualmente con un mock: el botón aparece en filas Pagada, Enviada y Borrador por igual

### 2026-07-18 (6) — Fix: dos tasas distintas confundían en el comprobante impreso
- **Problema reportado:** en un comprobante con precio negociado a mano (RD$32,200 por un Premium de $550), la línea mostraba correctamente "Tasa usada: 1 USD = 58.5455 DOP" (32200/550, matemática correcta), pero el pie de la factura además mostraba "Tasa aplicada: 1 USD = 58.6210 DOP" — la tasa general que se usó como sugerencia automática antes del ajuste manual. Dos números distintos en el mismo documento generaban confusión, aunque ninguno estaba mal calculado
- **`panel/facturas/ver.php`**: se agrega `$any_line_override` (true si algún ítem tiene `effective_rate` guardado). La nota general "Tasa aplicada" del pie ahora solo se muestra cuando **ningún** ítem tiene tasa propia — si hay una línea con precio negociado, esa nota por línea es la única que aparece, evitando mostrar un número que ya no corresponde a nada cobrado realmente en esa factura
- Verificado con mock: comprobante con un ítem con `effective_rate` → el pie ya no muestra "Tasa aplicada", solo queda la nota de la línea

### 2026-07-18 (5) — Precio negociado a mano en pesos (con tasa efectiva por línea)
- **Motivo:** el usuario a veces acuerda con el cliente un precio en pesos que no coincide exactamente con la tasa × precio oficial (redondeos, negociación). El precio base en USD del catálogo (Deluxe, Premium, Spots) **siempre debe permanecer protegido** — nunca se toca, solo se actualiza si cambian los precios en `ads/index.html`
- **`panel/setup.php`**: agrega columna `invoice_items.effective_rate DECIMAL(10,4) NULL`
- **`panel/facturas/nueva.php`**:
  - JS: nuevas `isCatalogPriced()` / `updatePriceEditability()` — el campo Precio de un ítem del catálogo (Deluxe/Premium/Spots) queda **readonly cuando la factura es en Dólares** (protegido, como siempre) y **editable cuando es en Pesos** (se puede escribir un valor negociado). Los ítems genéricos/manuales siguen siempre editables, en cualquier moneda. Cambiar de moneda o tocar la tasa de cambio general resetea el precio al valor auto-calculado (y su editabilidad) — el override manual es un ajuste puntual, no algo que sobreviva a cambios posteriores de la tasa
  - PHP: `calcCatalogPrice()` con `$rateMul=1.0` calcula el precio USD "oficial" de ese ítem; si la factura es en pesos y el precio enviado no coincide con el auto-calculado (con tolerancia de redondeo a 2 decimales), se respeta el precio del admin y se guarda `effective_rate = precio_pesos / precio_usd_oficial` en `invoice_items`. Si la factura es en USD, el precio sigue recalculándose siempre server-side (protegido, sin cambios)
- **`panel/facturas/ver.php`**: cuando una línea tiene `effective_rate` guardado, se muestra "Tasa usada: 1 USD = X.XXXX DOP" debajo de esa línea (estilo `.line-rate-note`, gris/discreto) — así el comprobante/factura deja constancia de la tasa real aplicada en esa negociación puntual, sin depender de la "Tasa aplicada" general del pie
- Verificado en navegador con mock (fetch simulado): Deluxe protegido y bloqueado en USD ($1,900.00, readonly); al pasar a Pesos se desbloquea y permite escribir RD$110,000.00 (en vez del RD$118,456.83 auto-calculado), el subtotal lo respeta; al volver a Dólares el precio regresa a $1,900.00 protegido

### 2026-07-18 (4) — Editar facturas/comprobantes ya generados + fix de texto solapado en el PDF
- **`panel/facturas/nueva.php`**: se quitó la restricción `AND status = 'draft'` tanto al cargar una factura para editar como al guardarla — antes solo se podían editar borradores, así que un Comprobante (siempre `status='paid'`) o una factura ya Enviada/Pagada/Vencida no se podían corregir. Ahora se puede editar cualquiera. Al guardar una corrección, el estado existente **se conserva** (una factura "Enviada" sigue "Enviada" después de arreglar un typo) en vez de reiniciarse a "Borrador" — solo se fuerza `status='paid'` cuando el toggle está en Comprobante, igual que antes. El título de la página ahora distingue "Editar factura" vs "Editar comprobante"
- **`panel/facturas/index.php`**: el botón "Editar" ahora aparece para cualquier estado (antes solo para Borradores), sin tocar el resto de los botones de acción (Marcar enviada/pagada/vencida, Eliminar, Anular siguen con sus mismas condiciones)
- **Fix visual en `panel/facturas/ver.php`**: la nota "Tasa aplicada: 1 USD = X DOP" (agregada en el cambio anterior) tenía `margin-top:-1rem`, lo que la hacía superponerse con la línea del Total en la vista imprimible/PDF, dejando el texto apilado e ilegible. Se quitó el margen negativo — ahora la nota queda debajo del Total sin solaparse. Verificado con un mock estático en navegador usando los mismos datos del comprobante que reportó el problema

### 2026-07-18 (3) — Facturas en Dólares o Pesos Dominicanos con tasa en vivo
- **`panel/setup.php`**: agrega columnas `currency VARCHAR(3) DEFAULT 'USD'` y `exchange_rate DECIMAL(10,4) NULL` a `invoices`
- **`panel/includes/exchange_rate.php`** (nuevo): `fetchLiveExchangeRate()` consulta `https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json` (gratuito, sin API key — misma fuente que usa `finance.anonimustradelive.com` en `anonimusfinance-transfer/anonimusfinance/src/lib/exchange.ts`), con `https://latest.currency-api.pages.dev/v1/currencies/usd.json` como respaldo y `60.5` como fallback fijo si ambas fallan. Sin caché propia — se consulta solo cuando el admin activa/actualiza la moneda Pesos, no en cada carga de página
- **`panel/facturas/nueva.php`**: nuevo toggle **Dólares / Pesos dominicanos** (mismo patrón visual que Factura/Comprobante). Al elegir Pesos aparece el campo "Tasa de cambio" con botón "🔄 Actualizar" que trae la tasa en vivo vía AJAX (`action=get_exchange_rate`) y la deja editable por si el admin quiere ajustarla. Todos los precios calculados en vivo desde el catálogo de ads (Deluxe, Premium, addons, Spots) se multiplican por la tasa tanto en el JS (`applyLiveCalc()`) como en el PHP server-side (`calcCatalogPrice()`, que ahora recibe `$rateMul` y `$symbol`) — los ítems genéricos se escriben directamente en la moneda elegida, sin conversión. Al editar una factura ya guardada, los precios NO se recalculan automáticamente (se respeta lo guardado, igual que ya pasaba con USD) — solo se recalculan si el admin cambia de moneda o toca la tasa manualmente
- **`panel/facturas/ver.php`**: símbolo dinámico (`$` o `RD$`) en toda la tabla y totales; muestra "Tasa aplicada: 1 USD = X.XXXX DOP" debajo del total cuando la factura es en pesos
- **`panel/facturas/index.php`**: listado muestra el símbolo correcto junto al total y una etiqueta "DOP" cuando aplica
- Verificado con un mock estático en navegador (fetch simulado): Deluxe $1,900 → RD$118,456.83 con tasa 62.3457; Spot Pico 12/mes con nota de ahorro "−10% · ahorras RD$7,481.48"; Bundle 15% recalculado sobre el subtotal en pesos; vuelta a USD revierte todo correctamente a los valores originales
- ⚠️ **Pendiente tras el deploy:** volver a visitar `panel.anonimustradelive.com/setup.php` una vez (agrega `currency`/`exchange_rate` a `invoices`), luego borrarlo del servidor de nuevo

### 2026-07-18 (2) — Catálogo, tipos de servicio editables y motor de descuentos en Facturación
- **`ads/index.html`**: único cambio funcional — las claves del objeto `const PRECIOS = {...}` ahora van entre comillas (JSON válido). Cero cambio de comportamiento en el navegador; verificado que la calculadora pública sigue calculando idéntico ($2,601 en un escenario Deluxe+addon+Spot Pico 8/mes con Bundle, antes y después del cambio)
- **`panel/includes/ads_pricing.php`** (nuevo): lee `ads/index.html` en tiempo real, extrae el bloque `PRECIOS` con regex y lo parsea con `json_decode()`. Prueba 3 rutas candidatas (constante `ADS_INDEX_PATH` si está definida en `config.php`, mismo árbol de archivos, o docroot hermano de producción) para funcionar tanto en local como en el hosting real donde `panel.` y el dominio principal son document roots separados. Así, si cambian los precios en `ads/index.html`, el módulo de Facturación los refleja automáticamente — **no hay copia duplicada que mantener sincronizada**
- **`panel/setup.php`**: agrega tablas `service_types` (tipos de servicio editables, reemplaza el ENUM fijo) y `catalog_items` (catálogo de productos, con `logic_type` que distingue ítems con precio automático de ads —`content_deluxe`, `content_premium`, `addon_publicidad_deluxe/premium`, `spot_inicio/pico/cierre`— de ítems `generic` sin ninguna regla especial). También altera `invoices` (`service_type` de ENUM a VARCHAR libre, + columna `doc_type`) e `invoice_items` (+ `catalog_item_id`, `frequency`, `line_note`). Semilla precargada con los 7 ítems de ads (Deluxe, Premium, 2 addons, 3 spots) y los 3 tipos de servicio originales
- **`panel/facturas/nueva.php`**: reescrito con selector de catálogo por línea (agrupado por tipo de servicio, con "✏️ Escribir manualmente" como opción libre), botones **"+ Nuevo"** (tipo de servicio) y **"+ Nuevo producto"** (catálogo, por línea) que guardan vía AJAX sin recargar la página. Al elegir un Spot aparece el selector de frecuencia (4/8/12/16/20 por mes) y el precio + nota de descuento ("−10% · ahorras $120") se calculan solos. Al detectar contenido + cintillo en la misma factura, autopuebla el descuento del 15% (Bundle) — el admin puede seguir ajustándolo a mano. Toggle **Factura / Comprobante**: Comprobante oculta la fecha de vencimiento y fuerza `status='paid'` al guardar. El precio de los ítems con lógica especial se **recalcula server-side al guardar** (nunca confía en lo que mande el navegador, por seguridad)
- **`panel/facturas/index.php`** y **`ver.php`**: listado muestra badge "✅ Comprobante" cuando aplica; la vista imprimible muestra "Factura" o "Comprobante" en el encabezado y la nota de descuento debajo de cada línea que la tenga
- **`panel/assets/style.css`**: estilos nuevos para el toggle Factura/Comprobante, los selects con botón "+ Nuevo" inline, y el bloque de cada línea de servicio (ahora con selector de catálogo + frecuencia arriba, campos editables abajo)
- **Bug encontrado y corregido durante la verificación**: el cálculo en vivo (JS) solo recalculaba precio para ítems tipo `spot_*`, dejando Deluxe/Premium/addons en $0 hasta guardar. Corregido en `applyLiveCalc()` para cubrir los 4 tipos de contenido también. Reverificado con un escenario Deluxe ($1,900) + Spot Pico 12/mes ($1,080, −10%) → subtotal $2,980, Bundle −$447, total $2,533 — exacto
- ⚠️ **Pendiente tras el deploy:** volver a visitar `panel.anonimustradelive.com/setup.php` una vez (la migración de tablas nuevas), luego borrarlo del servidor de nuevo

### 2026-07-17 — Migración reg/ → panel/registros/ + nuevo panel administrativo unificado
- **Motivo:** el plan de hosting no permite más subdominios. Para crear `panel.anonimustradelive.com` (herramientas administrativas, empezando por Facturación) hubo que **eliminar el subdominio `reg.anonimustradelive.com`** y liberar el slot, así que el bot de registro se **migró** (no se duplicó) hacia adentro de la nueva estructura de `panel/`
- `panel/registros/webhook.php`: copia exacta de `reg/webhook.php`, solo cambia la ruta del `require` (`../config.php`) y las URLs de notificación al admin (ahora apuntan a `https://panel.anonimustradelive.com/registros/`)
- `panel/registros/index.php`: migración quirúrgica de `reg/index.php` (1126 líneas) — se reemplazó el bloque de auth/DB propio por los includes compartidos de panel (`includes/auth.php`, `includes/db.php`) y el `<!DOCTYPE html>...<nav>` propio por el nav compartido (`includes/nav.php` + `includes/footer.php`). El `<style>` completo, ambos modales (notas y chat), toda la lógica PHP (AJAX de chat, notas, acciones KYC/migración/aceptar/rechazar) y ambos `<script>` quedaron **byte-idénticos** — verificado con diff ignorando solo la reindentación
- `panel/registros/setup.php`: migrado igual (solo cambia la ruta del config)
- `reg/login.php` y `reg/logout.php`: **retirados** — Registros ahora usa el login único de `panel/` (`$_SESSION['panel_admin']`)
- `reg/fix_zoomex_platform.php`: **no migrado** — ya había cumplido su propósito (los 2 registros corruptos ya se repararon en la sesión anterior)
- `panel/config.example.php`: agregadas las constantes `BOT_TOKEN`, `ADMIN_TG_ID`, `COMMUNITY_CHAT_ID` (antes solo en `reg/config.php`)
- `panel/includes/nav.php`: el tab "Registros" ahora enlaza a `/registros/` (antes `/registros.php`, el viejo wrapper de iframe — **eliminado**, ya no hace falta envolver nada porque el código vive nativamente adentro)
- `.cpanel.yml`: quitado el bloque de deploy a `reg.anonimustradelive.com`; agregado deploy de `panel/registros/*.php`
- **`reg/` eliminado por completo** del repositorio (los 6 archivos, incluyendo los ya retirados) — todo migrado o retirado intencionalmente
- Respaldo completo de `reg/` + `panel/` (incluyendo `reg/config.php` con los secretos reales, que nunca estuvo en git) guardado fuera del repo en `anonimustradelive_backup_reg_panel_<timestamp>/` antes de borrar nada
- ✅ **Corte completado el 2026-07-18** — subdominio migrado, webhook re-registrado y verificado, todo probado en producción. Ver sección "Runbook de corte" más abajo para el detalle.

### 2026-07-15 (3) — Fix registros Zoomex corruptos (platform vacío)
- Bug detectado: 2 registros (`El Mejorrrrr`, `Angel`) llegaron eligiendo Zoomex en el bot **antes** de que el usuario corriera `setup.php`. Como el ENUM `platform` todavía no incluía `'zoomex'`, MySQL en modo no estricto guardó `platform=''` en vez de dar error — por eso no aparecían con el tag "Zoomex" ni al filtrar por esa plataforma en el panel admin
- `reg/fix_zoomex_platform.php`: script de un solo uso (mismo patrón que `setup.php`) que 1) asegura el ALTER del ENUM y 2) busca registros con `platform` inválido/vacío y los repara a `'zoomex'` (es la única plataforma que puede producir este bug, ya que pepperstone/bingx/bitunix siempre fueron valores válidos del ENUM)
- `.cpanel.yml`: agregada línea de deploy para `fix_zoomex_platform.php`
- ⚠️ **Pendiente:** visitar `reg.anonimustradelive.com/setup.php` primero, luego `reg.anonimustradelive.com/fix_zoomex_platform.php` una vez para reparar los registros afectados. Ambos scripts son de un solo uso — se pueden eliminar del servidor después de confirmar que funcionaron

### 2026-07-15 (2) — Zoomex como tercer exchange en el bot de registro
- `reg/webhook.php`: nueva opción "Zoomex" en el paso de selección de exchange crypto (`asset_crypto`), en paralelo con BingX (con KYC) y Bitunix (sin KYC). Zoomex se trata igual que Bitunix — **sin verificación KYC**
- Nuevo `case 'platform_zoomex'` con el link de referido `https://partner.zoomex.com/aff/ZX904826`, mismo patrón que `platform_bitunix`
- Los 4 mapas `$plabels` del archivo (confirmación, corrección de datos, notificación al admin) actualizados con `'zoomex' => 'Zoomex'`
- `reg/index.php`: filtro de plataforma en el panel admin incluye Zoomex; `$ref_urls` para el botón "⛔ UID no referido" incluye el link de Zoomex. El botón "📋 KYC" sigue condicionado solo a `platform === 'bingx'`, por lo que no aplica a Zoomex (correcto, sin KYC)
- `reg/setup.php`: ENUM `platform` extendido a `'zoomex'` en el `CREATE TABLE` (instalación nueva) y agregado un `ALTER TABLE MODIFY COLUMN` (instalación existente)
- ⚠️ **Pendiente tras el deploy:** visitar `reg.anonimustradelive.com/setup.php` una vez para que corra el ALTER y la DB acepte `platform='zoomex'` — si no, los registros con Zoomex fallarán al insertarse

### 2026-07-15 — Nuevo partner Zoomex en bio
- `bio/index.html`: nueva tarjeta "Abrir cuenta en Zoomex" (`https://partner.zoomex.com/aff/ZX904826`), colocada justo debajo de Pepperstone en la sección Apóyanos
- `images/Zoomex_logo_black-and-green.png`: logo tipo wordmark (1160×342, fondo sólido #05080A, acento teal #0BD9BD) — a diferencia de los demás logos del bio (cuadrados con transparencia), este necesitó tratamiento especial
- Nueva variante `.bio-link.v-teal` + variables `--teal: #0BD9BD` y `--zoomex-bg: #05080A`: el fondo de la tarjeta se fijó al mismo color exacto del fondo del logo (muestreado con PIL) para que quede "seamless" sin caja/borde visible alrededor del wordmark
- Nueva clase `.bio-link-icon--wide` (height:26px, width:auto, max-width:110px) para logos wordmark anchos — el patrón `.bio-link-icon` normal (34×34 cuadrado) solo sirve para logos cuadrados/transparentes como Pepperstone
- `.claude/launch.json`: `ads-preview` cambiado de `--directory "."` (relativo, ambiguo según cwd — a veces resolvía a `ads/` en vez de la raíz del proyecto) a ruta absoluta del proyecto, para que sirva correctamente tanto `ads/` como `bio/` e `images/`
- Regla para futuros partners con logo tipo wordmark (no cuadrado/transparente): muestrear el color de fondo del PNG con PIL (`im.getpixel()`) y usarlo como `background` exacto de la tarjeta en vez de intentar aproximarlo a ojo

### 2026-07-01 — Rediseño oferta de contenido orgánico + paleta mono-marca
- `ads/index.html`: nueva oferta de contenido orgánico, reemplazando Plan Premium/Deluxe antiguos (por videos/semana) por dos paquetes con propósito distinto:
  - **Deluxe** ("Tope de gama") — alta producción, 2 videos/mes, $1,900/mes. Es el ancla cara (no se busca vender, pero si se vende está bien remunerado)
  - **Premium** ("Más popular") — máximo alcance, 4 videos/mes, $550/mes. Es el paquete que sí se quiere vender (mejor deal, más barato que antes)
  - Ambos con checkbox opcional **"Derecho a publicidad pagada"** (+$400 cada uno, antes Deluxe era +$700, unificado a pedido del usuario) — consentimiento para uso comercial/pauta pagada de la imagen, distinto del uso orgánico incluido
  - Botón ⓘ con popover explicando el derecho a publicidad pagada en cada tarjeta
- **Zona 4 nueva**: sección explicativa fija debajo del calculador — "¿Orgánico o publicidad pagada?" con: explicación, ejemplo hipotético (💡), aviso legal de infracción de derechos de autor/imagen si se usa sin permiso (⚠️), y cierre de buena fe (🤝). Solo informativa, sin interacción, para no romper el flujo 1-2-3
- **Paleta rediseñada a mono-marca** (antes 6 colores compitiendo: azul+dorado+púrpura+naranja+rojo+verde): ahora neutro + azul como único acento en toda la calculadora. Tiers y spots se diferencian por insignia (`.c-tier-badge`) o ícono, no por color de tarjeta. Checkbox "off" y precio nunca se pintan de color de marca (evita que la opción nula/$0 compita visualmente con el CTA)
- Spots (columna 2) rediseñados para ser pixel-consistentes con columna 1: mismo tamaño de fuente, mismo fondo "off" (`var(--bg3)`), botón ⓘ en posición fija (pie de tarjeta con borde punteado, como el addon de publicidad pagada) en vez de inline (evita que dependa del largo del nombre/badge)
- Distribución de info en spots: horario en negro (peso 600, igual que "Derecho a publicidad pagada"), frecuencia mensual en gris — todo reservado con `min-height` para que la tarjeta no cambie de tamaño al activar/desactivar
- `.c-tier-badge`: `margin-left:5px` en desktop (como antes), `margin-left:0` solo en mobile (`@media max-width:900px`) para que al saltar de línea quede alineado al inicio del nombre
- **Validación del CTA**: "Solicitar propuesta" ahora bloquea la navegación del mailto y muestra un aviso en rojo ("Selecciona al menos un servicio...") si el usuario no seleccionó nada; se oculta automáticamente al hacer una selección (`validateCtaClick()`)
- Altura fija de las 3 columnas del calculador (crítica, costó horas ajustar originalmente): se mantiene estable en **515px** (subió ligeramente de 510px por el pie de tarjeta nuevo en spots, verificado sin salto entre estado off/on)
- Orden de tarjetas en sección Formatos invertido: Contenido orgánico primero (destacada), Cintillo en vivo segundo
- Emojis quitados de nombres de spots (Spot Inicio/Pico/Cierre) por pedido explícito — "quita profesionalismo"

### 2026-06-28 (4)
- `ads/index.html`: descripción de planes cambiada a dos líneas con `<br>` en mobile (evita punto flotante al hacer wrap)

### 2026-06-28 (3)
- `ads/index.html`: precio total muestra "USD" como label pequeño junto al monto
- Dentado del recibo en mobile: restaurado (se había ocultado con `display:none`), zigzag más prominente (20px en lugar de 16px), más padding arriba y abajo, `border-bottom-radius:11px` en `.sum-receipt` para respetar el `border-radius` del card

### 2026-06-28 (2)
- `ads/index.html`: botones "Solicitar propuesta" y "Enviar correo" ahora generan el cuerpo del correo dinámicamente con la selección del usuario (plan, spots, frecuencias, descuentos, total)
- Función `updateMailtoLinks()` llamada al final de `recalc()` — actualiza el `href` de `.sum-cta` y `.contact-email` con `?subject=...&body=...` URL-encoded

### 2026-06-28 (1)
- `ads/index.html`: animación upsell corregida — `max-height` + `opacity` CSS transition; el botón precio/CTA desliza suavemente cuando aparece/desaparece el mensaje de bundle
- Fix: etiqueta de frecuencia en desglose mostraba "5/sem/sem" (doble sufijo) → corregido a "5/sem"
- Botón de contacto cambiado de mostrar el email a decir "Enviar correo"
- Spot Pico: quitada estrella ⭐ del nombre
- Planes renombrados: **Plan Premium** (👑, fondo dorado) y **Plan Deluxe** (💎, fondo violeta) con hover intenso por color
- Descripciones de spots actualizadas: Inicio y Cierre → "Audiencia moderada"; Pico queda como "Mayor audiencia"
- Spots con color propio: Inicio=naranja, Pico=rojo, Cierre=verde; cada card tiene fondo tintado + hover intenso igual que los planes
- Especificaciones rediseñadas: "Formato fijo: PNG, JPG · sin transparencia" y "Formato animado: GIF, MP4" (eliminado "Consultar disponibilidad")
- Stream mock actualizado a `cintillo_pana.gif`
- Galería cintillos reordenada: Prado → PP → Pana

### 2026-06-27 (3)
- `libros/index.html`: nueva página `/libros` — biblioteca de libros recomendados
- Mismo diseño que `/bio` (dark theme, Montserrat, colores purple/red)
- Sección Top 5 con tarjetas doradas numeradas (1-5) con tagline de por qué leerlos y en qué orden
- Lista completa organizada en 5 categorías: Psicología/Mentalidad, Gestión de Riesgo, Análisis Técnico, Mentalidad Ganadora, Joyas Ocultas (28 libros total)
- Links Amazon afiliados tomados de `commands/libros.php`
- CTAs al final: Únete a la comunidad (Telegram) + Agendar asesoría (Calendly)
- `.cpanel.yml`: agregado deploy de `libros/index.html`

### 2026-06-27 (2)
- `ads/index.html`: rediseño completo del Paso 2 del calculador (Cintillos en vivo)
- **Eliminado**: timeline visual, tabs de spot, slider único compartido (noUiSlider), CDN links de noUiSlider
- **Nuevo**: 3 tarjetas de spot independientes (Inicio/Pico/Cierre), cada una con control de 6 píldoras (Off · 4 · 8 · 12 · 16 · 20) y badge de precio+descuento+frecuencia en tiempo real
- **Colores por spot**: Inicio=verde (#16a34a), Pico=ámbar (#d97706), Cierre=rojo (#dc2626); borde y sombra de la tarjeta se activan al seleccionar frecuencia
- **JS**: `setFreq()` + `updateBadge()` reemplazan toda la lógica de slider; PRECIOS cintillos ahora usa claves mensuales (0,4,8,12,16,20); `cinDiscount()` usa precio en 4/mes como base para calcular ahorro
- **Acceptance test verificado**: Intensivo + los 3 spots a 20/mes → Subtotal $4,520, Bundle −$678, **Total $3,842** ✓

### 2026-06-27 (1)
- `ads/index.html` + `ads/ads_img/`: múltiples cambios visuales y de copy
- **Hero**: imagen real del live como fondo (`hero_img_2.png`), gradient horizontal izquierda→derecha para legibilidad del texto
- **Audience strip**: género separado en "83–87% Hombres · 13–17% Mujeres"; stat débil (22 min) reemplazada por "532K+ vistas totales YouTube+TikTok+IG"; bandera dominicana como SVG inline en desktop (emoji en mobile)
- **Fan deck reels**: imágenes reales, links directos a cada reel de IG, overlay estilo IG con contador de vistas siempre visible + likes/comentarios con slide-in en hover
- **Stream mock**: captura real del live + cintillo `cintillo_pp.gif` animado
- **Galería cintillos**: 3 cintillos reales (Pana, PP, Prado)
- **Copy**: proofreading general — frases más cortas y directas; em dashes (—) reemplazados por comas; "piezas" → "videos"; pitch y "Ideal si" del cintillo reescritos
- **Borde rasgado recibo**: efecto zigzag CSS (`linear-gradient` triangulares) en la separación del área de totales del calculador
- `ads/ads_img/cintillo_pp.gif`: convertido desde MP4 con ffmpeg (960×108, 15fps, paletteuse), CSS hace stretch a 100%

### 2026-06-26 (9)
- `ads/index.html`: horario corregido a 8:30 AM (hero kicker + timeline); tabs de spot con color por spot: Inicio=verde, Pico=ámbar, Cierre=rojo — borde izquierdo + nombre + fondo activo tintado, coherencia con pins del timeline
- `.claude/launch.json` (raíz): agregada config `ads-preview` en puerto 5501 sirviendo `ads/` con `python -m http.server --directory` para que el preview funcione desde la sesión raíz

### 2026-06-26 (8)
- `ads/index.html`: 5 correcciones de layout y UX en el calculador
- Layout calculador: paso 1 (contenido) ahora ocupa fila completa; paso 2 + resumen en grid lado a lado (1fr + 340px) — mejor uso del espacio horizontal
- Orden de tabs corregido: Inicio | Pico | Cierre (coincide con el orden de izquierda a derecha del timeline)
- Slider thumb: `.noUi-horizontal .noUi-handle` con `box-sizing:border-box; padding:0` — círculo perfecto 24×24px
- Slider pips: `padding: 4px 6px 2.5rem` en `.slider-wrap` para que Off/1×/2×… no sean cortados
- Responsive: breakpoint 1024px (340px panel), 900px (stacked), 660px (spot-tabs en columna)

### 2026-06-26 (7)
- `ads/index.html`: pasada de profundidad visual — degradados, sombras en capas, hover lift
- Botones (nav-cta, hero-cta, sum-cta): gradiente azul 135°, sombra azul, translateY(-1/-2px) en hover
- Formato cards: sombra en capas, lift de 3px en hover; card destacada (contenido orgánico) con gradiente más profundo y sombra azul
- c-opt y spot-tab activos: gradiente suave EFF6FF→DBEAFE con sombra
- sum-total-box: gradiente + inset highlight
- calc-summary: sombra con tinte azul
- specs-card: sombra con tinte azul
- step-num y tag-blue: gradiente azul claro

### 2026-06-26 (6)
- `ads/index.html`: selector de spot + slider único activo en paso 2 del calculador
- Reemplazados 3 sliders separados por: timeline visual + 3 tabs de selector + 1 slider
- Timeline: barra de reproducción con pins coloreados (Inicio=verde 10%, Pico=ámbar 42%, Cierre=rojo 90%), clickeables
- Tabs: muestran estado actual de cada spot ("Off" o "2×/sem · $532/mes" en verde), se actualizan al mover el slider
- Pips del slider simplificados a "Off / 1× / 2× / 3× / 4× / 5×" para evitar texto apretado
- Estado 0 del slider muestra instrucción en cursiva; >0 muestra frecuencia + precio + descuento

### 2026-06-26 (5)
- Rediseño completo `ads/index.html` estilo Preline UI con noUiSlider
- Fuente cambiada de Montserrat a Inter (Google Fonts)
- Sliders nativos `<input type="range">` reemplazados por noUiSlider v15.8.1 (jsDelivr CDN)
- noUiSlider: pips etiquetados "Off / 1×/sem / 2×/sem / 3×/sem / 4×/sem / 5×/sem", carril azul visible, thumb blanco con borde azul
- Estado inicial del slider: instrucción "Desliza para elegir con cuánta frecuencia semanal aparecer" en lugar de "Desactivado"
- Paleta Preline UI: cards blancas con `box-shadow` sutil, slate-900 para texto, slate-500 para muted
- Variable CSS nueva `--blue-lt: #EFF6FF` para estados activos
- Sección contacto rediseñada como card de degradado azul oscuro a azul (blanco sobre azul)
- Fan cards y toda la lógica de precios (PRECIOS, recalc, selectContent) sin cambios

### 2026-06-26 (4)
- Rediseño y compactación de `ads/index.html`: de 6 secciones a 5
- Eliminadas: sección Audiencia completa, accordion de Servicios, sección Bundle separada
- Añadidas: strip de 4 estadísticas de audiencia (compacto, debajo del hero), sección Formatos (2 tarjetas: Cintillo vs Contenido), sección Visuales (fan deck + mockup livestream + galería cintillos + specs)
- Hero: soporte para imagen de fondo (placeholder gradient, comentario con instrucciones para reemplazar con foto real)
- Sección Formatos: explica diferencia clave — cintillo=solo exposición, contenido=endorsement+IG collab aparece en perfil del cliente
- Sección Visuales: 3 tarjetas fan-deck (top reels con hover, placeholders listos para imágenes), mockup 16:9 con cintillo en 20% inferior, galería de 3 cintillos (1920×216), card de specs técnicas
- Textos más grandes (mínimo 0.82rem), flujo más claro para adultos
- Próximo paso: usuario pasa imágenes (capturas de reels, foto del live, cintillos existentes, captura de stream)

### 2026-06-26 (3)
- Actualización de métricas de plataformas en `ads/index.html` con datos reales de junio 2026
- YouTube: 76.4K vistas totales, 5.9K espectadores mensuales, 18 viewers simultáneos promedio en live, pico 60, 22 min duración promedio
- TikTok: 284.2K vistas (12 meses), 97.4K viewers únicos, 3.9K seguidores
- Instagram: 171.7K vistas, 73.5K cuentas alcanzadas
- Demografía consolidada: 83-87% masculino, ~50% de 25-34 años, top países: RD, EE.UU., Venezuela, Colombia, México
- Proof items actualizados: crecimiento TikTok (0→3.9K en 6 meses), 22 min promedio en live, datos de edad/nicho

### 2026-06-26 (2)
- Rediseño completo `ads/index.html`: psicología de color, "Don't Make Me Think", funnel B2B
- Paleta: navy oscuro (#06101E) + azul eléctrico (#2563EB) como acento principal, rojo solo en logo
- Estructura nueva: Hero → Calculador (sección 2) → Audiencia → Servicios accordion → Bundle → Contacto
- Fix: Spot Inicio cards mostraba $200/mes → corregido a $280/mes (el correcto)
- Fix: flecha eliminada del botón "Ver calculador" en nav
- Tablas de precios dentro de accordion colapsable (menos fricción antes del calculador)
- Stats de plataformas PENDIENTES — el usuario dará los datos actualizados

### 2026-06-26
- Nueva página `ads/index.html` — landing page de publicidad en `anonimustradelive.com/ads`
- `.cpanel.yml`: agregadas líneas para `ads/` (mkdir + cp)

### 2026-06-25 (4)
- Chat de soporte: botón con 3 estados visuales — 💬 gris (inactivo) / 🟢 verde (activo, último mensaje nuestro) / 🟠 naranja (pendiente, último mensaje del usuario sin responder)
- Estado se actualiza en tiempo real: al recibir mensaje del usuario → naranja, al enviar → verde
- Poll de 20s en background actualiza todos los estados de la tabla

### 2026-06-25 (3)
- Chat de soporte: agregados 5 botones de plantillas (🔢 ID, 🔄 Migración, 📋 KYC, ⏳ Recordatorio, ✅ Cierre)
- Al hacer click en una plantilla, el texto se carga en el textarea para revisión/edición antes de enviar

### 2026-06-25 (2)
- Nuevo: sistema de chat de soporte bilateral en el panel de registro
- `support_messages` tabla nueva (registration_id, direction in/out, message, leido)
- Campo `support_active TINYINT` en tabla `registrations`
- `reg/webhook.php`: intercepta mensajes cuando `support_active=1` → guarda en DB + notifica admin. Si usuario manda imagen/archivo → warning "solo texto permitido"
- `reg/index.php`: botón 💬 Chat en cada fila (verde cuando activo, badge rojo con conteo no leídos), modal de chat con burbujas, polling cada 4s, badges se refrescan cada 20s en segundo plano
- `reg/setup.php`: migración agregada (ALTER + CREATE TABLE IF NOT EXISTS)
- ⚠️ Después de deploy, navegar a `reg.anonimustradelive.com/setup.php` para crear columna y tabla, luego eliminarlo

### 2026-06-25
- Fix: `/libros` desde el grupo ahora responde en ese mismo grupo e hilo (`$thread_id` capturado en router)
- Fix crítico: `commands/` no estaba en `.cpanel.yml` — los archivos nunca llegaban al servidor. Agregado `mkdir -p commands/` + `cp` de los 3 archivos
- Regla: cada archivo nuevo en `commands/` requiere su línea correspondiente en `.cpanel.yml`

### 2026-06-17
- Refactorización de `telegram-auth-webhook.php` a estructura modular (router + commands/)
- Restaurados comandos `/libros`, `/librosp`, `/toplibros`, `/toplibrosp` en `commands/libros.php`
- Actualizado horario de live en bio: 8:30 AM – 12:00 PM ET
- Reordenados links del bio: Comunidad → Mentoría → Correo → Fondeo → Apóyanos
- Agregados FundingPips y The5ers a bio y al panel admin (enlace rechazo UID no referido)
- Bot de registro: 7 mejoras de flujo (confirmación antes de registrar, estados con botones, rechazado verifica DB, placeholder warning, mejora texto aprender, contexto de tiempo, hint /start)
- Bot de registro: botón `⛔ UID no referido` en panel admin (solo exchanges)
