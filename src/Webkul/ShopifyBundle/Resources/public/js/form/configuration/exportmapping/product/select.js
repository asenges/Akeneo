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
        AddProductSelect
    ) {
        return AddProductSelect.extend({
            resultsPerPage: 5,
            className: 'AknFieldContainer akn-product',
            // events:{
            //     'change input': 'updateModel'
            // },
            convertBackendItem(item) {
                return {
                    id: item,
                    text: item
                };
            },
            // updateModel(event) {
            //     const model = this.getFormModel();
            //     const akeneoProductSku = $(event.target).val();
            //     model.set('akeneoProductSku', akeneoProductSku);
            //     model.set('akeneoProductName', this.$("select option:selected").text());
            // },
        });
    }
);

