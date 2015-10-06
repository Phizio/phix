<?php

/* ХЕЛПЕРЫ */

function redirect($url) {
    if ($url == 404) $url = $app['404'];
    header($url);
    exit();
}