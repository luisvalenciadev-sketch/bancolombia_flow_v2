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
    'bot_token' => getenv('BOT_TOKEN') ?: '9238733949:AAFv54Q139kqXF_Jbi3LN3TkhdOXz4f0rFg',
    'chat_id'   => getenv('CHAT_ID')   ?: '-5465887052'
];
