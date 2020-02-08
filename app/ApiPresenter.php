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
    const USE_CACHE = true;
    //const USE_CACHE = false;

    /** @var string API cache profile */
    const API_CACHE = "tenminutes";

    /** @var string API access time limit */
    const ACCESS_TIME_LIMIT = 3599;

    /** @var string podcasts CSV */
    const PODCASTS_CSV = "podcasts.csv";

    /** @var string episodes path */
    const EPISODES_PATH = ROOT . "/XML/";

    /** @var string episodes file extension */
    const EPIS_EXT = ".epis.json";

    /** @var string private API key salt */
    const PRIVATE_KEY_SALT = "2BH*L(H+]*H%&T)j-MqB._8'%_6:;UAu";

    /** @var int maximum records */
    const MAX_RECORDS = 300;

    /** @var int minimum CSV filesize joke :) */
    const CSV_MIN_SIZE = 42;

    /** @var int maximum access hits */
    const MAX_API_HITS = 1000;

    /** @var string CSV headers */
    const CSV_HEADERS = "title,description,author,copyright,itunes_author,itunes_category,itunes_explicit,itunes_image,itunes_keywords,itunes_owner,itunes_subtitle,itunes_summary,itunes_type,generator,pubDate,lastBuildDate,ttl,managingEditor,docs,rssfeed,link,episodes,episodes_version,xmlid,uid";

    /** @var string CSV headers for episodes */
    const CSV_HEADERS_EPIS = "title,uid,episodes,episodes_version";

    /** @var string CSV headers for checksum hashing */
    const CSV_HEADERS_CHECKSUM = "title,description,author,copyright,itunes_author,itunes_category,itunes_explicit,itunes_image,itunes_keywords,itunes_owner,itunes_subtitle,itunes_summary,itunes_type,generator,managingEditor,rssfeed,link";

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
            "api_quota" => (int) self::MAX_API_HITS,
            "api_usage" => $this->accessLimiter(),
            "access_time_limit" => self::ACCESS_TIME_LIMIT,
            "cache_time_limit" => $this->getData("cache_profiles")[self::API_CACHE],
            "records_quota" => self::MAX_RECORDS,
            "fn" => $view,
            "name" => "PodcastAPI",
            "uuid" => $this->getUID(),
        ];

        // user authorization status
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
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras);
                break;

            case "GetPrivateStatus": // IMPLEMENTED
                if (!$d["user"]["id"] ?? null) {
                    // unauthorized user
                    return $this->writeJsonData(401, $extras);
                }
                $data = $this->getPrivateStatus($d); // user data
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras);
                break;

            case "GetWordCloud":
                $data = $this->getWordCloud();
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "GetAPIkey": // IMPLEMENTED
                if (!$d["user"]["id"]) {
                    // unauthorized user
                    return $this->writeJsonData(401, $extras);
                }
                $data = $this->getAPIkey($d);
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras);
                break;

            case "GetAPIkeys":
                if (!$d["user"]["id"]) {
                    // unauthorized user
                    return $this->writeJsonData(401, $extras);
                }
                $data = $this->getAPIkeys();
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras);
                break;

            case "CheckAPIkey": // IMPLEMENTED
                $key = $match["params"]["key"] ?? null;
                $data = $this->checkAPIkey($key); // user key
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras);
                break;

            case "GetPodcasts": // IMPLEMENTED
                $list = $match["params"]["list"] ?? null;
                $data = $this->getPodcasts($list); // podcast list
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData((object) $data, $extras);
                break;

            case "GetEpisodes": // IMPLEMENTED
                $list = $match["params"]["list"] ?? null;
                $data = $this->getEpisodes($list); // podcast list
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData((object) $data, $extras);
                break;

            case "GetPodcastsVersions": // IMPLEMENTED
                if (self::USE_CACHE && $data = Cache::read($view, self::API_CACHE)) {
                    $extras["keys"] = array_keys($data);
                    return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                }
                $data = $this->getPodcastsVersions();
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                Cache::write($view, $data, self::API_CACHE);
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "GetEpisodesVersions": // IMPLEMENTED
                if (self::USE_CACHE && $data = Cache::read($view, self::API_CACHE)) {
                    $extras["keys"] = array_keys($data);
                    return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                }
                $data = $this->getEpisodesVersions();
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                Cache::write($view, $data, self::API_CACHE);
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "AddPodcast":
                $data = $this->addPodcast();
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "CreateAPIkey":
                if (!$d["user"]["id"]) {
                    // unauthorized user
                    return $this->writeJsonData(401, $extras);
                }
                $data = $this->createAPIkey();
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "DeleteAPIkey":
                if (!$d["user"]["id"]) {
                    // unauthorized user
                    return $this->writeJsonData(401, $extras);
                }
                $data = $this->deleteAPIkey();
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "DeletePodcasts":
                $data = $this->deletePodcasts();
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "AddTag":
                $data = $this->addTag();
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "DeleteTag":
                $data = $this->deleteTag();
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "DeleteAllTags":
                $data = $this->deleteAllTags();
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
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
            $i++;
        }

        $result = [
            "podcasts_count" => $count,
            "hashed_headers" => $columns,
            "records" => $arr,
        ];
        return $result;
    }

    /**
     * Get episodes versions
     *
     * @return array
     */
    private function getEpisodesVersions()
    {
        // read data
        $f = self::PODCASTS_CSV;
        $records = $this->readCsv($f);
        if (\is_null($records)) {
            return null;
        }

        // records count
        $count = count($records["uid"]);

        // cycle through all records
        $i = 0;
        $arr = [];
        foreach ($records["uid"] as $r) {
            $arr[$i]["title"] = $records["title"][$i];
            $arr[$i]["uid"] = $r;
            $arr[$i]["version"] = $records["episodes_version"][$i];
            $i++;
        }

        $result = [
            "podcasts_count" => $count,
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

        // get CSV
        $f = self::PODCASTS_CSV;
        $csv = $this->readCsv($f);
        if (\is_null($csv)) {
            return null;
        }

        $count = 0;
        $records = [];
        $columns = explode(",", self::CSV_HEADERS);

        // traverse the list of IDs
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

        $result = [
            "headers" => $columns,
            "records" => $records,
        ];
        return $result;
    }

    /**
     * Get episodes
     *
     * @param string $list list of records, separated by comma
     * @return array
     */
    private function getEpisodes($list)
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

        // get CSV
        $f = self::PODCASTS_CSV;
        $csv = $this->readCsv($f);
        if (\is_null($csv)) {
            return null;
        }

        $count = 0;
        $records = [];
        $columns = explode(",", self::CSV_HEADERS_EPIS);

        // traverse the list of IDs
        foreach ($list as $id) {
            $arr = [];
            foreach ($columns as $c) {
                if (array_key_exists($id, $csv[$c])) {
                    $arr[$c] = $csv[$c][$id];
                } else {
                    $arr = null;
                }
            }

            // read-in items in JSON
            $arr["items"] = \json_decode(@file_get_contents(self::EPISODES_PATH . $csv["xmlid"][$id] . self::EPIS_EXT));

            $records[$id] = $arr;
            $count++;
            if ($count >= self::MAX_RECORDS) {
                // limit reached
                break;
            }
        }

        // add "items" to "headers"
        $columns[] = "items";
        $result = [
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
     * Get API key list
     *
     * @return array
     */
    private function getAPIkeys()
    {
        if (\is_null($d) || !isset($d["user"]["email"])) {
            return null;
        }

        $result = [
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
        // records
        $records = $this->readCsv(self::PODCASTS_CSV);
        if (\is_null($records)) {
            return null;
        }

        // timestamp
        $file = DATA . "/" . self::PODCASTS_CSV;
        $timestamp = file_exists($file) ? @filemtime($file) : null;

        // records count
        $count = count($records["uid"]);

        // podcast of the day
        $x = round(date("j") * $count / 31);
        $potd_name = $records["title"][$x];
        $potd_url = $records["link"][$x];
        $potd_rss = $records["rssfeed"][$x];
        $potd_img = $records["itunes_image"][$x];

        $result = [
            "timestamp" => $timestamp,
            "podcasts_count" => $count,
            "potd" => [
                "title" => $potd_name,
                "link" => $potd_url,
                "rss" => $potd_rss,
                "img" => str_replace("http://", "https://", $potd_img),
            ],
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

        chdir(DATA);
        $result = [
            "timestamp" => $timestamp,
            "podcasts_count" => count($records["uid"]),
            "podcasts_dbs" => \array_filter(\glob("podcasts-????-??-??.csv"), "is_file"),
            "user_id" => $d["user"]["id"],
            "user_email" => $d["user"]["email"],
            "user_name" => $d["user"]["name"],
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

        // cache
        $cache_key = "{$id}_csv_processed";
        if ($data = Cache::read($cache_key, "csv")) {
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
            return null;
        }

        // CSV columns
        $columns = explode(",", self::CSV_HEADERS);

        $data = [];
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
            @$redis->expire($key, self::ACCESS_TIME_LIMIT);
            @$redis->exec();
        } catch (\Exception $e) {
            return null;
        }
        $val++;
        return $val;
    }
}
