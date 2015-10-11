<?php
/**
 * Created by PhpStorm.
 * User: SANEK333
 * Date: 10.10.2015
 * Time: 13:25
 */
header('Content-type: text/plain; charset=utf-8');
require_once('c_files.php');

$root = $_SERVER['DOCUMENT_ROOT'] . '/local/files';

$test=new \AlexKonov\Repositories($root);
$list=$test->search(['>=id'=>1], ['start'=>0, 'limit'=>5]);
foreach ($list as $v)
    print_r($v->__toArray());

/*
$repo = new AlexKonov\Repository($root);
$repo->get(1);

print_r($repo->__toArray());
*/