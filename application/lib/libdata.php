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
}

class SocketMessage2Eventstream {
    function __construct($message){
        Eventstream::sendEvent(json_decode($message, true));
    }
}

?>
