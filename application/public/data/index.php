<?php

/*
 * DATA EVENT STREAM
 */

include("../../lib/libmuscari.php");
include("../../lib/libdata.php");

Muscari::init("eventstream");

Eventstream::sendEvent(MuscariEvent::getCompleteSysEvent());
Eventstream::sendEvent(MuscariEvent::getCompleteContentEvent());
MuscariSocket::listen("SocketMessage2Eventstream");

Muscari::close();

?>
