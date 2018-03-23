<?php

namespace Wxkingstar\Garfield;

class GarfieldProbe
{
    private static $REDIS_CONF = [];

    public static function init(array $redis_conf)
    {
        self::$REDIS_CONF = $redis_conf;

        define("QDEBUG", true);
        define("QXHPROF", true);
        define("QDEBUG_STARG_TIME", microtime(true));

        if (isset($_SERVER['HTTP_HOST'])) {
            self::log("http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}", 'info_url');
            if (!empty($_POST)) {
                self::log($_POST, 'info_post');
            }
            if (!empty($_COOKIE)) {
                self::log($_COOKIE, 'info_cookie');
            }
            self::log($_SERVER['DOCUMENT_ROOT'], 'info_code_path');
        }

        register_shutdown_function(function () {
            if (defined("QDEBUG") && QDEBUG == true) {
                $error = error_get_last();
                BaseModelDebug::formatErrorInfo($error['type'], $error['message'], $error['file'], $error['line']);

                $statInfo = BaseModelDebug::getStatInfo();
                GarfieldProbe::table(
                    array(
                        array('资源', '次数', '消耗时间(ms)'),
                        array('sql', $statInfo['db']['count'] . ' 次', $statInfo['db']['time'] . ' ms'),
                        array('request', $statInfo['request']['count'] . ' 次', $statInfo['request']['time'] . ' ms'),
                        array('api', $statInfo['api']['count'] . ' 次', $statInfo['api']['time'] . ' ms'),
                        array('mc', $statInfo['mc']['count'] . ' 次', '不统计'),
                        //array('redis',      $statInfo['redis']['count'].' 次',      $statInfo['redis']['time'] . ' ms'  ),
                        array('总运行时间', '', sprintf("%0.3f", (microtime(true) - QDEBUG_STARG_TIME)) . "s")
                    ),
                    'all_info'
                );

                BaseModelDebug::sendOnlineDebug(BaseModelDebug::$error);
            }

            return true;
        });
    }

    public static function log($value, $type = 'debug')
    {
        if (defined("QDEBUG") && class_exists("\BaseModelDebug", false)) {
            BaseModelDebug::debug($value, $type, 'log');
        }
    }

    public static function info($value, $type = 'info')
    {
        if (defined("QDEBUG") && class_exists("\BaseModelDebug", false)) {
            BaseModelDebug::debug($value, $type, 'info');
        }
    }

    public static function wran($value, $type = 'wran')
    {
        if (defined("QDEBUG") && class_exists("\BaseModelDebug", false)) {
            BaseModelDebug::debug($value, $type, 'wran');
        }
    }

    public static function table($value, $type = 'debug')
    {
        if (defined("QDEBUG") && class_exists("\BaseModelDebug", false)) {
            BaseModelDebug::debug($value, $type, 'table');
        }
    }

    public static function error($value, $type = 'error')
    {
        if (defined("QDEBUG") && class_exists("\BaseModelDebug", false)) {
            BaseModelDebug::setError(2);
            BaseModelDebug::debug($value, $type, 'error');
        }
    }

    public static function trace($value, $type = 'trace')
    {
        if (defined("QDEBUG") && class_exists("\BaseModelDebug", false)) {
            BaseModelDebug::debug($value, $type, 'trace');
        }
    }

    public static function closeShowDebug()
    {
        if (defined("QDEBUG") && class_exists("\BaseModelDebug", false)) {
            BaseModelDebug::$showDebug = false;
        }
    }

    public static function openShowDebug()
    {
        if (defined("QDEBUG") && class_exists("\BaseModelDebug", false)) {
            BaseModelDebug::$showDebug = true;
        }
    }

    public static function getRedis()
    {
        $redis = new \Redis();
        $redis->connect(self::$REDIS_CONF['host'], self::$REDIS_CONF['port'], self::$REDIS_CONF['db']);
        $redis->auth(self::$REDIS_CONF['password']);
        return $redis;
    }
}
