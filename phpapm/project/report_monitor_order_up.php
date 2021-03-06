<?php

/**
 * @desc   v2排序操作：上升一位
 * @author xing39393939@gmail.com
 * @since  2013-03-06 22:06:23
 * @throws 注意:无DB异常处理
 */
class report_monitor_order_up extends project_config
{
    function _initialize()
    {
        if (empty($_COOKIE['admin_user']) || $_COOKIE['admin_user'] != md5(serialize($this->admin_user))) {
            exit();
        }

        $conn_db = _ocilogon($this->db);
        if (!$_REQUEST['orderby'])
            $this->report_monitor_order();
        //上面的减下来
        $sql = "update  {$this->report_monitor_config} set orderby=:orderby where v1=:v1 and   orderby=:orderby-1 ";
        $stmt = _ociparse($conn_db, $sql);
        _ocibindbyname($stmt, ':v1', $_REQUEST['v1']);
        _ocibindbyname($stmt, ':orderby', $_REQUEST['orderby']);
        $oci_error = _ociexecute($stmt);
        //本身上升
        $sql = "update  {$this->report_monitor_config} set orderby=:orderby-1 where  v1=:v1 and v2=:v2 ";
        $stmt = _ociparse($conn_db, $sql);
        _ocibindbyname($stmt, ':v1', $_REQUEST['v1']);
        _ocibindbyname($stmt, ':v2', $_REQUEST['v2']);
        _ocibindbyname($stmt, ':orderby', $_REQUEST['orderby']);
        $oci_error = _ociexecute($stmt);
        header("location: {$_SERVER['HTTP_REFERER']}");
    }
}

?>