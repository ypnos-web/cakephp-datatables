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
        'conditionsOr' => [],  // table-wide search conditions
        'conditionsAnd' => [], // column search conditions
        'matching' => [],      // column search conditions for foreign tables
    ];

    protected $_viewVars = [
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'draw' => 0
    ];

    protected $_isAjaxRequest = false;

    protected $_tableName = null;

    protected $_plugin = null;

    /**
     * Process query data of ajax request
     *
     */
    private function _processRequest()
    {
        // -- check whether it is an ajax call from data tables server-side plugin or a normal request
        $this->_isAjaxRequest = $this->request->is('ajax');

        // -- add limit
        if( isset($this->request->query['length']) && !empty($this->request->query['length']) )
        {
            $this->config('length', $this->request->query['length']);
        }

        // -- add offset
        if( isset($this->request->query['start']) && !empty($this->request->query['start']) )
        {
            $this->config('start', (int)$this->request->query['start']);
        }

        // -- add order
        if( isset($this->request->query['order']) && !empty($this->request->query['order']) )
        {
            $order = $this->config('order');
            foreach($this->request->query['order'] as $item) {
                $order[$this->request->query['columns'][$item['column']]['name']] = $item['dir'];
            }
            $this->config('order', $order);
        }

        // -- add draw (an additional field of data tables plugin)
        if( isset($this->request->query['draw']) && !empty($this->request->query['draw']) )
        {
            $this->_viewVars['draw'] = (int)$this->request->query['draw'];
        }

        // -- don't support any search if columns data missing
        if( !isset($this->request->query['columns']) ||
            empty($this->request->query['columns']) )
        {
            return;
        }

        // -- check table search field
        $globalSearch = (isset($this->request->query['search']['value']) ?
            $this->request->query['search']['value'] : false);

        // -- add conditions for both table-wide and column search fields
        foreach($this->request->query['columns'] as $column)
        {
            if( $globalSearch && $column['searchable'] == 'true' ) {
                $this->_addCondition( $column['name'], $globalSearch, 'or' );
            }
            $localSearch = $column['search']['value'];
            /* In some circumstances (no "table-search" row present), DataTables
               fills in all column searches with the global search. Compromise:
               Ignore local field if it matches global search. */
            if( !empty($localSearch) && ($localSearch !== $globalSearch) ) {
                $this->_addCondition( $column['name'], $column['search']['value'] );
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
        $this->_processRequest();
        $data = $table->find($finder, $options);

        // -- record count
        $this->_viewVars['recordsTotal'] = $data->count();

        // -- filter result
        $data->where($this->config('conditionsAnd'));
        foreach($this->config('matching') as $association => $where) {
            $data->matching( $association, function ($q) use ($where) {
                return $q->where($where);
            });
        };
        $data->andWhere(['or' => $this->config('conditionsOr')]);

        $this->_viewVars['recordsFiltered'] = $data->count();

        // -- add limit
        $data->limit( $this->config('length') );
        $data->offset( $this->config('start') );

        // -- sort
        $data->order( $this->config('order') );

        // -- set all view vars to view and serialize array
        $this->_setViewVars();
        return $data;

    }

    private function _getController()
    {
        return $this->_registry->getController();
    }

    private function _setViewVars()
    {
        $_serialize = [];
        foreach($this->_viewVars as $field => $value) {
            $_serialize[] = $field;
        }
        $this->_getController()->set($this->_viewVars);
        $this->_getController()->set('_serialize', $_serialize);
    }

    private function _addCondition($column, $value, $type = 'and')
    {
        $condition = ["$column LIKE" => "$value%"];

        if( $type === 'or' ) {
            $this->config('conditionsOr', $condition); // merges
            return;
        }

        list($association, $field) = explode('.', $column);
        if( $this->_tableName == $association) {
            $this->config('conditionsAnd', $condition); // merges
        } else {
            $this->config('matching', [$association => $condition]); // merges
        }
    }
}
