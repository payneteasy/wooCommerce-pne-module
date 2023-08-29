# Содержание

## [Установка WordPress](#установка-wordpress-1) 
## [Установка плагина WooCommerce](#установка-плагина-woocommerce-1)
## [Установка и настройка плагина Payneteasy Gateway](#установка-и-настройка-плагина-payneteasy-gateway-1)
## [Процесс создания товара](#процесс-создания-товара-1)
## [Процесс оплаты](#процесс-оплаты-1)
## [Список ошибок](#список-ошибок-1)

- ***Протестировано на WooCommerce версии 8.0.1***

# Установка WordPress

1. Войти в [панель админа WordPress](http://wordpress.org/wp-admin/).
2. Выбрать язык.
   
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/1-rus.jpg" alt="drawing" width="200"/>
    

3. Начало процесса установки WordPress.
   
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/2-rus.jpg" alt="drawing" width="450"/>

4. Ввести название базы данных, имя пользователя и пароль.
   
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/3-rus.jpg" alt="drawing" width="450"/>

5. Начать установку.
   
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/4-rus.jpg" alt="drawing" width="450"/>

6. Выбрать название сайта, имя пользователя и задать пароль.
   
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/5-rus.jpg" alt="drawing" width="450"/>

7. Конец процесса установки.
    
   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/6-rus.jpg" alt="drawing" width="450"/>

# Установка плагина WooCommerce

1. Перейти в раздел [**Плагины**](http://wordpress.org/wp-admin/plugins.php) и нажать на кнопку `Добавить новый`.
2. Найти плагин *wooCommerce* в поиске.
3. Установить плагин, нажав на кнопку `Установить`.

<img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/7-rus.jpg" alt="drawing" width="1000"/>
   
4. Активировать плагин, нажав на кнопку `Активировать`.

<img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/8-rus.jpg" alt="drawing" width="350"/>

5. Выбрать название магазина, тип товара и местоположение.

   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/9-rus.jpg" alt="drawing" width="450"/>

# Установка и настройка плагина Payneteasy Gateway

1. После установки WooCommerce в директории **/wp-content/plugins/** необходимо создать новую директорию **paynet-easy-gateway**.

```bash
cd /wp-content/plugins/
mkdir paynet-easy-gateway
```

2. Рекурсивно скопировать содержимое директории **wooCommerce-pne-module** (не копировать саму папку) в созданную директорию **/wp-content/plugins/paynet-easy-gateway**.

```bash
cp -r wooCommerce-pne-module/* /wp-content/plugins/paynet-easy-gateway
```

Содержимое директории *wooCommerce-pne-module*

* admin
* docs
* images
* index.php
* languages
* LICENSE.txt
* PaynetEasy
* paynet-easy-gateway.php
* public
* README.md
* templates
* uninstall.php


3. Перейти в раздел **Плагины** и активировать плагин *Payneteasy Шлюз*.

   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/10-rus.jpg" alt="drawing" width="500"/>
   
4. Перейти на страницу настройки плагина [**Woocommerce/Настройки/Платежи**](http://wordpress.org/wp-admin.php?page=wc-settings&tab=checkout), включить метод *Payneteasy Шлюз* и передвинуть его вверх. Сохранить изменения.

  <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/11-rus.jpg" alt="drawing" width="1000"/>
   
5. Перейти на [страницу настройки плагина *Payneteasy Gateway*](http://wordpress.org/wp-admin/admin.php?page=wc-settings&tab=checkout&section=payneteasy).

  <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/12-rus.jpg" alt="drawing" width="500"/>

| Параметр         | Описание                                                                                                                                                    | 
|------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Вкл./Выкл.       | Включить или выключить плагин PaynetEasy Шлюз                                                                                                               |
| Заголовок        | Этот заголовок будет выводиться рядом с формой оплаты                                                                                                       |
| Описание         | Этот текст будет выводиться на странице оплаты                                                                                                              |
| Тестовый режим   | Тестовый режим работы шлюза                                                                                                                                 |
| Режим журналов   | Может быть выбран один из следующих режимов `EMERGENCY`, `ALERT`, `CRITICAL`, `ERROR`, `WARNING`, `NOTICE`, `INFO`, `DEBUG`                                 |
| Метод интеграции | Прием платежных реквизитов реализован на стороне PaynetEasy Шлюза. Должен быть выбран один из методов интеграции: `Встроенная форма`, `Удаленная форма`     |
| End point        | Терминал — это точка входа для транзакций, соединяющей стороны для единой валютной интеграции. Должен использоваться либо терминал, либо группа терминалов  |
| End point group  | Группа терминалов — это точка входа для транзакций продавца для мультивалютной интеграции. Должен использоваться либо терминал, либо группа терминалов      |
| Логин            | Логин продавца                                                                                                                                              |
| Секретный ключ   | Контрольный ключ продавца                                                                                                                                   |
| URL шлюза        | URL для шлюза                                                                                                                                               |
> Первый набор параметров предназначен для производственной среды `gate` и заполняется реальными данными.
>
> Второй набор параметров предназначен для тестирования в среде `sandbox` и заполняется тестовыми данными.

# Процесс создания товара

1. Перейти в раздел **Товары** и нажать на кнопку `Добавить`.

 <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/13-rus.jpg" alt="drawing" width="1000"/>

Все нижеперчисленные параметры должны быть настроены:

* Название товара
* Описание товара
* Цена товара 

После заполнения всех полей товар может быть опубликован нажатием на кнопку `Опубликовать`.

<img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/14-rus.jpg" alt="drawing" width="1000"/>

# Процесс оплаты

1. Перейти в `Магазин` и начать процесс оплаты.

   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/15-rus.jpg" alt="drawing" width="600"/>

2. Добавить предмет в корзину, нажав на кнопку `В корзину`. Далее товар можно найти в корзине, нажав на копку `Просмотр корзины`.

   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/16-rus.jpg" alt="drawing" width="200"/>
   
3. Выбрать количесвто товара и нажать на кнопку `Оформить заказ`, чтобы начать процесс оплаты.

   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/17-rus.jpg" alt="drawing" width="600"/>
   
4. Заполнить платёжную форму и нажать на кнопку `Подтвердить заказ`.

   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/18-rus.jpg" alt="drawing" width="600"/>

| Параметр              | Описание                                                                                                                                           | 
|-----------------------|----------------------------------------------------------------------------------------------------------------------------------------------------|
| Имя                   | Имя плательщика                                                                                                                                    |
| Фамилия               | Фамилия плательщика                                                                                                                                |
| Страна/Регион         | Страна плательщика                                                                                                                                 |
| Адрес                 | Адрес плательщика                                                                                                                                  |
| Населённый пункт      | Населенный пункт  плательщика                                                                                                                      |
| Область/район         | Район/облать плательщика                                                                                                                           |
| Почтовый индекс       | Почтовый индекс плательщика                                                                                                                        |
| Телефон               | Телефонный номер плательщика, включая код страны                                                                                                   |
| Email                 | Электронная почта плательщика                                                                                                                      |
| Номер кредитной карты | Номер банковской карты плательщика                                                                                                                 |
| Имя владельца карты   | Имя владельца банковской карты, написанное на лицевой стороне карты                                                                                |
| Месяц                 | Месяц истечения срока действия банковской карты                                                                                                    |
| Год                   | Год истечения срока действия банковской карты                                                                                                      |
| CVV код               | CVV2 код плательщика. CVV2 (Card Verification Value) это трех- или четырехзначный номер, написанный ПОСЛЕ номера кредитной карты                   |


5. Начало процесса перенаправления.


   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/19-rus.jpg" alt="drawing" width="550"/>


6. Плательщик перенаправляется на форму ожидания.
   

   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/20-rus.jpg" alt="drawing" width="550"/> 

 
8. Плательщик перенаправляется на финальную форму оплаты.

   <img src="https://github.com/payneteasy/wooCommerce-pne-module/blob/master/images-rus/21-rus.jpg" alt="drawing" width="550"/>
   

# Список ошибок

1. **Ошибка:** `Callback URL cannot be local or private`

**Решение**

В файле **/var/www/html/wp-content/plugins/paynet-easy-gateway/PaynetEasy/WoocommerceGateway/WCIntegration.php** убрать локальный IP `home_url('/')` и заполнить внешним URL или IP, например, `https://httpstat.us/200`.

2. **Ошибка:** `Project with X currency doest not apply request with currency Y`

**Решение**

Неверная валюта. Валюта оплаты в WooCommerce должна совпадать с валютой терминала. Валюта может быть изменена в разделе [основных настроек плагина WooCommerce](http://wordpress.org/wp-admin/admin.php?page=wc-settings)

3. **Ошибка:** `Amount is less/higher than minimum/maximum X`

**Решение**

Должны быть проверены лимиты на уровне терминала.

4. **Ошибка:** `Internal server error`

**Решение**

Должны быть проверены системные файлы WooCommerce. Данную ошибку может выдавать неверная настройка .php файлов, их неправильная установка и т.д. Лучшее решение - обновление или переустановка репозитория.

5. **Ошибка:** `Error occured. HTTP code: '50x'` 

**Решение**

URL Шлюза в настройках плагина *Payneteasy Шлюз*  должен быть изменен на корректный. Пример: *https://sandbox.payneteasy.eu/paynet/api/v2*.

6. **Ошибка:** `Property 'signingKey' does not defined in PaymentTransaction property 'queryConfig'`

**Решение**

Неверный Секретный Ключ продавца. Должна быть проверена корректность [настроек плагина Payneteasy Шлюз](http://wordpress.org/wp-admin/admin.php?page=wc-settings&tab=checkout&section=payneteasy).

7. **Ошибка:** `Some Request fields are invalid: Gateway url does not valid in Request`

**Решение**

Неверный url шлюза. Должна быть проверена корректность [настроек плагина Payneteasy Шлюз](http://wordpress.org/wp-admin/admin.php?page=wc-settings&tab=checkout&section=payneteasy).

8. **Ошибка:** `End point with id 0 not found`

**Решение**

Неверный Endpoint или пустое поле Логин/заполнено неверно. Должна быть проверена корректность [настроек плагина Payneteasy Шлюз](http://wordpress.org/wp-admin/admin.php?page=wc-settings&tab=checkout&section=payneteasy).
