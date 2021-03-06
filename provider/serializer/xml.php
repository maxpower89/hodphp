<?php

namespace framework\provider\serializer;

//simple json serializer
use framework\lib\serialization\Serializer;

class XML extends Serializer
{

    function unserialize($data, $assoc = false, $type = null)
    {
        if (!is_null($type)) {
            $annotData = $this->annotation->getAnnotationsForClass($type, 'serializeNamespace');

            if (!empty($annotData)) {
                $annotData = $this->annotation->translate($annotData[0]);
                return json_decode(json_encode(simplexml_load_string($data)->children($annotData->parameters[0], $annotData->parameters[1])), true);
            } else {
                $namespaces = simplexml_load_string($data)->getNamespaces();

                if (count($namespaces)) {
                    return json_decode(json_encode(simplexml_load_string($data)->children(array_keys($namespaces)[0], true)), true);
                }
            }
        }
        return json_decode(json_encode(simplexml_load_string($data)->children()), true);
    }

    function serialize($data)
    {
        $data = $this->prepareObject($data);

        $initData = $this->getInitData($data);
        $xmlData = new \SimpleXMLElement($initData["wrapper"]);
        if ($initData["rootElement"]) {
            $this->arrayToXml($data["annotated"][$initData["rootElement"]]["_value"], $xmlData);
        } else {
            $this->arrayToXml($data["annotated"], $xmlData);
        }
        return $xmlData->asXML();
    }

    function getInitData($data)
    {

        $customWrapper = null;
        $rootElement = false;
        $wrapper = '<?xml version="1.0" encoding="UTF-8"?>';

        if (!is_null($data["type"])) {
            if (isset($data["classAnnotations"]["wrapper"])) {
                $annotData = $data["classAnnotations"]["wrapper"];
                $customWrapper = '<' . $annotData->parameters[0] .
                    (!empty($annotData->parameters[1]) ? ' xmlns="' . $annotData->parameters[1] . '"' : '') .
                    '></' . $annotData->parameters[0] . '>';
            } else {
                foreach ($data as $key => $val) {
                    if (isset($data["annotated"][$key]["root"])) {
                        $customWrapper = '<' . $key . '></' . $key . '>';
                        $rootElement = $key;
                        break;
                    }
                }
            }
        }

        if ($customWrapper === null) {
            $customWrapper = "<data></data>";
        }

        return array(
            "wrapper" => $wrapper . $customWrapper,
            "rootElement" => $rootElement
        );
    }

    function arrayToXml($data, &$xml_data)
    {
        foreach ($data as $key => $value) {
            $namespace = null;
            $namespacePrefix = null;
            if (!empty($value["_annotations"]["namespace"])) {
                $namespace = $value["_annotations"]["namespace"]->parameters[0];
                $namespacePrefix = $namespace . ":";
            }
            if (substr($key, 0, 1) != "_") {
                $inputValue = $value;
                if (isset($value["_annotated"])) {
                    $inputValue = $value["_annotated"];
                } elseif (isset($value["_value"])) {
                    $inputValue = $value["_value"];
                }

                if (isset($value["_classAnnotations"]["wrapper"])) {
                    $key = $value["_classAnnotations"]["wrapper"]->parameters[0];
                }
                if (is_numeric($key)) {
                    $key = 'KeyMissing' . $key;
                }
                if (is_object($inputValue)) {
                    $child = $xml_data->addChild($namespacePrefix . $namespace . $key, null, $namespace);
                    $this->arrayToXml($inputValue, $child);
                } elseif (is_array($inputValue)) {
                    if (!isset($inputValue["_annotations"]["noWrap"])) {
                        if ($value["_classAnnotations"]["value"]) {
                            $subValue = "";
                            foreach ($inputValue as $subInputValue) {
                                if (@$subInputValue["_annotations"]["content"]) {
                                    $subValue .= $subInputValue["_value"];
                                }
                            }
                            $child = $xml_data->addChild($namespacePrefix . $key, $subValue, $namespace);
                        } else {
                            $child = $xml_data->addChild($namespacePrefix . $key, null, $namespace);
                        }
                    } else {
                        $child = $xml_data;
                    }
                    $this->arrayToXml($inputValue, $child);
                } else {
                    if (is_array($value) && @$value["_annotations"]["attribute"]) {
                        $xml_data->addAttribute($key, htmlspecialchars($inputValue), $namespace);
                    } elseif (!is_array(@$value["_annotations"]) || !@$value["_annotations"]["content"]) {
                        $xml_data->addChild($namespacePrefix . $namespace . $key, htmlspecialchars($inputValue), $namespace);
                    }
                }
            }

        }
    }
}

