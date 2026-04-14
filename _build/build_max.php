<?php
/**
 * Скрипт сборки транспортного пакета: build_max.php
 */
echo "<pre>";
$pkgName = 'max';
$pkgVersion = '0.0.4'; // Обновил версию
$pkgRelease = 'v';

$configFile = dirname(dirname(__FILE__)) . '/core/config/config.inc.php';

if (!file_exists($configFile)) {
    die("Ошибка: Не найден файл конфигурации MODX по адресу: " . $configFile);
}

require_once $configFile;
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

$modx->loadClass('transport.modPackageBuilder', '', false, true);
$builder = new modPackageBuilder($modx);
$builder->createPackage($pkgName, $pkgVersion, $pkgRelease);

// 1. Регистрация Namespace
$builder->registerNamespace($pkgName, false, true, '{core_path}components/max/');

// 2. Создание Категории (max v 001)
$category = $modx->newObject('modCategory');
$category->set('id', 1);
$category->set('category', 'max');

// 3. Системные настройки
$settings = [
    'bot_subscribe_url' => [
        'value' => 'add_text',
        'xtype' => 'textfield',
        'description' => 'setting_bot_subscribe_url_desc' // Ключ для лексикона
    ],
    'max_bot_admins' => [
        'value' => '{}',
        'xtype' => 'textarea',
        'description' => 'setting_max_bot_admins_desc'
    ],
    'max_bot_name' => [
        'value' => 'add_text',
        'xtype' => 'textfield',
        'description' => 'setting_max_bot_name_desc'
    ],
    'max_bot_token' => [
        'value' => 'add_text',
        'xtype' => 'textfield',
        'description' => 'setting_max_bot_token_desc'
    ],
];

foreach ($settings as $key => $data) {
    $setting = $modx->newObject('modSystemSetting');
    $setting->fromArray([
        'key' => $key,
        'namespace' => $pkgName,
        'area' => 'general',
        'value' => $data['value'],
        'xtype' => $data['xtype'],
        'description' => $data['description'],
    ], '', true, true);

    $builder->putVehicle($builder->createVehicle($setting, [
        xPDOTransport::PRESERVE_KEYS => true,
        xPDOTransport::UPDATE_OBJECT => false,
    ]));
}

// 4. Упаковка Категории + Привязка Резолвера (один раз)
$vehicle = $builder->createVehicle($category, [
    xPDOTransport::UNIQUE_KEY => 'category',
    xPDOTransport::PRESERVE_KEYS => false,
    xPDOTransport::UPDATE_OBJECT => true,
]);

// Привязываем PHP-скрипт, который пропишет описания в лексиконы
$vehicle->resolve('php', [
    'source' => dirname(__FILE__) . '/resolvers/settings.resolver.php',
]);
$builder->putVehicle($vehicle);

// 5. Файлы Core и Assets
$builder->putVehicle($builder->createVehicle([
    'source' => MODX_CORE_PATH . 'components/max',
    'target' => "return MODX_CORE_PATH . 'components/';",
], ['vehicle_class' => 'xPDOFileVehicle']));

$builder->putVehicle($builder->createVehicle([
    'source' => MODX_ASSETS_PATH . 'components/max',
    'target' => "return MODX_ASSETS_PATH . 'components/';",
], ['vehicle_class' => 'xPDOFileVehicle']));

// 6. Атрибуты пакета (Readme, Changelog)
$docsPath = MODX_CORE_PATH . 'components/max/docs/';
$builder->setPackageAttributes([
    'changelog' => file_exists($docsPath . 'changelog.txt') ? file_get_contents($docsPath . 'changelog.txt') : 'Обновление '.$pkgVersion,
    'readme'    => file_exists($docsPath . 'readme.txt') ? file_get_contents($docsPath . 'readme.txt') : 'Описание пакета MAX.',
    'license'   => file_exists($docsPath . 'license.txt') ? file_get_contents($docsPath . 'license.txt') : 'Лицензионное соглашение.',
]);

// Финализация
$builder->pack();

// Копирование
$signature = $builder->getSignature();
$packageFile = MODX_CORE_PATH . 'packages/' . $signature . '.transport.zip';
$destFile = dirname(__FILE__) . '/' . $signature . '.transport.zip';

if (file_exists($packageFile)) {
    copy($packageFile, $destFile);
    $modx->log(modX::LOG_LEVEL_INFO, "Пакет собран и скопирован: " . $destFile);
}
