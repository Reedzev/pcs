<?php
/**
 * Database utilities
 * @author      PCS Team
 * @copyright   (C) Powerful Community System
 * @package     Powerful Community System
 * @since       2018-04-23
 */

namespace PCS;

use \PCS\Db\Select as Select;
use \PCS\Db\Exception as DbException;
use \PCS\Text as Text;

/**
 * Database utility class
 *
 * @package PCS
 */
class Db
{
    const PARAM_INT = \PDO::PARAM_INT;
    const PARAM_NULL = \PDO::PARAM_NULL;
    const PARAM_BOOL = \PDO::PARAM_BOOL;
    const PARAM_STR = \PDO::PARAM_STR;
    const PARAM_RAW = 'raw';

    protected static $multitons = [];
    protected static $cachedDefaultHash;
    protected $pdo;
    protected $prefix;
    public $collation = 'utf8mb4_general_ci';
    public $charset = 'utf8mb4';

    /**
     * Get an instance of \PCS\Db
     *
     * @param     array    $settings    Connection settings
     * @code
     *     \PCS\Db::i([
     *         'db' => 'pcs',          // Database name
     *         'host' => 'localhost',  // Database hostname
     *         'user' => 'pcs',        // Database user
     *         'pass' => '',           // Database password
     *         'port' => '3306',       // Database port
     *         'prefix' => '',         // Database prefix
     *     ]);
     * @endcode
     *
     * @return    \PCS\Db               Database instance
     */
    public static function i($settings = NULL)
    {
        $isDefault = FALSE;
        if ($settings == NULL) {
            $isDefault = TRUE;
            if (static::$cachedDefaultHash == NULL) {
                $settings = [
                    'db' => \PCS\Settings::config()['db_name'],
                    'host' => \PCS\Settings::config()['db_host'],
                    'user' => \PCS\Settings::config()['db_user'],
                    'pass' => \PCS\Settings::config()['db_pass'],
                    'port' => \PCS\Settings::config()['db_port'],
                    'prefix' => \PCS\Settings::config()['db_prefix']
                ];
                static::$cachedDefaultHash = md5("{$settings['host']};{$settings['port']};{$settings['db']};{$settings['user']}");
            }
            $hash = static::$cachedDefaultHash;
        } else {
            $hash = md5("{$settings['host']};{$settings['port']};{$settings['db']};{$settings['user']}");
        }
        if (!isset(static::$multitons[$hash])) {
            try {
                static::$multitons[$hash] = new Db($settings);
            } catch (DbException $e) {
                unset(static::$multitons[$hash]);
                if ($isDefault) {
                    static::$cachedDefaultHash = NULL;
                }
                throw $e;
            }
        }
        return static::$multitons[$hash];
    }

    /**
     * Db constructor.
     *
     * @param    array    $settings    Settings
     */
    public function __construct($settings)
    {
        try {
            $this->pdo = new \PDO("mysql:host={$settings['host']};dbname={$settings['db']};port={$settings['db']}", $settings['user'], $settings['pass']);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            throw new DbException("Failed to load PDO: " . $e->getMessage());
        }
        $this->prefix = $settings['prefix'];
    }

    /**
     * Prepares SELECT database clause
     *
     * @param     string|array     $columns    Column(s) to select
     * @param     string           $table      Source table
     * @param     string|array     $where      WHERE clause (optional)
     * @param     string           $order      ORDER BY clause (optional)
     * @param     int              $limit      Data limit (optional)
     * @param     int              $offset     Data offset (optional)
     * @param     int              $group      GROUP BY clause (optional)
     * @return    Select                       Select iterator
     *
     * @see      \PCS\Db\Select
     */
    public function select($columns, $table, $where = NULL, $order = NULL, $limit = NULL, $offset = NULL, $group = NULL)
    {
        $stmt = "SELECT ";
        $pdo = &$this->pdo;
        $bindParams = [];
        $prefix = strlen($this->prefix) ? $this->prefix : NULL;
        // Build $columns to statement
        if (is_array($columns)) $columns = implode(",", $columns);
        $stmt .= $columns;
        $stmt .= $prefix ? " FROM $prefix$table AS $table" : " FROM $table";
        if ($where != NULL) {
            if (is_array($where)) {
                $shift = array_shift($where);
                $stmt .= " WHERE $shift";
                if (count($where)) $bindParams = $where;
            } else {
                $stmt .= " WHERE $where";
            }
        }
        if ($group != NULL) {
            if (is_array($group)) {
                $stmt .= " GROUP BY " . implode(",", $group);
            } else {
                $stmt .= " GROUP BY $group";
            }
        }
        if ($order != NULL) $stmt .= " ORDER BY $order";
        if ($limit != NULL) $stmt .= " LIMIT $limit";
        if ($offset != NULL) $stmt .= " OFFSET $offset";
        return new Select($stmt, $bindParams, $this);
    }

    public function insert($table, $insert)
    {
        $keys = implode(",", array_keys($insert));
        $params = array_values($insert);
        $count = count($insert);
        $sparams = implode(",", array_fill(0, $count, "?"));
        $prepared = $this->prepareStatement("INSERT INTO {$this->prefix}$table ($keys) VALUES($sparams);", $params);
        try {
            $prepared->execute();
        } catch (\PDOException $e) {
            throw new DbException($e->getMessage() . "\nQuery: {$prepared->queryString}");
        }
    }

    public function update($table, $update, $where = NULL)
    {
        $keys = implode(", ", array_map(function ($v) {
            return "$v=?";
        }, array_keys($update)));
        $stmt = "UPDATE {$this->prefix}$table SET $keys";
        $bindParams = [];
        $params = array_values($update);
        if ($where != NULL) {
            if (is_array($where)) {
                $shift = array_shift($where);
                $stmt .= " WHERE $shift";
                if (count($where)) array_push($params, ...$where);
            } else {
                $stmt .= " WHERE $where";
            }
        }
        $prepared = $this->prepareStatement($stmt, $params);
        try {
            $prepared->execute();
        } catch (\PDOException $e) {
            throw new DbException($e->getMessage() . "\nQuery: $stmt");
        }
    }

    /**
     * Delete clause
     *
     * @param    string          $table    Table name
     * @param    array|null      $where    Where clause
     */
    public function delete($table, $where = NULL)
    {
        $stmt = "DELETE FROM {$this->prefix}$table";
        $bindParams = [];
        if ($where != NULL) {
            if (is_array($where)) {
                $shift = array_shift($where);
                $stmt .= " WHERE $shift";
                if (count($where)) $bindParams = $where;
            } else {
                $stmt .= " WHERE $where";
            }
        }
        $prepared = $this->prepareStatement($stmt, $bindParams);
        try {
            $prepared->execute();
        } catch (\PDOException $e) {
            $prepared->closeCursor();
            throw new DbException($e->getMessage() . "\nQuery: $stmt");
        }
    }

    /**
     * Resolves parameter type to bind.
     *
     * @param     mixed $val Value to resolve its type
     * @return    int              Parameter type
     */
    public static function paramType($val)
    {
        if (is_object($val) && $val->type == 'raw') return static::PARAM_RAW;
        if (is_bool($val)) return \PDO::PARAM_BOOL;
        if (is_null($val)) return \PDO::PARAM_NULL;
        if (is_numeric($val) && !is_string($val)) return \PDO::PARAM_INT;
        return \PDO::PARAM_STR;
    }

    public function rawParam($val)
    {
        return new class($val)
        {
            public $type = 'raw';
            public $content;

            public function __construct($val)
            {
                $this->content = $val;
            }

            public function __toString()
            {
                return (string)$this->content;
            }
        };
    }

    /**
     * Prepares statement
     *
     * @param     string $query SQL query
     * @param     array $params Parameters to bind
     * @return    \PDOStatement
     */
    public function prepareStatement($query, $params = [])
    {
        $offset = 0;
        $toBind = [];
        foreach ($params as $k => $v) {
            $type = static::paramType($v);
            $rk = $k - $offset;
            if ($type == static::PARAM_RAW) {
                $query = Text::i()->replaceNth("?", $v, $query, $rk + 1);
                $offset++;
            } else {
                $toBind[$rk] = [$v, $type];
            }
        }
        $statement = $this->pdo->prepare($query);
        foreach ($toBind as $k => $v) {
            $statement->bindValue($k + 1, $v[0], $v[1]);
        }
        return $statement;
    }

    /**
     * Builds column definition
     *
     * @code
     *     \PCS\Db::i()->buildColumnDefinition([
     *         'name'           => 'id',                // Column name; required
     *         'type'           => 'DECIMAL',           // Column type; required
     *         'length'         => '20',                // Column length; may be optional or required depending on data type
     *         'decimals'       => 2,                   // Decimals; may be required or optional depending on data type.
     *         'values'         => [0, 1],              // Array of acceptable values; required for ENUM and SET data type.
     *         'allow_null'     => FALSE,               // Null acceptability; optional
     *         'default'        => 1.25,                // Column default value; optional
     *         'comment'        => 'A comment',         // Column comment; optional
     *         'unsigned'       => true,                // Will specify UNSIGNED for numeric types; optional (default: false)
     *         'zerofill'       => true,                // Will specify ZEROFILL for numeric types; optional (default: false)
     *         'auto_increment' => true,                // Will specify AUTO_INCREMENT; optional (default: false)
     *         'binary'         => true,                // Will specify BINARY for TEXT data types; optional (default: false)
     *         'primary'        => true,                // Will specify PRIMARY KEY; optional (default: false)
     *         'unqiue'         => true,                // Will specify UNIQUE; optional (default: false)
     *         'key'            => true,                // Will specify KEY; optional (default: false)
     *      ]);
     * @endcode
     * @param    array     $data    Column data
     * @return   string
     */
    public function buildColumnDefinition($data)
    {
        $def = "`{$data['name']}` " . mb_strtoupper($data['type']) . ' ';

        if (
            in_array(mb_strtoupper($data['type']), ['VARCHAR', 'VARBINARY'])
            or
            (
                isset($data['length']) and $data['length']
                and
                in_array(mb_strtoupper($data['type']), ['BIT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT', 'REAL', 'DOUBLE', 'FLOAT', 'DECIMAL', 'NUMERIC', 'CHAR', 'BINARY'])
            )
        ) {
            $def .= "({$data['length']}";

            if (in_array(mb_strtoupper($data['type']), ['REAL', 'DOUBLE', 'FLOAT']) or (in_array(mb_strtoupper($data['type']), ['DECIMAL', 'NUMERIC']) and isset($data['decimals']))) {
                $def .= ',' . $data['decimals'];
            }

            $def .= ') ';
        }

        if (in_array(mb_strtoupper($data['type']), ['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT', 'REAL', 'DOUBLE', 'FLOAT', 'DECIMAL', 'NUMERIC'])) {
            if (isset($data['unsigned']) and $data['unsigned'] === TRUE) {
                $def .= 'UNSIGNED ';
            }
            if (isset($data['zerofill']) and $data['zerofill'] === TRUE) {
                $def .= 'ZEROFILL ';
            }
        }

        if (in_array(mb_strtoupper($data['type']), ['ENUM', 'SET'])) {
            $values = [];
            foreach ($data['values'] as $v) {
                $values[] = "'{$this->pdo->quote( $v )}'";
            }

            $def .= '(' . implode(',', $values) . ') ';
        }

        if (isset($data['binary']) and $data['binary'] === TRUE and in_array(mb_strtoupper($data['type']), ['CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT'])) {
            $def .= 'BINARY ';
        }

        if (in_array(mb_strtoupper($data['type']), ['CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'ENUM', 'SET'])) {
            $def .= "CHARACTER SET '{$this->charset}' COLLATE '{$this->collation}' ";
        }

        if (isset($data['allow_null']) and $data['allow_null'] === FALSE) $def .= 'NOT NULL '; else $def .= 'NULL ';

        if (isset($data['auto_increment']) and $data['auto_increment'] === TRUE) {
            $def .= 'AUTO_INCREMENT ';
        } else {
            /* Default value */
            if (isset($data['default']) and !in_array(mb_strtoupper($data['type']), ['TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'BLOB', 'MEDIUMBLOB', 'BIGBLOB', 'LONGBLOB'])) {
                if ($data['type'] == 'BIT') {
                    $def .= "DEFAULT {$data['default']} ";
                } else {
                    $defaultValue = in_array(mb_strtoupper($data['type']), ['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT', 'REAL', 'DOUBLE', 'FLOAT', 'DECIMAL', 'NUMERIC']) ? floatval($data['default']) : (!in_array($data['default'], ['CURRENT_TIMESTAMP', 'BIT']) ? '\'' . $this->pdo->quote($data['default']) . '\'' : $data['default']);
                    $def .= "DEFAULT {$defaultValue} ";
                }
            }
        }

        if (isset($data['primary'])) $def .= 'PRIMARY KEY ';
        elseif (isset($data['unique'])) $def .= 'UNIQUE ';
        if (isset($data['key'])) $def .= 'KEY ';

        if (isset($data['comment']) and !empty($data['comment'])) $def .= "COMMENT '{$this->pdo->quote( $data['comment'] )}'";

        return $def;
    }

    /**
     * Build index definition
     *
     * @code
     *     \PCS\Db::i()->buildIndexDefinition([
     *         'type'        => 'key',               // Column type ("primary", "key", "unique" or "fulltext")
     *         'name'        => 'index_name',        // Column name; optional in "primary" type
     *         'length'      => 200,                 // Index length (used when taking part of a text field, for example)
     *         'columns'     => ['column']           // Columns to be in the index
     *     ]);
     * @endcode
     * @param      array $data Index data
     * @return    string
     */
    public function buildIndexDefinition($data)
    {
        $def = (!in_array(strtolower($data['type']), ['primary', 'unique', 'fulltext']) == 'key' ? 'KEY ' : strtoupper($data['name']) . ' KEY ') . $data['name'];
        $def .= '(' . implode(array_map(function ($val) {
                return "`$val`";
            }, $data['columns'])) . ')';
        return $def;
    }

    /**
     * Build table query
     *
     * @param     array $data
     * @return    string
     *
     * @see Db::createTable
     */
    private function buildTableQuery($data)
    {
        $query = 'CREATE ';
        if (isset($data['temporary']) and $data['temporary'] === TRUE) {
            $query .= 'TEMPORARY ';
        }
        $query .= 'TABLE ';
        if (isset($data['if_not_exists']) and $data['if_not_exists'] === TRUE) {
            $query .= 'IF NOT EXISTS ';
        }

        $query .= "`{$this->prefix}{$data['name']}` (\n\t";
        $definitions = [];
        foreach ($data['columns'] as $col) {
            $definitions[] = $this->buildColumnDefinition($col);
        }
        if (isset($data['indexes'])) {
            foreach ($data['indexes'] as $index) {
                $definitions[] = $this->buildIndexDefinition($index);
            }
        }
        $query .= implode(",\n\t", $definitions);
        $query .= "\n)\n";
        $query .= "CHARACTER SET '{$this->charset}' COLLATE '{$this->collation}' ";
        if (isset($data['comment'])) {
            $query .= "COMMENT '{$this->pdo->quote($data['comment'])}'";
        }

        return $query;
    }

    /**
     * Create new table
     *
     * @code
     *     \PCS\Db::i()->createTable([
     *         'name'          => 'small_table',    // Table name; required
     *         'temporary'     => true,             // Should the table be temporary; optional (default: false)
     *         'if_not_exists' => true,             // Should the table be created only if not yet exists; optional (default: false)
     *         'columns'       => [                 // Column definitions; at least one element required.
     *             [
     *                 'name' => 'some_text',
     *                 'type' => 'TEXT'
     *             ],
     *             ...
     *         ],
     *         'indexes'       => [ ... ]           // Index definitions; optional
     * @endcode
     * @param    array    $data
     * @return   void
     *
     * @see Db::buildColumnDefinition
     * @see Db::buildIndexDefinition
     */
    public function createTable($data)
    {
        $query = $this->buildTableQuery($data);
        try {
            $this->pdo->exec($query);
        } catch (\PDOException $e) {
            throw new DbException($e->getMessage() . "\nQuery: $query");
        }
    }

    /**
     * Get PDO instance
     *
     * @return \PDO
     */
    public function pdo()
    {
        return $this->pdo;
    }
}