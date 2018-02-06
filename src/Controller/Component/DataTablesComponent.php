<?php
namespace DataTables\Controller\Component;

use Cake\Collection\Collection;
use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\Database\Driver\Postgres;
use Cake\Network\Exception\BadRequestException;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use DataTables\Lib\ColumnDefinitions;

/**
 * DataTables component
 */
class DataTablesComponent extends Component
{

    protected $_defaultConfig = [
        'start' => 0,
        'length' => 10,
        'order' => [],
        'prefixSearch' => true, // use "LIKE …%" instead of "LIKE %…%" conditions
        'conditionsOr' => [],  // table-wide search conditions
        'conditionsAnd' => [], // column search conditions
        'matching' => [],      // column search conditions for foreign tables
        'comparison' => [], // per-column comparison definition
    ];

    protected $_defaultComparison = [
        'string' => 'LIKE',
        'text' => 'LIKE',
        'uuid' => 'LIKE',
        'integer' => '=',
        'biginteger' => '=',
        'float' => '=',
        'decimal' => '=',
        'boolean' => '=',
        'binary' => 'LIKE',
        'date' => 'LIKE',
        'datetime' => 'LIKE',
        'timestamp' => 'LIKE',
        'time' => 'LIKE',
        'json' => 'LIKE',
    ];

    protected $_viewVars = [
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'draw' => 0
    ];

    /** @var Table */
    protected $_table = null;

    /** @var ColumnDefinitions */
    protected $_columns = null;

    public function initialize(array $config)
    {
        /* Set default comparison operators for field types */
        if (Configure::check('DataTables.ComparisonOperators')) {
            $operators = Configure::read('DataTables.ComparisonOperators');
            $this->_defaultComparison = array_merge($this->_defaultComparison, $operators);
        };

        /* setup column definitions */
        $this->_columns = new ColumnDefinitions();
    }

    public function columns()
    {
        return $this->_columns;
    }

    /**
     * Process draw option (pass-through)
     */
    private function _draw()
    {
        if (empty($this->request->query['draw']))
            return;

        $this->_viewVars['draw'] = (int)$this->request->query['draw'];
    }

    /**
     * Process query data of ajax request regarding order
     * Alters $options if delegateOrder is set
     * In this case, the model needs to handle the 'customOrder' option.
     * @param $options: Query options
     * @param ColumnDefinitions|array Column definitions
     */
    private function _order(array &$options, &$columns)
    {
        if (empty($this->request->query['order']))
            return;

        $order = $this->config('order');
        /* extract custom ordering from request */
        foreach ($this->request->query['order'] as $item) {
            if (!count($columns)) // note: empty() does not work on objects
                throw new \InvalidArgumentException('Column ordering requested, but no column definitions provided.');

            $dir = strtoupper($item['dir']);
            if (!in_array($dir, ['ASC', 'DESC']))
                throw new BadRequestException('Malformed order direction.');

            $c = $columns[$item['column']] ?? null;
            if (!$c || !($c['orderable'] ?? true)) // orderable is true by default
                throw new BadRequestException('Illegal column ordering.');

            if (empty($c['field']))
                throw new \InvalidArgumentException('Column description misses field name.');

            $order[$c['field']] = $dir;
        }

        /* apply ordering */
        if (!empty($options['delegateOrder'])) {
            $options['customOrder'] = $order;
        } else {
            $this->config('order', $order);
        }

        /* remove default ordering in favor of our custom one */
        unset($options['order']);
    }

    /**
     * Process query data of ajax request regarding filtering
     * Alters $options if delegateSearch is set
     * In this case, the model needs to handle the 'globalSearch' option.
     * @param $options: Query options
     * @param ColumnDefinitions|array $columns Column definitions
     * @return: true if additional filtering takes place
     */
    private function _filter(array &$options, &$columns) : bool
    {
        /* add limit and offset */
        if (!empty($this->request->query['length'])) {
            $this->config('length', $this->request->query['length']);
        }
        if (!empty($this->request->query['start'])) {
            $this->config('start', (int)$this->request->query['start']);
        }

        $haveFilters = false;
        $delegateSearch = $options['delegateSearch'] ?? false;

        /* add global filter (general search field) */
        $globalSearch = $this->request->query['search']['value'] ?? false;
        if ($globalSearch) {
            if (empty($columns))
                throw new \InvalidArgumentException('Filtering requested, but no column definitions provided.');

            if ($delegateSearch) {
                $options['globalSearch'] = $globalSearch;
                $haveFilters = true;
            } else {
                foreach ($columns as $c) {
                    if (!($c['searchable'] ?? true)) // searchable is true by default
                        continue;

                    if (empty($c['field']))
                        throw new \InvalidArgumentException('Column description misses field name.');

                    $this->_addCondition($c['field'], $globalSearch, 'or');
                    $haveFilters = true;
                }
            }
        }

        /* add local filters (column search fields) */
        foreach ($this->request->query['columns'] ?? [] as $index => $column) {
            $localSearch = $column['search']['value'] ?? null;
            if (!empty($localSearch)) {
                if (!count($columns)) // note: empty() does not work on objects
                    throw new \InvalidArgumentException('Filtering requested, but no column definitions provided.');

                $c = $columns[$index] ?? null;
                if (!$c || !($c['searchable'] ?? true)) // searchable is true by default
                    throw new BadRequestException('Illegal filter request.');

                if (empty($c['field']))
                    throw new \InvalidArgumentException('Column description misses field name.');

                if ($delegateSearch) {
                    $options['localSearch'][$c['field']] = $localSearch;
                } else {
                    $this->_addCondition($c['field'], $localSearch);
                }
                $haveFilters = true;
            }
        }

        return $haveFilters;
    }

    /**
     * Find data
     *
     * @param $tableName: ORM table name
     * @param $finder: Finder name (as in Table::find())
     * @param $options: Finder options (as in Table::find())
     * @param $columns: Column definitions needed for filter/order operations
     * @return Query to be evaluated (Query::count() may have already been called)
     */
    public function find(string $tableName, string $finder = 'all', array $options = [], array $columns = []) : Query
    {
        $delegateSearch = $options['delegateSearch'] ?? false;
        if (empty($columns))
            $columns = $this->_columns;

        // -- get table object
        $this->_table = TableRegistry::get($tableName);

        // -- process draw & ordering options
        $this->_draw();
        $this->_order($options, $columns);

        // -- call table's finder w/o filters
        $data = $this->_table->find($finder, $options);

        // -- retrieve total count
        $this->_viewVars['recordsTotal'] = $data->count();

        // -- process filter options
        $haveFilters = $this->_filter($options, $columns);

        // -- apply filters
        if ($haveFilters) {
            if ($delegateSearch) {
                // call finder again to process filters (provided in $options)
                $data = $this->_table->find($finder, $options);
            } else {
                $data->where($this->config('conditionsAnd'));
                foreach ($this->config('matching') as $association => $where) {
                    $data->matching($association, function (Query $q) use ($where) {
                        return $q->where($where);
                    });
                }
                if (!empty($this->config('conditionsOr'))) {
                    $data->where(['or' => $this->config('conditionsOr')]);
                }
            }
        }

        // -- retrieve filtered count
        $this->_viewVars['recordsFiltered'] = $data->count();

        // -- add limit
        if ($this->config('length') > 0) { // dt might provide -1
            $data->limit($this->config('length'));
            $data->offset($this->config('start'));
        }

        // -- sort
        $data->order($this->config('order'));

        // -- set all view vars to view and serialize array
        $this->_setViewVars();
        return $data;

    }

    private function _setViewVars()
    {
        $controller = $this->_registry->getController();

        $_serialize = $controller->viewVars['_serialize'] ?? [];
        $_serialize = array_merge($_serialize, array_keys($this->_viewVars));

        $controller->set($this->_viewVars);
        $controller->set('_serialize', $_serialize);
    }

    private function _addCondition($column, $value, $type = 'and')
    {
        /* extract table (encoded in $column or default) */
        $table = $this->_table;
        if (($pos = strpos($column, '.')) !== false) {
            $table = TableRegistry::get(substr($column, 0, $pos));
            $column = substr($column, $pos + 1);
        }

        $textCast = "";

        /* build condition */
        $comparison = trim($this->_getComparison($table, $column));
        // wrap value for LIKE and NOT LIKE
        if (strpos(strtolower($comparison), 'like') !== false) {
            $value = $this->config('prefixSearch') ? "{$value}%" : "%{$value}%";

            if($this->_table->getConnection()->getDriver() instanceof Postgres) {
                $textCast = "::text";
            }
        }
        $condition = ["{$table->alias()}.{$column}{$textCast} {$comparison}" => $value];

        /* add as global condition */
        if ($type === 'or') {
            $this->config('conditionsOr', $condition); // merges
            return;
        }

        /* add as local condition */
        if ($table === $this->_table) {
            $this->config('conditionsAnd', $condition); // merges
        } else {
            $this->config('matching', [$table->alias() => $condition]); // merges
        }
    }

    /**
     * Get comparison operator by entity and column name.
     *
     * @param $column: Database column name (may be in form Table.column)
     * @return: Database comparison operator
     */
    protected function _getComparison(Table $table, string $column) : string
    {
        $config = new Collection($this->config('comparison'));

        /* Lookup per-column configuration for the comparison operator */
        $userConfig = $config->filter(function ($item, $key) use ($table, $column) {
            $wanted = sprintf('%s.%s', $table->alias(), $column);
            return strtolower($key) === strtolower($wanted);
        });
        if (!$userConfig->isEmpty()) {
            return $userConfig->first();
        }

        /* Lookup per-field type configuration for the comparison operator */
        $columnDesc = $table->schema()->column($column);
        return $this->_defaultComparison[$columnDesc['type']] ?? '=';
    }
}
