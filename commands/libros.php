<?php
// Comandos: /libros, /librosp, /toplibros, /toplibrosp
// Vars disponibles: $TG_TOKEN, $TG_CHAT_ID, $TG_THREAD_ID, $cmd, $user_id, $chat_type

$lista_completa =
    "📚 <b>Biblioteca AnonimusTrade Live</b>\n" .
    "Libros esenciales para traders serios.\n\n" .
    "Usa /toplibros para ver el Top 5 imprescindible.\n" .
    "━━━━━━━━━━━━━━━━━━━━\n\n" .

    "🧠 <b>Psicología y Mentalidad del Trader</b>\n" .
    "• <a href=\"https://amzn.to/3RBpGGQ\">Trading en la zona</a> — Mark Douglas\n" .
    "• <a href=\"https://amzn.to/437unun\">El trader disciplinado</a> — Mark Douglas\n" .
    "• <a href=\"https://amzn.to/4uJPT4F\">Vivir del trading</a> — Alexander Elder\n" .
    "• <a href=\"https://amzn.to/4dFFcdh\">La psicología del dinero</a> — Morgan Housel\n" .
    "• <a href=\"https://amzn.to/4nWojhP\">Pensar rápido, pensar despacio</a> — Daniel Kahneman\n" .
    "• <a href=\"https://amzn.to/4fduegj\">The Inner Voice of Trading</a> — Michael Martin\n" .
    "• <a href=\"https://amzn.to/4u6gM1u\">Why You Win or Lose</a> — Fred Kelly\n" .
    "• <a href=\"https://amzn.to/4o5hLhe\">The Mental Game of Trading</a> — Jared Tendler\n" .
    "• <a href=\"https://amzn.to/4dZJvyZ\">Mastering the Trade</a> — John Carter\n" .
    "• <a href=\"https://amzn.to/4dW8xiE\">Hábitos atómicos</a> — James Clear\n\n" .

    "💰 <b>Gestión de Riesgo y Capital</b>\n" .
    "• <a href=\"https://amzn.to/4viHXXR\">Recuerdos de un Operador de Acciones en la Bolsa</a> — Edwin Lefèvre\n" .
    "• <a href=\"https://amzn.to/4vFlzID\">The Zurich Axioms</a> — Max Gunther\n" .
    "• <a href=\"https://amzn.to/3PMguPl\">Tener éxito en trading</a> — Van Tharp\n" .
    "• <a href=\"https://amzn.to/4a1Zawu\">The Art of Risk</a> — Kayt Sukel\n" .
    "• <a href=\"https://amzn.to/3RwGzm3\">¿Existe la suerte?</a> — Nassim Taleb\n" .
    "• <a href=\"https://amzn.to/437vJFt\">El Cisne Negro</a> — Nassim Taleb\n\n" .

    "📈 <b>Análisis Técnico y Estrategia</b>\n" .
    "• <a href=\"https://amzn.to/3PTyUxp\">Análisis técnico de los mercados financieros</a> — John Murphy\n" .
    "• <a href=\"https://amzn.to/49AlFsm\">Las velas japonesas</a> — Steve Nison\n" .
    "• <a href=\"https://amzn.to/4dFt7EW\">Cómo ganar dinero con las acciones</a> — William O'Neil\n" .
    "• <a href=\"https://amzn.to/4edcu3E\">Encyclopedia of Chart Patterns</a> — Thomas Bulkowski\n" .
    "• <a href=\"https://amzn.to/4x0sDRi\">The Art and Science of Technical Analysis</a> — Adam Grimes\n\n" .

    "🏆 <b>Mentalidad Ganadora y Alto Rendimiento</b>\n" .
    "• <a href=\"https://amzn.to/49z3seJ\">Mindset: la actitud del éxito</a> — Carol Dweck\n" .
    "• <a href=\"https://amzn.to/4vpjCjo\">El obstáculo es el camino</a> — Ryan Holiday\n" .
    "• <a href=\"https://amzn.to/49zDCHn\">Extreme Ownership</a> — Jocko Willink\n\n" .

    "💎 <b>Joyas Ocultas</b>\n" .
    "• <a href=\"https://amzn.to/4dFtP54\">Best Loser Wins</a> — Tom Hougaard\n" .
    "• <a href=\"https://amzn.to/4dFHaub\">The Daily Trading Coach</a> — Brett Steenbarger\n" .
    "• <a href=\"https://amzn.to/4fQSUv9\">Enhancing Trader Performance</a> — Brett Steenbarger\n" .
    "• <a href=\"https://amzn.to/3RwnZKO\">La biología de la toma de riesgos</a> — John Coates\n" .
    "• <a href=\"https://amzn.to/4uH8q1k\">Mente y mercados</a> — James Dalton\n" .
    "• <a href=\"https://amzn.to/49wactR\">The Art of Execution</a> — Lee Freeman-Shor\n\n" .

    "━━━━━━━━━━━━━━━━━━━━\n" .
    "💜 AnonimusTrade Live — Trading transparente, sin filtros.";

$top5 =
    "🏆 <b>Top 5 Libros — Lectura Obligatoria</b>\n" .
    "Léelos en este orden. Back to back.\n" .
    "━━━━━━━━━━━━━━━━━━━━\n\n" .

    "1️⃣ <a href=\"https://amzn.to/3RBpGGQ\">Trading en la zona</a>\n" .
    "    ✍️ Mark Douglas\n\n" .

    "2️⃣ <a href=\"https://amzn.to/4dW8xiE\">Hábitos atómicos</a>\n" .
    "    ✍️ James Clear\n\n" .

    "3️⃣ <a href=\"https://amzn.to/437unun\">El trader disciplinado</a>\n" .
    "    ✍️ Mark Douglas\n\n" .

    "4️⃣ <a href=\"https://amzn.to/4o5hLhe\">The Mental Game of Trading</a>\n" .
    "    ✍️ Jared Tendler\n\n" .

    "5️⃣ <a href=\"https://amzn.to/4dFFcdh\">La psicología del dinero</a>\n" .
    "    ✍️ Morgan Housel\n\n" .

    "━━━━━━━━━━━━━━━━━━━━\n" .
    "📚 Ver lista completa: /libros\n" .
    "💜 AnonimusTrade Live — Trading transparente, sin filtros.";

switch ($cmd) {
    case '/libros':
        if ($chat_type === 'private') {
            // Desde DM → publica en el grupo de la comunidad
            sendToGroup($TG_TOKEN, $TG_CHAT_ID, $lista_completa, $TG_THREAD_ID ?? '');
            sendMessage($TG_TOKEN, $user_id, "✅ Lista completa enviada al grupo.");
        } else {
            // Desde el grupo → responde en ese mismo grupo e hilo
            sendToGroup($TG_TOKEN, (string)$chat_id, $lista_completa, (string)($thread_id ?? ''));
        }
        break;

    case '/librosp':
        sendMessage($TG_TOKEN, $user_id, $lista_completa);
        break;

    case '/toplibros':
        if ($chat_type === 'private') {
            sendToGroup($TG_TOKEN, $TG_CHAT_ID, $top5, $TG_THREAD_ID ?? '');
            sendMessage($TG_TOKEN, $user_id, "✅ Top 5 enviado al grupo.");
        } else {
            sendToGroup($TG_TOKEN, (string)$chat_id, $top5, (string)($thread_id ?? ''));
        }
        break;

    case '/toplibrosp':
        sendMessage($TG_TOKEN, $user_id, $top5);
        break;
}
