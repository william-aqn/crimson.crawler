<?

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

if (class_exists("crimson_crawler"))
    return;

class crimson_crawler extends CModule {

    var $MODULE_ID = "crimson.crawler";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;

    function crimson_crawler() {
        $arModuleVersion = array();

        $path = str_replace("\\", "/", __FILE__);
        $path = substr($path, 0, strlen($path) - strlen("/index.php"));
        include($path . "/version.php");

        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];

        $this->MODULE_NAME = Loc::getMessage("CRIMSON_CRAWLER_MODULE_NAME");
        $this->MODULE_DESCRIPTION = Loc::getMessage("CRIMSON_CRAWLER_MODULE_DISCRIPTION");

        $this->PARTNER_NAME = Loc::getMessage("CRIMSON_PARTNER_NAME");
        $this->PARTNER_URI = Loc::getMessage("CRIMSON_PARTNER_URI");
        
    }

    function AddAgent() {
        \CAgent::AddAgent("\CrimsonCrawlerHelper::start();", $this->MODULE_ID, "N", 3600);
    }

    function DoInstall() {
        global $APPLICATION;
        $this->InstallEvents();
        $this->InstallFiles();
        $this->AddAgent();
        $APPLICATION->IncludeAdminFile(Loc::getMessage("CRIMSON_INSTALL_TITLE"), $_SERVER["DOCUMENT_ROOT"] . "/local/modules/{$this->MODULE_ID}/install/step1.php");
    }

    function DoUninstall() {
        global $APPLICATION;
        $this->UninstallFiles();
        \CAgent::RemoveModuleAgents($this->MODULE_ID);
        $APPLICATION->IncludeAdminFile(Loc::getMessage("CRIMSON_UNINSTALL_TITLE"), $_SERVER["DOCUMENT_ROOT"] . "/local/modules/{$this->MODULE_ID}/install/unstep1.php");
    }

    function InstallFiles() {
        CopyDirFiles($_SERVER["DOCUMENT_ROOT"] . "/local/modules/{$this->MODULE_ID}/install/admin", $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin", true, true);
        RegisterModule($this->MODULE_ID);
    }

    function UninstallFiles() {
        DeleteDirFilesEx("/bitrix/admin/crimson-crawler.php");
        UnRegisterModule($this->MODULE_ID);
    }

//    /**
//     * Установка событий модуля
//     *
//     * @return true
//     */
//    public function InstallEvents() {
//        $eventManager = EventManager::getInstance();
//        $eventManager->registerEventHandlerCompatible(
//                'main',
//                'OnBuildGlobalMenu',
//                $this->MODULE_ID,
//                'CrimsonCrawlerHelper',
//                'menu'
//        );
//
//        return true;
//    }
//
//    /**
//     * Удаление событий модуля
//     *
//     * @return true
//     */
//    public function UnInstallEvents() {
//        EventManager::getInstance()->unRegisterEventHandler(
//                'main',
//                'OnBuildGlobalMenu',
//                $this->MODULE_ID,
//                'CrimsonCrawlerHelper',
//                'menu'
//        );
//
//        return true;
//    }

}
