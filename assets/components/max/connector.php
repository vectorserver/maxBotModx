<?php
// Подключаем MODX
define('MODX_API_MODE', true);
require dirname(__DIR__, 3) . '/index.php';
/* @var  modX $modx */

$modx->getService('error', 'error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_FATAL);
$modx->setLogTarget(XPDO_CLI_MODE ? 'ECHO' : 'HTML');

require_once __DIR__ . '/maxbotmodx.class.php';

$bot_name = $modx->getOption('max_bot_name');
$bot_token = $modx->getOption('max_bot_token');
$bot_admins = $modx->getOption('max_bot_admins');
$bot_subscribe_url = $modx->getOption('bot_subscribe_url');

$bot = new maxBotModx(
    $modx,
    $modx->getOption('max_bot_name'),
    $modx->getOption('max_bot_token'),
    $modx->getOption('bot_subscribe_url')
);

// Для активации просто заходим на: connector.php?subscribe=1
if (isset($_GET['subscribe'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "--- Запуск процесса перерегистрации ---\n";

    // 1. Получаем список всех текущих подписок
    $current = $bot->getSubscriptions();

    if ($current['status'] === 200 && !empty($current['data']['subscriptions'])) {
        echo "Найдено активных подписок: " . count($current['data']['subscriptions']) . "\n";

        foreach ($current['data']['subscriptions'] as $sub) {
            $urlToDelete = $sub['url'];
            echo "Удаление: $urlToDelete ... ";

            // 2. Вызываем ваш метод удаления
            $delRes = $bot->deleteSubscription($urlToDelete);

            if ($delRes['status'] === 200 || $delRes['status'] === 204) {
                echo "OK\n";
            } else {
                echo "Ошибка (Код: {$delRes['status']})\n";
                // Если API ругается, выведем детали
                if (isset($delRes['data'])) print_r($delRes['data']);
            }
        }
    } else {
        echo "Список подписок пуст.\n";
    }

    // 3. Регистрируем новую подписку из настроек
    echo "\n--- Регистрация новой подписки ---\n";
    $finalRes = $bot->subscribe();

    if ($finalRes['status'] === 200 || $finalRes['status'] === 201) {
        echo "Успешно! Вебхук активен.\n";
        print_r($finalRes['data']);
    } else {
        echo "Ошибка при регистрации:\n";
        print_r($finalRes);
    }

    exit;
} else{
    $handleWebhook = $bot->handleWebhook();

    if (!empty($handleWebhook['message'])) {
        $userId = (string)$handleWebhook['message']['sender']['user_id'];
        $text = trim($handleWebhook['message']['body']['text']);
        $firstName = $handleWebhook['message']['sender']['name'] ?? 'Пользователь';

        $settingKey = 'max_bot_admins';
        /** @var modSystemSetting $adminsObj */
        $adminsObj = $modx->getObject('modSystemSetting', $settingKey);

        if ($adminsObj) {
            $currentAdmins = json_decode($adminsObj->get('value'), true);
            if (!is_array($currentAdmins)) {
                $currentAdmins = [];
            }

            // --- КОМАНДА РЕГИСТРАЦИИ ---
            if ($text === '/reg') {
                $isUpdated = false;

                // 1. Проверяем старый формат (где ID был просто значением в списке)
                $oldKey = array_search($userId, $currentAdmins, true);
                if ($oldKey !== false && !isset($currentAdmins[$userId])) {
                    unset($currentAdmins[$oldKey]); // Удаляем старую запись без имени
                    $isUpdated = true;
                }

                // 2. Проверяем новый формат (где ID - это ключ) и сверяем имя
                if (!isset($currentAdmins[$userId]) || $currentAdmins[$userId] !== $firstName) {
                    $currentAdmins[$userId] = $firstName; // Добавляем или обновляем имя
                    $isUpdated = true;
                }

                if ($isUpdated) {
                    $adminsObj->set('value', json_encode($currentAdmins, JSON_UNESCAPED_UNICODE));
                    if ($adminsObj->save()) {
                        $modx->getCacheManager()->refresh(['system_settings' => []]);
                        $bot->sendMessage($userId, "✅ Данные обновлены! Вы в списке как: {$firstName} (ID: {$userId}).");
                    }
                } else {
                    $bot->sendMessage($userId, "ℹ️ {$firstName}, вы уже в списке с актуальными данными.");
                }
            }

            // --- КОМАНДА УДАЛЕНИЯ ---
            if ($text === '/dell') {
                $hasChanged = false;

                if (isset($currentAdmins[$userId])) {
                    unset($currentAdmins[$userId]);
                    $hasChanged = true;
                }

                $oldKey = array_search($userId, $currentAdmins, true);
                if ($oldKey !== false) {
                    unset($currentAdmins[$oldKey]);
                    $hasChanged = true;
                }

                if ($hasChanged) {
                    $adminsObj->set('value', json_encode($currentAdmins, JSON_UNESCAPED_UNICODE));
                    if ($adminsObj->save()) {
                        $modx->getCacheManager()->refresh(['system_settings' => []]);
                        $bot->sendMessage($userId, "❌ Вы успешно удалены из списка администраторов.");
                    }
                } else {
                    $bot->sendMessage($userId, "ℹ️ Вас нет в списке администраторов.");
                }
            }
        }
    }




    // Проверяем входящие параметры из GET/POST запроса (например: connector.php?cmd=send&msg=Привет)
    if (isset($_REQUEST['cmd']) && $_REQUEST['cmd'] == "send" && !empty($_REQUEST['msg'])) {

        // 1. Получаем список ID админов из системной настройки
        $adminsJson = $modx->getOption('max_bot_admins');
        $admins = json_decode($adminsJson, true);

        if (is_array($admins) && !empty($admins)) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "--- Запуск рассылки администраторам ---\n";



            //minishop2 order
            $messageText = rawurldecode($_REQUEST['msg']);

            if (!$messageText) exit('Нечего слать!');

            foreach ($admins as $userId) {
                // 2. Декодируем сообщение (если оно пришло в url-encoded виде)


                // 3. Отправляем через наш обновленный метод (он сам подставит user_id в URL)
                $response = $bot->sendMessage($userId, $messageText);

                if ($response['status'] === 200) {
                    echo "✅ Отправлено админу [ID: $userId]\n";
                } else {
                    echo "❌ Ошибка отправки [ID: $userId]. Код: {$response['status']}\n";
                    // Если нужно отладить, можно вывести $response['data']
                }
            }
        } else {
            echo "Список администраторов пуст (настройка max_bot_admins).\n";
        }
        exit;
    }


}





// Завершаем скрипт без вывода лишнего мусора
@session_write_close();
exit();