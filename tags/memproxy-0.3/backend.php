<?php

/**
 * Backend server configuration
 *
 * The key of the first array is the host name, in lower case
 * that will be sent in $_SERVER["HTTP_HOST"].  That key points
 * to an array of servers with ports.
 *
 * TODO: add weighting
 *
 */

$backend_array = array(

    "brian.phorum.org" =>
        array(
            array("brian.phorum.org", 80),
            array("brian.phorum.org", 8081),
            array("brian.phorum.org", 8082),
        ),

);

?>
