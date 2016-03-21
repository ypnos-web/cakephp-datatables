# cakephp-datatables

[DataTables](https://www.datatables.net) is a jQuery plugin for intelligent HTML tables. Next to adding dynamic elements to the table, it also has great supports for on-demand data fetching and server-side processing. The _cakephp-datatables_ plugin makes it easy to use the functionality DataTables provides in your CakePHP 3 application. It consists of a helper to add DataTables to your view and a Component to transparently process AJAX requests made by DataTables.

## Requirements

* PHP 7
* CakePHP 3.x
* DataTables 1.10.x

Optional:

* Twitter Bootstrap 3 (http://getbootstrap.com)
* FontAwesome 4 (http://fortawesome.github.io/Font-Awesome)

The core templates are written in Twitter Bootstrap syntax with FontAwesome icons but can be changed easily.

## Installation

### Add plugin

Use composer to install this plugin.
Add the following repository and requirement to your composer.json:

    "require": {
        "ypnos-web/cakephp-datatables": "dev-master"
    }


Load plugin in ***app/bootstrap.php***:
    
    Plugin::load('DataTables', ['bootstrap' => false, 'routes' => false]);

### Add component and helper

For example in your AppController:

    class AppController extends Controller
    {
        
        public $helpers = [
            'DataTables' => [
                'className' => 'DataTables.DataTables'
            ]
        ];
        
        public function initialize()
        {
            $this->loadComponent('DataTables.DataTables');
        }
        
    }

### Step 3: Include assets

Include jQuery and jQuery DataTables scripts first and then the dataTables logic:

    echo $this->Html->script('*PATH*/jquery.min.js');
    echo $this->Html->script('*PATH*/jquery.dataTables.min.js');
    echo $this->Html->script('*PATH*/dataTables.bootstrap.min.js'); (Optional)
    echo $this->Html->script('DataTables.cakephp.dataTables.js');

Include dataTables css:

    echo $this->Html->css('*PATH*/dataTables.bootstrap.css');

If you don't use Bootstrap, see the DataTables documentation for which files to include instead. For FontAwesome, you might also want to have a look at [this](https://www.datatables.net/blog/2014-06-06).

There is also a bunch of really helpful extensions for DataTables that we support, e.g. [Scroller](https://datatables.net/extensions/scroller/) and [Select](https://datatables.net/extensions/select/).


## Usage

There is two parts to DataTables from the Cake perspective:

1. Adding DataTables support to a table in the view template
2. Custom Controller logic to support DataTables AJAX requests

### Pimp your table in the template

A typical bootstrap table looks like this:

```html
<table id="<?=$tableid?>" class="table table-striped table-hover linktable dataTable">
</table>
```

Now we could add and populate a `<thead>` element for our table heading and a `<tbody>` element for the data. With DataTables you can either do it this way or provide the data (and metadata, for the heading) directly to DataTables so it will populate the table. A third scenario is to provide no data at all and let DataTables fetch the data from the server.

In this example of a table showing users, we let DataTables generate both heading and body for us:

```php
$this->DataTables->init([
	'ajax' => [
		'url' => $this->Url->build() // current controller, action, params
	],
	'data' => $data,
	'deferLoading' => $data->count(), // https://datatables.net/reference/option/deferLoading
	'columns' => [
		[
			'name' => 'Users.id',
			'data' => 'id',
			'visible' => false,
			'searchable' => false,
		],
		[
			'title' => __('First Name'),
			'name' => 'Users.firstname',
			'data' => 'firstname'
		],
		[
			'title' => __('Last Name'),
			'name' => 'Users.lastname',
			'data' => 'lastname'
		],
		[
			'title' => __('Login name'),
			'name' => 'Users.username',
			'data' => 'username',
			'className' => 'text-primary',
		],
		[
			'title' => __('Department'),
			'name' => 'Departments.name',
			'data' => 'department.name'
		],
	],
	'order' => [3, 'asc'], // order by username
]);
```

Some notes on this example:

1. The options array provided to `init()` are options to DataTables as per the [DataTables options reference](https://datatables.net/reference/option/).
2. All columns need `name` defined so that DataTables can read the correct variable from `$data` (which is provided in JSON format). The identifiers in `name` therefore correspond to how the data is accessible from the view variable.
3. All columns need `data` defined so that DataTable AJAX requests for searching and ordering are understood by the model. The identifiers in `data` therefore correspond to the ORM table columns.
4. All columns need `title` defined if you do not provide a table heading in the HTML.
5. You can determine which columns should be searchable, orderable or even visible. An invisible column can be helpful for custom javascript callbacks which are discussed below.
6. Some options are default in the plugin, e.g. `serverSide = true`, which you can overwrite if needed.

After initialization, the DataTables javascript code needs to be generated and embedded. Example:

```php
echo $this->Html->scriptBlock($this->DataTables->draw("#$tableid"));
```

### Process DataTables AJAX requests

As mentioned earlier, in the default configuration and above example, DataTables will serve the initially provided data first, but let the server process any new requests (e.g. sorting, filtering, pagination or scrolling using [Scroller](https://datatables.net/extensions/scroller/)).

To make this work, we can replace our regular find operation with the wrapper provided by the plugin:

```php
$data = $this->DataTables->find('Users', 'all', [
	'contain' => ['Departments']
	'order' => ['username' => 'asc']
]);
$this->set('data', $data);
$this->set('_serialize', array_merge($this->viewVars['_serialize'], ['data']));
```

Again some notes on this example:

1. The DataTables component provides a `find()` function that processes any parameters in the requests initiated by DataTables. It is a drop-in replacement for calling the table's finder directly. There is no need to differentiate between regular and JSON requests in the controller.
2. We provide a default order argument that needs to match the order argument in the template. Otherwise DataTables will indicate a wrong ordering to the user. This is related to our use of the `deferLoading` option.
3. DataTables performs JSON requests and CakePHP's JSON view uses the `_serialize` view variable to determine which view variables to send back. The DataTables plugin sets a bunch of these, so it is crucial to _append_ the data variable here. If your view variable is not called 'data', set [DataTables `ajax.dataSrc` option](https://datatables.net/reference/option/ajax).

### Extended functionality

Our plugin supports more DataTables features than explained here. These include per-column search fields, elegant passing of callback functions, and delegating search and ordering to the model layer (needed for more complicated data sources). Documentation on these will follow.

## Credits

This work is based on the [code by Frank Heider](https://github.com/fheider/cakephp-datatables).
