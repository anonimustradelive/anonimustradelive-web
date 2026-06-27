# AnonimusTrade Live — Contexto para Claude Code

Este documento contiene todo lo necesario para continuar el desarrollo del sitio y bots de **AnonimusTrade Live** sin perder contexto entre sesiones.

> ⚠️ **Actualizar este archivo después de cada cambio importante.**

---

## Sobre el proyecto

**AnonimusTrade Live** es una comunidad de trading en vivo dominicana fundada por **Richard Cejas** y **Ridolfi Mosquea**. Anti-gurú, sin filtros, análisis real con entradas en vivo.

- **Sitio principal:** https://anonimustradelive.com
- **Bio/Linktree:** https://anonimustradelive.com/bio/
- **Bot de registro:** https://reg.anonimustradelive.com
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
├── reg/                       ← 🤖 Bot de registro a la comunidad
│   ├── config.php             ← ⛔ GITIGNOREADO
│   ├── webhook.php            ← bot de registro (máquina de estados)
│   ├── index.php              ← panel admin de registros
│   ├── login.php              ← login del panel admin
│   ← logout.php
│   └── setup.php              ← migraciones DB (ejecutar una vez, luego borrar)
│
├── bio/
│   └── index.html             ← página tipo linktree
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
└── Asistente/
    └── contexto-anonimustradelive.md  ← contexto del show (no tocar)
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

// Bot de registro (reg/config.php — también gitignoreado)
BOT_TOKEN          // token del bot de registro
DB_HOST/NAME/USER/PASS  // credenciales MySQL
ADMIN_TG_ID        // Telegram ID del admin (Richard)
COMMUNITY_CHAT_ID  // ID del grupo para invitar usuarios
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

### 2. Bot de registro (`reg/webhook.php`)

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
`platform_bingx`, `platform_bitunix`, `ya_tengo_pepperstone`,
`migr_confirmado`, `reg_confirmar`, `reg_corregir`,
`kyc_done`, `migr_notify`

**Plataformas soportadas:**
- Pepperstone (broker Forex, requisito de acceso a la comunidad)
- BingX (exchange crypto, requiere KYC)
- Bitunix (exchange crypto, sin KYC)

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

## Base de datos MySQL (bot de registro)

### Tabla `registrations`
| Campo | Tipo | Notas |
|---|---|---|
| id | INT AUTO_INCREMENT | |
| telegram_user_id | BIGINT | |
| telegram_name | VARCHAR(255) | |
| telegram_username | VARCHAR(255) | |
| profile_type | ENUM('principiante','trader') | |
| asset_type | ENUM('crypto','tradicional') | NULL para principiantes |
| platform | ENUM('pepperstone','bingx','bitunix') | |
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

## Panel Admin (`reg/index.php`)

**URL:** https://reg.anonimustradelive.com/index.php

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

## Bio page (`bio/index.html`)

**Orden actual de links:**
1. Comunidad — bot de Telegram
2. Mentoría — Calendly 1:1
3. Correo — anonimustradelive@outlook.com
4. Cuentas de fondeo — FundingPips · The5ers
5. Apóyanos — Pepperstone, BingX, Bitunix, Pana
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
| Pana | https://pana.go.link/am7IP | Fintech para retiros USDC |
| FundingPips | https://app.fundingpips.com/register?referral_code=f225e2bb | Prop firm |
| The5ers | https://www.the5ers.com/?afmc=xmw | Prop firm |
| Surfshark | https://surfshark.club/friend/2EHfq785 | VPN (recomendado para EE.UU./UE) |

---

## Reglas de desarrollo

1. **NUNCA subir a GitHub:** `config.php`, `reg/config.php`, `auth-tokens.json`, `donors.json`, `crypto_seen_txs.json`
2. **Siempre hacer commit + push + deploy en cPanel** tras cada cambio
3. **MarkdownV2 en Telegram:** usar siempre `mdEscape()` para contenido dinámico; nunca `htmlspecialchars()`
4. **Estructura modular del bot:** nunca poner lógica de negocio en el router; usar `commands/`
5. **Actualizar este archivo** (`CLAUDE_CONTEXT.md`) después de cada cambio relevante

---

## Historial de cambios recientes

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
