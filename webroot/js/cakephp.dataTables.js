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

function initDataTables(id, data, params) {
    /* Use text renderer by default. Escapes HTML. */
    $.each(data.columns, function(i, val) {
        if (!val.render) {
            data.columns[i].render = $.fn.dataTable.render.text();
        }
    });

    /* determine table height by default in scrolling case */
    if (data.scrollY === true) {
        data.height = data.scrollY = calculateHeight(id);
    }

    /* create new instance */
    var table = $(id).dataTable(data);

    /* call requested initializer methods */
    for (var i = 0; i < params.calls.length; i++) {
        var fn = window[params.calls[i]];
        fn(table, params);
    }
}

/**
 * Add clickable behavior to table rows
 * Builds upon datatables-select. As soon as a row is selected, the link fires
 * Uses parameter rowLink:
 * .url (e.g. controller + action link)
 * .type 'href' (default) or 'load'
 * .target selector for load
 */
function initRowLinks(table, params) {
    table.api().on('select', function (e, dt, type, indexes) {
        var row = table.api().rows(indexes);
        var rowData = row.data();
        var id = rowData[0].id;
        var url = params.rowLink.url + '/' + id;
        if (params.rowLink.type === 'load') {
            $(params.rowLink.target).load(url);
            table.api().rows(indexes).deselect(); // revert selection
        } else {
            window.location.href = url;
        }
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
