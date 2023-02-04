<?php

/*
 * DATA EVENT STREAM
 */

include("../../lib/libmuscari.php");
include("../../lib/libdata.php");

Muscari::init("eventstream");

Eventstream::sendEvent(Eventstream::collectSys());
MuscariSocket::listen("SocketMessage2Eventstream");

Muscari::close();

?>
