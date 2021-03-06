<?php

namespace framework\lib\serialization;
//a representation of a serializer
abstract class Serializer extends \framework\core\Lib
{
    abstract function serialize($data);

    abstract function unserialize($data, $assoc = false, $type = null);

    function prepareObject($data,$fields=false)
    {
        $original=$data;
        try {
            if((is_array($data) && !(isset($data["data"]) && isset($data["type"]) && isset($data["original"]))) || !is_array($data)) {
                $data = $this->getArrayData($data);
            }
            $newData = $data["data"];
            if (is_array($data["type"])) {
                foreach ($data["data"] as $key => $val) {

                    if(!empty($fields) && !in_array($key,$fields)){
                        if(array_key_exists($key,$newData)) {
                            unset($newData[$key]);
                        }
                        if(array_key_exists($key,$newDataAnnotated)) {
                            unset($newDataAnnotated[$key]);
                        }
                        continue;
                    }

                    if ($data["type"][$key] != "array" && $data["type"][$key] != "value") {
                        $_data = array(
                            "data" => $data["data"][$key],
                            "type" => $data["type"][$key],
                            "original" => $data["original"][$key]
                        );
                        $_dataNew = $this->prepareObject($_data);
                        $newData[$key] = $_dataNew["data"];
                        $newDataAnnotated[$key] = $_dataNew["annotated"];
                    } else {
                        $newData[$key] = $this->helper->str->ensureUTF8($val);
                        $newDataAnnotated[$key] = array("annotations" => array(), "value" => $this->prepareObject($val));
                    }
                }
            }

            if ($data["type"] != "array" && $data["type"] != "value") {
                if (!is_array($data["type"])) {
                    foreach ($data["data"] as $key => $value) {
                        if(!empty($fields) && !in_array($key,$fields)){
                            if(array_key_exists($key,$newData)) {
                                unset($newData[$key]);
                            }
                            if(array_key_exists($key,$newDataAnnotated)) {
                                unset($newDataAnnotated[$key]);
                            }
                            continue;
                        }
                        if(is_string($value)||is_numeric($value)){
                            $newData[$key]=$this->helper->str->ensureUTF8($newData[$key]);
                        }
                        $dkey = $key;
                        $annotations = $this->annotation->getAnnotationsForField($data["type"], $key, "serialize");
                        $translatedAnnotations = array();
                        $valueAnnotated = array();
                        $dynamicArray = false;
                        foreach ($annotations as $annotation) {

                            $annotation = $this->annotation->translate($annotation);
                            $translatedAnnotations[$annotation->function] = $annotation;

                            if ($annotation->function == "ignore") {
                                unset($newData[$key]);
                                unset($newDataAnnotated[$key]);
                            }elseif($annotation->function=="ignoreEmpty"){
                                if(empty($newData[$key])){
                                    if(array_key_exists($key,$newData)) {
                                        unset($newData[$key]);
                                    }
                                    if(array_key_exists($key,$newDataAnnotated)) {
                                        unset($newDataAnnotated[$key]);
                                    }
                                }
                            } elseif ($annotation->function=="ignoreNull") {
                                if(is_null($newData[$key])){
                                    if(array_key_exists($key,$newData)) {
                                        unset($newData[$key]);
                                    }
                                    if(array_key_exists($key,$newDataAnnotated)) {
                                        unset($newDataAnnotated[$key]);
                                    }
                                }
                            } else {
                                if ($annotation->function == "rename") {
                                    $tmp = $newData[$key];
                                    $tmpValueAnnotated = @$valueAnnotated[$key]?:null;
                                    unset($newData[$key]);
                                    unset($newDataAnnotated[$key]);
                                    unset($valueAnnotated[$key]);
                                    $newData[$annotation->parameters[0]] = $tmp;
                                    $valueAnnotated[$key] = $tmpValueAnnotated;
                                    $key = $annotation->parameters[0];
                                }
                                if ($annotation->function == "dynamic") {
                                    $dynamicData = $this->dynamicGet($data, $dkey);
                                    $classAnnotations = array();
                                    if (!@$dynamicData["isNotArray"]) {
                                        $dynamicArray = true;
                                        if (is_array($dynamicData)) {
                                            foreach ($dynamicData as $_key => $_value) {
                                                $newData[$key][$_key] = $_value["data"];
                                                $valueAnnotated[$key][$_key] = $_value["annotated"];
                                                $classAnnotations[$_key] = $_value["classAnnotations"];
                                            }
                                        }
                                    } else {
                                        $newData[$key] = $dynamicData["data"];
                                        $valueAnnotated[$key] = $dynamicData["annotated"];
                                        $classAnnotations = $dynamicData["classAnnotations"];
                                    }

                                }
                                if ($annotation->function == "enumName") {
                                    $newData[$key] = $newData[$key] = $data["original"]->{$key}->name;
                                }
                                if ($annotation->function == "modelOnly" && !property_exists(get_class($data["original"]), $key)) {
                                    unset($newData[$key]);
                                    unset($newDataAnnotated[$key]);
                                }
                            }
                        }
                        if ($dynamicArray) {
                            $newDataAnnotated[$key]["_annotations"] = $translatedAnnotations;
                            if (is_array($newData[$key])) {
                                foreach ($newData[$key] as $_key => $_value) {
                                    $newDataAnnotated[$key][$_key] = array("_classAnnotations" => $classAnnotations[$_key], "_value" => $newData[$key][$_key]);
                                    if (isset($valueAnnotated[$key][$_key])) {
                                        $newDataAnnotated[$key][$_key]["_annotated"] = $valueAnnotated[$key][$_key];
                                    }
                                }
                            }
                        } else {
                            $insertData="";
                            if(isset($newData[$key])&&(@$newData[$key]||is_numeric($newData[$key])||is_string(@$newData[$key])||is_bool(@$newData[$key]))){
                                $insertData=$newData[$key];
                            }
                            $newDataAnnotated[$key] = array("_classAnnotations" => @$classAnnotations ?: array(), "_annotations" => $translatedAnnotations, "_value" => $insertData);
                            if (isset($valueAnnotated[$key])) {
                                $newDataAnnotated[$key]["_annotated"] = $valueAnnotated[$key];
                            }
                        }
                    }
                }
                $data["data"] = $newData;
                $data["annotated"] = $newDataAnnotated;
            }

                return $data;
        }catch (\Exception $ex){
            return $original;
        }
    }

    function getArrayData($data)
    {
        $original = $data;
        if (is_array($data)) {
            $original = array();
            $type = array();
            foreach ($data as $key => $val) {
                if (is_object($data[$key]) && method_exists($data[$key], "toArray")) {
                    $original[$key] = $data[$key];
                    $type[$key] = $data[$key]->_getType();
                    $data[$key] = $data[$key]->toArray();
                } else {
                    if (is_array($val)) {
                        $type[$key] = "array";
                    } else {
                        $type[$key] = "value";
                    }
                    $original[$key] = $val;
                }

            }
        } else if (is_object($data) && method_exists($data, "toArray") && method_exists($data, "_getType")) {
            $type = $data->_getType();
            $data = $data->toArray();
        } else {
            $type = "value";
        }

        $translatedAnnotations = array();
        if (!is_array($type) && $type != "array" && $type != "value") {
            if($type) {
                $annotations = $this->annotation->getAnnotationsForClass($type, "serialize");
            }else{
                $annotations=[];
            }
            foreach ($annotations as $annotation) {
                $annotation = $this->annotation->translate($annotation);
                $translatedAnnotations[$annotation->function] = $annotation;
            }
        }

        return array(
            "data" => $data,
            "type" => $type,
            "classAnnotations" => $translatedAnnotations,
            "original" => $original,
        );
    }

    function dynamicGet($data, $key)
    {
        $tempData = $data["original"]->$key;

        $fields=false;
        $annotations = $this->annotation->getAnnotationsForField($data["type"], $key, "serializeFields");
        if(count($annotations)){
            $translation=$this->annotation->translate($annotations[0]);
            $fields=$translation->parameters;
        }

        if (is_array($tempData)) {
            foreach ($tempData as $_key => $_value) {
                if (is_object($_value)) {
                    $tempData[$_key] = $this->prepareObject($_value,$fields);
                }
            }
        } elseif (is_object($tempData)) {
            $tempData = $this->prepareObject($tempData,$fields);
            $tempData["isNotArray"] = true;
        }

        return $tempData;
    }
}

