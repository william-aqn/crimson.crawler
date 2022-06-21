<?php
define("ADMIN_MODULE_NAME", "crimson.crawler");

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

\CUtil::InitJSCore(array('ajax'));
global $APPLICATION;
$RIGHT = $APPLICATION->GetGroupRight(ADMIN_MODULE_NAME);
if ($RIGHT == "D") {
    $APPLICATION->AuthForm(Loc::getMessage("CRIMSON_NOT_ACCESS"));
}

\Bitrix\Main\Loader::includeModule(ADMIN_MODULE_NAME);
$crawler = new \CrimsonCrawlerHelper();
$config = $crawler->getConfig();
//print_r($config); die();
$id = $_REQUEST['id'];
if (!$id || !array_key_exists($id, $config['sites'])) {
    LocalRedirect("/bitrix/admin/settings.php?mid=crimson.crawler&lang=" . LANGUAGE_ID);
    return false;
}
$url = $config['sites'][$id];
$APPLICATION->SetTitle(Loc::getMessage("CRIMSON_CRAWLER_TITLE") . " - $url");

function get_progress_block($id, $crawler) {
    $status = $crawler->initTask($id);
    \CAdminMessage::ShowMessage(array(
        "MESSAGE" => "Обработка: {$status['links_processed']}/{$status['links_total']}",
        "DETAILS" => "#PROGRESS_BAR#<br/>Недавнее действие: {$status['last_update']}<br/>Статус: {$status['status']}",
        "TYPE" => "PROGRESS",
        "PROGRESS_TOTAL" => $status['links_total'],
        "PROGRESS_VALUE" => $status['links_processed'],
        //"PROGRESS_WIDTH" => "600",
        "HTML" => true,
    ));
}

if ($_REQUEST['action'] && check_bitrix_sessid()) {
    switch ($_REQUEST['action']) {

        // Перезапускаем парсер
        case 'reset':
            $crawler->stop();
            $crawler->clear($id);
            $crawler->initTask($id);
            $crawler->run($id);
            break;

        // Запускаем парсер
        case 'run':
            $crawler->run($id);
            break;

        // Останавливаем парсер
        case 'stop':
            $crawler->stop();
            break;

        // Статус для ajax обработчика
        case 'status':
            $APPLICATION->restartBuffer();
            echo get_progress_block($id, $crawler);
            die();
            break;

        // Детальная страница
        case 'detail':
            $APPLICATION->restartBuffer();
            highlight_string($crawler->getTaskReportDetailFile($id, $_REQUEST['fid']));
            die();
            break;

        default:
            break;
    }
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");

$aTabs = array(
    [
        "DIV" => "crimson_tab_settings",
        "TAB" => "Статус",
        "ICON" => "main_user_edit",
    //"TITLE" => $url
    ],
    [
        "DIV" => "crimson_tab_sitemap",
        "TAB" => "Актуальный sitemap",
        "ICON" => "main_user_edit",
    //"TITLE" => $url
    ],
    [
        "DIV" => "crimson_tab_links",
        "TAB" => "Ссылки для парсера",
        "ICON" => "main_user_edit",
    //"TITLE" => $url
    ],
    [
        "DIV" => "crimson_tab_report",
        "TAB" => "Отчёт парсера",
        "ICON" => "main_user_edit",
    //"TITLE" => $url
    ],
);
$tabControl = new \CAdminTabControl("tabControl", $aTabs);
?>
<form id="crimson_tab" method="POST" action="" name="crimson_tab">
    <?
    echo bitrix_sessid_post();
    $tabControl->Begin();
    $tabControl->BeginNextTab();
    \CAdminMessage::ShowNote('Если запустить одновременно обход 2+ карт сайта, запущен останется только последний. Остальные будут остановлены.');
    
    echo "<div id='crimson_progress_block'>";
    get_progress_block($id, $crawler);
    echo "</div>";
    // Актуальный sitemap
    $tabControl->BeginNextTab();
    $data = $crawler->parseSitemap($url);
    echo '<h2>Sitemaps</h2>';
    foreach ($data['sitemaps'] as $url => $tags) {
        echo 'URL: ' . $url . '<br>';
        echo 'LastMod: ' . $tags['lastmod'] . '<br>';
        echo '<hr>';
    }
    echo '<h2>URLs</h2>';
    foreach ($data['urls'] as $url => $tags) {
        echo 'URL: ' . $url . '<br>';
        echo 'LastMod: ' . $tags['lastmod'] . '<br>';
//        echo 'ChangeFreq: ' . $tags['changefreq'] . '<br>';
//        echo 'Priority: ' . $tags['priority'] . '<br>';
        echo '<hr>';
    }

    // Ссылка для парсера
    $tabControl->BeginNextTab();
    echo "<pre>" . $crawler->loadTaskLinksFile($id) . "<pre>";

    // Отчёт парсера
    $tabControl->BeginNextTab();
    \CAdminMessage::ShowNote('Если парсер запущен - обновите страницу, что бы получить актуальные данные.');
    if ($fname = $crawler->getTaskReportFileName($id)) {
        $handle = fopen($fname, "r");
        echo "<tr><th>Отчёт</th><th>Код</th><th>Время</th><th>Ссылка</th></tr>";
        while (($data = fgetcsv($handle, null, "\t")) !== FALSE) {
            //var_dump($data);

            $item = [
                'detail_url' => $APPLICATION->GetCurPageParam("action=detail&fid={$data[0]}&sessid=" . bitrix_sessid(), []),
                'url' => $data[1],
                'status' => ($data[2] != 200 ? "<b>{$data[2]}</b>" : $data[2]),
                'time' => $data[3],
            ];
            echo "<tr> <td><a href='{$item['detail_url']}' target='_blank'>Отчёт</a></td> <td>{$item['status']}</td> <td>{$item['time']}</td> <td><a href='{$item['url']}' target='_blank'>{$item['url']}</a></td> </tr>";
        }
        fclose($handle);
    } else {
        echo "Нет данных";
    }

    // завершение формы без вывода кнопок
    $tabControl->Buttons();
    ?>
    <input type="submit" value="Обновить" />
    <button type="submit" value="reset" name="action" class="adm-btn">Перезапустить</button>
    <button type="submit" value="stop" name="action" class="adm-btn">Остановить</button>
    <button type="submit" value="run" name="action" class="adm-btn">Запустить</button>
    <?
    //echo '<a class="adm-btn" href="settings.php?lang=' . LANGUAGE_ID . '&amp;mid=divasoft.monitor&amp;back_url_settings=' . urlencode('/bitrix/admin/dvs-monitor.php?lang=' . LANGUAGE_ID) . '">Настройка</a>';
    ?>
    <?
    $tabControl->End();
    ?>
</form><style type="text/css">
    #crimson_tab_report_edit_table tr td, #crimson_tab_report_edit_table tr th {
        border-left: 1px gray dotted;
        border-top: 1px gray dotted;
        text-align: center;
    }
    #crimson_tab_report_edit_table tr td:last-child, #crimson_tab_report_edit_table tr th:last-child {
        text-align: left;
        border-right: 1px gray dotted;
    }
    #crimson_tab_report_edit_table tr:last-child td{
        border-bottom: 1px gray dotted;
    }
</style>
<script type="text/javascript">
    function get_status() {
        BX.ajax({
            url: '<?= $APPLICATION->GetCurPageParam("", ['action', 'fid', 'sessid']) ?>',
            data: {action: 'status', sessid: BX.message('bitrix_sessid')},
            method: 'POST',
            //dataType: 'json',
            timeout: 5,
            async: true,
            processData: true,
            scriptsRunFirst: true,
            emulateOnload: true,
            start: true,
            cache: false,
            onsuccess: function (data) {
                BX.adjust(BX('crimson_progress_block'), {html: data});
                // TODO: Если status = finish не запускать. Подгрузить отчёт.
                setTimeout(get_status, 1000);
            }
        });
    }
    get_status();
</script>
<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
?>