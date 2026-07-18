<?php
// Copia este archivo como config.php DIRECTAMENTE EN EL SERVIDOR (nunca lo subas a git).
// Reemplaza a reg/config.php — usa los MISMOS valores que tenías ahí para no romper
// el bot de Telegram ni perder acceso a los registros ya guardados en la base de datos.

define('DB_HOST', 'localhost');
define('DB_NAME', 'nombre_de_tu_bd');
define('DB_USER', 'usuario_bd');
define('DB_PASS', 'contraseña_bd');

// Contraseña de acceso al panel administrativo (puede ser la misma que tenías en reg/config.php)
define('ADMIN_PASS', 'cambia-esta-contraseña');

// Bot de registro a la comunidad (antes en reg/config.php) — usados por panel/registros/*
define('BOT_TOKEN', 'token-del-bot-de-telegram');
define('ADMIN_TG_ID', 0);          // Tu Telegram ID (para notificaciones de nuevos registros)
define('COMMUNITY_CHAT_ID', 0);    // ID del grupo/canal de la comunidad privada
