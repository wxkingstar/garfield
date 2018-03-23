<?php

namespace Garfield\Agent;

class GarfieldProbe
{
    private static $REDIS_CONF = [];

    public static function init(array $redis_conf)
    {
        self::$REDIS_CONF = $redis_conf;

        define("QDEBUG", true);
        define("QXHPROF", true);
        define("QDEBUG_STARG_TIME", microtime(true));

        require(dirname(__FILE__) . "/BaseModelDebug.php");

        if (extension_loaded('xhprof') && QXHPROF) {
            xhprof_enable(XHPROF_FLAGS_NO_BUILTINS | XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY, []);
        }

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

        register_shutdown_function('BaseModelDebug::qShutDown');
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
