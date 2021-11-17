define([
    'jquery',
    'underscore',
    'shopify/form/configuration/exportmapping/modal',
    'pim/user-context',
    'oro/translator',
    'pim/fetcher-registry',
    'pim/initselect2',
], function(
    $,
    _,
    BaseModal,
    UserContext,
    __,
    FetcherRegistry,
    initSelect2,
    ) {

    return BaseModal.extend({
        /**
         * Renders the form
         *
         * @return {Promise}
         */
        render() {
            if (!this.configured) return this;

            const akeneoProductSku = this.getFormData().akeneoProductSku;
            const akeneoProductName = this.getFormData().akeneoProductName;
            const selectedAkeneoProductSku = akeneoProductSku;
            this.$el.html(this.template({
                label: __('webkul_shopify_connector.form.configuration.export_mapping.properties.akeneo_product'),
                akeneoProductSku: selectedAkeneoProductSku,
                required: __('pim_enrich.form.required'),
                error: this.parent.validationErrors['akeneoProductSku'],
                type: this.getFormData().type,
                fields: null
            }));

            this.getFormModel().set('akeneoProductSku', selectedAkeneoProductSku);
            this.getFormModel().set('akeneoProductName',this.$("select option:selected").text());

            this.delegateEvents();
        }
    });
});
