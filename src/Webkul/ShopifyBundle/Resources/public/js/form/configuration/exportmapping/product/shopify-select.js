'use strict';
define(
    [
        'jquery',
        'underscore',
        'pim/initselect2',
        'pim/form/common/fields/simple-select-async',

    ],
    function (
        $,
        _,
        initSelect2,
        AddShopifyProductSelect
    ) {
        return AddShopifyProductSelect.extend({
            resultsPerPage: 10,
            className: 'AknFieldContainer shopify-product',
            events:{
                'change input': 'updateModel'
            },
            /**
             * {@inheritdoc}
             */
            postRender(templateContext) {
                const $select2 = this.$('.select2');
                initSelect2.init($select2, this.getSelect2Options(templateContext));

                const onChange = () => {
                    this.errors = [];
                    this.updateModel(this.getFieldValue($select2));
                    // this.getRoot().render();
                }

                $select2.on('change', onChange);
            },
            /**
             * Returns the options for Select2 library
             *
             * @returns {Object}
             */
            getSelect2Options() {
                var credentialId = this.getFormModel().attributes.credentialId;
                return {
                    ajax: {
                        url: this.choiceUrl + '/' + credentialId,
                        cache: true,
                        data: this.select2Data.bind(this),
                        results: this.select2Results.bind(this),
                        type: this.choiceVerb,
                    },
                    initSelection: this.select2InitSelection.bind(this),
                    placeholder: undefined !== this.config.placeholder ? __(this.config.placeholder) : ' ',
                    dropdownCssClass: '',
                    allowClear: this.allowClear,
                };
            },
            convertBackendItem(item) {
                return {
                    id: item.id,
                    text: item.title + " ( " + item.id + " )"
                };
            },
            updateModel(event) {
                const model = this.getFormModel();
                var value = '';

                if( typeof($(event.target).val()) !== 'undefined') {
                    value = $(event.target).val();
                } else {
                    value = event;
                }  
                const akeneoProductSku = value;
                model.set('shopifyProductId', akeneoProductSku);
                model.set('shopifyProductName', this.$("select option:selected").text());
            }, 
        });
    }
);

