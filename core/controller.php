<?php
namespace hodphp\core;
//just a wrapper for controller.. might be useful someday
class Controller extends Base
{
    /**noAspects*/
    function __initialize()
    {
        $this->language->load("global");
        $this->language->load("_global");
        $this->language->load(Loader::$controller);

        $this->event->raise("controllerPreLoad", array("controller" => $this));
        if (method_exists($this, "__onLoad")) {
            $this->__onload();
        }
        $this->event->raise("controllerPostLoad", array("controller" => $this));
    }

    function __authorize()
    {
        if ($this->config->get("deny." . Loader::$module, "access")) {
            return false;
        }
        return true;
    }

    function __onAuthorizationFail()
    {
        $this->event->raise("authorizationFail");
        throw new \Exception("Authorization failed");
    }

    function __preActionCall($method)
    {
        $this->event->raise("controllerPreAction", array("controller" => $this));
        $inModuleAnnotation = $this->annotation->getAnnotationsForMethod($this->_getType(), $method, "inModule");
        if (!empty($inModuleAnnotation)) {
            $annotation = $this->annotation->translate($inModuleAnnotation[0]);
            Loader::goModule($annotation->parameters[0]);
            $this->language->load(Loader::$controller);
            Loader::goBackModule();
        } else {
            $this->language->load(Loader::$controller);
        }

        $annotations = $this->annotation->getAnnotationsForMethod($this->_getType(), $method, "http");
        foreach ($annotations as $annotation) {
            $annotation = $this->annotation->translate($annotation);
            if (strtolower($annotation->function) != strtolower($_SERVER['REQUEST_METHOD'])) {
                throw new \Exception("This method requires " . $annotation->function);
            }
        }

        $annotations = $this->annotation->getAnnotationsForMethod($this->_getType(), $method, "noAction");
        foreach ($annotations as $annotation) {
            throw new \Exception("This is an internal method.");
        }

        $annotations = $this->annotation->getAnnotationsForMethod($this->_getType(), $method, "timeout");
        foreach ($annotations as $annotation) {
            $annotation = $this->annotation->translate($annotation);
            $time = @$annotation->parameters[0] ?: 0;
            set_time_limit($time);
        }

        $viewAnnotation = $this->annotation->getAnnotationsForMethod($this->_getType(), $method, "masterView");
        foreach ($viewAnnotation as $annotation) {
            $annotation = $this->annotation->translate($annotation);
            $this->response->masterView = $annotation->parameters[0];
        }

    }
}