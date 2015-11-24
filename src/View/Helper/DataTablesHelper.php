<?php
namespace DataTables\View\Helper;

use Cake\View\Helper;
use Cake\View\View;
use Cake\ORM\Query;
use Cake\View\StringTemplateTrait;

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
        'js' => [
            'calls' => null,
            'delay' => 600,
        ],
    ];

    public function init(array $options = [])
    {
        $this->_templater = $this->templater();
        $this->config($options);

        // -- default to initColumnSearch() if user didn't specify js calls array
        if(is_null($this->config('js.calls')))
        {
            $this->config('js.calls', ['initColumnSearch']);
        }

        // -- load i18n
        $this->config('language', [
            'paginate' => [
                'next' => '<i class="fa fa-chevron-right"></i>',
                'previous' => '<i class="fa fa-chevron-left"></i>'
            ],
            'processing' => __d('DataTables', 'Your request is processing ...'),
            'lengthMenu' =>
                '<select class="form-control">' .
                '<option value="10">' . __d('DataTables', 'Display {0} records', 10) . '</option>' .
                '<option value="25">' . __d('DataTables', 'Display {0} records', 25) . '</option>' .
                '<option value="50">' . __d('DataTables', 'Display {0} records', 50) . '</option>' .
                '<option value="100">' .__d('DataTables', 'Display {0} records', 100) . '</option>' .
                '</select>',
            'info' => __d('DataTables', 'Showing _START_ to _END_ of _TOTAL_ entries'),
            'infoFiltered' => __d('DataTables', '(filtered from _MAX_ total entries)')
        ]);

        return $this;
    }

    public function draw($selector)
    {
        // -- pass on parameters to javascript
        $json = json_encode($this->config('js'));
        $js = "params = $json;";

        // -- initialize DataTables
        $json = json_encode($this->config());
        $js .= "table=jQuery('$selector').dataTable($json);";

        // -- call javascript methods
        $js .= implode('();', $this->config('js.calls')).'();';

        return $js;
    }

}
