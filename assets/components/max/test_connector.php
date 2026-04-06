<?php

/**
 * Пример универсального коннектора для MODX
 * Файл: assets/components/maxbot/connector.php
 */

// Инициализация MODX API
define('MODX_API_MODE', true);
require_once dirname(__DIR__, 3) . '/index.php';
require_once __DIR__ . '/maxBotModx.class.php';

$bot = new maxBotModx(
    $modx,
    $modx->getOption('max_bot_name'),
    $modx->getOption('max_bot_token'),
    $modx->getOption('bot_subscribe_url')
);

// Регистрация вебхука
// Запуск: site.ru/connector.php?subscribe=1
if (isset($_GET['subscribe'])) {
    $bot->subscribe(); // Регистрирует URL из системных настроек
    exit('Вебхук обновлен!');
}

// Обработка входящих (Webhook)
$data = $bot->handleWebhook();

if (!empty($data['message'])) {
    $userId = $data['message']['sender']['user_id'];
    $text = trim($data['message']['body']['text']);

    // Команда регистрации админа
    if ($text === '/reg') {
        $setting = $modx->getObject('modSystemSetting', 'max_bot_admins');
        $admins = json_decode($setting->get('value'), true) ?: [];

        if (!in_array($userId, $admins)) {
            $admins[] = $userId;
            $setting->set('value', json_encode($admins));
            $setting->save();
            $modx->getCacheManager()->refresh(['system_settings' => []]);

            $bot->sendMessage($userId, "✅ Вы добавлены в список администраторов!");
        }
    }
}

// Внешняя отправка (API для сайта)
// Запуск: site.ru/connector.php?cmd=send&msg=Текст
if ($_REQUEST['cmd'] === 'send' && !empty($_REQUEST['msg'])) {
    $admins = json_decode($modx->getOption('max_bot_admins'), true);

    foreach ($admins as $adminId) {
        $bot->sendMessage($adminId, $_REQUEST['msg']);
    }
    exit('Сообщение разослано администраторам.');
}
