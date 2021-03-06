<?php

/**
 * @desc   缓存资源检测
 * @author xing39393939@gmail.com
 * @since  2013-03-06 22:06:23
 * @throws 注意:无DB异常处理
 */
class monitor_check extends project_config
{
    function _initialize()
    {
        #每小时执行一次
        if (date('i') != 30) {
            exit();
        }

        //清空之前xss文件,重新检测
        $dirs = glob('/dev/shm/xss_' . VHOST . '/*');
        foreach ($dirs as $k => $v)
            unlink($v);
        if (class_exists('memcache_config')) {
            $memcache_config = new memcache_config;
            print_r($memcache_config->config);
            foreach ($memcache_config->config as $k => $v) {
                //不要检测 couchbase
                if (isset($v['bucket'])) continue;

                foreach ($v as $vv) {
                    $memcache_server = new memcache_server(array(
                        $vv
                    ));
                    $memcache_server->connect('testkey');
                    $x = $memcache_server->memcacheObj->getStats();
                    $memcache_server->close();
                    _status($x["bytes"] / 1048576, VHOST . "(Memcache状态)", '已使用(M)', "{$memcache_server->current_host['host']}:{$memcache_server->current_host['port']}", NULL, VIP, 0, 'replace');
                    _status($x["limit_maxbytes"] / 1048576, VHOST . "(Memcache状态)", '总空间(M)', "{$memcache_server->current_host['host']}:{$memcache_server->current_host['port']}", NULL, VIP, 0, 'replace');
                    _status($x["curr_items"], VHOST . "(Memcache状态)", 'KEY个数', "{$memcache_server->current_host['host']}:{$memcache_server->current_host['port']}", NULL, VIP, 0, 'replace');
                    _status(round($x["uptime"] / 86400, 0), VHOST . "(Memcache状态)", '运行天数', "{$memcache_server->current_host['host']}:{$memcache_server->current_host['port']}", NULL, VIP, 0, 'replace');
                }
            }
        }

        die('OK');
    }
}

?>