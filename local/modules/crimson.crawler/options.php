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

        // Проверяем наличие sitemap.xml у сайтов
        $crawler = new \CrimsonCrawlerHelper();
        foreach ($crawler->sites() as $key => $site) {
            \CAdminMessage::ShowMessage(array(
                "MESSAGE" => "[{$key}] {$site['NAME']}",
                "DETAILS" => "{$site['SITEMAP_FILE']}<br><a href='/bitrix/admin/seo_sitemap.php?lang=ru'>Настроить</a>",
                "HTML" => true,
                "TYPE" => ($site['SITEMAP_FILE_EXISTS'] == 'Y' ? "OK" : "ERR"),
            ));
        }

        $aTabs = [
            [
                "DIV" => "edit_all",
                "TAB" => "Общие настройки",
                "OPTIONS" => [
                    'Выберите сайты для переобхода', // Заголовок
//                    [
//                        'SITES', // Ключ
//                        'Активные сайты в системе', // Название поля
//                        's1', // По умолчанию
//                        [
//                            'multiselectbox',
//                            $crawler->sites_multiselect()
//                        ]
//                    ],
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
                    [
                        'TIME',
                        'Интервал переобхода (минут)',
                        60,
                        ['text', 5]
                    ]
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
