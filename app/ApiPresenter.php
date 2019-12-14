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
    /** @var int maximum of records */
    const MAX_RECORDS = 50;

    /** @var int minimum CSV file size */
    const CSV_MIN_SIZE = 42;

    /** @var int maximum access hits */
    const MAX_API_HITS = 1000;

    /** @var string API cache profile */
    const API_CACHE = "tenseconds";

    /** @var string CSV header */
    const CSV_HEADERS = "title,description,author,copyright,itunes_author,itunes_category,itunes_explicit,itunes_image,itunes_keywords,itunes_owner,itunes_subtitle,itunes_summary,itunes_type,generator,pubDate,lastBuildDate,ttl,managingEditor,docs,rssfeed,link,xmlid,uid";

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

            case "GetPublicStatus":
                if ($cache = Cache::read($view, self::API_CACHE)) {
                    return $this->writeJsonData($cache, $extras);
                }
                $data = $this->getPublicStatus();
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

            case "GetAPIKey":
                if (!$d["user"]["id"]) { // user unauthorized
                    return $this->writeJsonData(401, $extras);
                }
                $data = $this->getAPIkey();
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "CreateAPIKey":
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

            case "DeleteAPIKey":
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

            case "GetPrivateStatus":
                if (!$d["user"]["id"]) { // user unauthorized
                    return $this->writeJsonData(401, $extras);
                }
                //$data = $this->getPrivateStatus();
                $data = [
                    "id" => $d["user"]["id"],
                    "email" => $d["user"]["email"],
                    "name" => $d["user"]["name"],
                ];
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "GetPodcastsVersions":
                if ($cache = Cache::read($view, self::API_CACHE)) {
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
                if ($cache = Cache::read($view, self::API_CACHE)) {
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

            case "GetPodcasts":
                if ($cache = Cache::read($view, self::API_CACHE)) {
                    return $this->writeJsonData($cache, $extras);
                }
                $data = $this->getPodcasts();
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                Cache::write($view, $data, self::API_CACHE);
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

            case "AddPodcastsTag":
                $data = $this->addPodcastsTag();
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "DeletePodcastsTag":
                $data = $this->deletePodcastsTag();
                if (is_null($data)) {
                    sleep(2); // fail
                    return $this->writeJsonData(404, $extras);
                }
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "DeletePodcastsTags":
                $data = $this->deletePodcastsTags();
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
     * Get public status
     *
     * @return array
     */
    private function getPublicStatus()
    {
        // get all records
        $records = $this->readCsv($this->getCfg("podcasts_csv"));
        // statistics
        $result = [
            "podcasts_count" => count($records["uid"]),
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
            return [];
        }

        // primary / secondary cache
        $cache_key = "{$id}_csv_processed";
        $cache_key_bak = "{$id}_csv_backup";
/*
        // primary cache
        if ($data = Cache::read($cache_key, "csv")) {
            return $data;
        }

        // secondary cache
        if ($data = Cache::read($cache_key_bak, "csv_bak")) {
            return $data;
        }
*/
        // load CSV file from DATA folder
        $csv = \file_get_contents(DATA . "/$id");
        if (strlen($csv) < self::CSV_MIN_SIZE) {
            return [];
        }
        // create Symfony file lock
        $store = new FlockStore();
        $factory = new Factory($store);
        $lock = $factory->createLock("read-csv-seznam");
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
        //dump($key);
        $redis = new RedisClient([
            'server' => '127.0.0.1:6377',
            'timeout' => 2,
        ]);
        try {
            $val = (int) @$redis->get($key);
        } catch (\Exception $e) {
            return 0;
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
            return 0;
        }
        return $val++;
    }
}
