<?php
/**
 * A convenience method to set up a column definitions array
 */

namespace DataTables\Lib;

use Traversable;

class ColumnDefinitions implements \JsonSerializable, \ArrayAccess, \IteratorAggregate, \Countable
{
    protected $columns = [];
    protected $index = [];

    /**
     * @param $column string|array name or pre-filled array
     * @param $fieldname: ORM field this column is based on
     * @return ColumnDefinition
     */
    public function add($column, string $fieldname = null) : ColumnDefinition
    {
        if (!is_array($column))
            $column = [
                'name' => $column,
                'data' => $column, // a good guess (user can adjust it later)
            ];
        if ($fieldname)
            $column['field'] = $fieldname;

        $column = new ColumnDefinition($column, $this);
        $this->store($column);

        return $column;
    }

    /**
     * Set titles of columns in given order
     * Convenience method for setting all titles at once
     * @param $titles array of titles in order of columns
     */
    public function setTitles(array $titles)
    {
        if (count($titles) != count($this->columns)) {
            $msg = 'Have ' . count($this->columns) . ' columns, but ' . count($titles) . ' titles given!';
            throw new \InvalidArgumentException($msg);
        }
        foreach ($titles as $i => $t) {
            if (!empty($t))
                $this->columns[$i]['title'] = $t;
        }
    }

    /**
     * Serialize to an array in json
     * @return: column definitions
     */
    public function jsonSerialize() : array
    {
        return array_values($this->columns);
    }

    public function offsetExists($offset) : bool
    {
        if (is_numeric($offset))
            return isset($this->columns[$offset]);
        return isset($this->index[$offset]);
    }

    public function offsetGet($offset)
    {
        if (is_numeric($offset))
            return $this->columns[$offset];
        return $this->columns[$this->index[$offset]];
    }

    public function offsetSet($offset, $value)
    {
        throw new \BadMethodCallException('Direct setting is not supported! Use add().');
    }

    public function offsetUnset($offset)
    {
        /* we do not allow splicing because DataTables uses a column's index
           for the ordering command. So the order of columns needs to stay
           consistent from the Controller down to the table displayed. */
        throw new \BadMethodCallException('Unset is not supported!');
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->columns);
    }

    public function count()
    {
        return count($this->columns);
    }

    protected function store(ColumnDefinition $column)
    {
        $this->columns[] = $column;
        /* keep track of where we stored it.
           Note: our array is only growing! No splicing! */
        $this->index[$column['name']] = count($this->columns) - 1;
    }
}
