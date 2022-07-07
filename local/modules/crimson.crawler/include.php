<?

class CrimsonCrawlerIblock {

    public function add(&$arFields) {
        return static::init($arFields);
    }

    public function update(&$arFields) {
        return static::init($arFields);
    }

    private static function init(&$arFields) {
        if (!$arFields["RESULT"]) {
            return false;
        }

        $helper = new \CrimsonCrawlerHelper();
        $iblocks = $helper->getConfig()['iblocks'];
        if (in_array($arFields['IBLOCK_ID'], $iblocks)) {

            $res = \CIBlockElement::GetByID($arFields['ID']);
            if ($ar_res = $res->GetNext()) {
                $sites = $helper->getSites();
                $url = "http://{$sites[$ar_res['LID']]['SERVER_NAME']}{$ar_res['DETAIL_PAGE_URL']}";
                if ($content = $helper->innerParser($url)) {
                    \CAdminNotify::Add([
                        'MESSAGE' => "Переобход ссылки <a href='$url' target='_blank'>{$content['url']}</a> завершён. Код: {$content['code']}; Время загрузки: {$content['time']}",
                        'TAG' => 'crimson_crawler_notify',
                        'MODULE_ID' => $helper->getModuleId(),
                        'ENABLE_CLOSE' => 'Y'
                    ]);
                }
            }
        }
    }

}

class CrimsonCrawlerHelper {

    private $module_id;
    private $sites = [];
    private $config = [];

    const WORK_DIR = __DIR__ . '/worker';
    const CRAWLER_SH = self::WORK_DIR . "/crawler.sh";

    public function __construct() {
        $this->module_id = basename(__DIR__);
        \Bitrix\Main\Loader::includeModule("main");
        $this->getConfig();
    }

    public function getModuleId() {
        return $this->module_id;
    }

    public function innerParser($url) {
        if (!$url) {
            return false;
        }
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_NOBODY, true); // Только загловки вернутся

        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);

        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $resp = curl_exec($curl);
        $data = [
            'code' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
            'time' => curl_getinfo($curl, CURLINFO_TOTAL_TIME),
            'url' => curl_getinfo($curl, CURLINFO_EFFECTIVE_URL),
            'result' => $resp
        ];
        //highlight_string($resp); die();
        curl_close($curl);
        return $data;
    }

    /**
     * Список активных сайтов
      [LID] => en
      [SORT] => 2
      [DEF] => N
      [ACTIVE] => Y
      [NAME] => Proxysale (en)
      [DIR] => /en/
      [LANGUAGE_ID] => en
      [DOC_ROOT] =>
      [DOMAIN_LIMITED] => N
      [SERVER_NAME] => proxy-sale.com
      [SITE_NAME] => Proxysale
      [EMAIL] => no-reply@proxy-sale.com
      [CULTURE_ID] => 2
     */
    public function getSites() {
        if (count($this->sites) == 0) {
            $this->sites = [];
            $res = \Bitrix\Main\SiteTable::getList(array('filter' => array('=ACTIVE' => 'Y')));
            while ($item = $res->Fetch()) {
                // Проверка на существование карты сайта
                $this->checkSitemap($item);
                $this->sites[$item['LID']] = $item;
            }
        }
        return $this->sites;
    }

    private function checkSitemap(&$item) {
        if (!$item['DOC_ROOT']) {
            $item['DOC_ROOT'] = $_SERVER["DOCUMENT_ROOT"];
        }
        $file = "{$item['DOC_ROOT']}{$item['DIR']}sitemap.xml";

        $item['SITEMAP_FILE'] = $file;
        $item['SITEMAP_FILE_EXISTS'] = (file_exists($file) ? "Y" : "N");
    }

    public function sitesMultiselect() {
        $sites = $this->getSites();
        $ret = [];
        foreach ($sites as $item) {
            $ret[$item['LID']] = "[{$item['NAME']}] - Файл " . ($item['SITEMAP_FILE'] == 'N' ? "отсутствует" : "существует") . " [{$item['SITEMAP_FILE']}]";
        }
        return $ret;
    }

    /**
     * Получить данные из настроек модуля
     * @return array
     */
    public function getConfig() {
        if (count($this->config) == 0) {
            $urls = explode("\n", \COption::GetOptionString($this->module_id, "URL"));
            $sites = [];
            foreach ($urls as $url) {
                $url = trim($url);
                if (!$url) {
                    continue;
                }
                $sites[md5($url)] = $url;
            }
            $iblocks = explode(",", \COption::GetOptionString($this->module_id, "IBLOCKS"));
            $this->config = [
                'sites' => $sites,
                'iblocks' => $iblocks,
                'time' => \COption::GetOptionString($this->module_id, "TIME")
            ];
        }

        return $this->config;
    }

    /**
     * Запускаем в фоне скрипт
     * @param type $id
     * @return boolean
     */
    public function run($id) {
        $run_dir = static::WORK_DIR . "/task/$id";
        if (!is_dir($run_dir)) {
            return false;
        }

        $command = "/bin/bash " . static::CRAWLER_SH . " $run_dir 2>&1 > $run_dir/crawler.log &";
        //echo $command;
        proc_close(proc_open($command, [], $foo));
        //var_dump(exec($command));
        return true;
    }

    /**
     * Останавливаем всё
     * @param type $id
     * @return type
     */
    public function stop() {
        $command = "sh " . static::CRAWLER_SH . "";
        return exec($command);
        //return true;
    }

    /**
     * Удалить рекурсивно директорию
     * @param type $dirPath
     * @return boolean
     */
    private function rmdirRecursive($dirPath) {
        if (!empty($dirPath) && \is_dir($dirPath)) {
            $dirObj = new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS); //upper dirs not included,otherwise DISASTER HAPPENS :)
            $files = new \RecursiveIteratorIterator($dirObj, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $path)
                $path->isDir() && !$path->isLink() ? \rmdir($path->getPathname()) : \unlink($path->getPathname());
            \rmdir($dirPath);
            return true;
        }
        return false;
    }

    /**
     * Очистить папку с заданием
     * @param type $id
     * @return boolean
     */
    public function clear($id) {
        if ($this->getUrlById($id)) {
            return $this->rmdirRecursive(static::WORK_DIR . "/task/$id");
        }
        return false;
    }

    /**
     * Получить url карты сайта
     * @param type $id
     * @return boolean
     */
    public function getUrlById($id) {
        if (!$this->config['sites'][$id]) {
            return false;
        }
        return $this->config['sites'][$id];
    }

    /**
     * Проверить/создать директорию для задания
     * @param type $id
     * @return boolean
     */
    private function checkWorkDir($id) {
        if (!is_dir(static::WORK_DIR . "/task/$id/")) {
            return mkdir(static::WORK_DIR . "/task/$id/");
        }
        return true;
    }

    /**
     * Проверить файл со ссылками задания
     * @param type $id
     * @param string $name
     * @return boolean
     */
    public function checkTaskLinksFile($id, &$fname = "") {
        $fname = static::WORK_DIR . "/task/$id/links.csv";
        if (!file_exists($fname)) {
            return false;
        }
        return true;
    }

    /**
     * Проверяем/получаем файл статуса задания
     * @param type $id
     * @param type $last_update
     * @return string
     */
    public function getTaskStatusFromFile($id, &$last_update) {
        $fname = static::WORK_DIR . "/task/$id/status.csv";
        if (!file_exists($fname)) {
            $last_update = '-';
            return "wait";
        }
        $last_update = date("d.m.Y H:i:s", filemtime($fname));
        return file_get_contents($fname);
    }

    /**
     * Получаем список загруженными файлов задания 
     * @param type $id
     * @return type
     */
    public function getTaskCountParsedLinks($id) {
        return glob(static::WORK_DIR . "/task/$id/page_*");
    }

    /**
     * Проверяем/получаем файл с отчётом по заданию
     * @param type $id
     * @param type $last_update
     * @return string
     */
    public function getTaskReportFileName($id) {
        $fname = static::WORK_DIR . "/task/$id/report.csv";
        if (!file_exists($fname)) {
            return false;
        }
        return $fname;
        //return file_get_contents($fname);
    }

    public function getTaskReportDetailFile($taskId, $fileId) {

        if (!$this->getUrlById($taskId)) {
            return false;
        }

        $fname = static::WORK_DIR . "/task/$taskId/$fileId";
        if (!file_exists($fname)) {
            return false;
        }
        return file_get_contents($fname);
    }

    /**
     * Содержимое файла ссылок
     * @param type $id
     * @return boolean
     */
    public function loadTaskLinksFile($id) {
        if ($this->checkTaskLinksFile($id, $fname)) {
            $urls = file_get_contents($fname);
            return $urls;
        }
        return false;
    }

    /**
     * (Пере)Создать файл со ссылками
     * @param type $id
     * @param type $url
     * @return boolean
     */
    private function genTaskLinksFile($id) {
        if ($sitemap_url = $this->getUrlById($id)) {
            $result = $this->parseSitemap($sitemap_url);
            $f = @fopen(static::WORK_DIR . "/task/$id/links.csv", 'w');
            if (!$f) {
                return false;
            }
            foreach ($result['urls'] as $url => $arr) {
                fwrite($f, "$url\n");
            }
            fclose($f);
            return $result;
        }
        return false;
    }

    public function parseSitemap($url) {
        // Подключаем парсер xml
        require_once __DIR__ . '/lib/smparser/autoload.php';
        try {
            $config = [
                // put any GuzzleHttp options here
                'guzzle' => [
                    'allow_redirects' => [
                        'max' => 2,
                    ],
                    'verify' => false
                ]
            ];
            $parser = new \vipnytt\SitemapParser('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:102.0) Gecko/20100101 Firefox/102.0', $config);
            $parser->parseRecursive($url);
            return ['sitemaps' => $parser->getSitemaps(), 'urls' => $parser->getURLs()];
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        return false;
    }

    /**
     * Инициализируем файл с заданием
     * @param type $id
     * @return string
     */
    public function initTask($id) {
        $ret = ['last_update' => '', 'links_total' => 0, 'links_processed' => 0, 'status' => 'init'];
        if ($url = $this->getUrlById($id)) {
            // Проверяем/создаём папку для отчёта
            if (!$this->checkWorkDir($id)){
                $ret['status'] = 'error: cant create work dir: ['.static::WORK_DIR . "/task/$id/]";
                return $ret;
            }

            // Проверяем/создаём файл со ссылками
            if (!$this->checkTaskLinksFile($id)) {
                if (!$this->genTaskLinksFile($id)) {
                    $ret['status'] = 'error: no generate links.csv';
                    return $ret;
                }
            }

            // Получаем статус из файла, и время модификации файла
            $ret['status'] = $this->getTaskStatusFromFile($id, $ret['last_update']);
            $taskLinks = $this->loadTaskLinksFile($id);
            // Считаем количество ссылок в файле
            $ret['links_total'] = count(explode("\n", $taskLinks)) - 1; // -1
            // Считаем количество загруженных страниц
            $ret['links_processed'] = count($this->getTaskCountParsedLinks($id));
        }

        return $ret;
    }

    /**
     * Агент для проверки
     * @return string
     */
    function start() {
        try {
            //static::check();
        } catch (Exception $exc) {
            
        }
        return "\CrimsonCrawlerHelper::start();";
    }

}
