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

    public function initialize(array $config) : void
    {
        /* set default i18n (not possible in _$defaultConfig due to use of __d() */
        if (empty($this->getConfig('language'))) {
            // defaults from datatables.net/reference/option/language
            $this->setConfig('language', [
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
     * @param $dtOptions: Options for DataTables (to be merged with this helper's config as defaults)
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

        return $table.$this->Html->scriptBlock($code, ['block' => true]);
    }

    /**
     * Return JavaScript code to initialize DataTables object
     * Use this method if you want to render the <table> element yourself
     * Typically the output of this method is fed to HtmlHelper::scriptBlock()
     * @param string $selector JQuery selector for the <table> element
     * @param array $options Optional additional/replacement configuration to this helper's config
     * @return string
     */
    public function draw(string $selector, array $options = []) : string
    {
        // incorporate any defaults set earlier
        $options += $this->getConfig();
        // fill-in missing language options, in case some were customized
        if(isset($options['language']['url'])) {
            $options['language'] = ['url' => $options['language']['url']];
        } else {
            $options['language'] += $this->getConfig('language');
        }

        // sanitize & translate order
        if (!empty($options['order']))
            $this->translateOrder($options['order'], $options['columns']);

        // remove field names, which are an internal/server-side setting
        foreach ($options['columns'] as $key => $v)
            unset($options['columns'][$key]['field']);

        // prepare javascript object from the config, including method calls
        $json = CallbackFunction::resolve(json_encode($options));
        $json = str_replace('"#!!','',$json);
        $json = str_replace('!!#"','',$json);

        // return a call to initializer method
        return "dt.initDataTables('{$selector}', {$json});\n";
    }

    public function translateOrder(array &$order, &$columns)
    {
        // sanitize cakephp style input [a => b] -> [[a, b]]
        $new_order = [];
        array_walk($order, function ($val, $key) use (&$new_order) {
            if (is_integer($key))
                $new_order[] = $val;
            else
                $new_order[] = [$key, $val];
        });
        $order = $new_order;

        // sanitize single column input [a, b] -> [[a, b]]
        if (count($order) == 2 && !is_array($order[0]))
            $order = [$order];

        // translate order columns
        foreach ($order as $i => $o) {
            if (is_numeric($order))
                continue; // already a numerical index

            foreach ($columns as $key => $v) {
                // user might have specified it either way…
                if ($o[0] === ($v['data'] ?? null) || $o[0] === ($v['field'] ?? null)) {
                    $order[$i][0] = $key;
                    break;
    }
            }
        }
        return $order;
    }
}
