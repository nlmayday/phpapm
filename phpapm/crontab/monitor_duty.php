<?php

/**
 * @desc   项目满意度，责任验收
 * @author xing39393939@gmail.com
 * @since  2013-03-06 22:06:23
 * @throws 注意:无DB异常处理
 */
class monitor_duty extends project_config
{
    function _initialize()
    {
        #每小时执行一次
        if (date('i') != 30) {
            exit();
        }

        $conn_db = _ocilogon($this->db);
        _ociexecute(_ociparse($conn_db, "alter session set nls_date_format='YYYY-MM-DD HH24:MI:SS'"));

        $sql = "select * from {$this->report_monitor_v1}  where IS_DUTY=1 ";
        $stmt = _ociparse($conn_db, $sql);
        _ociexecute($stmt);
        $v1_all = $_row_all = array();
        while (ocifetchinto($stmt, $_row_all, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS)) {
            $v1_all[$_row_all['V1']] = $_row_all['V1'];
        }
        $sql = "select t.lookup, trunc(t.cal_date) cal_date, v1
               from {$this->report_monitor_date} t
               where t.cal_date >= trunc(sysdate - 7)
               and t.cal_date < trunc(sysdate - 6) and t.lookup is null
               group by trunc(t.cal_date), t.lookup, v1 ";
        $stmt = _ociparse($conn_db, $sql);
        _ociexecute($stmt);
        $_row = array();
        while (ocifetchinto($stmt, $_row, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS)) {
            if ($v1_all[$_row['V1']])
                continue;
            _status(1, VHOST . "(BUG错误)", "验收责任未到位", $_row['V1'], "", VIP);
        }
        if ($_GET['no_manyi'])
            return;
        //技术基础分
        _status(100, VHOST . "(项目满意分)", "基础分", "基础分", "基础分", VIP, 0, 'replace');
        //错误率占10％
        $sql = "select (select nvl(sum(fun_count), 0)
             from {$this->report_monitor_date} t
            where v1 like '%(BUG错误)'
              and v2 = 'SQL错误'
              and t.cal_date = trunc(sysdate)) php_num,
           (select nvl(sum(fun_count), 0)
             from {$this->report_monitor_date} t
            where v1 like '%(BUG错误)'
              and v2 = 'PHP错误'
              and t.cal_date = trunc(sysdate)) sql_num,
           (select sum(t.fun_count)
             from {$this->report_monitor_date} t
            where v1 like '%(WEB日志分析)'
              and t.cal_date = trunc(sysdate)) web_num  from dual ";
        $stmt = _ociparse($conn_db, $sql);
        $oci_error = _ociexecute($stmt);
        $_row = array();
        ocifetchinto($stmt, $_row, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
        $_row['SQLERR'] = round(($_row['PHP_NUM'] + $_row['SQL_NUM']) / $_row['WEB_NUM'] * 100, 2);
        $manyi = 0;
        if ($_row['SQLERR'] > 1)
            $manyi = -10;
        elseif ($_row['SQLERR'] < 0.1)
            $manyi = 0;
        else {
            $manyi = -($_row['SQLERR'] * 10);
        }
        _status($manyi, VHOST . "(项目满意分)", "PHP+SQL错误率", "PHP+SQL错误率", "PHP_NUM:{$_row['PHP_NUM']},SQL_NUM:{$_row['SQL_NUM']},WEB_NUM:{$_row['WEB_NUM']}@{$_row['SQLERR']}%", VIP, 0, 'replace');
        //sql量40％
        $sql = "select (select nvl(sum(fun_count), 0)
             from {$this->report_monitor_date} t
            where v1 like '%(SQL统计)'
            and t.cal_date = trunc(sysdate - 2/24)) sql_num,
           (select sum(t.fun_count)
             from {$this->report_monitor_date} t
            where v1 like '%(WEB日志分析)'
              and t.cal_date = trunc(sysdate - 2/24)) web_num,
            (select sum(t.fun_count)
             from {$this->report_monitor_date} t
            where v1 like '%(WEB日志分析)'
              and t.cal_date = trunc(sysdate - 1)) y_web_num
           from dual  ";
        $stmt = _ociparse($conn_db, $sql);
        $oci_error = _ociexecute($stmt);
        $_row = array();
        ocifetchinto($stmt, $_row, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
        $_row['SQLERR'] = round($_row['SQL_NUM'] / $_row['WEB_NUM'] * 100, 2);
        $manyi = 0;
        //按照前一天web量 判断
        if ($_row['Y_WEB_NUM'] >= 2000000) {
            if ($_row['SQLERR'] > 1 * 100)
                $manyi = -40;
            elseif ($_row['SQLERR'] < 6 / 10 * 100)
                $manyi = 0;
            else {
                $manyi = -(100 - ($_row['SQLERR'] - 6 / 10 * 100) / (1 * 100 - 6 / 10 * 100) * 100) * 40 / 100;
            }
        }
        if ($_row['Y_WEB_NUM'] < 2000000 && $_row['Y_WEB_NUM'] >= 300000) {
            if ($_row['SQLERR'] > 2 * 100)
                $manyi = -40;
            elseif ($_row['SQLERR'] < 1.2 * 100)
                $manyi = 0;
            else {
                $manyi = -(100 - ($_row['SQLERR'] - 1.2 * 100) / (2 * 100 - 1.2 * 100) * 100) * 40 / 100;
            }
        }
        if ($_row['Y_WEB_NUM'] < 300000) {
            if ($_row['SQLERR'] > 50 * 100)
                $manyi = -40;
            elseif ($_row['SQLERR'] < 30 * 100)
                $manyi = 0;
            else {
                $manyi = -(100 - ($_row['SQLERR'] - 30 * 100) / (50 * 100 - 30 * 100) * 100) * 40 / 100;
            }
        }
        _status($manyi, VHOST . "(项目满意分)", "SQL回源率", "SQL回源率", "SQL_NUM:{$_row['SQL_NUM']},WEB_NUM:{$_row['WEB_NUM']}@{$_row['SQLERR']}%", VIP, 0, 'replace');

        //单小时sql上限
        $sql = "select nvl(sum(fun_count), 0) sql_num
                             from {$this->report_monitor_date} t
                            where v1 like '%(SQL统计)'
                              and t.cal_date = trunc(sysdate)";
        $stmt = _ociparse($conn_db, $sql);
        $oci_error = _ociexecute($stmt);
        $_row = array();
        ocifetchinto($stmt, $_row, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
        //扣分单小时SQL上限
        $hour = date('H');
        $manyi = 0;
        $sql_error = round($_row['SQL_NUM'] / $hour);
        if ($sql_error >= 300000) {
            $num = 5 * intval(($sql_error - 300000) / 10000);
            $manyi = $manyi - $num;
        }
        _status($manyi, VHOST . "(项目满意分)", "扣分:单小时SQL上限", "扣分:单小时SQL上限", "SQL_NUM:{$_row['SQL_NUM']},H:{$hour},平均sql量:{$sql_error}", VIP, 0, 'replace');
        //memcache 20%
        $sql = "select (select nvl(sum(fun_count), 0)
             from {$this->report_monitor_date} t
            where v1 like '%(Memcache)'
              and t.cal_date = trunc(sysdate)) mem_num,
           (select sum(t.fun_count)
             from {$this->report_monitor_date} t
            where v1 like '%(WEB日志分析)'
              and t.cal_date = trunc(sysdate)) web_num from dual ";
        $stmt = _ociparse($conn_db, $sql);
        $oci_error = _ociexecute($stmt);
        $_row = array();
        ocifetchinto($stmt, $_row, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
        $_row['SQLERR'] = round($_row['MEM_NUM'] / $_row['WEB_NUM']);
        $manyi = 0;
        if ($_row['SQLERR'] > 6)
            $manyi = -20;
        elseif ($_row['SQLERR'] < 3)
            $manyi = 0;
        else {
            $manyi = -(100 - ($_row['SQLERR']) / (6 - 3) * 100) * 20 / 100;
        }
        _status($manyi, VHOST . "(项目满意分)", "Memcache回源率", "Memcache回源率", "MEM_NUM:{$_row['MEM_NUM']},WEB_NUM:{$_row['WEB_NUM']}@" . ($_row['SQLERR'] * 100) . "%", VIP, 0, 'replace');

        $sql = "select sum(t.fun_count) sqlerr
               from {$this->report_monitor_hour} t
              where v1 like '%(BUG错误)'
                and v2 = '验收责任未到位'
                and t.cal_date = trunc(sysdate-1/24,'hh24') ";
        $stmt = _ociparse($conn_db, $sql);
        $oci_error = _ociexecute($stmt);
        $_row = array();
        ocifetchinto($stmt, $_row, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
        $manyi = 10;
        if ($_row['SQLERR'] > 0)
            $manyi = -10;
        _status($manyi, VHOST . "(项目满意分)", "项目验收", "项目验收", $_row['SQLERR'], VIP, 0, 'replace');
        //tcp满意度 30%
        $sql = "select nvl(sum(fun_count), 0) TCP
                             from {$this->report_monitor_date} t
                            where v1 like '%(WEB日志分析)' and V2='TCP连接'
                              and t.cal_date = trunc(sysdate-1/24)";
        $stmt = _ociparse($conn_db, $sql);
        $oci_error = _ociexecute($stmt);
        $_row = array();
        ocifetchinto($stmt, $_row, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
        $manyi = 0;
        if ($_row['TCP'] > 120) //tcp超120 每增加10扣100 无上限
            $manyi = -(floor(($_row['TCP'] - 120) / 10) * 100);
        elseif ($_row['TCP'] < 70)
            $manyi = 0;
        else {
            $manyi = 0 - (70 - ($_row['TCP'] - 70) / 100 * 100) * 30 / 100;
        }
        _status($manyi, VHOST . "(项目满意分)", "TCP连接数", "TCP连接数", 'TCP连接数:' . $_row['TCP'], VIP, 0, 'replace');

        //扣分项
        //机器重启当天,每小时扣200分
        $sql = "select fun_count from {$this->report_monitor_date} t where v1 like'%(WEB日志分析)' and v2='运行天数' and t.cal_date = trunc(sysdate - 1/24 )";
        $stmt = _ociparse($conn_db, $sql);
        $oci_error = _ociexecute($stmt);
        $_row = array();
        $manyi = 0;
        ocifetchinto($stmt, $_row, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
        if ($_row['FUN_COUNT'] == '') {
            $manyi = -200;
        }
        _status($manyi, VHOST . "(项目满意分)", "扣分:机器重启", "机器重启", NULL, VIP, 0, 'replace');

        //非定时任务扣分(非定时任务代码执行超过1秒占总量的0.1%以上,扣20分)
        $sql = "select (select nvl(sum(fun_count), 0)
                     from {$this->report_monitor_date} t
                    where v1 like '%(BUG错误)' and (v2 ='超时')
                      and t.cal_date = trunc(sysdate)) sql_num,
                  (select sum(t.fun_count)
                     from {$this->report_monitor_date} t
                    where v1 like '%(BUG错误)'
                      and t.cal_date = trunc(sysdate)) web_num
             from dual ";
        $stmt = _ociparse($conn_db, $sql);
        $oci_error = _ociexecute($stmt);
        $_row = array();
        ocifetchinto($stmt, $_row, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
        $_row['SQLERR'] = $_row['SQL_NUM'] / $_row['WEB_NUM'];
        $manyi = 0;
        if ($_row['SQL_NUM'] >= 1000) {
            $num = 5 * intval($_row['SQL_NUM'] / 1000);
            $manyi = $manyi - $num;
        }
        _status($manyi, VHOST . "(项目满意分)", "扣分:执行超时", "执行超时", "OVER_NUM:{$_row['SQL_NUM']}", VIP, 0, 'replace');

        //问题sql扫描
        $sql = "select fun_count from {$this->report_monitor_date} t where v1 like'%(问题SQL)' and v2='全表扫描' and t.cal_date = trunc(sysdate-1/24)";
        $stmt = _ociparse($conn_db, $sql);
        $oci_error = _ociexecute($stmt);
        $_row = array();
        $manyi = 0;
        ocifetchinto($stmt, $_row, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
        if ($_row['FUN_COUNT'] > 5) {
            $manyi = -10;
        } else {
            $manyi = -($_row['FUN_COUNT'] * 2.5);
        }
        _status($manyi, VHOST . "(项目满意分)", "扣分:问题sql", "全表扫描", '问题SQL' . $_row['FUN_COUNT'], VIP, 0, 'replace');

        // CPU>8 或者 LOAD>8 扣10分
        $sql = "select nvl(avg(fun_count), 0) CPU
                             from {$this->report_monitor_date} t
                            where v1 like '%(WEB日志分析)' and V2='CPU'
                              and t.cal_date = trunc(sysdate-1/24)";
        $stmt = _ociparse($conn_db, $sql);
        $oci_error = _ociexecute($stmt);
        $_row = array();
        ocifetchinto($stmt, $_row, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);

        $sql = "select nvl(avg(fun_count), 0) LOAD
                                     from {$this->report_monitor_date} t
                                    where v1 like '%(WEB日志分析)' and V2='Load'
                                      and t.cal_date = trunc(sysdate-1/24)";
        $stmt = _ociparse($conn_db, $sql);
        $oci_error = _ociexecute($stmt);
        $_row_load = array();
        ocifetchinto($stmt, $_row_load, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
        $manyi = 0;
        if ($_row['CPU'] > 8 || $_row_load['LOAD'] > 8) {
            $manyi = -10;
        }
        _status($manyi, VHOST . "(项目满意分)", "扣分:CPU LOAD", "CPU或LOAD过高", "CPU:{$_row['CPU']};LOAD:{$_row_load['LOAD']}", VIP, 0, 'replace');

        //web 500扣分 WEB日志出现5xx错误 [占0.05% 扣1分,没加一个万分点,扣1分,无上限]
        $sql = "select (select nvl(sum(fun_count), 0)
                             from {$this->report_monitor_date} t
                            where v1 like '%(WEB日志分析)' and v2 like '5%'
                              and t.cal_date = trunc(sysdate)) err_num,
                           (select sum(t.fun_count)
                             from {$this->report_monitor_date} t
                            where v1 like '%(WEB日志分析)'
                              and t.cal_date = trunc(sysdate)) web_num from dual ";
        $stmt = _ociparse($conn_db, $sql);
        $oci_error = _ociexecute($stmt);
        $_row = array();
        ocifetchinto($stmt, $_row, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
        $manyi = 0;
        $sql = "select nvl(sum(fun_count), 0) err_t_num
                                     from {$this->report_monitor_date} t
                                    where v1 like '%(WEB日志分析)' and v2 = '499'
                                      and t.cal_date = trunc(sysdate)";
        $stmt = _ociparse($conn_db, $sql);
        _ociexecute($stmt);
        $_row_t = array();
        ocifetchinto($stmt, $_row_t, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
        if ($_row_t['ERR_T_NUM'] > 500) {
            $_row['ERR_NUM'] = $_row['ERR_NUM'] + $_row_t['ERR_T_NUM'];
        }
        $_row['SQLERR'] = round($_row['ERR_NUM'] / $_row['WEB_NUM'], 4);
        if ($_row['SQLERR'] >= 0.0005) {
            $manyi = ($manyi - ($_row['SQLERR'] - 0.0005)) * 10000;
        }
        _status($manyi, VHOST . "(项目满意分)", "扣分:5xx错误", "5xx错误", "ERR_NUM:{$_row['ERR_NUM']},WEB_NUM:{$_row['WEB_NUM']}@" . ($_row['SQLERR'] * 10000) . "万分", VIP, 0, 'replace');

        //[扣分:包含文件] "10个到∞个"每个扣除5分
        $sql = "select nvl(sum(fun_count), 0) fun_count
                             from {$this->report_monitor_date} t
                            where v1 like '%(包含文件)' and V2='10s到∞个'
                              and t.cal_date = trunc(sysdate) ";
        $stmt = _ociparse($conn_db, $sql);
        $oci_error = _ociexecute($stmt);
        $_row = array();
        ocifetchinto($stmt, $_row, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
        $manyi = 0;
        if ($_row['FUN_COUNT']) {
            $manyi = $manyi - ($_row['FUN_COUNT'] * 5);
        }
        _status($manyi, VHOST . "(项目满意分)", "扣分:包含文件", "包含文件", "包含文件个数：{$_row['FUN_COUNT']}", VIP, 0, 'replace');
        $manyi = 0;
        //扣分:安全事故
        $sql = "select nvl(sum(fun_count), 0) COCK
                                     from {$this->report_monitor_hour} t
                                    where v1 like '%(BUG错误)' and V2='上传木马入侵'
                                      and t.cal_date= trunc(sysdate-1/24,'hh24')";
        $stmt = _ociparse($conn_db, $sql);
        _ociexecute($stmt);
        $_row_cock = array();
        ocifetchinto($stmt, $_row_cock, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
        $manyi = $manyi - $_row_cock['COCK'] * 50;
        _status($manyi, VHOST . "(项目满意分)", "扣:安全", "安全事故", "入侵个数：{$_row_cock['COCK']}", VIP, 0, 'replace');

        //扣分:故障事故
        $sql = "select fun_count,v3,to_char(cal_date,'yyyy-mm-dd hh24') cal_date
                                     from {$this->report_monitor_hour} t
                                    where v1 like '%(BUG错误)' and V2='PHP错误'
                                      and t.cal_date>= trunc(sysdate-1,'hh24') order by cal_date desc";
        $stmt = _ociparse($conn_db, $sql);
        _ociexecute($stmt);
        $_row_php = array();
        $manyi = 0;
        $data = $arr = array();
        $time = date('Y-m-d H', time() - 3600);
        while (ocifetchinto($stmt, $_row_php, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS)) {
            $data[$_row_php['V3']][$_row_php['CAL_DATE']]['count'] = $_row_php['FUN_COUNT'];
        }
        foreach ($data as $k => $v) {
            if (!isset($v[$time]['count']) || $v[$time]['count'] <= 0) {
                unset($data[$k]);
            } else {
                for ($i = time() - 3600; $i >= time() - 3600 * 24; $i--) {
                    $i_time = date('Y-m-d H', $i);
                    if (!isset($v[$time]) || $v[$i_time]['count'] <= 0) {
                        break;
                    } else {
                        $arr[$k][$i_time] = $v[$i_time]['count'];
                    }
                }
            }
        }
        foreach ($arr as $k => $v) {
            if (count($v) >= 6) {
                $manyi = $manyi - (count($v) - 5) * 100;
            }
        }
        _status($manyi, VHOST . "(项目满意分)", "扣:故障", "故障事故", NULL, VIP, 0, 'replace');
    }
}

?>