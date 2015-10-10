<?php
/**
 * Created by PhpStorm.
 * User: SANEK333
 * Date: 10.10.2015
 * Time: 13:25
 */
header('Content-type: text/plain; charset=utf-8');
require_once('c_files.php');

$root=$_SERVER['DOCUMENT_ROOT'] . '/local/files';
$test = new AlexKonov\File($root);
$test->get(125);

$repo=new AlexKonov\Repository($root);
$repo->name='Test name';
$repo->save();
var_dump($repo->addFile($test));
print_r($repo->getErrors());
print_r($repo->__toArray());
