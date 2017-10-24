<?php
namespace DataTables\View\Helper;

use Cake\View\Helper;
use Cake\View\StringTemplateTrait;
use DataTables\Lib\JSFunction;

/**
 * DataTables helper
 *
 *
 */
class DataTablesHelper extends Helper
{

    use StringTemplateTrait;

    protected $_defaultConfig = [
        'searching' => true,
        'processing' => true,
        'serverSide' => true,
        'deferRender' => true,
    ];

    public function init(array $options = [])
    {
        $this->_templater = $this->templater();

        // -- load i18n (defaults from datatables.net/reference/option/language)
        $this->config('language', [
            'emptyTable' => __d('data_tables', 'No data available in table'),
            'info' => __d('data_tables', 'Showing _START_ to _END_ of _TOTAL_ entries'),
            'infoEmpty' => __d('data_tables', 'No entries to show'),
            'infoFiltered' => __d('data_tables', '(filtered from _MAX_ total entries)'),
            'lengthMenu' => __d('data_tables', 'Show _MENU_ entries'),
            'processing' => __d('data_tables', 'Processing...'),
            'search' => __d('data_tables', 'Search:'),
            'zeroRecords' => __d('data_tables', 'No matching records found'),
            'paginate' => [
                'first' => __d('data_tables', 'First'),
                'last' => __d('data_tables', 'Last'),
                'next' => __d('data_tables', 'Next'),
                'previous' => __d('data_tables', 'Previous'),
            ],
            'aria' => [
                'sortAscending' => __d('data_tables', ': activate to sort column ascending'),
                'sortDescending' => __d('data_tables', ': activate to sort column descending'),
            ],
        ]);

        // -- load user config (may overwrite i18n)
        $this->config($options);

        return $this;
    }

    /**
     * Return a Javascript function wrapper to be used in DataTables configuration
     * @param string $name Name of Javascript function to call
     * @param array $args Optional array of arguments to be passed when calling
     * @return JSFunction
     */
    public function callback(string $name, array $args = []) : JSFunction
    {
        return new JSFunction($name, $args);
    }

    public function draw($selector)
    {
        $options = $this->config();

        // remove field names, which are an internal/server-side setting
        foreach ($options['columns'] ?? [] as $name => $column)
            unset($options['columns'][$name]['field']);

        // prepare javascript object from the config, including method calls
        $json = JSFunction::resolve(json_encode($options));

        // call initializer method
        return "initDataTables('$selector', $json);\n";
    }

}
