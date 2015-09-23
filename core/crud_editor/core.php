<?php
// Массив входных данных (ранее обработаны в scripts/f_secure.php)
$data_in = array_merge($_GET, $_POST);
$act = $_GET['act'];

// Флаги необходимости подключения сторонних скриптов
$yandex_map_need = false;
$dadata_need = false;
$page['error_msg'] = '';

// Дефолтные настройки
if (empty($page['crud_editor']['primary_key'])) $page['crud_editor']['primary_key'] = 'id';

function add_error ($field_name, $error_type) {
	global $page;
	switch ($error_type) {
		case 'empty': $err = "Не заполнено обязательное поле: " . $page['crud_editor']['fields'][$field_name]['desc']; break;
		case 'error': $err = "Проверьте правильность заполнения поля: " . $page['crud_editor']['fields'][$field_name]['desc']; break;
		case 'minlength': $err = "Слишком короткое значение поля '" . $page['crud_editor']['fields'][$field_name]['desc'] . "'! Вы должны ввести не менее " . $page['crud_editor']['fields'][$field_name]['minlength'] . " симв."; break;
		default: $err = $error_type; break;
	}
	$page['error_msg'] .= "$err<br />";
	$page['crud_editor']['fields'][$field_name]['error_msg'] .= "$err<br />";
    f_log($err, 'EDITOR');
}

// Генерация SQL-запроса на создание таблицы (при этом дальнейшее выполнение завершается)
if ($data_in['act'] == 'sql' && $app['mode'] == 'debug') require(PHIX_CORE . '/crud_editor/sql_generator.php');


// Действия
if ($data_in['act'] == 'del') {
	// Удаление элемента
	if ( db_request("DELETE FROM `{$page['crud_editor']['table']}` WHERE `{$page['crud_editor']['primary_key']}`='".$data_in['id']."' LIMIT 1") ) { header("Location: $self?msg=3"); exit; }
	else $page['error_msg'] .= $page['crud_editor']['messages']['delete_error'] . '<br />';
} else if ($data_in['act'] == 'add') {
	// Обработка входных данных согласно свойствам полей
	foreach ($page['crud_editor']['fields'] as $f_name => $f_options) {
		if ($f_options['type'] == 'digit') $data_in[$f_name] = intval ($data_in[$f_name]);
		if (empty($data_in[$f_name])) {
			// Если это обязательное поле - высвечиваем ошибку
			if (!empty($f_options['required'])) add_error ($f_name, 'empty');
			continue;
		}
		// Проверка правильности заполнения определенных типов полей
		switch ($f_options['type']) {
			case 'text': 
				if (!empty($f_options['minlength']) && strlen($data_in[$f_name]) < $f_options['minlength']) add_error ($f_name, 'minlength');
				break;
			case 'email':
				if (!preg_match("|^[-0-9a-z_\.]+@[-0-9a-z_^\.]+\.[a-z]{2,6}$|i", $data_in[$f_name])) add_error ($f_name, 'error');
				break;
			case 'phone':
				if (!preg_match("|^[0-9]{10}$|i", $data_in[$f_name])) add_error ($f_name, 'error');
				break;
			case 'digit':
				if (!empty($f_options['minvalue']) && $data_in[$f_name] < $f_options['minvalue']) add_error ($f_name, 'minvalue');
				if (!empty($f_options['maxvalue']) && $data_in[$f_name] > $f_options['maxvalue']) add_error ($f_name, 'maxvalue');
				// Если указаны варианты, то значение должно быть выбрано из их числа
				if (!empty($f_options['variants']) && !isset ($f_options['variants'][$data_in[$f_name]])) add_error ($f_name, 'error');
				break;
		}
	}
	// При отсутствии ошибок производим запись в БД
	if (empty($page['error_msg'])) {	
		// Часть запроса, отвечающая за сохранение полей
		$fields_query_arr = Array();
		$timestamp_exists = false;
		foreach ($page['crud_editor']['fields'] as $f_name => $f_options) {
			if ($f_options['tag'] == 'list') continue;
			if ($f_options['tag'] == 'checkboxes') {
                if (!is_array($data_in[$f_name]) || !count($data_in[$f_name]) ) $fields_query_arr[] = "`$f_name`=''";
                else $fields_query_arr[] = "`$f_name`='" . implode(',', $data_in[$f_name]) . "'";
                continue;
            }
            if ($f_options['type'] == 'timestamp' && !$timestamp_exists) {$timestamp_exists = true; continue;}
            if ($f_name == $author) continue; // автор записи прописывается отдельно (см. ниже)
			$fields_query_arr[] = "`$f_name`='" . $data_in[$f_name] . "'";
		}

		
		if (empty($data_in['id'])) {
			// Добавление новой записи
            if (!empty($author)) $fields_query_arr[] = "`$author`='$enter_user'";
            if ( db_request("INSERT INTO `{$page['crud_editor']['table']}` SET " . implode(", ", $fields_query_arr)) )
				$header_msg = 1;
				//@action_after_insert();
		} else {
			// Редактирование существующей записи
			if ( db_request("UPDATE `{$page['crud_editor']['table']}` SET " . implode(", ", $fields_query_arr) . " 
							WHERE `{$page['crud_editor']['primary_key']}`='" . $data_in['id'] . "' LIMIT 1") )
				$header_msg = 2;
				//@action_after_edit();
		}
		if (!empty($header_msg)) {header("Location: $self?msg=$header_msg"); exit;}
		else $page['error_msg'] .= $page['crud_editor']['messages']['save_error'] . '<br />';
	}
}
if (!empty($data_in['id'])) {
	// Подгрузка информации о редактируемом элементе
	$el_data = db_row ("SELECT * FROM `{$page['crud_editor']['table']}` WHERE `{$page['crud_editor']['primary_key']}`='".$data_in['id']."' LIMIT 1");
	$data_in = array_merge($data_in, $el_data);
} else if (!empty($data_in['msg'])) {
	// Высвечивание сообщение об успешности предыдущего действия по переадресации на список элементов
	switch ($data_in['msg']) {
		case 1: $page['success_msg'] = $page['crud_editor']['messages']['create_success']; break;
		case 2: $page['success_msg'] = $page['crud_editor']['messages']['save_success']; break;
		case 3: $page['success_msg'] = $page['crud_editor']['messages']['delete_success']; break;
	}
}

// Рендеринг скрипта инициализации datatable
if ($act != "add" && $act != "new" && $act != "edit" && empty($data_in['id'])) {
    // Опция сортировки по нужной колонке
    if ($page['crud_editor']['sort_column']) 
        $datatable_options['order'] = "order: [[" . ($page['crud_editor']['sort_column']-1)
                                    . ", '{$page['crud_editor']['sort_order']}' ]],";
    if ($page['crud_editor']['display_length'])
        $datatable_options['iDisplayLength'] = "iDisplayLength: {$page['crud_editor']['display_length']},";
    $page['js_raw'] .= r('crud_editor/js_raws/datatable.js', $datatable_options);
    // Флаг того, что редактор находится в режиме вывода списка
    $crud_editor_list_mode = true;
} else foreach ($page['crud_editor']['fields'] as $f_name => $f_options) { // Проход по всем заявленным полям
	if ($f_options['tag'] == 'picture') {
        resource([
            'monstrum/jcrop/dist/jquery.Jcrop.css',
            'monstrum/jcrop/dist/jquery.Jcrop.js',
        ]);
        // Выставляем флаг необходимости подключения модалки кадрирования (он будет проверяться в шаблоне layout.php)
        $jcrop_modal_need = true;
        // Инициализация плагина загрузки фото
        $jq_upload_options['f_name'] = $f_name;
        $jq_upload_options['picture_type'] = $f_options['picture_type'];
        $jq_upload_options['picture_element'] = intval($data_in['id']);
        $jq_upload_options['picture_proportions'] = ($f_options['picture_proportions']) ?: 1;
        $page['js_raw'] .= r('crud_editor/js_raws/jquery_upload.js', $jq_upload_options);
    }

	if (!empty($f_options['dadata']['type'])) {
        // Разрешенные типы Dadata
        $dadata_types = ['ADDRESS', 'NAME', 'PARTY', 'BANK', 'EMAIL'];
        // Если указан один из поддерживаемых дадатой типов
        if (array_search($f_options['dadata']['type'], $dadata_types) !== false) {
            // На данной странице будет использован плагин Dadata
            resource([
                'https://dadata.ru/static/css/lib/suggestions-15.7.css',
                'https://dadata.ru/static/js/lib/jquery.suggestions-15.7.min.js',
            ]);
            // Инициализация плагина для текущего поля
            $dadata_options = [
                'f_name' => $f_name,
                'dadata_api_key' => $app['dadata_api_key'],
            ];
            $page['js_raw'] .= r('crud_editor/js_raws/dadata/' . strtolower($f_options['dadata']['type']) . '.js',
                                $dadata_options);
        }
    }
    // На данной странице будет использовано API Яндекс-карт
	if (!empty($f_options['dadata']['yandex_map'])) {
        resource([
            'http://api-maps.yandex.ru/2.0/?load=package.full&lang=ru-RU',
            'crud_editor/yandex_maps.js',
        ], 'js');
        $page['js_raw'] .= r('crud_editor/js_raws/yandex_map.js', [
            'f_name' => $f_name,
            'address' => $data_in[$f_name],
        ]);
    }
}

// Управление типом отображения в зависимости от текущего состояния редактора (список или редактирование)
$page['view'] = ($crud_editor_list_mode) ? 'crud_editor/list_mode' : 'crud_editor/edit_mode';