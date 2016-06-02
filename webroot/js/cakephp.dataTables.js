"use strict";

var dt = dt || {}; // initialize namespace

dt.init = dt.init || {}; // namespace for initializers
dt.render = dt.render || {}; // namespace for renderers

dt.initDataTables = function (id, data) {
    /* Use text renderer by default. Escapes HTML. */
    $.each(data.columns, function (i, val) {
        if (!val.render) {
            data.columns[i].render = $.fn.dataTable.render.text();
        }
    });

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
        .bind("input", function (e) { // Bind for field changes
            // If enough characters, or search cleared with backspace
            if (this.value.length >= minSearchCharacters || !this.value) {
                table.api().search(this.value).draw();
            }
        })
        .bind("keydown", function (e) { // Bind for key presses
            if (e.keyCode == 13) // Enter key
                table.api().search(this.value).draw();
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
 * Resize table to perfectly fit into (remainder of) window
 * This works best if you set 'scrollY' to your minimum desired height
 * If you do not want to use scrollY, use a fixed height on the table element
 * Body margin is considered for fixed footer, body padding for fixed header
 * This installs a listener for 'fullscreenToggle' on the table
 * @param table dataTables object
 * @param offset Offset to subtract from calculated optimal height (fine-tuning)
 * @param fullscreen true to fit into whole window height (default: remaining)
 */
dt.init.fitIntoWindow = function (table, offset, fullscreen) {
    var wrapper = $('#' + table.attr('id') + '_wrapper');
    var body = wrapper.find('.dataTables_scrollBody');
    if (body.length == 0) // neither scrollX / scrollY used
        body = table;

    // use initial height as minimum height
    var minHeight = body.outerHeight();

    // store fullscreen state in table
    table.data('fullscreen', fullscreen);

    var resizer = function () {
        var total = window.innerHeight;
        if (table.data('fullscreen') || (wrapper.offset().top + minHeight > total)) {
            /* fit table in window minus body padding (fixed header) */
            total -= $('body').outerHeight(false) - $('body').height();
        } else {
            /* fit table between previous content and footer (body margin) */
            total -= wrapper.offset().top;
            total -= $('body').outerHeight(true) - $('body').outerHeight(false);
        }
        /* take table decorations (e.g. info, filter elements) into account */
        var self = wrapper.outerHeight(true) - body.outerHeight(false);

        /* set height and propagate change */
        body.css('height', total - self - offset + "px");
        if (typeof(table.api().scroller) !== 'undefined')
            table.api().scroller.measure(true);
        else
            table.api().draw();
    };

    // initial call
    resizer();

    // call on window resize
    var resizeTimer;
    $(window).on('resize', function (e) {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(resizer, 250);
    });

    // allow toggling fullscreen mode
    table.on('toggleFullscreen', function () {
        table.data('fullscreen', !table.data('fullscreen'));
        resizer();
    });
};

/**
 * Render a date as localized string
 * @param data The data for the cell (based on columns.data)
 * @param type 'filter', 'display', 'type' or 'sort'
 * @param full The full data source for the row
 * @param meta Object containing additional information about the cell
 * @returns Manipulated cell data
 */
dt.render.date = function (data, type, full, meta)
{
    if (type === 'display') {
        var date = new Date(data);
        return date.toLocaleDateString(document.documentElement.lang);
    }
    return data;
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
