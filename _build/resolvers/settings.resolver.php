<?php

/** @var xPDOTransport $transport */
/** @var array $options */
/** @var modX $modx */
if ($transport->xpdo) {
    $modx =& $transport->xpdo;

    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:

            // Массив соответствий Ключ -> Описание
            $descriptions = [
                'bot_subscribe_url' => 'URL адрес для входящих вебхуков от MAX API.',
                'max_bot_admins' => 'Список ID администраторов в формате JSON. Пример: {"107409437":"Имя"}',
                'max_bot_name' => 'Уникальное имя вашего бота (Username).',
                'max_bot_token' => 'Токен авторизации, полученный в личном кабинете MAX.',
            ];

            foreach ($descriptions as $key => $text) {
                // Создаем запись в лексиконе, чтобы MODX подтянул описание в настройки
                $entry = $modx->getObject('modLexiconEntry', [
                    'name' => 'setting_' . $key . '_desc',
                    'namespace' => 'max',
                    'language' => 'ru',
                ]);
                if (!$entry) {
                    $entry = $modx->newObject('modLexiconEntry');
                    $entry->fromArray([
                        'name' => 'setting_' . $key . '_desc',
                        'namespace' => 'max',
                        'language' => 'ru',
                        'topic' => 'default',
                    ]);
                }
                $entry->set('value', $text);
                $entry->save();
            }

            // Очищаем кэш лексиконов, чтобы изменения вступили в силу
            $modx->lexicon->clearCache();
            break;
    }
}
return true;
