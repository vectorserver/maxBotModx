<?php

class maxBotModx {
    protected $modx;
    private $name;
    private $token;
    private $subscribeUrl;
    private $apiUrl = 'https://platform-api.max.ru';

    public function __construct(modX &$modx, $name, $token, $subscribeUrl) {
        $this->modx = &$modx;
        $this->name = $name;
        $this->token = $token;
        $this->subscribeUrl = $subscribeUrl;
    }

    /**
     * Подписка на вебхук (использует URL из настроек)
     */
    public function subscribe() {
        return $this->request('subscriptions', [
            'url' => $this->subscribeUrl, // Берем из свойств класса
            'update_types' => ['message_created', 'bot_started'],
        ], 'POST');
    }

    public function getSubscriptions() {
        return $this->request('subscriptions', [], 'GET');
    }

    public function deleteSubscription($url) {
        return $this->request('subscriptions', ['url' => $url], 'DELETE');
    }



    /**
     * Отправка сообщения с поддержкой HTML/Markdown
     * @param int $userId ID получателя
     * @param string $text Текст сообщения
     * @param array $attachments Вложения (кнопки и т.д.)
     */
    public function sendMessage($userId, $text, $attachments = []) {
        // Формируем параметры в корне объекта, как требует API
        $params = [
            'text'   => $text,
            'format' => 'html', // Используем "html" из вашего перечисления (enum)
            'notify' => true    // Уведомление включено по умолчанию
        ];

        if (!empty($attachments)) {
            $params['attachments'] = $attachments;
        }

        // Отправляем user_id в URL (query params), а остальное в теле (JSON)
        return $this->request('messages', $params, 'POST', ['user_id' => $userId]);
    }

    /**
     * Чтение входящего Webhook
     */
    public function handleWebhook() {
        $input = file_get_contents('php://input');
        $inputData = json_decode($input, true);
        if (!empty($input)) {
            file_put_contents(MODX_BASE_PATH . '/assets/components/max/handleWebhook_log.php', "<?php \n".var_export($inputData, true).";");

        }
        return $inputData;
    }

    /**
     * Универсальный метод запроса
     */
    private function request($endpoint, $params = [], $method = 'POST', $queryParams = []) {
        $url = rtrim($this->apiUrl, '/') . '/' . ltrim($endpoint, '/');

        //Добавляем GET-параметры (user_id и прочие), если они есть
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        // Если метод GET, то основные параметры тоже в URL
        elseif ($method == 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        //Тело запроса (JSON) отправляем только для POST/PUT/PATCH
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($params)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: ' . $this->token
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'url' => $url,
            'method' => $method,
            'data' => json_decode($response, true)
        ];
    }


}
