<?php
/**
 * Database select class
 * @author      PCS Team
 * @copyright   (C) Powerful Community System
 * @package     Powerful Community System
 * @since       2018-04-24
 */

namespace PCS\Db;

use \PCS\Db\Exception as DbException;

// @todo select joins
class Select implements \Iterator, \Countable, \JsonSerializable
{
    public $query;

    protected $rewound = FALSE;
    protected $position;

    protected $db;
    protected $binds;

    public $num_rows;
    protected $single = FALSE;
    protected $result = [];

    /**
     * Select constructor.
     *
     * @param     string     $query    SQL query
     * @param     array      $binds    Where binds
     * @param     \PCS\Db    $db       Database instance
     */
    public function __construct($query, $binds = [], &$db)
    {
        $this->position = 0;
        $this->db = $db;
        $this->binds = $binds;
        $this->query = $query;
    }

    /**
     * Runs the query and fetches results.
     *
     * @return    void
     */
    public function run()
    {
        try {
            $statement = $this->db->prepareStatement($this->query, $this->binds);
            $statement->execute();
            $this->num_rows = $statement->rowCount();
            $this->result = [];
            if ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $this->single = count($row) == 1 ? TRUE : FALSE;
                $this->result[] = $row;
            }
            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $this->result[] = $row;
            }
            $statement->closeCursor();
        } catch (\PDOException $e) {
            throw new DbException($e->getMessage());
        }
    }

    /**
     * Returns the first element
     *
     * @return    mixed
     */
    public function first()
    {
        if (!$this->rewound) $this->rewind();
        if (!$this->valid()) throw new \UnderflowException;
        return $this->current();
    }

    /**
     * Return the current element
     *
     * @return    mixed
     */
    public function current()
    {
        $val = $this->result[$this->position];
        return ($this->single) ? reset($val) : $val;
    }

    /**
     * Move forward to next element
     *
     * @return    void
     */
    public function next()
    {
        $this->rewound = FALSE;
        $this->position++;
    }

    /**
     * Return the key of the current element
     *
     * @return    int
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * Checks if current position is valid
     *
     * @return    boolean
     */
    public function valid()
    {
        $this->count();
        return array_key_exists($this->position, $this->result);
    }

    /**
     * Rewind the Iterator to the first element
     *
     * @return    void
     */
    public function rewind()
    {
        if (!$this->rewound) $this->run();
        $this->position = -1;
        $this->next();
    }

    /**
     * Count elements of an object
     *
     * @return    int    The custom count as an integer.
     */
    public function count()
    {
        if (!$this->num_rows) {
            $this->run();
        }
        return $this->num_rows;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return    mixed    Data which can be serialized by <b>json_encode</b>
     */
    public function jsonSerialize()
    {
        $this->count();
        return $this->result;
    }
}