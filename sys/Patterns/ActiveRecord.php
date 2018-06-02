<?php
/**
 * Active Record pattern
 * @author      PCS Team
 * @copyright   (C) Powerful Community System
 * @package     Powerful Community System
 * @since       2018-05-10
 */

namespace PCS\Patterns;

/**
 * Active Record pattern class
 *
 * @package PCS\Patterns
 */
abstract class ActiveRecord
{
    static $databaseTable;
    static $databaseColumnId = 'id';
    static $databaseColumnPrefix = '';

    protected static $defaultValues = [];

    protected $_data = [];
    protected $_new = FALSE;
    protected $_changes = [];

    /**
     * Returns database to get data from.
     *
     * @return \PCS\Db
     */
    protected static function db()
    {
        return \PCS\Db::i();
    }

    /**
     * ActiveRecord constructor.
     */
    public function __construct()
    {
        $this->_new = TRUE;
        $this->fillDefaultValues();
    }

    /**
     * Load ActiveRecord by ID
     * @param     int                     $id       Record ID
     * @param     null                    $where    Where clause
     * @throws    \OutOfRangeException
     *
     * @return    ActiveRecord
     */
    public static function load($id, $where = NULL)
    {
        $class = get_called_class();
        if ($class == '\PCS\Patterns\ActiveRecord') throw new \BadMethodCallException();
        $multitonsUsed = isset(static::$multitons);
        if ($where != NULL || !($multitonsUsed && isset(static::$multitons[$id]))) {
            try {
                $result = static::db()->select('*', static::$databaseTable, $where ?: ["id=?", $id])->first();
                return static::constructFromRow($result);
            } catch (\UnderflowException $e) {
                throw new \OutOfRangeException;
            }
        }
        if (isset(static::$multitons) && isset(static::$multitons[$id])) {
            $func = \Closure::bind(function ($row) {
                $this->_new = FALSE;
                foreach ($row as $k => $v) {
                    $this->_data[$k] = $v;
                }
                return $this;
            }, new static, $class);
            return $func($row);
        }
    }

    /**
     * Fill default values
     *
     * @return    void
     */
    public function fillDefaultValues()
    {
        foreach (static::$defaultValues as $k => $v) {
            $this->_data[$k] = $v;
        }
    }

    /**
     * Construct record manually
     *
     * @param     array           $row                Row data
     * @param     bool            $updateMultitons    Should multitons be updated (if possible)?
     * @return    ActiveRecord
     */
    public static function constructFromRow($row, $updateMultitons = TRUE)
    {
        $class = get_called_class();
        if ($class == '\PCS\Patterns\ActiveRecord') throw new \BadMethodCallException();
        $prefix = static::$databaseColumnPrefix;
        $usedMultitons = isset(static::$multitons) && $updateMultitons;
        if (($length = mb_strlen($prefix)) > 0) {
            $row = array_combine(array_map(function ($val) use ($length) {
                return mb_substr($val, $length);
            }, array_keys($row)), array_values($row));
        }
        $func = \Closure::bind(function ($row) {
            $this->_new = FALSE;
            foreach ($row as $k => $v) {
                $this->_data[$k] = $v;
            }
            return $this;
        }, new $class, $class);
        if ($usedMultitons) {
            static::$multitons[$row[$prefix . static::$databaseColumnId]] = $row;
        }
        return $func($row);
    }

    /**
     * Column mappings
     *
     * @return    array
     */
    public abstract function mappings();

    /**
     * Magic method: __get
     *
     * @param     string        $key
     * @return    mixed|null
     */
    public function __get($key)
    {
        $mappings = $this->mappings();
        if ($key == static::$databaseColumnId) {
            return $this->_data[static::$databaseColumnId];
        } elseif (method_exists($this, "get_$key")) {
            return $this->{"get_$key"}();
        } elseif (isset($this->_data[$key])) {
            return $this->_data[$key];
        } else {
            return NULL;
        }
    }

    /**
     * Magic method: __set
     *
     * @param    string    $key
     * @param    mixed     $value
     * @return   void
     */
    public function __set($key, $value)
    {
        $mappings = $this->mappings();
        if (method_exists($this, "set_$key")) {
            $existsInDb = isset($mappings[$key]);
            $previous = $existsInDb ? $this->_data[$mappings[$key]] : NULL;
            $this->{"set_$key"}($value);
            if ($existsInDb) {
                if ($previous != $this->_data[$mappings[$key]]) {
                    $this->_changes[$mappings[$key]] = $previous;
                }
            }
        } elseif (isset($mappings[$key])) {
            $previous = (@$this->_data[$mappings[$key]]) ?: NULL;
            if ($previous != $value) {
                $this->_data[$mappings[$key]] = $value;
                $this->_changes[$mappings[$key]] = $previous;
            }
        }
    }

    public function save()
    {
        $insert = [];
        if ($this->_new) {
            foreach ($this->mappings() as $col) {
                if (isset($this->_data[$col])) {
                    $insert[static::$databaseColumnPrefix . $col] = $this->_data[$col];
                }
            }
            static::db()->insert(static::$databaseTable, $insert);
            $this->_data[static::$databaseColumnId] = static::db()->pdo()->lastInsertId();
            $this->_new = FALSE;
            if (isset(static::$multitons)) {
                static::$multitons[$this->id()] = $this->_data;
            }
        } else {
            foreach ($this->_changes as $col => $v) {
                $insert[static::$databaseColumnPrefix . $col] = $this->_data[$col];
            }
            static::db()->update(static::$databaseTable, $insert, [static::$databaseColumnPrefix . static::$databaseColumnId . '=?', $this->id()]);
            if (isset(static::$multitons)) {
                static::$multitons[$this->id()] = array_merge(static::$multitons[$this->id()], $this->_changes);
            }
        }
    }

    /**
     * Get record ID
     *
     * @return    int
     */
    public function id()
    {
        return $this->_data[static::$databaseColumnId];
    }
}