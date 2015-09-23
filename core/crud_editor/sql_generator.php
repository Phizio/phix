<?php

/* ГЕНЕРАЦИЯ SQL-ЗАПРОСА НА ОСНОВЕ ТЕКУЩЕЙ КОНФИГУРАЦИИ CRUD */

$fields_query_arr = Array();
$fields_query_arr[] = "`{$page['crud_editor']['table']}` int(11) NOT NULL AUTO_INCREMENT";
$timestamp_exists = false;
foreach ($page['crud_editor']['fields'] as $f_name => $f_options) {
    $f_tag = '';
    if (!empty($f_options['maxlength']) && $f_options['maxlength'] < 256) $maxlength = $f_options['maxlength'];
    else $maxlength = 255;
    switch ($f_options['type']) {
        case 'text':
        case 'hidden':
            if ($f_options['tag'] == 'textarea') $f_tag = 'text';
            else $f_tag = "varchar($maxlength)";
            break;
        case 'digit':
            if (!empty($f_options['variants']) && count($f_options['variants']) == 2) $f_tag = 'int(1)';
            else $f_tag = 'int(11)';
            break;
        case 'email':
        case 'phone': $f_tag = 'varchar(100)'; break;
        case 'timestamp': $f_tag = 'timestamp'; break;
    }
    if (empty($f_tag)) continue;
    $fields_query_el = "`$f_name` $f_tag NOT NULL";
    if ($f_options['type'] == 'timestamp') {
        if (!$timestamp_exists) { $fields_query_el .= " DEFAULT CURRENT_TIMESTAMP"; $timestamp_exists = true; }
        else $fields_query_el .= " DEFAULT '0000-00-00 00:00:00'";
    }
    $fields_query_arr[] = $fields_query_el;
}
$fields_query_arr[] = "PRIMARY KEY (`{$page['crud_editor']['table']}`)";
echo "<textarea style=\"width:100%\" rows=\"50\">CREATE TABLE IF NOT EXISTS `{$page['crud_editor']['table']}` (\r\n";
echo implode (",\r\n", $fields_query_arr);
echo "\r\n) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1</textarea>";
exit();