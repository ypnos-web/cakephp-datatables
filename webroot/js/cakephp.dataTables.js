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
 * Default filter delay to optimize performance
 * @type {number}
 */
var delay = 600;

/**
 * Add search behavior to all search fields in column footer
*/
function initSearch ()
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
                oFilterTimerId = window.setTimeout(drawTable, delay);
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
