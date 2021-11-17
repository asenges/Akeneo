'use strict';
define(
    [
        'jquery',
        'underscore',
        'webkul/shopifyConnector/form/configuration/exportmapping/baseSelect',
    ],
    function (
        $,
        _,
        baseSelect,
    ) {
        return baseSelect.extend({
            resultsPerPage: 25,
            className: 'AknFieldContainer akn-category',
        });
    }
);