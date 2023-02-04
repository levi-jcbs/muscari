<?php

class MuscariAPI {
    static function setError($error){
        global $RETURN;
        
        $RETURN=array();
        $RETURN["status"] = "error";
        $RETURN["description"] = $error;

        return true;
    }

    static function setSuccess($description = null){
        global $RETURN;
        
        $RETURN=array();
        $RETURN["status"] = "success";

        if($description != ""){
            $RETURN["description"] = $description;
        }
        return true;
    }
}

?>
