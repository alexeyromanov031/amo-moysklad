# amo-moysklad

## ТЗ

### В чем задача

Необходимо сделать двустороннюю интеграцию AmoCRM и МойСклад при помощи API: 

1. При переведении менеджером сделки в Амо до этапа "Заказано" в МоемСкладе должен формароваться Заказ Покупателя с данными из АмоCRM.
2. При изменении статуса Заказа Покупателя в Моем Складе, информация об этом должна уходить в Сделку в Амо и там должно изменяться соответсвующее поле.
3. При создании отгрузки в МоемСкладе по Заказу, соответствующая сделка в АмоЦРМ должна тоже менять свой статус.
4. При создании Входящего Платежа к Заказу в Моем Складе, соответсвующая сделка в Амо также должна сменить свой статус.

Интеграция будет использоваться на двух проектах: МГ и НЛ. Интеграция должна быть универсальна, данные о полях сделок, статусах и пр. будут находиться в конфигурационных файлах.

Для всего этого требуется написать 4 скрипта.

### 1. 

Первый скрипт должен получать данные о сделке из Амо и на их основе создавать Заказ Покупателя в МойСклад.

Скрипт должен срабатывать при переводе сделки в статус "Заказано". Он будет вызываться вебхуком. В момент вебхука скрипт получит данные о сделке. Формат и состав данных, получаемый от вебхука, описан в документации Амо: https://www.amocrm.ru/developers/content/crm_platform/webhooks-format

Сам вебхук настраивать не надо, нужно только разместить скрипт на домене проекта в файл export-order.php

Из полученного массива данных необходимо выделить следующие данные:

1. Общие для обоих проектов данные - ссылка на сделку Амо и бюджет сделки.
2. Поля, которые различаются в разных проектах. Эти поля, их id и соответсвующие сущности и поля в МоейСкладе прописаны в конфигурацинном файле filelds.csv. Образец конфиг файла для МГ проекта лежит в репозитории.
3. Массив тваров сделки. Для товаров используется кастомное супер-поле АмоЦРМ.

Перед созданием заказа в МойСклад все товары необходимо расценить, исходя из общего бюджета сделки, согласно пропорциям по прайс-листу. Сделать это необходимо следующим образом:
После активации скрипта, согласно массиву товаров, а конкретно, Артикулу товара, необходимо обратиться по API в МойСклад (https://dev.moysklad.ru/doc/api/remap/1.2/workbook/#workbook-fil-traciq-listanie-poisk-i-sortirowka-poisk - Поиск сущности по их текстовым строкам) и найти цены каждого товара из массива в прайслисте. Предполагается, что прайслист для этого будет использоваться один, id его находится в файле pricelist. После чего, согласно бюджету сделки, а так же прайсовым ценам на товары, полученные из прайслиста, необходимо рассчитать реальные цены, за которые был продан товар, по следующей формуле:

(цена товара 1) = (прайсовая цена товара 1) * (бюджет сделки - прайсовая цена доставки) / (сумма прайсовых цен всех товаров из сделки)

Пример

Заказ А

>Состав заказа из АМО:
>Кофта 3 шт.
>Штаны 3 шт.
>Доставка
>Бюджет 13000 рублей

>Смотрим прайс...
>Кофта по прайсу стоит 3000 рублей
>Штаны по прайсу стоит 2000 рублей
>Доставка 1000 рублей
>Сумма по прайсу 3000 * 3 + 2000 * 3 = 15000 рублей

>Получается, для каждого товара расчитываем цену:
>Цена кофты = 3000 * 13000-1000 / 15000 = 2400 рублей
>Цена штанов = 2000 * 13000-1000 / 15000 = 1600 рублей

!Возможно, что в AmoCRM бюджет измеряется в рублях, а в МоемСкладе - в копейках. Нужно тестировать, сверять.

После получения цен на товары, создать новый заказ в МоемСкладе по API, произведя поиск по каталогу по артикулу товара, и заполнив по каждому товару цену, расчитанную на прошлом этапе.
Так же в заказ нужно добавить данные, забранные из сделки, согласно конфигурационному файлу.
Заказ оформляется на розничного покупателя.

>По примеру выше в МоемСкладе сформируется заказ:
Кофта 2400 руб. * 3 шт. = 7200 руб.
Штаны 1600 руб. * 3 шт. = 4800 руб.
Итого 12000 рублей.

### 4.

Сделать вебхук, который отправляет информацию об отгруженном товаре из МоегоСклада обратно в Амо. Спрабатывать должен по изменению статуса Заказа Покупателя на обработан.
Передавать по API в соответсвующую сделку, менять в сделке поле "Обработан" (id в конфиг файле) на "да".

### 5.

Сделать вебхук, который срабатывает по созданию Отгрузки в МойСклад. Должен взять ссылку на сделку в Амо из Заказа из связанных документов с отгрузками, и в соответсвующей сделке устаность поле "Трек-номер" в соответсвии с полем "Трек-номер" в отгрузке (соответсвия полей в конфиг файле).

---ИЛИ---

Сделать вебхук, который срабатывает по созданию Отгрузки в МойСклад. Должен взять ссылку на сделку в Амо из Заказа из связанных документов с отгрузками, и в соответсвующей сделке устаность поле "Трек-номер" в соответсвии с полем "Трек-номер" в отгрузке (соответсвия полей в конфиг файле), а статус у сделки поменять в зависимости от изначальной воронки сделки и от значения кастомного поля. Зависимости статуса от воронки и от полей прописны в файле statuses.scv.

## Полезные ссылки

* [https://dev.moysklad.ru/](Документация МойСклад)
* [https://online.moysklad.ru/api/remap/1.2/entity/customerorder/metadata/attributes](GET запрос по которому можно получить даные по доп.полям заказа покупателя)
* [https://online.moysklad.ru/api/remap/1.2/entity/demand/metadata/attributes](GET запрос по которому можно получить даные по доп.полям отгрузок)
* [https://dev.moysklad.ru/doc/api/remap/1.2/documents/#dokumenty-zakaz-pokupatelq-sozdat-zakaz-pokupatelq](Пример создания заказа с дополнительными полями)
* [https://dev.moysklad.ru/doc/api/remap/1.2/workbook/#workbook-rabota-s-dopolnitel-nymi-polqmi-cherez-json-api](Информация о дополнительных полях)
