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
