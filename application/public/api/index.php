<?php

/*                                                 
 * API BACKEND                               
 */

include("../../lib/libmuscari.php");
include("../../lib/libapi.php");

Muscari::init();

$RETURN=array();

$REQ["group"]=$_GET["group"];
$REQ["action"]=$_GET["action"];
$REQ["type"]=$_GET["type"];
$REQ["property"]=$_GET["property"];

$REQ["id"]=$_GET["id"];
$REQ["content"]=$_GET["content"];

$full_request=$REQ["group"].":"
             .$REQ["action"]."-"
             .$REQ["type"];

if($REQ["property"] != ""){
    $full_request.="(".$REQ["property"].")";
}

$SYS_EVENT = array( "event" => "sys", "data" => [] );
$SYS_EVENT_TARGET = -3;  # 0+ User; -1 All users; -2 Mods; -3 Self [Default: Self]
$CONTENT_EVENT = array( "event" => "content", "data" => [] );
$CONTENT_EVENT_TARGET = -1;  # ... [Default: All users]

if(!User::$available){
    die("Error: not a valid user");  # besser machen
}

#
# BEGIN API
#

switch($full_request){
    ### MOD REQUESTS

    # SYS: NEW - PROJECT
case "sys:new-project":
    if(!User::$mod){ MuscariAPI::setError("not_a_mod"); break; }
    $SYS_EVENT_TARGET = -2;
    
    $old_active_id = Database::getFirstValue("SELECT id FROM projects WHERE active=1;");
    # $REQ["content"] = PROJECTNAME°23:59
    $project_name = explode("°", $REQ["content"])[0];
    $project_time = explode("°", $REQ["content"])[1];
    Database::query("UPDATE projects SET active=0;");
    Database::query("INSERT INTO projects SET name='".Database::escape( $project_name )."', active=1, time='".Database::escape( $project_time )."';");
    $new_active_id = Database::getFirstValue("SELECT LAST_INSERT_ID();");
    
    $SYS_EVENT["data"][] = MuscariEvent::genSysChunk("project", $old_active_id);
    $SYS_EVENT["data"][] = MuscariEvent::genSysChunk("project", $new_active_id);
    
    MuscariAPI::setSuccess();
    break;

case "sys:set-project(active)":  # TODO: Still possible to set non-existend project active.
    if(!User::$mod){ MuscariAPI::setError("not_a_mod"); break; }
    $SYS_EVENT_TARGET = -2;
    
    $old_active_id = Database::getFirstValue("SELECT id FROM projects WHERE active=1;");
    Database::query("UPDATE projects SET active=0;");
    Database::query("UPDATE projects SET active=1 WHERE id='".Database::escape( $REQ["id"] )."';");
    
    $SYS_EVENT["data"][] = MuscariEvent::genSysChunk("project", $old_active_id);
    $SYS_EVENT["data"][] = MuscariEvent::genSysChunk("project", $REQ["id"]);
    
    MuscariAPI::setSuccess();
    break;
    

    ### MIXED REQUESTS

case "content:remove-frage":
    $frage_owner=Database::getFirstValue("SELECT user FROM fragen WHERE id='".Database::escape($REQ["id"])."';");
    
    if(!User::$mod and User::$id != $frage_owner){ MuscariAPI::setError("not_a_mod_or_owner"); break; }
    $CONTENT_EVENT_TARGET = -1;
    
    Database::query("DELETE FROM fragen WHERE id='".Database::escape($REQ["id"])."';");
    $CONTENT_EVENT["data"][] = MuscariEvent::genContentChunk("frage", $REQ["id"]);
    
    MuscariAPI::setSuccess();
    break;

    
    ### USER REQUESTS

    # SYS: SET - USER (SESSION)
case "sys:set-user(session)":
    if(!($REQ["content"] != "")){ MuscariAPI::setError("content_missing"); break; }

    $new_session_id = Database::getFirstValue("SELECT session FROM user WHERE session='".Database::escape( $REQ["content"] )."';");

    if($new_session_id != ""){
        session_id($new_session_id);
        session_start();
        
        MuscariAPI::setSuccess();
    }else{
        MuscariAPI::setError("sessionid_not_found");
    }
    break;

    # SYS: SET - USER (NAME)
case "sys:set-user(name)":
    if(!($REQ["content"] != "")){ MuscariAPI::setError("content_missing"); break; }
            
    Database::query("UPDATE user SET name='".Database::escape( $REQ["content"] )."' WHERE id='".Database::escape( User::$id )."';");
    User::init();
    $SYS_EVENT["data"][] = MuscariEvent::genSysChunk("user");

    MuscariAPI::setSuccess();
    break;
    
    # SYS: SET - USER (LEVEL)
case "sys:set-user(level)":
    if(!($REQ["content"] != "")){ MuscariAPI::setError("content_missing"); break; }
            
    Database::query("UPDATE user SET level='".Database::escape( $REQ["content"] )."' WHERE id='".Database::escape( User::$id )."';");
    User::init();
    $SYS_EVENT["data"][] = MuscariEvent::genSysChunk("user");

    MuscariAPI::setSuccess();
    break;
    
    # SYS: SET - USER (OS)
case "sys:set-user(os)":
    if(!($REQ["content"] != "")){ MuscariAPI::setError("content_missing"); break; }
            
    Database::query("UPDATE user SET os='".Database::escape( $REQ["content"] )."' WHERE id='".Database::escape( User::$id )."';");
    User::init();
    $SYS_EVENT["data"][] = MuscariEvent::genSysChunk("user");

    MuscariAPI::setSuccess();
    break;

    # CONTENT: NEW - FRAGE
case "content:new-frage":
    if(!($REQ["content"] != "")){ MuscariAPI::setError("content_missing"); break; }

    Database::query("INSERT INTO fragen SET user='".Database::escape( User::$id )."', project='".Database::escape( Project::$id )."', time='".Database::escape( time() )."', inhalt='".Database::escape( $REQ["content"] )."';");
    $frage_id = Database::getFirstValue("SELECT LAST_INSERT_ID();");

    $CONTENT_EVENT["data"][] = MuscariEvent::genContentChunk("frage", $frage_id);

    MuscariAPI::setSuccess();
    break;
    
default:
    $RETURN["status"]="error";
    $RETURN["description"]="invalid_request";
    break;
}

#
# END API
#

# Send event(s)
if(count($SYS_EVENT["data"]) > 0){
    MuscariSocket::send($SYS_EVENT, $SYS_EVENT_TARGET);
}

if(count($CONTENT_EVENT["data"]) > 0){
    MuscariSocket::send($CONTENT_EVENT, $CONTENT_EVENT_TARGET);
}

# Return Status
echo json_encode($RETURN);

Muscari::close();                                                                                     

?>
