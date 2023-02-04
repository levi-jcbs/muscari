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
                while ($row = $result->fetch_assoc) {
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
                $length = strlen($message);

                while (true) {
                    $sent = socket_write($SOCKET, $message, $length);
                    if ($sent === false) { break; }
                    if ($sent < $length) {
                        $message = substr($message, $sent);
                        $length -= $sent;
                    }else{ break; }
                }
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
    static function genSysChunk($type){
        $chunk["type"]="$type";
        switch($type){
        case "text":
            $chunk=array_merge($chunk, Config::$application["text"]);
            break;

        default:
            return false;
            break;
        }

        return $chunk;
    }
}

#
# GLOBAL FUNCTIONS
#

function read($file){
    return file_get_contents(__DIR__ . "/" . $file);
}

?>
