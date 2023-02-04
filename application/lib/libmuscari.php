<?php

/*
 * GENERAL LIBRARY FOR MUSCARI
 */

class Muscari {
    
    # Initialize Muscari PHP script
    static function init( $type = "normal" ){
        Muscari::setPHPOptions();  # Set PHP-related options
                
        Config::init();
        Database::init();
        User::init();
        Project::init();

        Muscari::launchType($type);  # Start specific script type, e.g. eventstream
    }

    static function close(){
        Database::close();
    }
    
    static function setPHPOptions(){
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        
        session_set_cookie_params(["SameSite" => "Strict"]);
        session_set_cookie_params(["Secure" => "true"]);
    }
    
    static function launchType( $type ){
        switch($type){
        case "eventstream":
            set_time_limit(0);
            ignore_user_abort(true);
                        
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            ob_end_flush();  # Stop output buffer
            break;
            
        case "normal":
            break;

        default:
            die("Class Muscari(launchType): Invalid script type given.");
            break;
        }
    }
}

class Config {
    public static $database;
    public static $application;
    public static $appearance;
    
    static function init(){
        # Get general config
        $rawconfig=read("../config/config.json");
        if($rawconfig === false){
            $rawconfig=read("../config/config.default.json");
        }

        if($rawconfig === false){
            die("Class Config(init): No config file available");
        }

        $general=json_decode($rawconfig, true);
        
        Config::$database=json_decode(read("../config/database/".$general["database"].".json"), true);
        Config::$application=json_decode(read("../config/application/".$general["application"].".json"), true);
        Config::$appearance=json_decode(read("../config/appearance/".$general["appearance"].".json"), true);
    }
}

class Database {
    public static $connection;
    
    static function init(){
        Database::connect(Config::$database);
    }

    static function connect($config){
        if($config["server"] != "mysql"){ die("Class Database(connect): Server has to be mysql"); }
        Database::$connection = new mysqli(
            $config["host"],
            $config["user"],
            $config["password"],
            $config["database"],
            $config["port"]
        );

        if(Database::$connection->connect_errno){
            die("Class Database(connect): Connection failed: " . Database::$connection->connect_error);
        }
    }

    static function is_alive(){
        if( Database::$connection->ping() ){ return true; }
        return false;
    }

    static function query($query){
        return Database::$connection->query($query);
    }

    static function getFirstValue($query){
        $result = Database::query($query);
        $row = $result->fetch_row();

        return $row[0];
    }
    
    static function escape($string){
        return Database::$connection->real_escape_string($string);
    }

    static function close(){
        Database::$connection->close();
    }
}

class User {
    
    public static $available = false;
    public static $sessionid;
    public static $id;
    public static $name;
    public static $os;
    public static $level;
    public static $mod;
    
    static function init(){
        if($_GET["cookies_allowed"] == "0"){ User::disable(); return false; }  # Cookies disallowed by browser

        # Check if session can be started
        if(session_start(["sid_length" => 32])){
            $sessionid=session_id();  # Read session ID
        }else{
            setcookie(session_name(), null, -1, '/');  # Remove faulty session cookie
            User::disable(); return false;
        }
        session_write_close();

        $user_exists=false;
        $attempts=0;
        while (!$user_exists and $attempts <= 1){
            $result = Database::query("SELECT id, name, os, level FROM user WHERE session='".Database::escape($sessionid)."';");  # Check if user exists (again)
            while( $row = $result->fetch_assoc() ){
                $user_exists=true;
                User::$sessionid=$sessionid;
                User::$id=$row["id"];
                User::$name=$row["name"];
                User::$os=$row["os"];
                User::$level=$row["level"];

                if( in_array($sessionid, Config::$application["mods"]) ){  # Check if user is mod
                    User::$mod=true;
                }else{
                    User::$mod=false;
                }

                User::enable();
            }

            if( !$user_exists and $attempts == 0 ){ # Only create, if not already tried
                Database::query("INSERT INTO user SET name='Anonymous', os='Linux', level='1', session='".Database::escape($sessionid)."';");  # If not, try create
            }

            $attempts++;
        }
        
        if(!$user_exists){ User::disable(); return false; }  # Creating user failed.
    }

    static function disable(){
        User::$available=false;
    }

    static function enable(){
        User::$available=true;
    }
}

class Project {
    public static $id;
    public static $name;
    
    static function init(){
        $project_exists=false;
        $attempts=0;
        while(!$project_exists and $attempts <= 1){
            $result = Database::query("SELECT id, name FROM projects WHERE active='1';");
            while( $row = $result->fetch_assoc() ){
                $project_exists=true;
                Project::$id=$row["id"];
                Project::$name=$row["name"];
            }

            if(!$project_exists and $attempts == 0){  # Only create project if not already tried.
                Database::query("INSERT INTO projects SET name='First project', active='1';");
            }
            
            $attempts++;
        }

        if(!$project_exists){
            die("Class Project(init): Creating project failed but necessary.");
        }
    }
}

class MuscariSocket {
    static function listen($messageHandler){
        # Set user belonging to socket
        if(User::$available){
            $socket_user = User::$id;
        }else{
            $socket_user = -1;
        }

        # Save socket to DB and get ID
        Database::query("INSERT INTO sockets SET user='".Database::escape($socket_user)."';");
        $result = Database::query("SELECT LAST_INSERT_ID();");
        while( $row = $result->fetch_row() ){
            $socket_id = $row[0];
        }

        if(!is_numeric($socket_id)){ die("Class MuscariSocket(Listen): Save socket to database or getting ID failed."); }
                
        # Create socket
        $LISTEN_SOCKET = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if( socket_bind($LISTEN_SOCKET, __DIR__ ."/../sockets/".$socket_id.".sock") and
            socket_listen($LISTEN_SOCKET, 100) ){
            
            # Accept connections till connection aborted
            while( !connection_aborted() ){
                $COMMUNICATION_SOCKET = socket_accept($LISTEN_SOCKET);
                
                $message = trim(socket_read($COMMUNICATION_SOCKET, 100000, PHP_NORMAL_READ));
                
                socket_close($COMMUNICATION_SOCKET);
                
                new $messageHandler($message);
            }
        }

        socket_close($LISTEN_SOCKET);  # Close Socket
        unlink( __DIR__ ."/../sockets/".$socket_id.".sock" );
        MuscariSocket::forgetDeadSocket($socket_id);
    }

    static function send($event, $user = -1){
        # $user:
        # 0+ Specific user by ID
        # -1 All users
        # -2 Mods
        # -3 Self

        if($user >= 0){
            $user_specification="WHERE user='".Database::escape($user)."'";
        }elseif($user == -2){
            $user_specification="WHERE 0=1";
            foreach(Config::$application["mods"] AS $mod_sessionid){
                $result = Database::query("SELECT id FROM user WHERE session='".Database::escape($mod_sessionid)."';");
                while ($row = $result->fetch_assoc()) {
                    $mod_userid=$row["id"];
                    $user_specification.=" OR user='".Database::escape($mod_userid)."'";
                }
            }
        }elseif($user == -3 and User::$available){
            $user_specification="WHERE user=".Database::escape(User::$id);
        }else{
            $user_specification="";
        }

        $result = Database::query("SELECT id FROM sockets $user_specification;");
        while ( $row = $result->fetch_assoc() ){
            $socket_id = $row["id"];
            $SOCKET = socket_create(AF_UNIX, SOCK_STREAM, 0);
            if(socket_connect($SOCKET, __DIR__."/../sockets/".$socket_id.".sock")){
                $message = json_encode($event)."\n";
                
                socket_full_write($SOCKET, $message);
            }else{
                MuscariSocket::forgetDeadSocket($socket_id);
            }
            socket_close($SOCKET);
        }
        
        return true;
    }

    static function forgetDeadSocket($id){
        return Database::query("DELETE FROM sockets WHERE id='".Database::escape($id)."';");  # Remove socket from DB
    }
}

class MuscariEvent {
    # Function generate sys chunk
    static function genSysChunk($type, $id = null){
        $chunk["type"]="$type";
        switch($type){
        case "text":
            $chunk=array_merge($chunk, Config::$application["text"]);
            break;

        case "project":
            if(!is_numeric($id)){ return false; }
            $exists = false;
            $result = Database::query("SELECT id, name, active FROM projects WHERE id='". Database::escape($id) ."';");
            while($row = $result->fetch_assoc()){
                $exists = true;
                $chunk["id"]=$row["id"];
                $chunk["name"]=$row["name"];
                $chunk["active"]=$row["active"];
            }
            if(!$exists){
                $chunk["id"] = $id;
                $chunk["remove"] = 1;
            }
            break;

        case "user":
            if(User::$available){                                  
                $chunk["name"] = User::$name;          
                $chunk["sessionid"] = User::$sessionid;
                $chunk["level"] = User::$level;        
                $chunk["os"] = User::$os;              
                $chunk["mod"] = User::$mod;            
            }else{                                                 
                $chunk["unset"] = "1";                 
            }
            break;
            
        default:
            return false;
            break;
        }

        return $chunk;
    }

    # Function for generating content chunks
    static function genContentChunk($type, $id = null){
        $chunk["type"]="$type";
        switch($type){
        case "frage":
            if(!is_numeric($id)){ return false; }
            $exists = false;
            $result = Database::query("SELECT fragen.id, user.name, user.level, user.os, fragen.forum, fragen.status, fragen.inhalt FROM fragen, user WHERE fragen.user=user.id and fragen.id='".Database::escape( $id )."';");
            while($row = $result->fetch_assoc()){
                $exists = true;
                $chunk["id"] = $row["id"];
                $chunk["username"] = $row["name"];
                $chunk["level"] = $row["level"];
                $chunk["os"] = $row["os"];
                $chunk["forum"] = $row["forum"];
                $chunk["inhalt"] = $row["inhalt"];
                $chunk["status"] = $row["status"];
            }
            if(!$exists){
                $chunk["id"] = $id;
                $chunk["remove"] = 1;
            }
            break;

        default:
            return false;
            break;
        }
        
        return $chunk;
    }

    # Get complete content event                                                         
    static function getCompleteContentEvent(){                                           
        $e = array();                                                                
        $e["event"] = "content";                                                         

        # chunk: frage
        $result = Database::query("SELECT id FROM fragen ORDER BY id;");                   
        while ( $row = $result->fetch_assoc() ){                                 
            $e["data"][] = MuscariEvent::genContentChunk("frage", $row["id"]);     
        }                                                                        

        return $e;
    }
    
    # Get complete sys event                                                         
    static function getCompleteSysEvent(){                                           
        $e = array();                                                                
        $e["event"] = "sys";                                                         
                                                                                 
        # chunk: text                                                                
        $e["data"][]=MuscariEvent::genSysChunk("text");                              
                                                                                 
        # chunk: css, not supported from "MuscariEvent::genSysChunk()" yet, because. 
        foreach( Config::$appearance["css"] as $csschunk ){                          
            $tmpchunk = array();                                                     
            $tmpchunk["type"] = "css";                                               
            $tmpchunk["key"] = $csschunk[0];                                         
            $tmpchunk["value"] = $csschunk[1];                                       
                                                                                 
            $e["data"][]=$tmpchunk;                                                  
        }                                                                            
                                                                                 
        # chunk: project                                                             
        if(User::$mod){  # only for mods                                             
            $result = Database::query("SELECT id FROM projects;");                   
            while ( $row = $result->fetch_assoc() ){                                 
                $e["data"][] = MuscariEvent::genSysChunk("project", $row["id"]);     
            }                                                                        
        }                                                                            
                                                                                 
        # chunk: user                                                                
        $e["data"][] = MuscariEvent::genSysChunk("user");                            
                                                                                 
        return $e;                                                                   
    }                                                                                

}

#
# GLOBAL FUNCTIONS
#

function read($file){
    return file_get_contents(__DIR__ . "/" . $file);
}

function socket_full_write($SOCKET, $message){
    $length = strlen($message);
        
    while (true) {
        $sent = socket_write($SOCKET, $message, $length);
        if ($sent === false) { return false; }
        if ($sent < $length) {
            $message = substr($message, $sent);
            $length -= $sent;
        }else{ break; }
    }
    return true;
}

?>
