/**
 * Table instance
 *
 */
var table = null;

/**
 * Timer instance
 *
 */
var oFilterTimerId = null;

/**
 * Add clickable behavior to table rows
 * Builds upon datatables-select. As soon as a row is selected, the link fires
 * Uses parameter rowLinkBase (e.g. controller + action link)
 */
function initRowLinks()
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
function initColumnSearch()
{
    table.api().columns().every( function () {
        var index = this.index();
        var lastValue = ''; // closure variable to prevent redundant AJAX calls
        $('input, select', this.footer()).on('keyup change', function () {
            if (this.value != lastValue) {
                lastValue = this.value;
                // -- set search
                table.api().column(index).search(this.value);
                window.clearTimeout(oFilterTimerId);
                oFilterTimerId = window.setTimeout(drawTable, params.delay);
            }
        });
    });
}

/**
 * Function reset
 *
 */
function reset()
{
    table.api().columns().every(function() {
        this.search('');
        $('input, select', this.footer()).val('');
        drawTable();
    });
}

/**
 * Draw table again after changes
 *
 */
function drawTable() {
    table.api().draw();
}
