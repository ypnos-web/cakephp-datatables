<?php
namespace DataTables\View\Helper;

use Cake\View\Helper;
use DataTables\Lib\CallbackFunction;

/**
 * DataTables helper
 *
 *
 */
class DataTablesHelper extends Helper
{
    public $helpers = ['Html'];

    protected $_defaultConfig = [
        'searching' => true,
        'processing' => true,
        'serverSide' => true,
        'deferRender' => true,
    ];

    public function initialize(array $config)
    {
        /* set default i18n (not possible in _$defaultConfig due to use of __d() */
        if (empty($this->config('language'))) {
            // defaults from datatables.net/reference/option/language
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
        }
    }


    /**
     * Return a Javascript function wrapper to be used in DataTables configuration
     * @param string $name Name of Javascript function to call
     * @param array $args Optional array of arguments to be passed when calling
     * @return CallbackFunction
     */
    public function callback(string $name, array $args = []) : CallbackFunction
    {
        return new CallbackFunction($name, $args);
    }

    /**
     * Return a table with dataTables overlay
     * @param $id: DOM id of the table
     * @param $dtOptions: Options for DataTables
     * @param $htmlOptions: Options for the table, e.g. CSS classes
     * @return string containing a <table> and a <script> element
     */
    public function table(string $id = 'datatable', array $dtOptions = [], array $htmlOptions = []) : string
    {
        $htmlOptions = array_merge($htmlOptions,  [
            'id' => $id,
            'class' => 'dataTable ' . ($htmlOptions['class'] ?? ''),
        ]);
        $table = $this->Html->tag('table', '', $htmlOptions);

        $code = $this->draw("#{$id}", $dtOptions);

        return $table.$this->Html->scriptBlock($code);
    }

    /**
     * @deprecated use configShallow() instead
     */
    public function init(array $options = [])
    {
        /* merge options non-recursively */
        $this->configShallow($options);

        return $this;
    }

    public function draw(string $selector, array $options = [])
    {
        // incorporate any defaults set earlier
        $options += $this->config();
        // fill-in missing language options, in case some were customized
        $options['language'] += $this->config('language');

        // prepare javascript object from the config, including method calls
        $json = CallbackFunction::resolve(json_encode($options));

        // return a call to initializer method
        return "dt.initDataTables('{$selector}', {$json});\n";
    }
}
