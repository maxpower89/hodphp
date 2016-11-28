<?php
namespace provider\fieldHandler;

use lib\model\BaseFieldHandler;


class DbToMany extends BaseFieldHandler
{
    private $_field;
    private $_toTable;
    private $obj;
    private $loaded;
    private $_toModel;
    private $_toModelNamespace;
    private $_cascadeSave;
    private $_cascadeDelete;
    private $_saveReset;
    private $_initArray;

    function fromAnnotation($parameters,$type,$field)
    {
        $mapping=$this->provider->mapping->default;
        if(isset($parameters["model"])){
            $this->toTable($mapping->getTableForClass($parameters["model"]))
                ->toModel($parameters["model"]);
        }else if(isset($parameters["toTable"])){
            $this->toModel($mapping->getModelForTable($parameters["toTable"]))
                ->toTable($parameters["toTable"]);
        }


        if(isset($parameters["key"])){
            $this->field($parameters["key"]);
        }else{
            $this->field($mapping->getTableForClass($type))."_id";
        }

        if(isset($parameters["saveReset"]) && $parameters["saveReset"]=="true"){
            $this->saveReset();
        }

        if(isset($parameters["cascade"])){
            if($parameters["cascade"]=="all"){
                $this->cascadeAll();
            }
            if($parameters["cascade"]=="delete"){
                $this->cascadeDelete();
            }
            if($parameters["cascade"]=="save"){
                $this->cascadeSave();
            }
        }



    }

    function field($field)
    {
        $this->_field = $field;
        return $this;
    }

    function toTable($toTable)
    {
        $this->_toTable = $toTable;
        return $this;
    }

    function toModel($model, $namespace = false)
    {
        if(!$namespace){
            $model=str_replace("/","\\",$model);
            $exp=explode("\\",$model);
            if(isset($exp[1])){
                $this->_toModel=$exp[1];
                $this->_toModelNamespace=$exp[0];
            }else{
                $this->_toModel=$exp[0];
            }
        }else {
            $this->_toModel = $model;
            $this->_toModelNamespace = $namespace;
        }
        return $this;
    }

    function get($inModel)
    {
        if (!$this->loaded) {
            if ($this->_initArray) {

                $this->obj = array();
                foreach ($this->_initArray as $val) {

                    $model = $this->_toModel;
                    if(!is_object($val)) {
                        if ($this->_toModelNamespace) {
                            $namespace = $this->_toModelNamespace;
                            $this->obj[] = $this->model->$namespace->$model->fromArray($val);
                        } else {
                            $this->obj[] = $this->model->$model->fromArray($val);
                        }
                    }else{
                        $this->obj[]=$val;
                    }

                }
            } else {
                if($this->_field=="parent"){
                    $where="parent_id='".$this->db->parent["id"]."' and parent_module='".$this->db->parent["module"]."'";
                }
                elseif($this->db->parent){
                    $where="parent_id='".$this->db->parent["id"]."' and parent_module='".$this->db->parent["module"]."' and  `" . $this->_field . "` ='" . $this->_model->id . "' ";
                }else{
                    $where="`" . $this->_field . "` ='" . $this->_model->id . "'";
                }

                $query = $this->db->query("select * from `" . $this->_toTable . "` where ".$where);
                $this->obj=$query->fetchAllModel($this->_toModel, $this->_toModelNamespace);

            }
            $this->loaded = true;
        }
        return $this->obj;
    }

    function set($value)
    {
        if (is_array($value)) {
            $this->_initArray = $value;
            $this->loaded=false;
        }
    }

    function delete(){
        if($this->_cascadeDelete){
            $this->db->query("delete from `" . $this->_toTable . "` where `" . $this->_field . "` ='" . $this->_model->id . "'");
        }
    }

    function save()
    {
        $originalData = $this->db->query("select * from `" . $this->_toTable . "` where `" . $this->_field . "` ='" . $this->_model->id . "'")->fetchAll();
        $originalData = $this->toIdMap($originalData);
        $data = $this->get(false);

        if($this->_saveReset){

            if($this->_field=="parent"){
                $where="parent_id='".$this->db->parent["id"]."' and parent_module='".$this->db->parent["module"]."'";
            }
            elseif($this->db->parent){
                $where="parent_id='".$this->db->parent["id"]."' and parent_module='".$this->db->parent["module"]."' and  `" . $this->_field . "` ='" . $this->_model->id . "' ";
            }else{
                $where="`" . $this->_field . "` ='" . $this->_model->id . "'";
            }

            $this->db->query("delete from ".$this->_toTable." where ".$where);
        }

        $idField=$this->_field;
        foreach ($data as $val) {
            if ($this->_cascadeSave) {
                if(!$val->$idField){
                    $val->$idField=$this->_model->id;
                }
                $this->db->saveModel($val,$this->_toTable);
                if(isset($originalData[$val->id])){
                    unset($originalData[$val->id]);
                }
            }
        }

        if ($this->_cascadeDelete) {
            foreach($originalData as $val)
            {
                $this->db->query("delete from ".$this->_toTable." where id='".$val["id"]."' ");
            }
        }

    }

    private function toIdMap($array)
    {
        $result = array();
        if($array) {
            foreach ($array as $key => $val) {
                if (!is_array($val)) {
                    $val = $val->toArray();
                }
                $result[$val["id"]] = $val;
            }
        }
        return $result;
    }

    function cascadeAll()
    {
        $this->_cascadeDelete = true;
        $this->_cascadeSave = true;
        return $this;
    }

    function cascadeDelete(){
        $this->_cascadeDelete = true;
        return $this;
    }

    function cascadeSave(){
        $this->_cascadeSave=true;
        return $this;
    }

    function saveReset(){
        $this->_saveReset=true;
        return $this;
    }
}
