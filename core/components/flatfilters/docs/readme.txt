--------------------
FlatFilters
--------------------
Author: Artur Shevchenko <shev.art.v@yandex.ru>
Owner: MODX RSC – Russian Speaking Community  https://github.com/modx-pro
Support and development  Artur Shevchenko https://t.me/ShevArtV
With questions and problems - contact the https://modx.pro  AND  https://t.me/ru_modx
--------------------

Это компонент для фильтрации товаров в интернет-магазине на базе CMS Modx Revolution 2.8.x и MiniShop2 4.x.x

<strong>Для корректной работы требуется версия PHP не ниже 7.4.</strong>

<strong>Зависимости</strong>
<ul>
<li>SendIt</li>
<li>pdoTools</li>
<li>MiniShop2</li>
</ul>


<strong>!!!ВАЖНО!!! Функции поиска по сайту в данном компоненте нет.</strong>

<strong>Преимущества перед конкурентами</strong>
<ul>
<li>Не требует установки на сервер сторонних библиотек или сервисов типа ElasticSearch или Sphinx</li>
<li>Высокая скорость фильтрации (менее 1 секунды при 100 000 товаров)</li>
<li>Простота настройки</li>
<li>Фильтрация по множественным значениям</li>
</ul>

<strong>Начало использования</strong>
<ol>
<li>Установить</li>
<li>Создать конфигурацию</li>
<li>Произвести индексацию</li>
<li>Создать шаблон страницы фильтрации</li>
</ol>

<strong>Пример вызова сниппета</strong>
<code>
        {'!ffFiltering' | snippet: [
        'configId' => 10,
        'limit' => 8,
        'parents' => 0,
        'sortby' => ['Data.price' => 'ASC'],

        'wrapper' => '@FILE chunks/ffouter.tpl',
        'empty' => '@FILE chunks/ffempty.tpl',
        'priceTplOuter' => '@FILE chunks/ffrange.tpl',
        'favoriteTplOuter' => '@FILE chunks/ffcheckbox.tpl',
        'newTplOuter' => '@FILE chunks/ffcheckbox.tpl',
        'popularTplOuter' => '@FILE chunks/ffcheckbox.tpl',
        'colorTplOuter' => '@FILE chunks/ffcheckboxgroupouter.tpl',
        'colorTplRow' => '@FILE chunks/ffcheckboxgroup.tpl',
        'defaultTplOuter' => '@FILE chunks/ffselect.tpl',
        'defaultTplRow' => '@FILE chunks/ffoption.tpl',
        'publishedonTplOuter' => '@FILE chunks/ffdaterange.tpl',

        'returnIds' => 0,
        'element' => 'msProducts',
        'tpl' => '@FILE chunks/msproducts/filter-item.tpl',
        'includeTVs' => 'modifications',
        'includeThumbs' => 'small',
        'showUnpublished' => 1
        ]}
</code>



<a href="">Ссылка на видео презентацию</a>
