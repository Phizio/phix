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

$test=new \AlexKonov\EntityList();
$test->search(
    [
        '>id'=>12,
        'or'=>[
            'created_at'=>'1234-12-12 12:12:21',
            '>created_at'=>'1234-12-12 12:12:22',
            '<created_at'=>'1234-12-12 12:12:23',
            '!created_at'=>'1234-12-12 12:12:24',
            'and'=>[
                'name'=>10,
                '!name'=>11,
                'and'=>[
                    'name'=>12,
                    '!name'=>13,
                ],
                'or'=>[
                    'created_at'=>'1234-12-12 12:12:21',
                    '>created_at'=>'1234-12-12 12:12:22',
                    '<created_at'=>'1234-12-12 12:12:23',
                    '!created_at'=>'1234-12-12 12:12:24',
                ]

            ],
        ]

    ],
    []);

/*
$repo = new AlexKonov\Repository($root);
$repo->get(1);

print_r($repo->__toArray());
*/