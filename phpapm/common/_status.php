<?php
/**
 * @desc   WHAT? $uptype=replace/utf-8
 * @author
 * @since  2012-06-22 20:14:54
 * @throws 注意:无DB异常处理
 */
function _status($num, $v1, $v2, $v3 = VIP, $v4 = null, $v5 = VIP, $diff_time = 0, $uptype = null, $time = null, $add_array = array())
{
    if (!$time)
        $START_TIME_DATE = START_TIME_DATE;
    else
        $START_TIME_DATE = date('Y-m-d H:i:s',$time);

    $includes = array();
    if ($v2 == $v3)
        $v3 = VIP;

    //累计_status
    static $_status_sql = '';

    if ($v3 == NULL)
        $v3 = VIP;
    if ($v5 == VIP)
        $v5 = NULL;
    $_uptype = $code = NULL;
    list($_uptype, $code) = explode('/', $uptype);
    settype($add_array, 'array');
    $array = array(
            'vhost' => VHOST,
            'includes' => $includes,
            'num' => $num,
            #计算值
            'v1' => $v1,
            #大分类
            'v2' => $v2,
            #小分类
            'v3' => $v3,
            #主要统计类型
            'v4' => $v4,
            #具体的弹窗描述
            'v5' => $v5,
            #连接地址
            'diff_time' => $diff_time,
            'time' => $START_TIME_DATE,
            'uptype' => $_uptype
        ) + $add_array;
    $_status_sql .= "('" . addslashes(serialize($array)) . "'),";

    //入队列
    if ($v1 == VHOST . "(BUG错误)" && in_array($v2, array('定时', '内网接口', '页面操作', '其他功能'))) {
        $project_config = new project_config();
        $db_config = new oracleDB_config();
        $db_config = $db_config->dbconfig[$project_config->db];
        mysql_connect($db_config['TNS'], $db_config['user_name'], $db_config['password']) or die();
        mysql_select_db($db_config['db']) or die();
        $_status_sql = rtrim($_status_sql, ',');
        mysql_query("SET NAMES 'utf8'");
        mysql_query("insert into {$project_config->report_monitor_queue} (`queue`) values {$_status_sql}");
    }
}
?>