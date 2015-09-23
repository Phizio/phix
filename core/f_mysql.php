<?php

/* Соединение с базой данных MySQL */

$dbpnt = @mysql_connect($db['host'], $db['username'], $db['password']);
$mysql_err = mysql_error();
if (!$dbpnt) die("Не удалось подключиться к серверу MySQL<br />" . $mysql_err);
mysql_select_db($db['base'], $dbpnt);

/* ФУНКЦИИ ЗАПРОСОВ К БД */

// Получение строки из таблицы (в виде ассоциативного массива)
function db_row ($query, $need_log = false) {
	$ret = mysql_query($query);
	$err = mysql_error();
	if (!empty($err) || $need_log) {
		$query=addslashes(stripslashes(trim($query)));
		$err=addslashes(stripslashes(trim($err)));
		$ret2 = mysql_query("INSERT INTO `log` SET `type`='mysql', `value`='$query\r\n$err'");
	}	
	if (isset($ret) && !empty($ret)) return mysql_fetch_assoc($ret);
	else return false;
}

// Получение массива строк, каждый элемент массива - ассоциативный массив с информацией из одной строки
function db_array ($query, $need_log = false) {
	$ret = mysql_query($query);
	$err = mysql_error();
	$res = Array();
	if (isset($ret) && !empty($ret)) while ($row = mysql_fetch_assoc($ret)) {
		$res[] = $row;
	}
	if (!empty($err) || $need_log) {
		$query=addslashes(stripslashes(trim($query)));
		$err=addslashes(stripslashes(trim($err)));
		$ret2 = mysql_query("INSERT INTO `log` SET `type`='mysql', `value`='$query\r\n$err'");
	}
	return $res;
}

// Получение результата выполнения из MySQL
function db_result ($query, $need_log = false) {
	$ret = mysql_query($query);
	$err = mysql_error();
	if (!empty($err) || $need_log) {
		$query=addslashes(stripslashes(trim($query)));
		$err=addslashes(stripslashes(trim($err)));
		$ret2 = mysql_query("INSERT INTO `log` SET `type`='mysql', `value`='$query\r\n$err'");
	}
	if (isset($ret) && !empty($ret)) {
		$ret2 = @mysql_result($ret, 0);
		return $ret2;
	}
	else return false;
}

// Отправка произвольного запроса в MySQL, возвращает true / false
function db_request ($query, $need_log = false) {
	$ret = mysql_query($query);
	$err = mysql_error();
	if (!empty($err) || $need_log) {
		$query=addslashes(stripslashes(trim($query)));
		$err=addslashes(stripslashes(trim($err)));
		$ret2 = mysql_query("INSERT INTO `log` SET `type`='mysql', `value`='$query\r\n$err'");
	}
	if (isset($ret) && !empty($ret)) return true;
	else return false;
}

// Вставка строки в таблицу, возвращает id вставленной строки
function db_insert ($query, $need_log = false) {
	$ret = mysql_query($query);
	$err = mysql_error();
	$id = mysql_insert_id();
	if (!empty($err) || $need_log) {
		$query=addslashes(stripslashes(trim($query)));
		$err=addslashes(stripslashes(trim($err)));
		$ret2 = mysql_query("INSERT INTO `log` SET `type`='mysql', `value`='$query\r\n$err'");
	}
	if (isset($ret) && !empty($ret)) return $id;
	else return false;
}

// Запись произвольного сообщения в лог. Второй параметр необязателен
function f_log ($msg, $type = 'COMMON') {
    global $self;
    db_request("INSERT INTO `log` SET 
                `type` = '$type', 
                `self` = '$self',
                `value`='$msg'");
}

/* ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ

// Извлечение массива строк:

	$arr = db_array ("SELECT * FROM `vk_projects` WHERE `pj_id`>2");
	foreach ($arr as $row) {
		...
	}

// Принудительный LOG запроса (true вторым параметром):

	$arr = db_result ("SELECT COUNT(*) FROM `vk_projects` WHERE `pj_id`>0", true);
	
*/