<?php
/**
 * Created by PhpStorm.
 * User: SANEK333
 * Date: 10.10.2015
 * Time: 13:25
 */
header('Content-type: text/plain; charset=utf-8');
require_once('c_files.php');


$test = new AlexKonov\File($_SERVER['DOCUMENT_ROOT'] . '/local/files');
$test->get(125);

print_r($test->__toArray());
