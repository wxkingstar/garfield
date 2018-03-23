<?php
/**
 * All rights reserved.
 * @abstract        调试类
 * @version         1.0
 */

namespace Wxkingstar\Garfield;

class BaseModelDebug
{

    public static $debugId = 0;

    public static $error = 0;

    public static $showDebug = true;

    protected static $debugTypeFilter;

    protected static $onlineDebugData = array();

    protected static $statInfo = array(
        'mc' => array('count' => 0, 'time' => 0),
        'db' => array('count' => 0, 'time' => 0),
        'request' => array('count' => 0, 'time' => 0),
        'api' => array('count' => 0, 'time' => 0),
        'redis' => array('count' => 0, 'time' => 0),
    );

    /**
     * 获取统计信息
     * @param void
     * @return array
     */
    public static function getStatInfo()
    {
        return self::$statInfo;
    }

    public static function setError($error)
    {
        self::$error = $error;
    }

    /**
     * 累计统计信息
     * @param string $type 统计类型mc|db|request
     * @param int $startTime 开始时间，默认0为不统计时长
     * @param int $offset 增加的大小，默认为1
     * @return mixed
     */
    public static function addStatInfo($type, $startTime = 0, $offset = 1)
    {
        self::$statInfo[$type]['count'] += $offset;
        if ($startTime > 0) {
            $runTime = sprintf("%0.2f", (microtime(true) - $startTime) * 1000);
            self::$statInfo[$type]['time'] += $runTime;
            return $runTime . " ms";
        }
        return true;
    }

    public static function debug($value, $type = 'debug', $show = 'log')
    {
        if (defined("QDEBUG") && QDEBUG == true && self::$showDebug == true) {
            if ($show == "table") {
                $show = "table";
            } elseif ($show == "error") {
                $show = "error";
            } elseif ($show == "warning" || $show == "wran") {
                $show = "warning";
            } elseif ($show == "info") {
                $show = "info";
            } else {
                $show = "log";
            }
            if (is_string($value) && preg_match("/^UPDATE|^DELETE|^INSERT|^REPLACE|^ALTER|^TRUNCATE|^CREATE/i",
                    $value)) {
                $show = "warning";
            }
            if (in_array($type, [
                'mc_get',
                'mc_set',
                'mc_delete',
                'mc_connect',
                'mc_add',
                'mc_increment',
                'mc_decrement',
                'mc_setMulti',
                'mc_getMulti'
            ])) {
                $show = "label";
            }
            self::$onlineDebugData[] = array('key' => $type, 'type' => $show, 'value' => $value, 'show' => $show);
            // mc 信息统计
            if ((strpos($type, 'mc_') === 0) && !in_array($type, array('mc_connect'), true)) {
                self::addStatInfo('mc');
            }
        }
    }

    /**
     * 构造sql语句
     * @param string $sql
     * @param array $data
     * @return string
     */
    public static function setSql($sql, $data = [])
    {
        if (defined('QDEBUG') && QDEBUG == true && self::$showDebug == true) {
            $sqlShow = '';
            if (strpos($sql, '?') && is_array($data) && count($data) > 0) {
                $sqlArr = explode('?', $sql);
                $last = array_pop($sqlArr);
                $data = array_values($data);
                foreach ($sqlArr as $k => $v) {
                    //if (!empty($v) && isset($data[$k])) {
                    if (1) {
                        if (isset($data[$k]) && !is_array($data[$k])) {
                            $value = "'" . addslashes($data[$k]) . "'";
                        } elseif (isset($data[$k])) {
                            $valueArr = array();
                            foreach ($data[$k] as $val) {
                                $valueArr[] = "'" . addslashes($val) . "'";
                            }
                            $value = '(' . implode(', ', $valueArr) . ')';
                        } else {
                            $value = "''";
                        }
                        $sqlShow .= $v . $value;
                    }
                }
                $sqlShow .= $last;
            } else {
                $sqlShow = $sql;
            }
            return $sqlShow;
        }
        return '';
    }

    /**
     * online debug推送到监控中心
     * @param int $error
     * @return void
     */
    public static function sendOnlineDebug($error = 0)
    {
        if (defined('QDEBUG') && QDEBUG == true && self::$showDebug == true) {
            $user_id = 0;
            if (!isset($_SERVER['HTTP_HOST'])) {
                $method = $_SERVER['SCRIPT_NAME'];
            } else {
                $user_id = 0;

                $arr = [''];
                isset($_SERVER['QUERY_STRING']) && $arr = explode("&", $_SERVER['QUERY_STRING']);
                isset($arr[0]) && list($method) = explode("?", $arr[0]);
                if (empty($method)) {
                    $method = "/";
                }
            }
            $user_id = empty($user_id) ? 0 : $user_id;

            //debug_id
            $debug_id = uniqid();
            $debug_level = '';

            //获取客户端ip
            if (isset($_SERVER['HTTP_REMOTEIP'])) {
                $client_ip = $_SERVER['HTTP_REMOTEIP'];
            } elseif (!empty($_REQUEST['remote_addr'])) {
                $client_ip = $_REQUEST['remote_addr'];
            } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $client_ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
                $client_ip = $_SERVER['REMOTE_ADDR'];
            } else {
                $client_ip = '127.0.0.1';
            }

            $server_info = posix_uname();
            $server_ip = $server_info['nodename'];

            $debug_msg = json_encode(self::$onlineDebugData);
            if (strlen($debug_msg) > 40 * 1024 * 1024) {
                $debug_msg = json_encode([
                    array(
                        'key' => 'message',
                        'type' => "log",
                        'value' => "debug超过40M无法显示",
                        'show' => 'log'
                    )
                ]);
            }
            if (isset($_SERVER['HTTP_HOST'])) {
                $url = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
            } else {
                $url = "";
            }
            $html = ob_get_contents();
            $post = array(
                'pid' => defined('PROJECT_ID') ? PROJECT_ID : 1,
                'debug_id' => $debug_id,
                'debug_level' => $debug_level,
                'domain' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : "",
                'qid' => $user_id,
                'method' => $method,
                //'debug_msg' => serialize(self::$onlineDebugData),//触发autoload问题
                'debug_msg' => '',
                'html' => "",
                //'request' => json_encode(apache_request_headers()),
                'request' => "{}",
                //'response' => json_encode(apache_response_headers()),
                'response' => "{}",
                'error' => $error,
                'client_ip' => $client_ip,
                'request_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "",
                'server_ip' => $server_ip,
                'xhprof' => '',
                'url' => $url,
                'params' => '',
            );

            try {
                $post['user_id'] = $post['qid'];
                $dbg_arr = [];
                foreach (self::$onlineDebugData as $log) {
                    if ($log['show'] == "table") {
                        $type = "label";
                    } elseif ($log['show'] == "error") {
                        $type = "error";
                    } elseif ($log['show'] == "warning" || $log['show'] == "wran") {
                        $type = "warning";
                    } elseif ($log['show'] == "info") {
                        $type = "info";
                    } else {
                        $type = "log";
                    }
                    if (is_string($log['value']) && preg_match("/^UPDATE|^DELETE|^INSERT|^REPLACE|^ALTER|^TRUNCATE|^CREATE/i",
                            $log['value'])) {
                        $type = "warning";
                    }
                    $dbg_arr[] = ['id' => uniqid(), 'type' => $type, 'key' => $log['type'], 'value' => $log['value']];
                }
                $post['debug_id'] = $debug_id;
                $post['debug_msg'] = $debug_msg;
                $post['xhprof'] = '';
                $post['html'] = $html;
                $post['time'] = date("Y-m-d H:i:s");
                $redis = GarfieldProbe::getRedis();
                $redis->publish('dbg_queue', json_encode($post));
            } catch (\Exception $e) {
            }
        }
    }


    /**
     * 调试输出错误信息
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     */
    public static function formatErrorInfo($errno, $errstr, $errfile, $errline)
    {
        $errstr = strip_tags($errstr);
        $myerror = "$errstr in $errfile on line $errline";
        if (empty($errno)) {
            return;
        }

        switch ($errno) {
            case E_WARNING:
                $myerror = "==Warning==" . $myerror;
                if (BaseModelDebug::$error > 2 || BaseModelDebug::$error === 0) {
                    BaseModelDebug::$error = 2;
                }
                break;
            case E_ERROR:
                $myerror = "==Fatal error==" . $myerror;
                BaseModelDebug::$error = 1;
                break;
            case E_NOTICE:
                $myerror = "==Notice==" . $myerror;
                if (BaseModelDebug::$error > 3 || BaseModelDebug::$error === 0) {
                    BaseModelDebug::$error = 3;
                }
                break;
            case E_USER_ERROR:
                $myerror = "==My error==" . $myerror;
                break;
            case E_USER_WARNING:
                $myerror = "==My warning==" . $myerror;
                break;
            case E_USER_NOTICE:
                $myerror = "==My notice==" . $myerror;
                break;
            default:
                $myerror = "==Unknown error type [$errno]==" . $myerror;
                break;
        }

        if (defined("QDEBUG") && QDEBUG == true) {
            GarfieldProbe::error($myerror, 'error');
        }
    }

    public static function qDebugErrorHandler($errno, $errstr, $errfile, $errline)
    {

        self::formatErrorInfo($errno, $errstr, $errfile, $errline);

        return true;
    }

}

