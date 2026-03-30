# maxBotModx

Легкий PHP-класс для интеграции платформы **max.ru** с CMS **MODX Revolution**. Предназначен для быстрой организации рассылок, уведомлений и создания чат-ботов внутри вашей экосистемы MODX.

## Возможности

- **Простая инициализация**: Интеграция с объектом `$modx`.
- **Управление подписками**: Методы для регистрации, проверки и удаления Webhook-адресов.
- **Гибкая отправка сообщений**: Поддержка HTML-разметки и вложений (кнопок) через API.
- **Обработка входящих данных**: Готовый метод для приема Webhooks с автоматическим логированием для отладки.

---

## Системные требования

- PHP 7.4 или выше.
- Установленная CMS MODX Revolution.
- Расширение cURL для PHP.

---

### Инициализация
Подключите файл класса и инициализируйте его, передав необходимые параметры:

```php
require_once MODX_CORE_PATH . 'components/mybot/maxBotModx.class.php';

$config = [
    'name' => 'MyCompanyBot',
    'token' => 'ВАШ_API_TOKEN',
    'url' => 'https://site.ru' // URL вашего сниппета-обработчика
];

$maxBot = new maxBotModx($modx, $config['name'], $config['token'], $config['url']);
```
### Регистрация вебхука

```php
$result = $maxBot->subscribe();

if ($result['status'] == 200) {
    echo "Webhook успешно активирован!";
} else {
    echo "Ошибка регистрации: " . $result['data']['error'];
}

```

### Проверить текущие подписки:
```php
$activeSubscriptions = $maxBot->getSubscriptions();

```

### Обработка входящих сообщений (Webhook)
```php
$data = $maxBot->handleWebhook();

if ($data && isset($data['user_id'])) {
    $userId = $data['user_id'];
    $text = $data['text'];

    // Простая логика автоответчика
    if ($text == '/start') {
        $maxBot->sendMessage($userId, "Привет! Я бот на базе <b>MODX</b>. Чем могу помочь?");
    }
}

```
### Отправка
```php
$maxBot->sendMessage(12345, "Заказ №<b>555</b> успешно <u>оплачен</u>!");

```
### Сообщение с кнопками (Attachments)
```php
$buttons = [
    [
        'type' => 'link',
        'text' => 'Открыть каталог',
        'url'  => 'https://mysite.ru'
    ],
    [
        'type' => 'callback',
        'text' => 'Статус заказа',
        'payload' => 'check_status_123'
    ]
];

$maxBot->sendMessage(12345, "Выберите действие:", $buttons);

```

### Пример реализации эхо-бота:

```php
// Инициализация
$bot = new maxBotModx($modx, 'MyBot', 'TOKEN', 'https://site.ru');

// Получаем данные из входящего запроса
$handleWebhook = $bot->handleWebhook();

// Проверяем, что пришло именно сообщение
if (!empty($handleWebhook['message'])) {
    $message = $handleWebhook['message'];
    $userId  = $message['user_id'];
    $text    = $message['text'];

    // Простая проверка команд
    if ($text == '/start') {
        $bot->sendMessage($userId, "Привет! Я получил твой <b>/start</b>");
    } else {
        // Ответ на любое другое сообщение
        $bot->sendMessage($userId, "Вы написали: <i>" . $text . "</i>");
    }
}
```
###  Пример коннектора (test_connector.php)

Вы можете создать один файл-контроллер для всех задач:

1.  **Регистрация**: Просто перейдите по адресу `ваш-сайт.ру/test_connector.php?subscribe=1`.
2.  **Webhook**: Укажите этот URL в настройках платформы (или в `bot_subscribe_url`).
3.  **Уведомления**: Отправляйте системные сообщения, обращаясь к файлу: `test_connector.php?cmd=send&msg=Новый заказ!`.

> **Важно:** Для работы примера создайте в MODX системную настройки `max_bot_admins,max_bot_name,max_bot_token,bot_subscribe_url` с пустым массивом `[]`.

