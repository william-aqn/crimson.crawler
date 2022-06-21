<?

use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

\Bitrix\Main\EventManager::getInstance()->addEventHandler("main", "OnBuildGlobalMenu", function (&$aGlobalMenu, &$aModuleMenu) {
    global $USER;
    if (!$USER->IsAdmin())
        return;

    $aMenu = array(
        "parent_menu" => "global_menu_marketing",
        "section" => "clouds",
        "sort" => 50,
        "text" => GetMessage("CRIMSON_CRAWLER_MENU"),
        "title" => GetMessage("CRIMSON_CRAWLER_MENU"),
        "url" => "/bitrix/admin/settings.php?mid=crimson.crawler&lang=" . LANGUAGE_ID,
        "icon" => "update_menu_icon_partner",
        "page_icon" => "update_menu_icon_partner",
        "items_id" => "menu_crawler",
        "more_url" => array(
            "crimson-crawler.php",
        ),
        "items" => array()
    );
    
    // TODO: Стоит ли в модуль перенести...?
    $urls = explode("\n", \COption::GetOptionString('crimson.crawler', "URL"));
    foreach ($urls as $url) {
        if (!trim($url)) {
            continue;
        }
        $aMenu["items"][] = array(
            "text" => trim($url),
            "url" => "crimson-crawler.php?lang=" . LANGUAGE_ID . "&id=" . md5(trim($url)) . "",
            "more_url" => array(
                //"clouds_file_list.php?bucket=" . $arBucket["ID"],
            ),
            "title" => "",
            "page_icon" => "",
            "items_id" => "menu_clouds_bucket_" . md5(trim($url)),
            "module_id" => "crimson.crawler",
            "items" => array()
        );
    }
    $aModuleMenu[] = $aMenu;
});
