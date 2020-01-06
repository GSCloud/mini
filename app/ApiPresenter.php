<?php
/**
 * GSC Tesseract
 *
 * @category Framework
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 */

namespace GSC;

use Cake\Cache\Cache;
use League\Csv\Reader;
use League\Csv\Statement;
use RedisClient\RedisClient;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Store\FlockStore;

/**
 * API Presenter
 */
class ApiPresenter extends APresenter
{
    /** @var bool use cache? */
    //const USE_CACHE = true;
    const USE_CACHE = false;

    /** @var string podcasts CSV */
    const PODCASTS_CSV = "podcasts.csv";

    /** @var string private API key salt */
    const PRIVATE_KEY_SALT = "2BH*L(H+]*H%&T)j-MqB._8'%_6:;UAu";

    /** @var int maximum of records */
    const MAX_RECORDS = 100;
    //const MAX_RECORDS = 3;

    /** @var int minimum CSV file size = the meaning of the universe! */
    const CSV_MIN_SIZE = 42;

    /** @var int maximum access hits */
    const MAX_API_HITS = 1000;

    /** @var string API cache profile */
    const API_CACHE = "hour";

    /** @var string CSV headers */
    const CSV_HEADERS = "title,description,author,copyright,itunes_author,itunes_category,itunes_explicit,itunes_image,itunes_keywords,itunes_owner,itunes_subtitle,itunes_summary,itunes_type,generator,pubDate,lastBuildDate,ttl,managingEditor,docs,rssfeed,link,xmlid,uid";

    /** @var string CSV headers for checksum */
    const CSV_HEADERS_CHECKSUM = "title,description,author,copyright,itunes_author,itunes_category,itunes_explicit,itunes_image,itunes_keywords,itunes_owner,itunes_subtitle,itunes_summary,itunes_type,generator,pubDate,lastBuildDate,ttl,managingEditor,docs,rssfeed,link";

    /**
     * Main controller
     */
    public function process()
    {
        $cfg = $this->getCfg();
        $d = $this->getData();
        $match = $this->getMatch();
        $view = $this->getView();

        setlocale(LC_ALL, "cs_CZ.utf8");

        // check API keys
        $err = 0;
        if (isset($_GET["api"])) {
            $api = (string) $_GET["api"];
            $key = $this->getCfg("ci_tester.api_key") ?? null;
            // check CI tester key
            if ($key !== $api) {
                // invalid API key
                $err++;
            }
            // @TODO!!! check user generated key
        } else {
            // no API key
            $err++;
        }
        if ($err) {
            $this->checkRateLimit();
        }

        $extras = [
            "api_limit" => self::MAX_API_HITS,
            "api_time_limit" => self::API_CACHE,
            "api_usage" => $this->accessLimiter(),
            "fn" => $view,
            "name" => "PodcastAPI",
            "uuid" => $this->getUID(),
        ];

        $d["user"] = $this->getCurrentUser() ?? [];
        $d["admin"] = $g = $this->getUserGroup() ?? "";
        if ($g) {
            $d["admin_group_${g}"] = true;
        }

        // API calls
        switch ($view) {

            case "GetPublicStatus": // IMPLEMENTED
                $data = $this->getPublicStatus();
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "GetPrivateStatus": // IMPLEMENTED
                if (!$d["user"]["id"] ?? null) { // user unauthorized
                    return $this->writeJsonData(401, $extras);
                }
                $data = $this->getPrivateStatus($d);
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "GetAPIkey": // IMPLEMENTED
                if (!$d["user"]["id"]) { // user unauthorized
                    return $this->writeJsonData(401, $extras);
                }
                $data = $this->getAPIkey($d);
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "CheckAPIkey": // IMPLEMENTED
                $key = $match["params"]["key"] ?? null;
                $data = $this->checkAPIkey($key);
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "GetPodcasts": // IMPLEMENTED
                $list = $match["params"]["list"] ?? null;
                $data = $this->getPodcasts($list);
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "GetPodcastsVersions": // IMPLEMENTED
                if (self::USE_CACHE && $cache = Cache::read($view, self::API_CACHE)) {
                    return $this->writeJsonData($cache, $extras);
                }
                $data = $this->getPodcastsVersions();
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                Cache::write($view, $data, self::API_CACHE);
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "GetEpisodesVersions":
                if (self::USE_CACHE && $cache = Cache::read($view, self::API_CACHE)) {
                    return $this->writeJsonData($cache, $extras);
                }
                $data = $this->getEpisodesVersions();
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                Cache::write($view, $data, self::API_CACHE);
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "GetEpisodes":
                if (self::USE_CACHE && $cache = Cache::read($view, self::API_CACHE)) {
                    return $this->writeJsonData($cache, $extras);
                }
                $data = $this->getEpisodes();
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                Cache::write($view, $data, self::API_CACHE);
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "AddPodcasts":
                $data = $this->addPodcasts();
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "CreateAPIkey":
                if (!$d["user"]["id"]) { // user unauthorized
                    return $this->writeJsonData(401, $extras);
                }
                $data = $this->createAPIkey();
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "DeleteAPIkey":
                if (!$d["user"]["id"]) { // user unauthorized
                    return $this->writeJsonData(401, $extras);
                }
                $data = $this->deleteAPIkey();
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "DeletePodcasts":
                $data = $this->deletePodcasts();
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "AddTag":
                $data = $this->addTag();
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "DeleteTag":
                $data = $this->deleteTag();
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "DeleteAllTags":
                $data = $this->deleteAllTags();
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            default:
                sleep(2);
                return ErrorPresenter::getInstance()->process(404);
        }
        return $this;
    }

    /**
     * Get podcasts versions
     *
     * @return array
     */
    private function getPodcastsVersions()
    {
        // read data
        $f = self::PODCASTS_CSV;
        $records = $this->readCsv($f);
        if (\is_null($records)) {
            return null;
        }
        // records count
        $count = count($records["uid"]);
        // CRC columns
        $columns = explode(",", self::CSV_HEADERS_CHECKSUM);
        // string template
        $x = [];
        foreach ($columns as $c) {
            $x[] = $c . "<value>";
        }
        $template = join('', $x);
        // cycle through all records
        $i = 0;
        $arr = [];
        foreach ($records["uid"] as $r) {
            $str = [];
            foreach ($columns as $c) {
                $str[] = $c . $records[$c][$i];
            }
            $str = join('', $str);
            $arr[$i]["title"] = $records["title"][$i];
            $arr[$i]["uid"] = $r;
            $arr[$i]["version"] = hash("sha256", $str);
            $i++; // iterate over all records
        }
        // result
        $result = [
            "podcasts_count" => $count,
            "headers" => $columns,
            "version_template" => "sha256($template)",
            "records" => $arr,
        ];
        return $result;
    }

    /**
     * Get podcasts
     *
     * @param string $list list of records, separated by comma
     * @return array
     */
    private function getPodcasts($list)
    {
        if (!strlen($list)) {
            return null;
        }
        $list = explode(",", (string) $list);
        $list = array_map("trim", $list);
        $list = array_map("intval", $list);
        $list = array_map("abs", $list);
        $list = array_filter($list, "is_int");
        $list = array_unique($list);

        if (!count($list)) {
            return null;
        }
        // CSV columns
        $columns = explode(",", self::CSV_HEADERS);
        // read data
        $f = self::PODCASTS_CSV;
        $csv = $this->readCsv($f);
        if (\is_null($csv)) {
            return null;
        }
        $count = 0;
        $records = [];
        foreach ($list as $id) {
            $arr = [];
            foreach ($columns as $c) {
                if (array_key_exists($id, $csv[$c])) {
                    $arr[$c] = $csv[$c][$id];
                } else {
                    $arr = null;
                }
            }
            $records[$id] = $arr;
            $count++;
            if ($count >= self::MAX_RECORDS) {
                // limit reached
                break;
            }
        }
        // result
        $result = [
            "max_records_limit" => self::MAX_RECORDS,
            "headers" => $columns,
            "records" => $records,
        ];
        return $result;
    }

    /**
     * Get private API key
     *
     * @return string
     */
    private function getAPIkey($d = null)
    {
        if (\is_null($d) || !isset($d["user"]["email"])) {
            return null;
        }
        // generate API key and save it
        $key = hash("sha256", self::PRIVATE_KEY_SALT . $d["user"]["email"]);
        if (@\file_put_contents(DATA . "/${key}_private.key", $d["user"]["email"], LOCK_EX) === false) {
            return null;
        }
        $result = [
            "apikey" => $key,
        ];
        return $result;
    }

    /**
     * Check API key
     *
     * @return bool
     */
    private function checkAPIkey($key = null)
    {
        if (\is_null($key)) {
            return null;
        }
        // hexadecimal only!!!
        $key = preg_replace("/[^a-fA-F0-9]+/", "", trim($key));
        if (strlen($key) != 64) {
            return null;
        }
        // check key file existence
        $f = DATA . "/${key}_private.key";
        if (\file_exists($f)) {
            $author = trim(\file_get_contents($f));
        } else {
            return null;
        }
        // result
        $result = [
            "apikey" => $key,
            "valid" => true,
            "author" => $author,
        ];
        return $result;
    }

    /**
     * Get public status
     *
     * @return array
     */
    private function getPublicStatus()
    {
        $f = self::PODCASTS_CSV;
        $records = $this->readCsv($f);
        if (\is_null($records)) {
            return null;
        }
        $timestamp = file_exists(DATA . "/${f}") ? @filemtime(DATA . "/${f}") : null;
        $count = count($records["uid"]);
        $today = date('j');
        $x = round($today * $count / 31 );
        // podcast of the day
        $potd_name = $records["title"][$x];
        $potd_url = $records["link"][$x];
        $potd_rss = $records["rssfeed"][$x];
        // result
        $result = [
            "potd" => [
                "title" => $potd_name,
                "link" => $potd_url,
                "rss" => $potd_rss,
            ],
            "podcasts_count" => $count,
            "timestamp" => $timestamp,
            "system_load" => function_exists("sys_getloadavg") ? \sys_getloadavg() : null,
        ];
        return $result;
    }

    /**
     * Get private status
     *
     * @param string $d CSV name within DATA folder
     * @return void
     */
    private function getPrivateStatus($d = null)
    {
        if (\is_null($d)) {
            return null;
        }
        $f = self::PODCASTS_CSV;
        $records = $this->readCsv($f);
        if (\is_null($records)) {
            return null;
        }
        $timestamp = file_exists(DATA . "/${f}") ? @filemtime(DATA . "/${f}") : null;
        // result
        $result = [
            "id" => $d["user"]["id"],
            "email" => $d["user"]["email"],
            "name" => $d["user"]["name"],
            "podcasts_count" => count($records["uid"]),
            "timestamp" => $timestamp,
            "system_load" => function_exists("sys_getloadavg") ? \sys_getloadavg() : null,
        ];
        return $result;
    }

    /**
     * Read CSV file into array
     *
     * @param string $id CSV filename
     * @return array CSV data or empty array
     */
    private function readCsv($id)
    {
        if (empty($id)) {
            return null;
        }
        // caches
        $cache_key = "{$id}_csv_processed";
        $cache_key_bak = "{$id}_csv_backup";
        // primary cache
        if ($data = Cache::read($cache_key, "csv")) {
            return $data;
        }
        // secondary cache
        if ($data = Cache::read($cache_key_bak, "csv_bak")) {
            return $data;
        }
        // load CSV file
        $csv = \file_get_contents(DATA . "/$id");
        if (strlen($csv) < self::CSV_MIN_SIZE) {
            return null;
        }
        // create a lock
        $store = new FlockStore();
        $factory = new Factory($store);
        $lock = $factory->createLock("podcasts-csv");
        if (!$lock->acquire()) {
            // another thread is rebuilding the data
            return null;
        }
        $data = [];
        $columns = explode(",", self::CSV_HEADERS);
        foreach ($columns as $col) {
            $$col = [];
            try {
                $reader = Reader::createFromString($csv);
                $reader->setHeaderOffset(0);
                $records = (new Statement())->offset(1)->process($reader);
                $i = 0;
                foreach ($records->fetchColumn($col) as $c) {
                    $$col[$i] = $c;
                    $i++;
                }
                $data[$col] = $$col;
            } catch (\Exception $e) {
                if (!LOCALHOST) {
                    $this->addError("EXCEPTION: CSV reader, column: $col");
                    $this->addError($e->getMessage());
                }
            }
        }
        Cache::write($cache_key, $data, "csv");
        Cache::write($cache_key_bak, $data, "csv_bak");
        $lock->release();
        return $data;
    }

    /**
     * Access limiter
     *
     * @return int access count
     */
    private function accessLimiter()
    {
        $hour = date("H");
        $uid = $this->getUID();
        $key = "access_limiter_" . SERVER . "_" . PROJECT . "_{$hour}_{$uid}";
        $redis = new RedisClient([
            'server' => 'localhost:6377',
            'timeout' => 1,
        ]);
        try {
            $val = (int) @$redis->get($key);
        } catch (\Exception $e) {
            return null;
        }
        if ($val > self::MAX_API_HITS) {
            // over the limit!
            $this->setLocation("/err/420");
        }
        try {
            @$redis->multi();
            @$redis->incr($key);
            @$redis->expire($key, 3599);
            @$redis->exec();
        } catch (\Exception $e) {
            return null;
        }
        $val++;
        return $val;
    }
}
