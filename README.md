# cakephp-datatables

[DataTables](https://www.datatables.net) is a jQuery plugin for intelligent HTML tables. Next to adding dynamic elements to the table, it also has great supports for on-demand data fetching and server-side processing. The _cakephp-datatables_ plugin makes it easy to use the functionality DataTables provides in your CakePHP 3 application. It consists of a helper to add DataTables to your view and a Component to transparently process AJAX requests made by DataTables.

## Versioning

* Versions 3.x are for users of CakePHP 3.5, ideally 3.6.
* Version 2.0 is available for older CakePHP installations, but will not receive features
* Version 1.0 is a tag available for people who let their code rot. Consider upgrading by only changing a couple of lines!
* Branch `php5` is for people without PHP 7 and currently stuck at version 1.0 (pull requests welcome)

## Requirements

* PHP 7
* CakePHP 3.x
* DataTables 1.10.x

## Installation and Usage

Please see the [Documentation][doc], esp. the [Quick Start tutorial][quickstart]

[doc]: https://github.com/ypnos-web/cakephp-datatables/wiki
[quickstart]: https://github.com/ypnos-web/cakephp-datatables/wiki/Quick-Start

## Credits

This work is based on the [code by Frank Heider](https://github.com/fheider/cakephp-datatables).

___
## IMPORTANT SECURITY NOTICE for users prior to Oct 24, 2017

The original code by fheider is vulnerable to SQL injection attacks, which was made apparent by a recent
[addition to the CakePHP documentation](https://github.com/cakephp/cakephp/commit/b2b45af37f807068f6c23f152fe6e5bf64656915).
The vulnerability is fixed by a [breaking change](https://github.com/ypnos-web/cakephp-datatables/commit/81929ad62d1e4041d00c1904f67771fec04ecd5f)
in all branches in this repository. It affects the ordering and filtering functionality of DataTables in conjunction with
server-side processing. If you are using a prior version of this plugin, you need to update it immediately and, if needed, change your code to
[allow ordering and filtering with server-side processing](https://github.com/ypnos-web/cakephp-datatables/wiki/Quick-Start#enable-dynamic-filters-and-ordering).
