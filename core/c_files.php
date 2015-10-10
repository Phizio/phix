<?php
/**
 * Created by PhpStorm.
 * User: SANEK333
 * Date: 10.10.2015
 * Time: 11:51
 */
namespace AlexKonov;

class Exception extends \Exception
{
}

//todo: удалить
$db['host'] = 'localhost';
$db['username'] = 'root';
$db['password'] = '';
$db['base'] = 'nalog';
require_once('f_mysql.php');


//фильтрация данных
class Esc
{

    static public function sql($value, $type = 'string')
    {
        if ($type == 'string')
            $value = self::html($value);
        elseif ($type == 'int')
            $value = intval($value);
        elseif ($type == 'float')
            $value = floatval($value);
        elseif ($type == 'bool')
            $value = (int)!!$value;
        elseif ($type == 'json')
            $value = json_encode($value);
        elseif ($type == 'timestamp') {
            if (preg_match('/^\d+$/s', $value))
                $value = date('Y-m-d H:i:s');
            elseif (!preg_match('/^\d{4}\-\d{2}\-\d{2}\ \d{2}\:\d{2}\:\d{2}$/s', $value))
                $value = date('Y:m:d H:i:s');
        } else
            $value = self::html($value);
        return mysql_real_escape_string($value);
    }

    static public function html($str)
    {
        return htmlspecialchars((string)$str);
    }
}

//стандартные свойства и методы сущности
class Entity
{
    private
        $_fields = null;
    protected
        $_table,
        $_types = [];
    public
        $id = 0,
        $created_at = '0000-00-00 00:00:00',
        $updated_at = '0000-00-00 00:00:00',
        $deleted_at = '0000-00-00 00:00:00';

    public function __construct()
    {
        $this->_setTypes();
    }

    //указываем типы данных для фильтрации
    protected function _setTypes()
    {
        $this->_types['id'] = 'int';
        $this->_types['user_id'] = 'int';
        $this->_types['created_at'] = 'timestamp';
        $this->_types['updated_at'] = 'timestamp';
        $this->_types['deleted_at'] = 'timestamp';
    }

    //загрузить запись в объект;
    //по умолчанию грузятся только не удаленные записи
    //если надо удаленные, то $all=true
    public function get($id, $all = false)
    {
        $result = db_row("SELECT * FROM `$this->_table` WHERE `id`=" . esc::sql($id, 'int') .
            (!$all ? (" and `deleted_at`='0000-00-00 00:00:00'") : ''));
        if ($result)
            return $this->setFieldsFromArray($result);
        return false;
    }

    //сохраняем запись в бд, если записи с id нет, создаётся, иначе редактируется
    //$types - типы данных для фильтрации
    public function save($types = null)
    {
        if (is_array($types)) {
            foreach ($this->types as $field => $type)
                if (!isset($types[$field]))
                    $types[$field] = $type;
        } else
            $types = $this->_types;
        $values = $this->__toArray();
        $values['id'] = abs(intval($values['id']));
        $data = [];
        $create = true;
        if ($values['id'] > 0) {
            if (db_result("SELECT COUNT(*) FROM ` $this->_table` WHERE `id`=" . Esc::sql($values['id'], 'int')) > 0)
                $create = false;
        }
        if ($create) {
            if ($values['id'] > 0)
                $data[] = "`id`=" . Esc::sql($values['id'], 'int');
            if ($values['created_at'] == '0000-00-00 00:00:00')
                $values['created_at'] = date('Y:m:d H:i:s');
        } else {
            if ($values['updated_at'] == '0000-00-00 00:00:00')
                $values['updated_at'] = date('Y:m:d H:i:s');
        }
        foreach ($values as $k => $v)
            if ($k != 'id')
                $data[] = "`$k` = '" . Esc::sql($v, (isset($types[$k]) ? $types[$k] : null)) . "'";
        if ($create) {
            $result = db_insert("INSERT INTO `$this->_table` SET " . implode(', ', $data));
            if (!$result)
                return false;
        } else {
            $result = db_request("UPDATE `$this->_table` SET " . implode(', ', $data) . " WHERE `id`=" . Esc::sql($values['id'], 'int'));
            if (!$result)
                return false;
            $result = $values['id'];
        }
        return $this->get($result);
    }

    //"мягко" удаляем запись
    public function remove()
    {
        if ($this->deleted_at=='0000-00-00 00:00:00')
        {
            $this->deleted_at=date('Y-m-d H:i:s');
            $this->save();
        }
        return $this;
    }

    //восстанавливаем запись
    public function restore()
    {
        if ($this->deleted_at!='0000-00-00 00:00:00')
        {
            $this->deleted_at='0000-00-00 00:00:00';
            $this->save();
        }
        return $this;
    }

    //заполняем объект из массива
    public function setFieldsFromArray($data)
    {
        $fields = $this->__toArray();
        foreach ($fields as $k => $v)
            if (isset($data[$k]))
                $this->$k = $data[$k];
        return $this;
    }

    public function __toArray()
    {
        $this->_setFields();
        $result = [];
        foreach ($this->_fields as $k => $v)
            $result[$k] = $this->$k;
        return $result;
    }

    public function __set($name, $value)
    {
        $fields = $this->__toArray();
        if (isset($fields[$name]))
            $this->$name = $value;
        elseif (!property_exists($this, $name)) {
            $this->$name = $value;
            $this->_setFields($name);
        }

    }

    public function __toString()
    {
        return json_encode($this->__toArray());
    }

    //определяем свойства класса, являющиеся полями сущности
    protected function _setFields($field = null)
    {
        if (!$this->_fields) {
            $this->_fields = [];
            preg_match_all('/\[([a-z_][a-z0-9_]*)\]/si', print_r($this, true), $list);
            $this->_fields = [];
            foreach ($list[1] as $v)
                $this->_fields[$v] = null;
        }
        if ($field)
            $this->_fields[$field] = null;
        return $this->_fields;
    }


}

//класс для файла
class File extends Entity
{
    private
        $_root;
    protected
        $_table = 'files';
    public
        $user_id = 0,
        $reason = '',
        $type = '',
        $name = '',
        $description = '',
        $size = '',
        $path = false,
        $data = [];

    public function __construct($root, $id = 0)
    {
        parent::__construct();
        $this->_root = $root;
        if ($id > 0)
            $this->get($id);
    }


    public function save()
    {
        $this->path((string) $this->path);
        if (!strlen($this->path))
            throw new Exception('Path is emppty.');
        if (!file_exists($this->_root.'/'.$this->path))
            throw new Exception('File "'.$this->path.'" not found.');
        if (!is_file($this->_root.'/'.$this->path))
            throw new Exception('Path "'.$this->path.'" is not file.');

        return parent::save();
    }

    protected function _setTypes()
    {
        parent::_setTypes();
        $this->_types['reason'] = 'string';
        $this->_types['type'] = 'string';
        $this->_types['name'] = 'string';
        $this->_types['description'] = 'string';
        $this->_types['path'] = 'string';
        $this->_types['size'] = 'int';
        $this->_types['data'] = 'json';

    }

    public function path($path) {
        $path=preg_replace('/\.{1,}/','.',$path);
        $path=preg_replace('/\\{1,}/','/',$path);
        $path=preg_replace('/\\{1,}/','\\',$path);
        return $path;
    }
}

class Files
{


}
