"use strict";

function calculateHeight(id) {
    var body = document.body,
        html = document.documentElement;

    var total = Math.max(body.scrollHeight, body.offsetHeight,
        html.clientHeight, html.scrollHeight, html.offsetHeight),
        footer = $('footer').outerHeight(true),
        current = $(id).offset().top;

    return total - footer - current - 140; // empirical number, table headers
}

function initDataTables(id, data) {
    /* Use text renderer by default. Escapes HTML. */
    $.each(data.columns, function(i, val) {
        if (!val.render) {
            data.columns[i].render = $.fn.dataTable.render.text();
        }
    });

    /* determine table height by default in scrolling case */
    if (data.scrollY === true) {
        var height = calculateHeight(id);
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
}

/**
 * Add clickable behavior to table rows
 * Builds upon datatables-select. As soon as a row is selected, the link fires.
 * The URL is appended with the id field of the row data.
 * @param table dataTables object
 * @param urlbase target URL base (e.g. controller + action link)
 * @param target optional: call $(target).load instead of href redirect
 */
function initRowLinks(table, urlbase, target) {
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
}

/**
 * Add search behavior to all search fields in column footer
 * @param delay Delay in ms before starting request
 */
function initColumnSearch(table, delay) {
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
}

/**
 * Function reset
 *
 */
function resetColumnSearch(table) {
    table.api().columns().every(function () {
        this.search('');
        $('input, select', this.footer()).val('');
    });
    table.api().draw();
}
