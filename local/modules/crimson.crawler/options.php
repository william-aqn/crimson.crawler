<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
\Bitrix\Main\Loader::includeModule(basename(__DIR__));

/**
 * https://dev.1c-bitrix.ru/community/webdev/user/203730/blog/13249/
 */
class CrimsonCrawlerOptions {

    private $module_id;

    public function __construct() {
        $this->module_id = \basename(__DIR__);

        global $APPLICATION;
        $AUTH_RIGHT = $APPLICATION->GetGroupRight($this->module_id);

        if ($AUTH_RIGHT <= "D") {
            $APPLICATION->AuthForm(Loc::getMessage("ACCESS_DENIED"));
        }

        // Проверяем наличие curl на сервере
        if (!function_exists("curl_init")) {
            \CAdminMessage::ShowMessage(array(
                "MESSAGE" => "Установите php_curl",
                "DETAILS" => "Не установлено расширение curl на сервере.",
                "HTML" => true,
                "TYPE" => "ERR",
            ));
        }

        // TODO: проверить наличие сайтов в /etc/hosts
        // Проверяем наличие sitemap.xml у сайтов
        $crawler = new \CrimsonCrawlerHelper();
        foreach ($crawler->getSites() as $key => $site) {
            \CAdminMessage::ShowMessage(array(
                "MESSAGE" => "[{$key}] {$site['NAME']}",
                "DETAILS" => "{$site['SITEMAP_FILE']}<br><a href='/bitrix/admin/seo_sitemap.php?lang=ru'>Настроить</a>",
                "HTML" => true,
                "TYPE" => ($site['SITEMAP_FILE_EXISTS'] == 'Y' ? "OK" : "ERR"),
            ));
        }

        \Bitrix\Main\Loader::includeModule('iblock');
        $iblock_default_select = [];
        if (\Bitrix\Main\Loader::includeModule('seo')) {
            // Выбираем все инфоблоки из всех карт сайта
            $rsSiteMaps = \Bitrix\Seo\SitemapIblockTable::getList(['select' => ['IBLOCK_ID']]);
            while ($siteMap = $rsSiteMaps->fetch()) {
                $iblock_default_select[$siteMap['IBLOCK_ID']] = true;
            }
        }
//        $rsIblockTypes = \Bitrix\Iblock\TypeTable::getList();
//        while ($iblockTypes = $rsIblockTypes->fetch()) {
//            TB::pr($iblockTypes);
//        }
        // Выводим инфоблоки сгруппированные по типу инфоблока
        $listIblocks = [];
        $rsIblocks = \Bitrix\Iblock\IblockTable::getList([
                    'select' => ['ID', 'NAME', 'IBLOCK_TYPE_ID'],
                    //'select' => ['*'],
                    'order' => [['IBLOCK_TYPE_ID' => 'ASC'], ['SORT' => 'ASC']],
        ]);
        $curType = '';
        while ($iblocks = $rsIblocks->fetch()) {
            if ($curType != $iblocks['IBLOCK_TYPE_ID']) {
                $curType = $iblocks['IBLOCK_TYPE_ID'];
                // Небольшой хак для удобства восприятия. disabled, или optgroup нельзя в __AdmSettingsDrawList для options :(
                $listIblocks[$iblocks['IBLOCK_TYPE_ID']] = "---------------{$iblocks['IBLOCK_TYPE_ID']}---------------";
            }
            $listIblocks[$iblocks['ID']] = $iblocks['NAME'];
        }
        unset($curType);

        $aTabs = [
            [
                "DIV" => "edit_all",
                "TAB" => "Общие настройки",
                "OPTIONS" => [
                    'Выберите сайты для переобхода', // Заголовок
                    [
                        'URL', // Ключ
                        'Ссылки на sitemap.xml', // Название поля
                        "http://{$_SERVER['SERVER_NAME']}/sitemap.xml", // По умолчанию
                        [
                            'textarea',
                            5,
                            90
                        ]
                    ],
//                    [
//                        'TIME',
//                        'Интервал переобхода (минут)',
//                        60,
//                        ['text', 5]
//                    ],
                    'Выберите инфоблоки для переобхода элементов, после их изменения/добавления.',
                    ['note' => 'По умолчанию поле заполнено инфоблоками из существующих карт сайта. Нажимайте на поле с crtl, иначе всё сбросится'],
                    [
                        'IBLOCKS',
                        'Инфоблоки',
                        implode(',', array_keys($iblock_default_select)), // По умолчанию
                        [
                            'multiselectbox',
                            $listIblocks
                        ]
                    ],
                ]
            //"TITLE" => Loc::getMessage("TAB_TITLE")
            ]
        ];

        // Сохраняем настройки
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && strlen($_REQUEST['save']) > 0 && $AUTH_RIGHT == "W" && check_bitrix_sessid()) {
            foreach ($aTabs as $aTab) {
                \__AdmSettingsSaveOptions($this->module_id, $aTab['OPTIONS']);
            }
            LocalRedirect($APPLICATION->GetCurPage() . '?lang=' . LANGUAGE_ID . '&mid_menu=1&mid=' . urlencode($this->module_id) .
                    '&tabControl_active_tab=' . urlencode($_REQUEST['tabControl_active_tab']) . '&sid=' . urlencode(SITE_ID));
        }

        // Показываем форму
        $tabControl = new \CAdminTabControl('tabControl', $aTabs);
        ?><form method='post' action='' name='bootstrap'>
        <?
            $tabControl->Begin();
            foreach ($aTabs as $aTab) {
                $tabControl->BeginNextTab();
                \__AdmSettingsDrawList($this->module_id, $aTab['OPTIONS']);
            }
            $tabControl->Buttons(array('btnApply' => false, 'btnCancel' => false, 'btnSaveAndAdd' => false));
            ?>
            <?= \bitrix_sessid_post(); ?>
            <? $tabControl->End(); ?>
        </form><?
    }

}

new \CrimsonCrawlerOptions();
