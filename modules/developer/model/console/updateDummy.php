<?php

namespace hodphp\modules\developer\model\console;

use hodphp\core\Loader;
use hodphp\lib\model\BaseModel;
use ReflectionMethod;

class UpdateDummy extends BaseModel
{
    static $classes;
    function process()
    {
        self::$classes=[
            "hodphp\\core\\base"=>[
                "namespace"=>"hodphp\\core",
                "name"=>"Base",
                "properties"=>$this->getLibs()
            ],
            "hodphp\\core\\controller"=>[
                "namespace"=>"hodphp\\core",
                "name"=>"Controller",
                "extends"=>"Base"
            ],
            "hodphp\\core\\lib"=>[
                "namespace"=>"hodphp\\core",
                "name"=>"Lib",
                "extends"=>"Base"
            ],
        ];
        $this->getLibClasses();
        $this->getBaseClasses();
        $this->getServices();

        $result=$this->template->parseFile("console/dummyClasses",["classes"=>self::$classes]);
        if(!$this->filesystem->exists("data/dummyClasses")){
            $this->filesystem->mkdir("data/dummyClasses");
        }
        $this->filesystem->clearWrite("data/dummyClasses/dummyClasses.php",$result);


    }

    function getLibs(){
        $result=[];
        $this->getDynamicProperties("framework/lib","hodphp\\lib",$result);
        $this->getDynamicProperties("project/lib","project\\lib",$result);
        return $result;
    }

    function getBaseClasses(){
        $files=$this->filesystem->getFilesRecursive("framework",false,"base");
        foreach($files as $file){
            $relativePath=str_replace($this->filesystem->calculatePath("framework"),"hodphp/",$file);

            $explode=explode("/",$relativePath);
            $file=$explode[count($explode)-1];
            unset($explode[count($explode)-1]);
            $namespace=implode("\\",$explode);
            $path=str_replace("hodphp","framework",$namespace);
            $this->getClassForFile($path,$file,$namespace);
        }
    }

    function getLibClasses(){
        $this->getClassesForDir("framework/lib","hodphp\\lib");
        $this->getClassesForDir("project/lib","project\\lib");
    }

    function getClassesForDir($dir,$classPath,$fakePath=false){
        $result=[];
        $files=$this->filesystem->getFiles($dir,"php");
        foreach($files as $file){
            $clsInfo=$this->getClassForFile($dir,$file,$classPath,$fakePath);
            $result[$clsInfo["namespace"]."\\".$clsInfo["name"]]=$clsInfo;
        }
        return $result;
    }

    function getServices(){
        $properties=[];
        $classes=$this->getClassesForDir("project/service","project\\service","merge\\service");
        foreach($this->filesystem->getDirs("project/modules") as $dir){
            $classes=array_merge($classes,
                $this->getClassesForDir("project/modules/".$dir."/service","project\\modules\\".$dir."\\service","merge\\service")
            );
        }

        foreach($classes as $class){
            $properties[]=["name"=>$class["name"],"cls"=>"\\".$class["namespace"]."\\".$class["name"]];
        }
        self::$classes["hodphp\\lib\\service"]["properties"]=$properties;
    }


    function getClassForFile($dir,$file,$classPath,$fakePath=false){
        $cls=str_replace(".php","",$file);
        if(!isset( self::$classes[$classPath."\\".$cls])) {
            self::$classes[$classPath."\\".$cls]=true;
            self::$classes[$classPath . "\\" . $cls] = [
                "name" => $cls,
                "namespace" => $classPath,
                "properties" => $this->getProperties($dir, $file, $classPath . "\\" . $cls),
                "methods" => $this->getMethods($dir, $file, $classPath . "\\" . $cls),
                "file"=>$file,
                "folder"=>$dir,
            ];
            if($fakePath) {
                if (!self::$classes[$fakePath . "\\" . $cls]) {
                    self::$classes[$fakePath . "\\" . $cls] = self::$classes[$classPath . "\\" . $cls];
                    self::$classes[$fakePath . "\\" . $cls]["namespace"]=$fakePath;
                } else {
                    self::$classes[$fakePath . "\\" . $cls]["properties"] = array_merge(self::$classes[$fakePath . "\\" . $cls]["properties"], self::$classes[$classPath . "\\" . $cls]["properties"]);
                    self::$classes[$fakePath . "\\" . $cls]["methods"] = array_merge(self::$classes[$fakePath . "\\" . $cls]["methods"], self::$classes[$classPath . "\\" . $cls]["properties"]);
                }
            }

        }
        if($fakePath){
            return self::$classes[$fakePath . "\\" . $cls];
        }else{
            return self::$classes[$classPath . "\\" . $cls];
        }

    }

    function getProperties($dir,$file,$cls){
        $result=[];
        include_once($this->filesystem->calculatePath($dir)."/".$file);
        if(class_exists($cls)){
            foreach( get_class_vars($cls) as $name=>$value){
                $result[$name]=["name"=>$name,"cls"=>"\\".gettype($value)];
            }
        }
        return $result;
    }

    function getMethods($dir,$file,$cls){
        $result=[];
        include_once($this->filesystem->calculatePath($dir)."/".$file);
        if(class_exists($cls)){
            foreach( get_class_methods($cls) as $methodName){
                $result[$methodName]=["name"=>$methodName,"isAbstract"=>$this->getIsAbstract($cls,$methodName),"returnType"=>$this->getReturnType($cls,$methodName),"params"=>$this->getParamsFor($cls,$methodName)];
            }
        }
        return $result;
    }

    function getIsAbstract($cls,$methodName){
        $r = new ReflectionMethod($cls, $methodName);
        return $r->isAbstract();
    }

    function getReturnType($cls,$methodName){
        $r = new ReflectionMethod($cls, $methodName);
        $returnType= $r->getReturnType();
        if(!$returnType){
            $annot=$this->annotation->getAnnotationsForMethod($cls, $methodName, $prefix = "return ", $uncached = true, $noClass = true);
            if(count($annot)){
                $returnType= $annot[0];
            }
        }

        if($returnType&&!isset(self::$classes[strtolower($returnType)])){
            $explode=explode("\\",$returnType);
            $cls=$explode[count($explode)-1];
            $cls=str_replace("[]","",$cls);
            unset($explode[count($explode)-1]);
            unset($explode[0]);
            $namespace=implode("\\",$explode);
            $path=str_replace("hodphp","framework",$namespace);
            $this->getClassForFile($path,$cls.".php",$namespace);

        }
        return $returnType;
    }

    function getParamsFor($cls,$methodName){
        $result=[];
        $r = new ReflectionMethod($cls, $methodName);
        $params = $r->getParameters();
        foreach ($params as $param) {
            $result[]= '$'.$param->getName();
        }

        return implode(",",$result);
    }


    function getDynamicProperties($dir,$classPath,&$output){
        $files=$this->filesystem->getFiles($dir,"php");
        foreach($files as $file){
            $cls=str_replace(".php","",$file);
            $output[]=["name"=>$cls,"cls"=>"\\".$classPath."\\".$cls];
        }
    }
}