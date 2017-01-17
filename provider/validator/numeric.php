<?php
namespace  provider\validator;

use core\Loader;
use lib\validation\BaseValidator;

class Numeric extends BaseValidator{

     function validate($data){
         if(!is_numeric($data->data)){
             return $this->result(false,$this->language->get("empty","_validation"));
         }else{
             return $this->result(true,false);
         }
     }
    function isRequired(){return true;}
}
?>