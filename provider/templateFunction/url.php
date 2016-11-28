<?php
namespace provider\templateFunction;

use core\Loader;

class FuncUrl extends \lib\template\AbstractFunction
{
    function call($parameters, $data, $content = "", $unparsed = Array(),$module=false)
    {
        foreach($parameters as $key=>$parameter){
            if(is_object($parameter)){
                $parameters[$key]=$parameter->getData();
            }
        }
        return $this->route->createRoute($parameters);
    }
}

?>