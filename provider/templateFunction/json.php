<?php
namespace framework\provider\templateFunction;

class Json extends \framework\lib\template\AbstractFunction
{
    function call($parameters, $data, $content = "", $unparsed = Array(), $module = false)
    {
        //just returns the text
        if(is_object($parameters[0])){
            $value=$parameters[0]->getData()->toArray();
        }else{
            $value=$parameters[0];
        }
        return json_encode($value);
    }
}

