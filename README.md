# crimson.crawler
Переобход страниц для bitrix

* Парсит заданную карту сайта в отдельном потоке средствами сервера. Специально ограничен запуск только одного парсера из за большой нагрузки на сервер.

* При сохранении или обновлении элемента, выбранных инфоблоков в настройках данного модуля, запускаетя получение итоговой страницы.

## Генерирует отчёт
* Статус страницы 
* Время загрузки
* Содержимое страницы

TODO: 
* Запуск по расписанию
* Обновление вкладки с отчётом по завершению работы парсера

# Установка
Скопировать модуль в /local/modules и установить через админку.

# Рекомендации
Если установлена защита от DDoS - прописать в файле /etc/hosts, на сервере где запускается парсер, настоящий ip сайта и домен, что бы обойти защиту, и парсить напрямую.