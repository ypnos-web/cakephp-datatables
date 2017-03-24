<?php
namespace DataTables\Controller\Component;

use Cake\Collection\Collection;
use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;

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
        'comparison_datasource' => 'default'
    ];

    protected $_defaultComparison = [
        'string' => 'like',
        'text' => 'like',
        'uuid' => 'like',
        'integer' => 'equals',
        'biginteger' => 'equals',
        'float' => 'equals',
        'decimal' => 'equals',
        'boolean' => 'equals',
        'binary' => 'like',
        'date' => 'like',
        'datetime' => 'like',
        'timestamp' => 'like',
        'time' => 'like',
        'json' => 'like',
    ];

    protected $_viewVars = [
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'draw' => 0
    ];

    protected $_tableName = null;

    protected $_plugin = null;

    /**
     * Schema collection
     *
     * @var Cake\Database\Schema\Collection
     */
    protected $_collection = null;

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
     * @param $options: Query options from the request
     */
    private function _order(array &$options)
    {
        if (empty($this->request->query['order']))
            return;

        // -- add custom order
        $order = $this->config('order');
        foreach($this->request->query['order'] as $item) {
            $order[$this->request->query['columns'][$item['column']]['name']] = $item['dir'];
        }
        if (!empty($options['delegateOrder'])) {
            $options['customOrder'] = $order;
        } else {
            $this->config('order', $order);
        }

        // -- remove default ordering as we have a custom one
        unset($options['order']);
    }

    /**
     * Process query data of ajax request regarding filtering
     * Alters $options if delegateSearch is set
     * In this case, the model needs to handle the 'globalSearch' option.
     * @param $options: Query options from the request
     * @return: returns true if additional filtering takes place
     */
    private function _filter(array &$options) : bool
    {
        // -- add limit
        if (!empty($this->request->query['length'])) {
            $this->config('length', $this->request->query['length']);
        }

        // -- add offset
        if (!empty($this->request->query['start'])) {
            $this->config('start', (int)$this->request->query['start']);
        }

        // -- don't support any search if columns data missing
        if (empty($this->request->query['columns']))
            return false;

        // -- check table search field
        $globalSearch = $this->request->query['search']['value'] ?? false;
        if ($globalSearch && !empty($options['delegateSearch'])) {
            $options['globalSearch'] = $globalSearch;
            return true; // TODO: support for deferred local search
        }

        // -- add conditions for both table-wide and column search fields
        $filters = false;
        foreach ($this->request->query['columns'] as $column) {
            if ($globalSearch && $column['searchable'] == 'true') {
                $this->_addCondition($column['name'], $globalSearch, 'or');
                $filters = true;
            }
            $localSearch = $column['search']['value'];
            if (!empty($localSearch)) {
                $this->_addCondition($column['name'], $column['search']['value']);
                $filters = true;
            }
        }
        return $filters;
    }

    /**
     * Find data
     *
     * @param $tableName
     * @param $finder
     * @param array $options
     * @return array|\Cake\ORM\Query
     */
    public function find($tableName, $finder = 'all', array $options = [])
    {
        $delegateSearch = !empty($options['delegateSearch']);

        // -- get table object
        $table = TableRegistry::get($tableName);
        $this->_tableName = $table->alias();

        // Get main table schema
        $db = ConnectionManager::get($this->config('comparison_datasource'));
        $this->_collection = $db->schemaCollection();

        // -- process draw & ordering options
        $this->_draw();
        $this->_order($options);

        // -- call table's finder w/o filters
        $data = $table->find($finder, $options);

        // -- retrieve total count
        $this->_viewVars['recordsTotal'] = $data->count();

        // -- process filter options
        $filters = $this->_filter($options);

        // -- apply filters
        if ($filters) {
            if ($delegateSearch) {
                // call finder again to process filters (provided in $options)
                $data = $table->find($finder, $options);
            } else {
                $data->where($this->config('conditionsAnd'));
                foreach ($this->config('matching') as $association => $where) {
                    $data->matching($association, function ($q) use ($where) {
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
        $comparison = $this->getComparison($column);

        switch($comparison) {
            case 'equals':
                $condition = ["{$column} = " => $value];
                break;

            case 'greater':
                $condition = ["{$column} > " => $value];
                break;

            case 'greaterEquals':
                $condition = ["{$column} >= " => $value];
                break;

            case 'less':
                $condition = ["{$column} < " => $value];
                break;

            case 'lessEquals':
                $condition = ["{$column} <= " => $value];
                break;

            case 'notLike':
                $right = $this->config('prefixSearch') ? "{$value}%" : "%{$value}%";
                $condition = ["{$column} NOT LIKE" => $right];

            case 'like':
            default :
                $right = $this->config('prefixSearch') ? "{$value}%" : "%{$value}%";
                $condition = ["{$column} LIKE" => $right];
        }

        if ($type === 'or') {
            $this->config('conditionsOr', $condition); // merges
            return;
        }

        list($association, $field) = explode('.', $column);
        if ($this->_tableName == $association) {
            $this->config('conditionsAnd', $condition); // merges
        } else {
            $this->config('matching', [$association => $condition]); // merges
        }
    }

    /**
     * Get comparison by entity and column name.
     *
     * @param  string $column Database column
     * @return int            Database comparison type
     */
    protected function getComparison($column)
    {
        $config = new Collection($this->config('comparison'));
        $tableName = $this->_tableName;

        // Attempt to find the table name
        if (false !== ($pos = strpos($column, '.'))) {
            $entity = substr($column, 0, $pos);
            $tableName = TableRegistry::get($entity)->table();
            $columnName = substr($column, $pos + 1);

            // See if the controller defined a specific comparison type for this field
            $userConfig = $config->filter(function ($item, $key) use ($entity, $columnName) {
                return strtolower($key) === strtolower(sprintf('%s.%s', $entity, $columnName));
            });

            if (!$userConfig->isEmpty()) {
                return $userConfig->first();
            }
        }

        // Check application config if the column type has a default comparison defined
        $columnDesc = $this->_collection->describe($tableName)->column($columnName);
        $columnConfig = array_merge(
            $this->_defaultComparison,
            Configure::read('DataTables.Columns.Comparison')
        );

        if(isset($columnConfig[$columnDesc['type']])) {
            return $columnConfig[$columnDesc['type']];
        }

        return null;
    }
}
