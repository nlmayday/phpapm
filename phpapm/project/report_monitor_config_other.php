<?php

/**
 * @desc   修改v2接口id
 * @author xing39393939@gmail.com
 * @since  2013-03-06 22:06:23
 * @throws 注意:无DB异常处理
 */
class report_monitor_config_other extends project_config
{
    function _initialize()
    {
        if (empty($_COOKIE['admin_user']) || $_COOKIE['admin_user'] != md5(serialize($this->admin_user))) {
            exit();
        }

        if (!isset($_GET['NO_COUNT']) && !isset($_GET['DATA_UNITS']) && !isset($_GET['API_ID'])) {
            header("location:{$_SERVER['HTTP_REFERER']}");
            die();
        }
        $conn_db = _ocilogon($this->db);

        $sql = "select * from {$this->report_monitor_config} where id=:id ";
        $stmt = _ociparse($conn_db, $sql);
        _ocibindbyname($stmt, ':id', $_GET['id']);
        $oci_error = _ociexecute($stmt);
        $_row = array();
        ocifetchinto($stmt, $_row, OCI_ASSOC + OCI_RETURN_LOBS + OCI_RETURN_NULLS);
        $v2_config_other = unserialize($_row['V2_CONFIG_OTHER']);
        //修改是否参与
        if (isset($_GET['NO_COUNT'])) {
            $v2_config_other['NO_COUNT'] = ($_GET['NO_COUNT'] == 'true') ? true : false;
        }
        //修改数据单位
        if (isset($_GET['DATA_UNITS'])) {
            if ($_GET['DATA_UNITS'] == 'capacity') {
                $v2_config_other['DATA_UNITS'] = 'capacity';
            } elseif ($_GET['DATA_UNITS'] == 'digital') {
                unset($v2_config_other['DATA_UNITS']);
            }
        }
        //修改对应api id
        if (isset($_GET['API_ID'])) {
            if (is_numeric($_GET['API_ID']))
                $v2_config_other['API_ID'] = $_GET['API_ID'];
        }

        $v2_config_other = serialize($v2_config_other);
        $sql = "update {$this->report_monitor_config} set v2_config_other=:v2_config_other where id=:id ";
        $stmt = _ociparse($conn_db, $sql);
        _ocibindbyname($stmt, ':v2_config_other', $v2_config_other);
        _ocibindbyname($stmt, ':id', $_GET['id']);
        $oci_error = _ociexecute($stmt);
        if (!$v2_config_other['API_ID'])
            header("location:{$_SERVER['HTTP_REFERER']}");
        die();
    }
}

?>