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
        'dom' => '<<"row"<"col-sm-4"i><"col-sm-8"lp>>rt>',
    ];

    public function init(array $options = [])
    {
        $this->_templater = $this->templater();

        // -- load i18n
        $foo = __d('data_tables', 'Processing...');
        $this->config('language', [
            'paginate' => [
                'next' => '<i class="fa fa-chevron-right"></i>',
                'previous' => '<i class="fa fa-chevron-left"></i>'
            ],
            'processing' => __d('data_tables', 'Processing...'),
            'lengthMenu' =>
                '<select class="form-control">' .
                '<option value="10">' . __d('data_tables', 'Display {0} records', 10) . '</option>' .
                '<option value="25">' . __d('data_tables', 'Display {0} records', 25) . '</option>' .
                '<option value="50">' . __d('data_tables', 'Display {0} records', 50) . '</option>' .
                '<option value="100">' .__d('data_tables', 'Display {0} records', 100) . '</option>' .
                '</select>',
            'info' => __d('data_tables', 'Showing _START_ to _END_ of _TOTAL_ entries'),
            'infoFiltered' => __d('data_tables', '(filtered from _MAX_ total entries)'),
            'infoEmpty' => __d('data_tables', 'No entries to show'),
            'search' => __d('data_tables', 'Search:'),
            'zeroRecords' => __d('data_tables', 'No matching records found'),
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
        // -- initialize dataTables config
        $json = JSFunction::resolve(json_encode($this->config()));

        // -- call initializer method
        return "initDataTables('$selector', $json);\n";
    }

}
