<?php
namespace hodphp\provider\templateFunction;

class FuncRenderSection extends \hodphp\lib\template\AbstractFunction
{

        function call($parameters, $data, $content = "", $unparsed = Array(), $module = false)
        {
             $varname="template_sections_".$parameters[0];
               return $this->globals->$varname?:"";
        }

}
