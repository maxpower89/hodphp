<?php

namespace provider\templateFunction;

class FuncRenderHead extends \lib\template\AbstractFunction
{

    //make a text lowercase
    function call($parameters, $data, $content = "", $unparsed = Array(), $module = false)
    {
        $this->event->raise("headPreRender", func_get_args());
        $result = "";
        foreach ($this->document->getStylesheets() as $stylesheet) {
            $result .= $this->template->parseFile("components/stylesheet", array("stylesheet" => $stylesheet)) . "\n";
        }


        $varContent="";
        foreach ($this->document->getVars() as $key => $value) {
            $varContent .= $this->template->parseFile("components/var", array(
                "key" => $key,
                "value" => $value
            ));
        }
        if($varContent){
            $result.=$this->template->parseFile("components/inlineScript",array("content"=>$varContent));
        }

        foreach ($this->document->getScripts() as $script) {
            $result .= $this->template->parseFile("components/script", array("script" => $script)) . "\n";
        }


        $this->event->raise("headPostRender", func_get_args());
        return $result;
    }
}


?>