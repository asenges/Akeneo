define([
    'jquery',
    'underscore',
    'pim/form',
    'pim/user-context',
    'oro/translator',
    'pim/initselect2',
    'shopify/template/configuration/exportmapping/type'
], function(
    $,
    _,
    BaseForm,
    UserContext,
    __,
    initSelect2,
    template
    ) {

    return BaseForm.extend({
        options: {},
        template: _.template(template),
        events: {
            'change select': 'updateModel'
        },

        /**
         * Model update callback
         */
        updateModel() {
            const model = this.getFormModel();
            const type = this.$('select').select2('val');
            model.set('type', type);

            this.toggleModal(type)
        },

        /**
         * Toggle category/typeduct fields
         */
        toggleModal(type) {
            if(type == 'category') {
                $('.AknFieldContainer.akn-category').show()
                $('.AknFieldContainer.shopify-category').show()
                $('.AknFieldContainer.akn-product').hide();
                $('.AknFieldContainer.shopify-product').hide();

            } else {
                $('.AknFieldContainer.akn-category').hide()
                $('.AknFieldContainer.shopify-category').hide()
                $('.AknFieldContainer.akn-product').show()
                $('.AknFieldContainer.shopify-product').show()
            }
        },

        /**
         * Renders the form
         *
         * @return {Promise}
         */

        render() {

            if (!this.configured) return this;

            const type = this.getFormData().type;
            const selectedType = type || 'category';
            // this.getFormModel().set('type', selectedType);


            this.toggleModal(selectedType)

            this.$el.html(this.template({
                label: __('webkul_shopify_connector.form.configuration.export_mapping.properties.type'),
                type: selectedType,
                required: __('pim_enrich.form.required'),
                types: [{
                        'code': 'category',
                        'label': __('webkul_shopify_connector.form.configuration.export_mapping.properties.type.category')
                    }, {
                        'code': 'product',
                        'label': __('webkul_shopify_connector.form.configuration.export_mapping.properties.type.product')
                    }]
            }));
            this.getFormModel().set('type', selectedType);

            initSelect2.init(this.$('select'))

            this.delegateEvents();
        }
    });

});
