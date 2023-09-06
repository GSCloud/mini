<?php
/**
 * GSC Tesseract
 * php version 8.2
 *
 * @category CMS
 * @package  Framework
 * @author   Fred Brooker <git@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://app.gscloud.cz
 */

namespace GSC;

use Cake\Cache\Cache;
use Nette\Neon\Neon;
use RedisClient\RedisClient;

/**
 * API Presenter
 * 
 * @category CMS
 * @package  Framework
 * @author   Fred Brooker <git@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://app.gscloud.cz
 */

class ApiPresenter extends APresenter
{
    const ACCESS_TIME_LIMIT = 3599;
    const API_CACHE = 'tenminutes';
    const MAX_API_HITS = 1000;
    const MAX_RECORDS = 100;
    const USE_CACHE = false;

    /**
     * Main controller
     * 
     * @param mixed $param optional parameter
     * 
     * @return object the controller
     */
    public function process($param = null)
    {
        \setlocale(LC_ALL, 'cs_CZ.UTF-8');
        \error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

        $cfg = $this->getCfg();
        $d = $this->getData();
        $match = $this->getMatch();
        $view = $this->getView();

        // view properties
        $presenter = $this->getPresenter();
        $use_key = false;
        if (is_array($presenter)) {
            $use_key = \array_key_exists('use_key', $presenter[$view])
                ? $presenter[$view]['use_key'] : false;
        }
        $allow_key = false;
        if (is_array($presenter)) {
            $allow_key = \array_key_exists('allow_key', $presenter[$view])
                ? $presenter[$view]['allow_key'] : false;
        }
        $priv = false;
        if (is_array($presenter)) {
            $priv = \array_key_exists('private', $presenter[$view])
                ? $presenter[$view]['private'] : false;
        }

        // user data, permissions and authorizations
        $api_key = $_GET['apikey'] ?? null;
        $user_id = null;
        $user_group = null;
        if (is_array($d)) {
            $d['user'] = $this->getCurrentUser();
            $user_id = $d['user']['id'] ?? null;
            $d['admin'] = $user_group = $this->getUserGroup();
            if ($user_group) {
                $d["admin_group_{$user_group}"] = true;
            }
        }

        // general API properties
        $cache_profiles = (array) ($this->getData("cache_profiles") ?: []);
        $cache_time_limit = array_key_exists(self::API_CACHE, $cache_profiles)
            ? $cache_profiles[self::API_CACHE] : null;
        $extras = [
            "name" => "Tesseract REST API",
            "fn" => $view,
            "api_quota" => (int) self::MAX_API_HITS,
            "api_usage" => $this->accessLimiter(),
            "access_time_limit" => self::ACCESS_TIME_LIMIT,
            "cached" => self::USE_CACHE,
            "cache_time_limit" => $cache_time_limit,
            "records_quota" => self::MAX_RECORDS,
            "private" => $priv,
            "allow_key" => $allow_key,
            "use_key" => $use_key,
            "uuid" => $this->getUID(),
        ];

        // PRIVATE & NOT OAUTH2
        if ($priv && !$user_id) {
            return $this->writeJsonData(401, $extras);
        }

        // PRIVATE && OAUTH2 && NOT ALLOWED
        if ($priv && $user_id > 0 && !$user_group) {
            return $this->writeJsonData(401, $extras);
        }

        // process API calls
        switch ($view) {

        case "GetVersion":
            $data = [
                "version" => $this->getData('VERSION'),
            ];
            return $this->writeJsonData($data, $extras);

        default:
            sleep(5);
            return ErrorPresenter::getInstance()->process(404);
        }
    }

    /**
     * Redis access limiter
     *
     * @return mixed access count or null
     */
    public function accessLimiter()
    {
        $hour = date('H');
        $uid = $this->getUID();
        defined('SERVER') || define(
            'SERVER',
            strtolower(
                preg_replace(
                    "/[^A-Za-z0-9]/", '', $_SERVER['SERVER_NAME'] ?? 'localhost'
                )
            )
        );
        defined('PROJECT') || define('PROJECT', 'LASAGNA');
        $key = 'access_limiter_' . SERVER . '_' . PROJECT . "_{$hour}_{$uid}";
        \error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
        $redis = new RedisClient(
            [
            'server' => 'localhost:6377',
            'timeout' => 1,
            ]
        );
        try {
            $val = (int) @$redis->get($key);
        } catch (\Exception $e) {
            return null;
        }
        if ($val > self::MAX_API_HITS) {
            // over limit!
            $this->setLocation('/err/420');
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
