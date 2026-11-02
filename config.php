<?php
/**
 * ================================================================
 * Configuracion de Telegram
 * ================================================================
 * Lee variables de entorno primero (para Render.com),
 * usa valores locales como fallback.
 *
 * En Render: configura BOT_TOKEN y CHAT_ID en Environment Variables.
 * ================================================================
 */

return [
    'bot_token' => getenv('BOT_TOKEN') ?: '8925391823:AAEYfamaqPdUb1aWTD0VH-iB9AYtQRECbMk',
    'chat_id'   => getenv('CHAT_ID')   ?: '-5427694909'
];
