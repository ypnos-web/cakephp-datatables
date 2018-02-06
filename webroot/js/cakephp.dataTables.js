"use strict";

var dt = dt || {}; // initialize namespace

dt.init = dt.init || {}; // namespace for initializers
dt.render = dt.render || {}; // namespace for renderers

dt.initDataTables = function (id, options) {
    /* Use text renderer by default. Escapes HTML. */
    $.each(options.columns, function (i, val) {
        if (!val.render) {
            options.columns[i].render = $.fn.dataTable.render.text();
        }
    });

    /* call requested initializer methods */
    if (typeof(options.init) !== 'undefined') {
        var initializers = options.init;
        delete options.init;
        $(id).on('preInit.dt', function () {
            var table = $(id).dataTable(); // table jQuery object
            for (var i = 0; i < initializers.length; i++) {
                initializers[i](table);
            }
        });
    }

    /* create new instance */
    $(id).dataTable(options);
};

/**
 * Delay search trigger for DataTables input field
 * @param table dataTables object
 * @param minSearchCharacters minimum of characters necessary to trigger search
 * @param delay milliseconds to delay search to prevent premature searches while typing
 * @param selector optional, custom jQuery selector for an external search field
 */
dt.init.delayedSearch = function (table, minSearchCharacters, delay, selector) {
    /* code taken from http://stackoverflow.com/a/23897722/21974 */
    // Grab given or datatables input box and alter how it is bound to events
    selector = selector || '#' + table.attr('id') + '_filter input';
    minSearchCharacters = minSearchCharacters || 3;
    delay = delay || 200;
    var timer = null;

    var trigger = function (value) {
        table.api().search(value).draw();
    };

    $(selector)
        .off() // Unbind previous default bindings
        .on('input', function (e) { // Bind for field changes
            // If enough characters, or search cleared with backspace
            if (this.value.length >= minSearchCharacters || !this.value) {
                window.clearTimeout(timer);
                timer = window.setTimeout(trigger, delay, this.value);
            }
        })
        .on('keydown', function (e) { // Bind for key presses
            if (e.keyCode === 13) { // Enter key
                window.clearTimeout(timer);
                trigger(this.value);
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
        var value = table.api().search();
        if (!value) // no search results displayed, need no update
            return;
        table.api().search(value).draw();
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
        } else {
            window.location.href = url;
        }
        table.api().rows(indexes).deselect(); // revert selection
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
            if (this.value !== lastValue) {
                lastValue = this.value;
                // -- set search
                table.api().column(index).search(this.value);
                window.clearTimeout(timer);
                timer = window.setTimeout(table.api().draw, delay);
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
    var wrapper = $(table.api().table().container());
    var body = wrapper.find('.dataTables_scrollBody');
    if (body.length === 0) // neither scrollX / scrollY used
        body = table;

    // use initial height as minimum height
    var minHeight = body.outerHeight();

    // store fullscreen state in table
    table.data('fullscreen', fullscreen);

    table.on('fitIntoWindow', function () {
        var bodyTag = $('body');
        var total = window.innerHeight;
        if (table.data('fullscreen') || (wrapper.offset().top + minHeight > total)) {
            /* fit table in window minus body padding (fixed header) */
            total -= bodyTag.outerHeight(false) - bodyTag.height();
        } else {
            /* fit table between previous content and footer (body margin) */
            total -= wrapper.offset().top;
            total -= bodyTag.outerHeight(true) - bodyTag.outerHeight(false);
        }
        /* take table decorations (e.g. info, filter elements) into account */
        var self = wrapper.outerHeight(true) - body.outerHeight(false);

        /* check if height changed (e.g. not on only horizontal resize) */
        var height = total - self - offset;
        if (body.height() === height)
            return;

        /* set height and propagate change */
        body.css('height', height + "px");
        var api = table.api();
        if (typeof(api.scroller) !== 'undefined') {
            api.scroller.measure(false);
        }
        /* note: we do not redraw as it leads to several problems. */
    });

    // initial call
    table.trigger('fitIntoWindow');

    // call on window resize
    var resizeTimer;
    $(window).on('resize', function (e) {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () { table.trigger('fitIntoWindow') }, 250);
    });

    // allow toggling fullscreen mode
    table.on('toggleFullscreen', function () {
        table.data('fullscreen', !table.data('fullscreen'));
        table.trigger('fitIntoWindow');
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
    var needRedraw = false; // redraw when a filter is dis-applied
    table.api().columns().every(function () {
        // always clean up
        $(this.footer()).children('input, select').val('');

        if (!this.search())
            return;

        // remove the filter
        this.search('');
        needRedraw = true;
    });

    if (needRedraw)
        table.api().draw();
};
