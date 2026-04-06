<?php

/** @var modX $modx */
/** @var array $scriptProperties */
/** @var miniShop2 $miniShop2 */
/** @var msOrder $order */


$miniShop2 = $modx->getService('miniShop2');
$miniShop2->initialize($modx->context->key);

$token = $modx->getOption('tgBot_access_token', null, false);
$recipients = json_decode($modx->getOption('tgBot_admins'), true);

$order = $modx->getObject('msOrder', $id ?? $_GET['msorder']);
$oid = "msGetOrder_max" . $order->id.rand(66666,777777777);

if ($order) {

    $modx->setPlaceholders($order->toArray(), 'tgorder.');
}

if (!$_SESSION[$oid] && $order) {

    $_SESSION[$oid] = 1;
    $products = [];

    $cart_count = 0;
    $cart_discount_cost = 0;

    foreach ($order->getMany('Products') as $productDat) {

        /** @var msProduct $p_res */

        $product = $productDat->toArray();
        $p_res = $modx->getObject('modResource', $product['product_id']);

        $product['prod_portion'] = $p_res->getTVValue('prod_portion') ? json_decode($p_res->getTVValue('prod_portion'), true) : [];
        $product['migx'] = [];
        foreach ($product['prod_portion'] as $item) {
            $product['migx'][$item["type"]] = $item["value"];
        }
        $product['url'] = $modx->getOption('site_url') . $p_res->uri;

        $old_price = $product['original_price'] > $product['price']
            ? $product['original_price']
            : $product['old_price'];

        $discount_price = $old_price > 0 ? $old_price - $product['price'] : 0;

        $product['old_price'] = $miniShop2->formatPrice($old_price);
        $product['price'] = $miniShop2->formatPrice($product['price']);
        $product['cost'] = $miniShop2->formatPrice($product['cost']);
        $product['weight'] = $miniShop2->formatWeight($product['weight']);
        $product['discount_price'] = $miniShop2->formatPrice($discount_price);
        $product['discount_cost'] = $miniShop2->formatPrice($product['count'] * $discount_price);

        $product['id'] = (int)$product['id'];
        if (empty($product['name'])) {
            $product['name'] = $product['pagetitle'];
        } else {
            $product['pagetitle'] = $product['name'];
        }

        // Additional properties of product
        if (!empty($product['options']) && is_array($product['options'])) {
            foreach ($product['options'] as $option => $value) {
                $product['option.' . $option] = $value;
            }
        }

        // Add option values
        $options = $modx->call('msProductData', 'loadOptions', [$modx, $product['id']]);
        $products[] = array_merge($product, $options);

        // Count total
        $cart_count += $product['count'];
        $cart_discount_cost += $product['count'] * $discount_price;


    }

    $i = 0;
    $productsTpl = "";

    foreach ($products as $p) {
        $i++;

        if ($p["migx"]["Шт"]) {
            $p['name'] = "{$p['name']} ({$p['migx']["Шт"]} Шт.)";
        }

        if ($p["migx"]["Гр"]) {
            $p['name'] = "{$p['name']} ({$p['migx']["Гр"]} Гр.)";
        }
        $opts = [];
        foreach ($p['options'] as $name => $val) {
            $icon = "";
            if ($name == 'Начинка') {
                $icon = "🍲";
            }
            if ($name == 'Порция') {
                $icon = "🥡";
            }

            $opts[] = "{$icon}{$name}: {$val}";
        }
        $opts_str = implode(", ", $opts);
        $productsTpl .= "#{$i} <a href='{$p["url"]}'>{$p["name"]} {$opts_str} <code>{$p['price']} x {$p['count']} = {$p['cost']}</code> р.</a>
";


    }


    $pls = array_merge($scriptProperties, [
        'order' => $order->toArray(),
        'products' => $products,
        'user' => ($tmp = $order->getOne('User'))
            ? array_merge($tmp->getOne('Profile')->toArray(), $tmp->toArray())
            : [],
        'address' => ($tmp = $order->getOne('Address'))
            ? $tmp->toArray()
            : [],
        'delivery' => ($tmp = $order->getOne('Delivery'))
            ? $tmp->toArray()
            : [],
        'payment' => ($payment = $order->getOne('Payment'))
            ? $payment->toArray()
            : [],
        'total' => [
            'cost' => $miniShop2->formatPrice($order->get('cost')),
            'cart_cost' => $miniShop2->formatPrice($order->get('cart_cost')),
            'delivery_cost' => $miniShop2->formatPrice($order->get('delivery_cost')),
            'weight' => $miniShop2->formatWeight($order->get('weight')),
            'cart_weight' => $miniShop2->formatWeight($order->get('weight')),
            'cart_count' => $cart_count,
            'cart_discount' => $cart_discount_cost
        ],
    ]);

    $delivery = "
Заберу самостоятельно по адресу с. Угдан ул. Трактовая д.23";
    if ($order->delivery == 1) {
        $delivery = "
Район: {$pls["address"]['metro']}
Улица: {$pls["address"]['street']}
Дом: {$pls["address"]['building']}
Подъезд: {$pls["address"]['entrance']}";
    }


    $message = "
<b>Новый заказ #{$order->num} <a href='{$modx->getOption('site_url')}bayanmgr/?a=mgr/orders&namespace=minishop2&order={$order->id}'>подробнее</a></b>
на сумму {$pls['total']['cost']} р.
-----
Оплата: {$pls['payment']["name"]}
-----
Заказчик: {$pls['address']["receiver"]}
Телефон: {$pls['address']["phone"]}
Дата и время доставки: {$pls['address']["country"]}
-----
Доставка: {$pls['delivery']["name"]}, цена: {$pls['delivery']["price"]} р.{$delivery}
-----
Продукция:
{$productsTpl}
-----
Комментарий: {$pls["address"]['comment']}
";

    $message = rawurlencode($message);
    var_dump(file_get_contents("https://bg75.ru/assets/components/max/connector.php?cmd=send&msg=$message"));
    return true;
}