<?php

/* Регистрация, логин и сессии авторизации на сайте */

// Функция проверки авторизации (возвращает роль пользователя, либо true - если роль не определена)
function auth() {
    global $user;
    if (empty($user['id'])) return false;
    return ($user['role']) ?: true;
}

// Функция поиска аккаунта, соответствующего паре [логин-пароль]
function search_account($acc_login, $acc_pass) {
    global $user, $page, $log;
    $acc_pass_hash = sha1($acc_pass);
    $log .= "search_account($acc_login, $acc_pass) sha1 -> $acc_pass_hash<br />";
    $user = db_row("SELECT * FROM `users` WHERE
                    (`login` ='$acc_login' AND `pass` ='$acc_pass_hash') OR
                    (`email` ='$acc_login' AND `pass` ='$acc_pass_hash')");
    if (!empty($user['id'])) {
        if (empty($user['activate_code'])) return true;
        else $page['error_msg'] = "К сожалению, Ваш аккаунт еще не активирован!<br />"
            . "Пожалуйста, проверьте почту и активируйте Ваш аккаунт, "
            . "перейдя по ссылке, которую мы прислали Вам в письме";
    }
    $user = array();
    return false;
}

$user = []; // Массив, содержащий инфо о текущем пользователе

$login = $_POST['login'];
$pass = $_POST['pass'];
$act = $_GET['act']; // Действие

$register = intval($_POST['register']); // флаг данных из формы регистрации
$passchange = intval($_POST['passchange']); // флаг смены пароля

// Данные для редактирования аккаунта
$edit = intval($_POST['edit']); // флаг редактирования
$user_id = intval($_POST['user_id']);
if (empty($user_id)) $user_id = intval($_GET['user_id']);
$user_activate = $_POST['user_activate'];
if (empty($user_activate)) $user_activate = $_GET['user_activate'];
$user_login = $_POST['user_login'];
$user_pass = $_POST['user_pass'];
$user_email = $_POST['user_email'];
$user_name = $_POST['user_name'];
$user_phone = $_POST['user_phone'];
$user_phone = preg_replace('/[^\d]+/', '', $user_phone);
// Хеш запоминания юзера (галочка Запомнить меня при авторизации
$auth_hash = $_COOKIE['auth_hash'];

$log ="";
$ses_log = "";
$ses_pass = "";
$ses_ip = "";

// Запуск сессии
session_start();
$ses_user_id = $_SESSION['user_id'];
$ses_ip = $_SESSION['ip'];
$log.="ses_user_id = $ses_user_id , ses_admin_id  = $ses_admin_id , ses_ip = $ses_ip<br />";


if (!empty($ses_user_id)) { // в сессии присутствует id пользователя
    $log.="в сессии присутствует логин<br />";
    if ($ses_ip == $ip && $act != "quit") { // ip не изменился, попытка выхода не предпринималась
        // ищем в БД соответствующий аккаунт
        if (!empty($ses_user_id)) $user = db_row("SELECT * FROM `users` WHERE `id` = '$ses_user_id'");
    } else {
        $log.="сессия разорвана (ip или выход)<br />";
        session_destroy();
        setcookie('auth_hash', '', -1);
        //header("Location: login.php");
    }
} else if (!empty($auth_hash)) {
    $log .= "есть кукис-запись с предыдущей авторизации 'Запомнить меня' $auth_hash<br />";
    $user = db_row("SELECT * FROM `users` WHERE `auth_hash` = '$auth_hash'");
    if (!empty($user['id'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['ip'] = $ip;
    }
}

if (!empty($user_id) && !empty($user_activate)) {
    $user_info = db_row("SELECT * FROM `users` WHERE `id` = '$user_id'");
    if (!empty($user_info['id'])) {
        if ($user_activate == $user_info['activate_code'] && !empty($user_info['activate_code'])) {
            if ( db_request("UPDATE `users` SET `activate_code` = 0 WHERE `id` = '$user_id'") )
                $page['success_msg'] = "Ваш личный кабинет успешно активирован!";
            else $page['error_msg'] = "Произошла ошибка активации";
        } else if (empty($user_info['activate_code'])) {
            $page['error_msg'] = "Данный аккаунт уже активирован Вами ранее";
        } else $page['error_msg'] = "Ошибка! Неверный код активации";
    } else $page['error_msg'] = "Ошибка! Не найден пользователь с таким id";
} else if ($act == "register") {
    $log.= "поступили данные для регистрации нового аккаунта<br />";
    /* Проверка на заполнение всех обязательных полей: ненужное закоментировано */
    if ($_POST['agree'] != 'on') $page['error_msg'] .= "Необходимо ознакомиться с правилами Сайта и поставить галочку!<br />";
    if (empty($user_name)) $page['error_msg'] .= "Не указано имя!<br />";
    if (!empty($user_phone) && !preg_match("|^[0-9]{10}$|i", $user_phone))
        $page['error_msg'] .= "Ошибка в номере телефона! Необходимо ввести 10 цифр без пробелов.<br />";
    if (!preg_match("|^[-0-9a-z_\.]+@[-0-9a-z_^\.]+\.[a-z]{2,6}$|i", $user_email))
        $page['error_msg'] .= "Не указан или неверно указан E-mail<br />";
    else {
        // проверяем, не было ли уже зарегистрировано такого e-mail:
        $user_email_search = db_result("SELECT COUNT(*) FROM `users` WHERE `email` = '$user_email'");
        if (!empty($user_email_search))
            $page['error_msg'] .= "Данный E-mail уже был использован для регистрации на нашем сайте!<br />"
                                . "Если Вы забыли пароль, пожалуйста, воспользуйтесь формой восстановления пароля";
    }
    $user_login = $user_email; // Если в качестве логина выступает E-mail
    if (empty($page['error_msg'])) {
        // Генерация случайного хэша
        $user_activate = sprintf( '%04x', rand(0, 65536)) . sprintf( '%04x', rand(0, 65536));
        // Генерация случайного пароля
        $user_pass = sprintf( '%04x', rand(0, 65536)) . sprintf( '%04x', rand(0, 65536));
        $user_pass_hash = sha1($user_pass);
        // Сохраняем юзера
        $user_id = db_insert("INSERT INTO `users` SET
                                    `login` = '$user_login',
                                    `pass` = '$user_pass_hash',
                                    `email` = '$user_email',
                                    `name` = '$user_name',
                                    `phone` = '$user_phone',
                                    `activate_code` = '$user_activate'");
        mail(
            $user_email,
            "Регистрация {$app['name']}",
            r('emails/register.html', [
                'domain'        => $app['domain'],
                'user_id'       => $user_id,
                'user_activate' => $user_activate,
                'user_pass'     => $user_pass
            ]),
            "From: {$app['name']}<{$app['email']}>\r\nContent-type: text/html; charset=utf-8\r\n"
        );
        $page['success_msg'] .= "<strong>Регистрация прошла успешно!</strong>"
                            .   "На Ваш E-mail отправлено письмо cо ссылкой для активации личного кабинета";
    }
} else if ($act == "recovery") {
    $log.= "режим восстановления пароля<br />";
    if (!empty($user_id)) {
        // Получили данные для смены пароля
        $user = db_row("SELECT * FROM `users` WHERE `id` = '$user_id'");
        if (empty($user['activate_code']) || empty($user_activate) || $user_activate != $user['activate_code']) {
            if (!empty($user_pass)) {
                $user_pass_hash = sha1($user_pass);
                if ( db_request("UPDATE `users` SET `pass` = '$user_pass_hash', `activate` = 0
                                WHERE `id` = '{$user['user_id']}'") )
                    $page['success_msg'] .= "<strong>Пароль успешно изменен!</strong>"
                                        .   "Вы можете войти на сайт с новым паролем";
            } else $page['error_msg'] = "Пожалуйста, введите пароль!";
        } else $page['error_msg'] .= "Ошибка кода активации!"
                                    ."Проверьте корректность вставки ссылки из E-mail с инструкцией по восстановлению!";
    } else if (!preg_match("|^[-0-9a-z_\.]+@[-0-9a-z_^\.]+\.[a-z]{2,6}$|i", $user_email)) {
        $page['error_msg'] .= "Не указан или неверно указан E-mail<br />";
    } else {
        // проверяем, не было ли уже зарегистрировано такого e-mail:
        $user_email_search = db_result("SELECT COUNT(*) FROM `users` WHERE `email` = '$user_email'");
        if (empty($user_email_search)) {
            // Генерация случайного хэша
            $user_activate = sprintf( '%04x', rand(0, 65536)) . sprintf( '%04x', rand(0, 65536));
            if ( db_result("UPDATE `users` SET `activate_code` = '$user_activate'
                            WHERE `id`='{$user['user_id']}'") ) {
                mail(
                    $user_email,
                    "Восстановление доступа к {$app['name']}",
                    r('emails/recovery.html', [
                        'domain'        => $app['domain'],
                        'user_id'       => $user_id,
                        'user_activate' => $user_activate,
                        'user_pass'     => $user_pass
                    ]),
                    "From: {$app['name']}<{$app['email']}>\r\nContent-type: text/html; charset=utf-8\r\n"
                );
                $page['success_msg'] .= "<strong>Письмо успешно отправлено на $user_email</strong><br />"
                                    .   "Проверьте свой E-mail и следуйте инструкциям для восстановления пароля";
            }
        } else $page['error_msg'] .= "К сожалению, мы не можем найти ни одной учетной записи, связанной с данным E-mail!";
    }
} else if ($act == "edit") {
    $log.= "Режим редактирования данных аккаунта<br />";
    if (!empty($user['id'])) {
        if (!empty($passchange)) {
            // Для смены пароля нужно ввести старый пароль. Проверяем его правильность
            if ($pass == $user['user_pass']) {
                if (!empty($user_pass)) {
                    $user_pass_hash = sha1($user_pass);
                    if ( db_request("UPDATE `users` SET `pass` = '$user_pass_hash'
                                    WHERE `id` = '{$user['id']}'") ) 
                        $page['success_msg'] = "Пароль успешно изменен!";
                    else $page['error_msg'] .= "Произошла ошибка при сохранении нового пароля!";
                } else $page['error_msg'] .= "Не был введен новый пароль!";
            } else $page['error_msg'] .= "Старый пароль введен неверно!";
        } else {
            if (empty($user_name)) $page['error_msg'] .= "Не указано имя!<br />";
            if (empty($user_login)) $page['error_msg'] .= "Не указан логин!<br />";
            else {
                $user_login_search = db_result("SELECT COUNT(*) FROM `users`
                                                WHERE `login` = '$user_login' AND `id` <> '{$user['id']}'");
                if ($user_login_search) $page['error_msg'] .= "Данный логин уже занят!<br />";
            }
            if (!preg_match("|^[-0-9a-z_\.]+@[-0-9a-z_^\.]+\.[a-z]{2,6}$|i", $user_email))
                $page['error_msg'] .= "Не указан или неверно указан E-mail<br />";
            if (!empty($user_phone) && !preg_match("|^[0-9]{10}$|i", $user_phone)) 
                $page['error_msg'].="Ошибка в номере телефона! Необходимо ввести 10 цифр без пробелов.<br />";
            if (empty($page['error_msg'])) {
                if ( db_request("UPDATE `users` SET 
                                `name` = '$user_name',
                                `login` = '$user_login',
                                `email` = '$user_email',
                                `phone` = '$user_phone' 
                                WHERE `id` = '{$user['id']}'") )
                    $page['success_msg'] = "Информация успешно отредактирована!";
                else $page['error_msg'] .= "При сохранении информации произошла ошибка!";
            }
        }
        // Перезапрашиваем заново из базы результат
        $user = db_row("SELECT * FROM `users` WHERE `id` = '{$user['id']}'");
    } else $page['error_msg'] .= "Редактирование данных аккаунта отклонено, т.к. отсутствует авторизация";
} else if (!empty($login) || !empty($pass)) {
    $log.= "Режим авторизации<br />";
    if (empty($login) && !empty($pass)) $page['error_msg'] = "Пожалуйста, введите логин!";
    else if (!empty($login) && empty($pass)) $page['error_msg'] = "Пожалуйста, введите пароль!";
    if (empty($page['error_msg']) && search_account($login, $pass)) {
        $log.="соответствие найдено<br />";
        if (!empty($user['id'])) {
            $_SESSION['user_id'] = $user['id'];
            //Запоминание юзера в случае установленной опции 'Запомнить меня'
            if ($_POST['remember'] == "on") {
                if (!empty($user['auth_hash'])) $auth_hash = $user['auth_hash'];
                else $auth_hash = sprintf( '%04x', rand(0, 65536)) . sprintf( '%04x', rand(0, 65536)) . sprintf( '%04x', rand(0, 65536)) . sprintf( '%04x', rand(0, 65536));
                setcookie('auth_hash', $auth_hash, time()+2592000, '/', false);
                db_request("UPDATE `users` SET `auth_hash` = '$auth_hash' WHERE `id` ='{$user['id']}'");
            }
        }
        $_SESSION['ip'] = $ip;
    } else if (empty($page['error_msg']))
        $page['error_msg'] .= "К сожалению, авторизация не удалась. "
                            . "Проверьте правильность введенного $login, $pass логина и пароля!<br />";
} else $log.= "Сессия пуста, не предпринимается никаких действий<br />";

?>
