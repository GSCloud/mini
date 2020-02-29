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
    const API_CACHE = 'tenminutes';

    /** @var string API access time limit */
    const ACCESS_TIME_LIMIT = 3599;

    /** @var string podcasts CSV */
    const PODCASTS_CSV = 'podcasts.csv';

    /** @var string feed addendum */
    const FEED_ADDENDUM = 'listennotes-addendum02.txt';

    /** @var string LN redirect prefix */
    const LN_REDIR_PREFIX = 'https://www.listennotes.com/c/r/';

    /** @var string episodes path */
    const EPISODES_PATH = ROOT . '/XML/';

    /** @var string episodes file extension */
    const EPIS_EXT = '.epis.json';

    /** @var string private API key salt */
    const PRIVATE_KEY_PEPPER = "2BH*L(H+]*H%&T)j-MqB._8'%_6:;UAu";

    /** @var int maximum records */
    const MAX_RECORDS = 300;

    /** @var int minimum CSV filesize as a joke :) */
    const CSV_MIN_SIZE = 42;

    /** @var int maximum access hits */
    const MAX_API_HITS = 1000;

    /** @var string CSV headers */
    const CSV_HEADERS = 'title,description,author,copyright,itunes_author,itunes_category,itunes_explicit,itunes_image,itunes_keywords,itunes_owner,itunes_subtitle,itunes_summary,itunes_type,generator,pubDate,lastBuildDate,ttl,managingEditor,docs,rssfeed,link,episodes,episodes_version,xmlid,uid';

    /** @var string CSV headers for episodes */
    const CSV_HEADERS_EPIS = 'title,uid,episodes,episodes_version';

    /** @var string CSV headers for checksum hashing */
    const CSV_HEADERS_CHECKSUM = 'title,description,author,copyright,itunes_author,itunes_category,itunes_explicit,itunes_image,itunes_keywords,itunes_owner,itunes_subtitle,itunes_summary,itunes_type,generator,managingEditor,rssfeed,link';

    /**
     * Main controller
     */
    public function process()
    {
        $cfg = $this->getCfg();
        $d = $this->getData();
        $match = $this->getMatch();
        $view = $this->getView();

        setlocale(LC_ALL, 'cs_CZ.utf8');

        // check API keys @TODO!!!
        $err = 0;
        if (isset($_GET["api"])) {
            $api = (string) $_GET["api"];
            $key = $this->getCfg("ci_tester.api_key") ?? null;
            // check CI tester key
            if ($key !== $api) {
                // invalid API key
                $err++;
            }
        } else {
            // no API key
            $err++;
        }
        if ($err) {
            $this->checkRateLimit();
        }

        // general API properties
        $extras = [
            "api_quota" => (int) self::MAX_API_HITS,
            "api_usage" => $this->accessLimiter(),
            "uuid" => $this->getUID(),
            "access_time_limit" => self::ACCESS_TIME_LIMIT,
            "cache_time_limit" => $this->getData("cache_profiles")[self::API_CACHE],
            "records_quota" => self::MAX_RECORDS,
            "fn" => $view,
            "name" => "PodcastAPI",
        ];

        // user authorization
        $d["user"] = $this->getCurrentUser() ?? [];
        $d["admin"] = $g = $this->getUserGroup() ?? '';
        if ($g) {
            $d["admin_group_${g}"] = true;
        }

        // API call switch
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

            case "GetKey": // IMPLEMENTED
                if (!$d["user"]["id"]) {
                    // unauthorized user
                    return $this->writeJsonData(401, $extras);
                }
                $data = $this->getKey($d);
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras);
                break;

            case "CheckKey": // IMPLEMENTED
                $key = $match["params"]["key"] ?? null;
                $data = $this->checkKey($key); // user key
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras);
                break;

            case "GetKeys": // IMPLEMENTED
                if (!$d["user"]["id"]) {
                    // unauthorized user
                    return $this->writeJsonData(401, $extras);
                }
                $data = $this->getKeys();
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras);
                break;

            case "GetPodcasts": // IMPLEMENTED
                $list = $match["params"]["list"] ?? null;
                $data = $this->getPodcasts($list); // podcasts list
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData((object) $data, $extras);
                break;

            case "GetEpisodes": // IMPLEMENTED
                $list = $match["params"]["list"] ?? null;
                $data = $this->getEpisodes($list); // podcasts list
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData((object) $data, $extras);
                break;

            case "GetPodcastsByUid": // IMPLEMENTED
                $list = $match["params"]["list"] ?? null;
                $data = $this->getPodcastsByUid($list); // podcasts list, UIDs
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData((object) $data, $extras);
                break;

            case "GetEpisodesByUid": // IMPLEMENTED
                $list = $match["params"]["list"] ?? null;
                $data = $this->getEpisodesByUid($list); // podcasts list, UIDs
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

            case "AddPodcast": // IMPLEMENTED
                if (!$d["user"]["id"]) {
                    // unauthorized user
                    return $this->writeJsonData(401, $extras);
                }
                $feed = $_POST["url"] ?? null;
                $data = $this->addPodcast($feed);
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

            case "CreateKey": // IMPLEMENTED
                if (!$d["user"]["id"]) {
                    // unauthorized user
                    return $this->writeJsonData(401, $extras);
                }
                $name = $_POST["name"] ?? null;
                $data = $this->createKey($name, $d);
                if (is_null($data)) {
                    sleep(2);
                    return $this->writeJsonData(404, $extras);
                }
                $extras["keys"] = array_keys($data);
                return $this->writeJsonData($data, $extras, JSON_FORCE_OBJECT);
                break;

            case "DeleteKey":
                if (!$d["user"]["id"]) {
                    // unauthorized user
                    return $this->writeJsonData(401, $extras);
                }
                $data = $this->deleteKey();
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

            case "GetTagCloud":
                $data = $this->getTagCloud();
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

            case "DeleteTags":
                $data = $this->deleteTags();
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
        $columns = explode(',', self::CSV_HEADERS_CHECKSUM);

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
     * @param string $list list of IDs, separated by comma
     * @return array
     */
    private function getPodcasts($list)
    {
        if (!strlen($list)) {
            return null;
        }
        $list = explode(',', (string) $list);
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
        $columns = explode(',', self::CSV_HEADERS);

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
     * @param string $list list of IDs, separated by comma
     * @return array
     */
    private function getEpisodes($list)
    {
        if (!strlen($list)) {
            return null;
        }
        $list = explode(',', (string) $list);
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
        $columns = explode(',', self::CSV_HEADERS_EPIS);

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
     * Get podcasts by UID
     *
     * @param string $list list of UIDs, separated by comma
     * @return array
     */
    private function getPodcastsByUid($list)
    {
        if (!strlen($list)) {
            return null;
        }
        $list = explode(',', (string) $list);
        $list = array_map("trim", $list);
        $list = array_filter($list, function ($value) {
            return preg_match("/^([a-f0-9]{64})$/", $value) === 1; // validate SHA-256 hash
        });
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
        $columns = explode(',', self::CSV_HEADERS);
        $ids = array_flip($csv["uid"]);

        // traverse the list of UIDs
        foreach ($list as $uid) {
            $arr = [];
            $id = $ids[$uid];
            foreach ($columns as $c) {
                if (array_key_exists($id, $csv[$c])) {
                    $arr[$c] = $csv[$c][$id];
                } else {
                    $arr = null;
                }
            }

            $records[$uid] = $arr;
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
     * Get episodes by UID
     *
     * @param string $list list of UIDs, separated by comma
     * @return array
     */
    private function getEpisodesByUid($list)
    {
        if (!strlen($list)) {
            return null;
        }
        $list = explode(',', (string) $list);
        $list = array_map("trim", $list);
        $list = array_filter($list, function ($value) {
            return preg_match("/^([a-f0-9]{64})$/", $value) === 1; // validate SHA-256 hash
        });
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
        $columns = explode(',', self::CSV_HEADERS_EPIS);
        $ids = array_flip($csv["uid"]);

        // traverse the list of UIDs
        foreach ($list as $uid) {
            $arr = [];
            $id = $ids[$uid];
            foreach ($columns as $c) {
                if (array_key_exists($id, $csv[$c])) {
                    $arr[$c] = $csv[$c][$id];
                } else {
                    $arr = null;
                }
            }

            // read-in items in JSON
            $arr["items"] = \json_decode(@file_get_contents(self::EPISODES_PATH . $csv["xmlid"][$id] . self::EPIS_EXT));

            $records[$uid] = $arr;
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
     * Get private user API key
     *
     * @param array $d user data
     * @return string
     */
    private function getKey($d = null)
    {
        if (\is_null($d) || !isset($d["user"]["email"])) {
            return null;
        }

        // prepare key data
        $email = \strtolower($d["user"]["email"]);
        $container = hash("sha256", $email);
        $salt = hash("sha256", random_bytes(16));

        // delete old API key
        if ($x = @\file_get_contents(DATA . "/${container}_meta.key")) {
            // extract JSON META data
            $arr = @json_decode($x, true);
            if (!\is_array($arr)) {
                return null;
            }
            // delete private key file
            @\unlink(DATA . "/" . $arr["key"] . "_private.key");
        }

        // generate new API key
        $key = hash("sha256", self::PRIVATE_KEY_PEPPER . $email . $salt);
        $meta = json_encode([
            "email" => $email,
            "ip" => $this->getIP(),
            "key" => $key,
            "name" => $email,
            "salt" => $salt,
            "timestamp" => \time(),
            "type" => "user",
            "uid" => $this->getUID(),
        ]);

        // write META data
        if (@\file_put_contents(DATA . "/${container}_meta.key", $meta, LOCK_EX) === false) {
            return null;
        }

        // write key data
        if (@\file_put_contents(DATA . "/${key}_private.key", $container, LOCK_EX) === false) {
            return null;
        }

        $result = [
            "added" => true,
            "key" => $key,
            "message" => "Key was created.",
        ];
        return $result;
    }

    /**
     * Create application API key
     *
     * @param string $name app name
     * @param array $d user data
     * @return string
     */
    private function createKey($name = null, $d = null)
    {
        if (\is_null($name)) {
            return null;
        }
        if (\is_null($d) || !isset($d["user"]["email"])) {
            return null;
        }
        $name = trim($name);
        if (empty($name)) {
            return null;
        }

        // prepare key data
        $name = strtolower($name);
        $email = \strtolower($d["user"]["email"]);
        $container = hash("sha256", $name);
        $salt = hash("sha256", random_bytes(16));

        // delete old API key
        if ($x = @\file_get_contents(DATA . "/${container}_meta.key")) {
            // extract JSON META data
            $arr = @json_decode($x, true);
            if (!\is_array($arr)) {
                return null;
            }
            // delete private key file
            @\unlink(DATA . "/" . $arr["key"] . "_app.key");
        }

        // generate new API key
        $key = hash("sha256", self::PRIVATE_KEY_PEPPER . $name . $salt);
        $meta = json_encode([
            "email" => $email,
            "ip" => $this->getIP(),
            "key" => $key,
            "name" => $name,
            "salt" => $salt,
            "timestamp" => \time(),
            "type" => "app",
            "uid" => $this->getUID(),
        ]);

        // write META data
        if (@\file_put_contents(DATA . "/${container}_meta.key", $meta, LOCK_EX) === false) {
            return null;
        }

        // write key data
        if (@\file_put_contents(DATA . "/${key}_app.key", $container, LOCK_EX) === false) {
            return null;
        }

        $result = [
            "added" => true,
            "key" => $key,
            "message" => "Key was created.",
        ];
        return $result;
    }

    /**
     * Get API key list
     *
     * @return array API keys
     */
    private function getKeys()
    {
        $result = [
            "users" => $this->getUserKeys(),
            "apps" => $this->getAppKeys(),
        ];
        return $result;
    }

    /**
     * Check API key
     *
     * @param string $key API key
     * @return bool
     */
    private function checkKey($key = null)
    {
        if (\is_null($key)) {
            return null;
        }

        // hexadecimal & SHA-256 length only
        $key = strtolower(preg_replace("/[^a-fA-F0-9]+/", '', trim($key)));
        if (strlen($key) != 64) {
            return null;
        }

        // check key file
        $meta = null;
        $f = DATA . "/${key}_private.key"; // user key
        if (!\file_exists($f)) {
            $f = DATA . "/${key}_app.key"; // app key
        }

        // check container file
        if (\file_exists($f)) {
            $container = trim(@\file_get_contents($f));
            if (\strlen($container) != 64) { // invalid containter id
                return null;
            }
            $f = DATA . "/${container}_meta.key";
            // get meta data from container file
            if (\file_exists($f)) {
                if (\strlen(\file_get_contents($f)) < 42) {
                    return null;
                }
                $meta = json_decode(\file_get_contents($f), true); // @todo test JSON for validity!!!
            } else { // invalid meta file
                return [
                    "valid" => false,
                ];
            }
        } else { // invalid containter
            return [
                "valid" => false,
            ];
        }

        $result = [
            "container" => $container,
            "meta" => $meta,
            "valid" => true,
        ];
        sleep(1);
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
                "img" => str_replace('http://', 'https://', $potd_img),
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

        $result = [
            "timestamp" => $timestamp,
            "podcasts_count" => count($records["uid"]),
            "databases" => $this->getDatabases(),
            "user_id" => $d["user"]["id"],
            "user_email" => $d["user"]["email"],
            "user_name" => $d["user"]["name"],
            "system_load" => function_exists("sys_getloadavg") ? \sys_getloadavg() : null,
        ];
        return $result;
    }

    /**
     * Add podcast
     *
     * @param string $feed URL
     * @return void
     */
    private function addPodcast($feed = null)
    {
        if (\is_null($feed)) {
            return null;
        }
        $feed = \strtolower(trim($feed));
        if (\strpos($feed, self::LN_REDIR_PREFIX) === false) {
            $result = [
                "added" => false,
                "message" => "Malformed URL received. Operation failed.",
                "url" => $feed,
            ];
            return $result;
        }

        // check XMLID
        $xmlid = \str_replace(self::LN_REDIR_PREFIX, '', $feed);
        if (strlen($xmlid) > 32) {
            $result = [
                "added" => false,
                "message" => "Incorrect XMLID. Operation failed.",
                "url" => $feed,
            ];
            return $result;
        }
        $f = self::PODCASTS_CSV;
        $records = $this->readCsv($f);
        if (!\is_null($records)) {
            if (\in_array($xmlid, $records["xmlid"])) {
                $result = [
                    "added" => false,
                    "message" => "Duplicate entry.",
                    "url" => $feed,
                ];
                return $result;
            }
        }

        \file_put_contents(DATA . "/" . self::FEED_ADDENDUM, $feed . "\n", FILE_APPEND | LOCK_EX);

        $result = [
            "added" => true,
            "message" => "URL was added for the next feeding cycle.",
            "url" => $feed,
        ];
        return $result;
    }

    /* Get databases
     *
     * @return array list of CSV files
     */
    private function getDatabases()
    {
        chdir(DATA); // IMPORTANT!
        return \array_filter(\glob("podcasts-????-??-??.csv"), "is_file");
    }

    /* Get user keys
     *
     * @return array list of user keys
     */
    private function getUserKeys()
    {
        chdir(DATA); // IMPORTANT!
        $arr = \array_filter(\glob("*_private.key"), "is_file");
        array_walk($arr, function (&$value, &$key) {
            $value = \substr($value, 0, 16);
        });
        return $arr;
    }

    /* Get app keys
     *
     * @return array list of app keys
     */
    private function getAppKeys()
    {
        chdir(DATA); // IMPORTANT!
        $arr = \array_filter(\glob("*_app.key"), "is_file");
        array_walk($arr, function (&$value, &$key) {
            $value = \substr($value, 0, 64);
        });
        return $arr;
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
        $columns = explode(',', self::CSV_HEADERS);

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
