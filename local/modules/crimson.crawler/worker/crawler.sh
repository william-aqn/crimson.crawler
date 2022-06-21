#!/bin/bash

## Файл с логом
log_file="$1/report.csv"

## Файл со статусом
status_file="$1/status.csv"

## Файл с ссылками для парсинга
links="$1/links.csv"

## Полный путь до файла
self_file_full=$0

## Остановить этот скрипт
stop() {
    ps aux | grep "/bin/bas[h] $self_file_full" | awk '{print $2}' | xargs kill -9 $1
}

## Останавливаем, если нет входящих аргументов
if [[ ! $1 ]]; then
    stop
fi

## Проверяем ссылки
if [ ! -f "$links" ]; then
    echo -e "Not found $links"
    exit
fi

## Ограничеваем запуск скрипта 2й раз
procs=$(pgrep -f "/bin/bash ${self_file_full}")
procs_fix=$(echo "$procs" | wc -l)
## Число же надо
procs_fix=$((procs_fix+0))
if [[ "$procs_fix" -gt 1 ]]; then
    echo -e "Process already running"
    exit
fi

## Устанавливаем статус
set_status(){
    printf "%s\t%s" "$1" "$2" > "$status_file"
}

## Очищаем файл с логом
touch "$log_file"

## Текущий статус
set_status "start"

# https://everything.curl.dev/usingcurl/verbose/writeout
urls=$(cat "$links")
for n in ${urls[@]}
do
    ## Создаём md5 от ссылки
    fname=$(printf '%s' "$n" | md5sum | cut -d ' ' -f 1)
    ## Путь к файлу с содержимым загружаемой страницы
    fpathname=$(printf '%s/page_%s' "$1" "$fname")
    ## Текущий статус
    set_status "process" "$n"
    
    echo -e "parse $fname = $n"
    
    ## Добавляем название страницы в отчёт
    printf "page_%s\t" "$fname" >> "$log_file"
    curl --silent --insecure --request GET \
    --url "$n" \
    --data '{}' \
    --output "$fpathname" \
    --write-out "%{url_effective}\t%{http_code}\t%{time_total}\n" >> "$log_file"
    
    ## Останавливаем скрипт, если нет файла с заданием
    if [ ! -f "$links" ]; then
        set_status "stop"
        exit;
    fi
done

## Текущий статус
set_status "finish"