<?php

/*
 * LIBRARY FOR DATA EVENT STREAM
 */

class Eventstream {
    static function sendEvent($e){
        echo "event: " . $e["event"] . "\n";
        echo "data: " . json_encode($e) . "\n\n";
        flush();
        
        return true;
    }
    
    # Collect complete sys event
    static function getCompleteSysEvent(){
        $e = array();
        $e["event"] = "sys";

        # chunk: text
        $chunk=0;
        $e["data"][$chunk]["type"] = "text";
        $e["data"][$chunk]["host"] = Config::$application["text"]["host"];
        $e["data"][$chunk]["headline"] = Config::$application["text"]["headline"];

        # chunk: css
        foreach( Config::$appearance["css"] as $csschunk ){
            $chunk++;
            $e["data"][$chunk]["type"] = "css";
            $e["data"][$chunk]["key"] = $csschunk[0];
            $e["data"][$chunk]["value"] = $csschunk[1];
        }

        # chunk: project
        if(User::$mod){  # only for mods
            $result = Database::query("SELECT id, name, active FROM projects;");
            while ( $row = $result->fetch_assoc() ){
                $chunk++;
                $e["data"][$chunk]["type"] = "project";
                $e["data"][$chunk]["id"] = $row["id"];
                $e["data"][$chunk]["name"] = $row["name"];
                $e["data"][$chunk]["active"] = $row["active"];
            }
        }

        # chunk: user
        $chunk++;
        $e["data"][$chunk]["type"] = "user";
        if(User::$available){
            $e["data"][$chunk]["name"] = User::$name;
            $e["data"][$chunk]["sessionid"] = User::$sessionid;
            $e["data"][$chunk]["level"] = User::$level;
            $e["data"][$chunk]["os"] = User::$os;
            $e["data"][$chunk]["mod"] = User::$mod;
        }else{
            $e["data"][$chunk]["unset"] = "1";
        }

        return $e;
    }
}

class SocketMessage2Eventstream {
    function __construct($message){
        Eventstream::sendEvent($message);
    }
}

?>
