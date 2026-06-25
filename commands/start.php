<?php
// Comando: /start, /inicio
// Vars disponibles: $TG_TOKEN, $user_id

sendMessage($TG_TOKEN, $user_id,
    "👋 <b>Hola!</b> Soy el bot de AnonimusTrade Live.\n\n" .
    "Comandos disponibles:\n\n" .
    "<b>/acceso</b> — Link de acceso a las herramientas premium\n" .
    "<b>/libros</b> — Biblioteca completa de libros de trading\n" .
    "<b>/toplibros</b> — Top 5 libros esenciales\n\n" .
    "Envíame el comando que necesites 👇"
);
