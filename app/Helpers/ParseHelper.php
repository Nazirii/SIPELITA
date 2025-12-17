<?php
     function safe($key,$callback,&$error,$default=null)
    {
        try{
            return $callback();
        }catch(\Exception $e){
            $error[$key]=$e->getMessage();
            return $default;
        }

    }
    function validateValue($value,$type){
        if ($value===null || trim($value)===''){
            throw new \Exception("Value is null");
            
        }
        
        switch($type){
            case 'int':
                if (!is_numeric($value) ){
                    throw new \Exception("Value is not numeric");
                }
                return $value;
            case 'string':
                return strval($value);

            default:
                throw new \Exception("Unknown type for validation");
        }
    }
   

