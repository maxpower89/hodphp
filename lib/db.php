<?php
namespace lib;

use core\Loader;

class Db extends \core\Lib
{

    private $connections;
    private $errors = array();
    private $fields = array();
    var $parent = false;
    var $testMode = false;

    function connectConfigName($name)
    {
        if ($this->config->get("db." . $name . ".host", "server") && $this->config->get("db." . $name . ".username", "server") && $this->config->get("db." . $name . ".db", "server") && !isset($this->connections[$name])) {
            return $this->connect(
                $this->config->get("db." . $name . ".host", "server"),
                $this->config->get("db." . $name . ".username", "server"),
                $this->config->get("db." . $name . ".password", "server"),
                $this->config->get("db." . $name . ".db", "server"),
                $name
            );
        }
        return false;
    }

    function getPrefix()
    {
        if ($this->testMode) {
            return "test_";
        }
        return "";
    }

    function connect($host, $username, $password, $db, $connection)
    {
        if ($this->connections[$connection] = new \mysqli($host, $username, $password, $db)) {
            return true;
        }
        return false;
    }

    function __construct()
    {
        $this->connectConfigName("default");
    }

    function execute($queryString, $connection = "default", $params)
    {
        if (!isset($this->connections[$connection])) {
            $this->connectConfigName($connection); //to avoid manual connecting  a lot
        }

        if ($query = $this->connections[$connection]->prepare($queryString)) {
            $prefix = array(str_repeat("s", count($params)));
            $merged = array_merge($prefix, $params);
            $bound = call_user_func_array(array($query, "bind_param"), $this->refValues($merged));


            $result = $query->execute();
            if (!$result) {
                $this->debug->error("SQL Execute:" . $query->error, array(
                    "error" => $query->error,
                    "connection" => $connection,
                    "query" => $queryString
                ));
            }

            return $result;
        }

        return false;

    }

    function refValues($arr)
    {
        if (strnatcmp(phpversion(), '5.3') >= 0) //Reference is required for PHP 5.3+
        {
            $refs = array();
            foreach ($arr as $key => $value)
                $refs[$key] = &$arr[$key];
            return $refs;
        }
        return $arr;
    }

    function query($queryString, $connection = "default")
    {
        if (!isset($this->connections[$connection])) {
            $this->connectConfigName($connection); //to avoid manual connecting  a lot
        }

        $query = $this->connections[$connection]->query($queryString);
        if (!$query) {
            $error = $this->connections[$connection]->error;
            $this->debug->error("SQL Query:" . $error, array(
                "error" => $error,
                "connection" => $connection,
                "query" => $queryString
            ));
        }
        $result = Loader::createInstance("queryResult", "lib\db");
        $result->result = $query;

        return $result;
    }

    function lastId($connection = "default")
    {
        return MySqli_Insert_Id($this->connections[$connection]);
    }

    function numRows($query)
    {
        return mysqli_num_rows($query->result);
    }

    function fetch($query)
    {
        return mysqli_fetch_assoc($query->result);
    }

    function fetchAll($query, $data = null)
    {
        while ($fetch = $this->fetch($query)) {
            $data[] = $fetch;
        }
        return $data;
    }

    //dummy for now thought this could be useful in the future or when errors occur
    function cast($data, $from, $to)
    {
        Loader::loadClass("baseCaster", "lib\\db\\cast");

        $from = $this->getCastType($from);
        $to = $this->getCastType($to);
        $caster = Loader::getSingleton($from["type"] . "To" . ucfirst($to["type"]), "lib\\db\\cast");
        if ($caster) {
            $data = $caster->cast($data, $from["length"], $to["length"]);
        }
        return $data;
    }


    function ensureType($data, $type)
    {
        Loader::loadClass("baseEnsure", "lib\\db\\ensureType");

        $type = $this->getCastType($type);
        $ensure = Loader::getSingleton($type["type"], "lib\\db\\ensureType");
        if ($ensure) {
            $data = $ensure->ensure($data, $type["length"]);
        }
        return $data;
    }


    private function getCastType($str)
    {
        $exp = explode("(", $str);
        $result["type"] = $exp[0];
        if (count($exp) > 1) {
            $result["length"] = str_replace(")", "", $exp[1]);
        } else {
            $result["length"] = 0;
        }
        return $result;
    }

    function escape($string, $con = "default")
    {
        return $this->connections[$con]->real_escape_string($string);
    }

    function saveModel($model, $table = false, $ignoreParent = false, $con = "default")
    {
        $prefix = $this->db->getPrefix();
        if (!$table) {
            $table = $this->provider->mapping->default->getTableForClass($model->_getType());
        }
        if ($this->parent && !$ignoreParent) {
            $model->parent_id = $this->parent["id"];
            $model->parent_module = $this->parent["module"];
        }

        if (!isset($this->fields[$table])) {
            $this->fields[$table] = $this->db->query("SHOW columns FROM `" . $prefix.$table . "`", $con)->fetchAll();
        }

        if ($model->id) {
            $this->updateModel($model, $table, $con);
            $mode = "update";
            $id = $this->db->lastId();
        } else {
            $this->insertModel($model, $table, $con);
            $mode = "insert";
            $id = $model->id;
        }

        return array("mode" => $mode, "id" => $id);
    }

    function deleteModel($model, $table = false)
    {
        if (!$table) {
            $table = $this->provider->mapping->default->getTableForClass($model->_getType());
        }
        $this->execute("delete from `" . $table . "` where id='" . $model->id . "'");
        $model->_deleted();
    }

    function updateModel($model, $table, $con)
    {
        $prefix = $this->db->getPrefix();
        if (method_exists($model,"_isInvalidated")&&$model->_isInvalidated()) {
            $query = "update `" . $prefix.$table . "` set ";
            $data = $model->toArray();
            $i = 0;
            foreach ($this->fields[$table] as $field) {
                $fieldName = $field["Field"];
                if (isset($data[$fieldName])) {
                    if ($i) {
                        $query .= " , ";
                    }
                    $query .= "`" . $fieldName . "`='" . $data[$fieldName] . "' ";
                    $i++;
                }
            }
            $query .= " where id='" . $data["id"] . "'";
            $this->query($query, $con);
            $model->_saved();
        }
    }


    function insertModel($model, $table, $con)
    {
        $prefix = $this->db->getPrefix();
        $query = "insert into `" . $prefix.$table . "` set ";
        $data = $model->toArray();
        $i = 0;
        foreach ($this->fields[$table] as $field) {
            $fieldName = $field["Field"];
            if (isset($data[$fieldName]) && $fieldName != "id") {
                if ($i) {
                    $query .= " , ";
                }
                $query .= "`" . $fieldName . "`='" . $data[$fieldName] . "' ";
                $i++;
            }
        }
        $q = $this->query($query, $con);
        $model->id = $this->lastId($con);
        $model->_saved();

    }

    function select($table, $alias = false)
    {
        $select = Loader::createInstance("select", "lib/db");
        $select->table($table, $alias);
        return $select;
    }

    function selectModel($class, $namespace = "",$alias=false)
    {
        $select = Loader::createInstance("select", "lib/db");
        $select->byModel($class, $namespace,$alias);
        return $select;
    }

    function workWithParent($id, $module = false)
    {
        $this->parent = array("id" => $id, "module" => $module ? $module : Loader::$actionModule);
    }

    function startTestMode()
    {
        $this->testMode = true;
    }

    function stopTestMode()
    {
        $this->testMode = false;
    }

    function condition(){
        return Loader::createInstance("condition","lib/db");
    }

    function tableForModel($model,$namespace){
        $modelPath = $namespace . "\\" . $model;
        $table = $this->provider->mapping->default->getTableForClass($modelPath);
        return $table;
    }
}

?>
