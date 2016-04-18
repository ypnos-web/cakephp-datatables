"use strict";

var dt = dt || {}; // initialize namespace

dt.init = dt.init || {}; // namespace for initializers
dt.render = dt.render || {}; // namespace for renderers

dt.calculateHeight = function (id) {
    var body = document.body,
        html = document.documentElement;

    var total = Math.max(body.scrollHeight, body.offsetHeight,
        html.clientHeight, html.scrollHeight, html.offsetHeight),
        footer = $('footer').outerHeight(true),
        current = $(id).offset().top;

    return total - footer - current - 140; // empirical number, table headers
};

dt.initDataTables = function (id, data) {
    /* Use text renderer by default. Escapes HTML. */
    $.each(data.columns, function (i, val) {
        if (!val.render) {
            data.columns[i].render = $.fn.dataTable.render.text();
        }
    });

    /* determine table height by default in scrolling case */
    if (data.scrollY === true) {
        var height = dt.calculateHeight(id);
        if (height > 100) {
            data.height = data.scrollY = height;
        } else { // not enough space or window already scrolling
            delete data.scrollY; // disable scrollY
        }
    }

    /* create new instance */
    var table = $(id).dataTable(data);

    /* call requested initializer methods */
    if (typeof(data.init) === 'undefined')
        return;
    for (var i = 0; i < data.init.length; i++) {
        var fn = data.init[i];
        fn(table);
    }
};

/**
 * Delay search trigger for DataTables input field
 * @param table dataTables object
 * @param minSearchCharacters minimum of characters necessary to trigger search
 */
dt.init.delayedSearch = function (table, minSearchCharacters) {
    /* code taken from http://stackoverflow.com/a/23897722/21974 */
    // Grab the datatables input box and alter how it is bound to events
    var id = table.api().table().node().id + '_filter';
    $('#' + id + ' input')
        .unbind() // Unbind previous default bindings
        .bind("input", function (e) { // Bind our desired behavior
            // If enough characters, or the user pressed ENTER, search
            if (this.value.length >= minSearchCharacters || e.keyCode == 13) {
                // Call the API search function
                table.api().search(this.value).draw();
            }
            // Ensure we clear the search if they backspace far enough
            if (this.value == "") {
                table.api().search("").draw();
            }
        });
};

/**
 * Let an element change trigger a search (e.g. a custom input box)
 * @param table dataTables object
 * @param sender jQuery selector for the sending object
 */
dt.init.searchTrigger = function (table, sender)
{
    $(document).on('change', sender, function () {
        var value = table.search();
        if (!value) // no search results displayed, need no update
            return;
        table.search(value).draw();
    });
};

/**
 * Add clickable behavior to table rows
 * Builds upon datatables-select. As soon as a row is selected, the link fires.
 * The URL is appended with the id field of the row data.
 * @param table dataTables object
 * @param urlbase target URL base (e.g. controller + action link)
 * @param target optional: call $(target).load instead of href redirect
 */
dt.init.rowLinks = function (table, urlbase, target) {
    table.api().on('select', function (e, dt, type, indexes) {
        var row = table.api().rows(indexes);
        var rowData = row.data();
        var id = rowData[0].id;
        var url = urlbase + '/' + id;
        if (typeof target !== 'undefined') {
            $(target).load(url);
            table.api().rows(indexes).deselect(); // revert selection
        } else {
            window.location.href = url;
        }
    });
};

/**
 * Add search behavior to all search fields in column footer
 * @param delay Delay in ms before starting request
 */
dt.init.columnSearch = function (table, delay) {
    table.api().columns().every(function () {
        var index = this.index();
        var lastValue = ''; // closure variable to prevent redundant AJAX calls
        var timer = null; // Timer instance for delayed fetch
        $('input, select', this.footer()).on('keyup change', function () {
            if (this.value != lastValue) {
                lastValue = this.value;
                // -- set search
                table.api().column(index).search(this.value);
                window.clearTimeout(timer);
                timer = window.setTimeout(table.api().draw(), delay);
            }
        });
    });
};

/**
 * Append an element property to the data send to server in a datatables request
 * @param data The data object sent to the server
 * @param settings DataTables settings object
 * @param target Name of the object property to set
 * @param source Source for target value: { selector, property, default }
 * @returns The manipulated data object with new property
 */
dt.appendProperty = function (data, settings, target, source)
{
    var value = $(source.selector).prop(source.property);
    data[target] = (typeof(value) === 'undefined' ? source.default : value);
    return data;
};

/**
 * Escapes HTML characters
 * Inspired by dataTable.render.text()
 * Should be used in all render functions!
 */
dt.h = function (d)
{
    return typeof d === 'string' ?
        d.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') :
        d;
};

/**
 * Function reset
 *
 */
dt.resetColumnSearch = function (table) {
    table.api().columns().every(function () {
        this.search('');
        $('input, select', this.footer()).val('');
    });
    table.api().draw();
};
