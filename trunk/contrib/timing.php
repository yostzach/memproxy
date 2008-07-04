<?php

function timing_start() {
    $GLOBALS["_TIMING"]["start"] = microtime();
}

function timing_mark($mark) {
    $GLOBALS["_TIMING"][$mark] = microtime();
}

function timing_stop() {
    unset($GLOBALS["_TIMING"]["stop"]);
    timing_mark($key, "stop");
}

function timing_print() {
    $buf ='<table border="1" cellspacing="0" cellpadding="2">';
    $buf.="<tr><th>Mark</th><th>Time</th><th>Elapsed</th></tr>";
    foreach($GLOBALS["_TIMING"] as $mark => $mt) {
        $thistime = array_sum(explode(" ", $mt));
        if (isset($lasttime)) {
            $elapsed = round($thistime - $start, 4);
            $curr = round($thistime - $lasttime, 4);
            $buf.="<tr><td>$mark</td><td>$curr sec.</td><td>$elapsed sec.</td></tr>";
        } else {
            $start = $thistime;
        }
        $lasttime = $thistime;
    }
    $buf.="</table>";
    echo $buf;
}

?>