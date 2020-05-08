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
use RedisClient\RedisClient;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Store\FlockStore;

/**
 * API Presenter
 */
class ApiPresenter extends APresenter
{

    /** @var string operations log */
    const OPLOG = "/operations.log";

    /** @var bool use cache? */
    const USE_CACHE = true;

    /** @var string API cache profile */
    const API_CACHE = 'tenminutes';

    /** @var string API access time limit */
    const ACCESS_TIME_LIMIT = 3599;

    /** @var int maximum records */
    const MAX_RECORDS = 300;

    /** @var int maximum access hits */
    const MAX_API_HITS = 1000;

    /**
     * Main controller
     */
    public function process()
    {
        setlocale(LC_ALL, 'cs_CZ.utf8');

        $cfg = $this->getCfg();
        $d = $this->getData();
        $match = $this->getMatch();
        $view = $this->getView();

        // view properties
        $presenter = $this->getPresenter();
        $use_key = $presenter[$view]["use_key"] ?? false;
        $priv = $presenter[$view]["private"] ?? false;

        // user data, permissions and authorizations
        $api_key = $_GET["apikey"] ?? null;
        $d["user"] = $this->getCurrentUser() ?? [];
        $user_id = $d["user"]["id"] ?? null;
        $d["admin"] = $user_group = $this->getUserGroup();
        if ($user_group) {
            $d["admin_group_${user_group}"] = true;
        }

        // general API properties
        $extras = [
            "api_quota" => (int) self::MAX_API_HITS,
            "api_usage" => $this->accessLimiter(),
            "uuid" => $this->getUID(),
            "access_time_limit" => self::ACCESS_TIME_LIMIT,
            "cache_time_limit" => $this->getData("cache_profiles")[self::API_CACHE],
            "records_quota" => self::MAX_RECORDS,
            "private" => $priv,
            "use_key" => $use_key,
            "fn" => $view,
            "name" => "Tesseract MINI API",
        ];

        // write to operations log
        $this->writeOpStart($extras, $api_key);
        // access validation
        if (($priv) && (!$user_id)) {
            return $this->proxyJsonData(401, $extras);
        }
        if (($priv) && ($user_id) && (!$user_group)) {
            return $this->proxyJsonData(401, $extras);
        }
        if (($use_key) && (!$api_key)) {
            return $this->proxyJsonData(403, $extras);
        }
        if (($use_key) && ($api_key)) {
            $test = $this->checkKey($api_key);
            if (\is_null($test)) {
                return $this->proxyJsonData(401, $extras);
            }
            if ($test["valid"] !== true) {
                return $this->proxyJsonData(401, $extras);
            }
        }

        // API calls
        switch ($view) {
            case "call1": // IMPLEMENTED
                $data = [1, 2, 3, 4, 5];
                $param = $match["params"]["string"] ?? null;                
                if (is_null($param)) {
                    return $this->proxyJsonData(404, $extras);
                }
                $data["input"] = $param;
                //$extras["keys"] = array_keys($data);
                return $this->proxyJsonData($data, $extras);
                break;

            case "call2": // IMPLEMENTED
                $data = [1, 2, 3, 4, 5];
                $param = $match["params"]["number"] ?? null;
                if (is_null($param)) {
                    return $this->proxyJsonData(404, $extras);
                }
                $data["input"] = $param;
                //$extras["keys"] = array_keys($data);
                return $this->proxyJsonData($data, $extras);
                break;

            default:
                sleep(3);
                return ErrorPresenter::getInstance()->process(404);
        }
        return $this;
    }

    /**
     * Access limiter
     *
     * @return mixed access count or null
     */
    private function accessLimiter()
    {
        $hour = date("H");
        $uid = $this->getUID();
        $key = "access_limiter_" . SERVER . "_" . PROJECT . "_${hour}_${uid}";
        $redis = new RedisClient([
            'server' => 'localhost:6377',
            'timeout' => 1,
        ]);
        try {
            $val = (int) @$redis->get($key);
        } catch (\Exception $e) {
            return null;
        }
        if ($val > self::MAX_API_HITS) { // over limit!
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

    /**
     * write to operations log - START
     *
     * @param array $data extras
     * @param string $api_key API key (optional)
     * @return object Singleton
     */
    private function writeOpStart($data = null, $api_key = "")
    {
        if (empty($data)) {
            return;
        }
        $str = [ // log string
            date(DATE_ATOM),
            "OPSTART",
            "ip:" . (string) $this->getIP(),
            (string) $this->getCurrentUser()["email"],
            (string) $this->getCurrentUser()["id"],
            \strtr(\htmlspecialchars((string) $this->getCurrentUser()["name"]), ",", ""),
            "grp:" . (string) $this->getUserGroup(),
            "uid:" . (string) $this->getUID(),
            "use:" . (int) $data["api_usage"],
            "fn:" . (string) $data["fn"],
            "key:" . (string) $api_key,
            "url:" . \strtr(\htmlspecialchars($_SERVER["REQUEST_URI"] ?? ""), ",", "_"),
            "ua:" . \strtr(\htmlspecialchars($_SERVER["HTTP_USER_AGENT"] ?? ""), ",", "_"),
        ];
        $factory = new Factory(new FlockStore()); // FlockStore lock
        $lock = $factory->createLock("operationslog");
        $lock->acquire(true);
        \file_put_contents(DATA . self::OPLOG, join(",", $str) . "\n", FILE_APPEND);
        $lock->release();
        return $this;
    }

    /**
     * write to operations log - END
     *
     * @param string $stat status
     * @return object Singleton
     */
    private function writeOpEnd($stat = null)
    {
        if (empty($stat)) {
            return;
        }
        $str = [ // log string
            date(DATE_ATOM),
            "OPEND",
            "ip:" . (string) $this->getIP(),
            (string) $this->getCurrentUser()["email"],
            (string) $this->getCurrentUser()["id"],
            \strtr(\htmlspecialchars((string) $this->getCurrentUser()["name"]), ",", ""),
            "grp:" . (string) $this->getUserGroup(),
            "uid:" . (string) $this->getUID(),
            "status:" . $stat,
        ];
        $factory = new Factory(new FlockStore()); // FlockStore lock
        $lock = $factory->createLock("operationslog");
        $lock->acquire(true);
        \file_put_contents(DATA . self::OPLOG, join(",", $str) . "\n", FILE_APPEND);
        $lock->release();
        return $this;
    }

    /**
     * proxy JSON data to write
     *
     * @param mixed $p1 parameter 1
     * @param mixed $p2 parameter 2
     * @param mixed $p3 parameter 3 (optional)
     * @return object Singleton
     */
    public function proxyJsonData($p1, $p2, $p3 = null)
    {
        if (\is_int($p1)) {
            $this->writeOpEnd($p1);
            if ($p1 >= 400) { // error code >= 400
                sleep(3);
            }
        } else {
            $size = strlen(\json_encode($p1));
            $this->writeOpEnd("data length:$size");
        }
        if (!\is_null($p3)) {
            $this->writeJsonData($p1, $p2, $p3);
        } else {
            $this->writeJsonData($p1, $p2);
        }
        return $this;
    }

    /**
     * Get key usage from operations log
     *
     * @param string $key API key
     * @return mixed usage count
     */
    private function getKeyUsage($key)
    {
        if (\is_null($key)) {
            return null;
        }
        $x = trim((string) $key);
        if (self::USE_CACHE && $result = Cache::read("getKeyUsage_$x", self::API_CACHE)) {
            return $result; // read from cache
        }
        if (!$file = @\file(DATA . self::OPLOG)) {
            return null; // no data!
        }
        $filtered = \array_filter($file, function ($value) use ($x) {
            return \strpos($value, ",key:$x");
        });
        $result = count($filtered);
        Cache::write("getKeyUsage_$x", $result, self::API_CACHE);
        return $result;
    }

    /**
     * Get API call usage from operations log
     *
     * @param string $fn function name
     * @return mixed usage count
     */
    public static function getCallUsage($fn)
    {
        if (\is_null($fn)) {
            return null;
        }
        $x = trim((string) $fn);
        if (self::USE_CACHE && $result = Cache::read("getCallUsage_$x", self::API_CACHE)) {
            return $result; // read from cache
        }
        if (!$file = @\file(DATA . self::OPLOG)) {
            return null; // no data!
        }
        $filtered = \array_filter($file, function ($value) use ($x) {
            return \strpos($value, ",fn:$x");
        });
        $result = count($filtered) ? count($filtered) : "-"; // format the count
        Cache::write("getCallUsage_$x", $result, self::API_CACHE);
        return $result;
    }
}
