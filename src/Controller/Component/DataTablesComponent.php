<?php
namespace DataTables\Controller\Component;

use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\Network\Exception\BadRequestException;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

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
    ];

    protected $_viewVars = [
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'draw' => 0
    ];

    protected $_tableName = null;

    protected $_plugin = null;

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
     * @param $columns: Column definitions
     */
    private function _order(array &$options, array &$columns)
    {
        if (empty($this->request->query['order']))
            return;

        $order = $this->config('order');

        /* extract custom ordering from request */
        foreach ($this->request->query['order'] as $item) {
            if (empty($columns))
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
     * @param $columns: Column definitions
     * @return: true if additional filtering takes place
     */
    private function _filter(array &$options, array &$columns) : bool
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
                if (empty($columns))
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
     * @param array $options: Finder options (as in Table::find())
     * @param array $columns: Column definitions needed for filter/order operations
     * @return Query to be evaluated (Query::count() may have already been called)
     */
    public function find(string $tableName, string $finder = 'all', array $options = [], array $columns = []) : Query
    {
        $delegateSearch = $options['delegateSearch'] ?? false;

        // -- get table object
        $table = TableRegistry::get($tableName);
        $this->_tableName = $table->alias();

        // -- process draw & ordering options
        $this->_draw();
        $this->_order($options, $columns);

        // -- call table's finder w/o filters
        $data = $table->find($finder, $options);

        // -- retrieve total count
        $this->_viewVars['recordsTotal'] = $data->count();

        // -- process filter options
        $haveFilters = $this->_filter($options, $columns);

        // -- apply filters
        if ($haveFilters) {
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
        $right = $this->config('prefixSearch') ? "{$value}%" : "%{$value}%";
        $condition = ["{$column}::text LIKE" => $right];

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
}
