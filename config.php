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
    'bot_token' => getenv('BOT_TOKEN') ?: '9238733949:AAHwK7yZddKXH95fYiOpOhMYuf65y1dnsg0',
    'chat_id'   => getenv('CHAT_ID')   ?: '-5465887052'
];
