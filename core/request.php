<?php

/* ОБРАБОТКА ВХОДНЫХ ДАННЫХ */

// Получение текущего IP и адреса страницы
$ip = $_SERVER['REMOTE_ADDR'];
$self = substr ($_SERVER['PHP_SELF'], 1);