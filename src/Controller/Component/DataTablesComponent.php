<?php
namespace DataTables\Controller\Component;

use Cake\Controller\Component;
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
     * Process query data of ajax request
     * Alters $options if delegateOrder or delegateSearch are set
     * In this case, the model needs to handle 'customOrder' and 'globalSearch'
     * options.
     * Also, the option 'countUnfiltered' will notify the model the need to
     * provide two counts in the returned query (using Query::counter())
     */
    private function _processRequest(&$options)
    {
        // -- add limit
        if (!empty($this->request->query['length'])) {
            $this->config('length', $this->request->query['length']);
        }

        // -- add offset
        if (!empty($this->request->query['start'])) {
            $this->config('start', (int)$this->request->query['start']);
        }

        // -- add order
        if (!empty($this->request->query['order'])) {
            $order = $this->config('order');
            foreach($this->request->query['order'] as $item) {
                $order[$this->request->query['columns'][$item['column']]['name']] = $item['dir'];
            }
            if (!empty($options['delegateOrder'])) {
                $options['customOrder'] = $order;
            } else {
                $this->config('order', $order);
            }
        }

        // -- add draw (pass-through so dataTables knows the request order)
        if (!empty($this->request->query['draw'])) {
            $this->_viewVars['draw'] = (int)$this->request->query['draw'];
        }

        // -- don't support any search if columns data missing
        if (empty($this->request->query['columns']))
            return;

        // -- check table search field
        $globalSearch = $this->request->query['search']['value'] ?? false;
        if (!empty($options['delegateSearch'])) {
            $options['countUnfiltered'] = true;
            $options['globalSearch'] = $globalSearch;
            return; // TODO: support for deferred local search
        }

        // -- add conditions for both table-wide and column search fields
        foreach ($this->request->query['columns'] as $column) {
            if ($globalSearch && $column['searchable'] == 'true') {
                $this->_addCondition($column['name'], $globalSearch, 'or');
            }
            $localSearch = $column['search']['value'];
            if (!empty($localSearch)) {
                $this->_addCondition($column['name'], $column['search']['value']);
            }
        }
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
        // -- get table object
        $table = TableRegistry::get($tableName);
        $this->_tableName = $table->alias();

        // -- get query options
        $this->_processRequest($options);
        $data = $table->find($finder, $options);

        // -- record count
        $this->_viewVars['recordsTotal'] = $data->count();

        // -- filter result
        $data->where($this->config('conditionsAnd'));
        foreach ($this->config('matching') as $association => $where) {
            $data->matching($association, function ($q) use ($where) {
                return $q->where($where);
            });
        };
        $data->andWhere(['or' => $this->config('conditionsOr')]);

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
        $right = ($this->config('prefixSearch') ? "$value%" : "%$value%");
        $condition = ["$column LIKE" => $right];

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
