"use strict";

function initDataTables(id, data, params) {
    var elem = jQuery(id);
    var table = elem.dataTable(data); // create new instance

    // call requested initializer methods
    for (var i = 0; i < params.calls.length; i++) {
        var fn = window[params.calls[i]];
        fn(table, params);
    }
}

/**
 * Add clickable behavior to table rows
 * Builds upon datatables-select. As soon as a row is selected, the link fires
 * Uses parameter rowLinkBase (e.g. controller + action link)
 */
function initRowLinks(table, params)
{
    table.api().on('select', function (e, dt, type, indexes) {
        var rowData = table.api().rows(indexes).data();
        var id = rowData[0].id;
        window.location.href = params.rowLinkBase + '/' + id;
    });
}

/**
 * Add search behavior to all search fields in column footer
 * Uses parameter 'delay' (milliseconds)
 */
function initColumnSearch(table, params) {
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
                timer = window.setTimeout(table.api().draw(), params.delay);
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
