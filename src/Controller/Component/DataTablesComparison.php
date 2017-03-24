<?php
namespace DataTables\Controller\Component;

/**
 * Contains static properties which define the SQL comparison types
 */
class DataTablesComparison
{
    /**
     * LIKE comparison
     * @var integer
     */
    const LIKE = 0;

    /**
     * NOT LIKE comparison
     * @var integer
     */
    const NOT_LIKE = 1;

    /**
     * `=` comparison
     * @var integer
     */
    const EQUALS = 2;

    /**
     * `>` comparison
     * @var integer
     */
    const GREATER = 3;

    /**
     * `>=` comparison
     * @var integer
     */
    const GREATER_EQUALS = 4;

    /**
     * '<' comparison
     * @var integer
     */
    const LESS = 5;

    /**
     * '<=' comparison
     * @var integer
     */
    const LESS_EQUALS = 6;
}
