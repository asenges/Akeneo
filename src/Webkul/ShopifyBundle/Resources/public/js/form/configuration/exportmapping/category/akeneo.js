define([
    'jquery',
    'underscore',
    'shopify/form/configuration/exportmapping/modal',
    'pim/user-context',
    'oro/translator',
], function(
    $,
    _,
    BaseModal,
    UserContext,
    __,
    ) {

    return BaseModal.extend({
        /**
         * Renders the form
         *
         * @return {Promise}
         */
        render() {
            if (!this.configured) return this;

            const akeneoCategoryId = this.getFormData().akeneoCategoryId;
            const selectedAkeneoCategoryId = akeneoCategoryId;

            this.$el.html(this.template({
                label: __('webkul_shopify_connector.form.configuration.export_mapping.properties.akeneo_category'),
                akeneoCategoryId: selectedAkeneoCategoryId,
                required: __('pim_enrich.form.required'),
                error: this.parent.validationErrors['akeneoCategoryId'],
                type: this.getFormData().type,
                locale: UserContext.get('uiLocale'),
                fields: null
            }));

            this.getFormModel().set('akeneoCategoryId', selectedAkeneoCategoryId);
            this.getFormModel().set('akeneoCategoryName', this.$("select option:selected").text());

            this.delegateEvents();
        }
    });
});
