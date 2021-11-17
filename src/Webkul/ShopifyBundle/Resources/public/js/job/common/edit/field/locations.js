'use strict';


define([
    'underscore',
    'pim/job/common/edit/field/field',
    'shopify/template/common/edit/field/locations',
    'pim/fetcher-registry',
    'oro/loading-mask',
    'jquery.select2',

], function (
    _,
    BaseField,
    fieldTemplate,
    fetcherRegistry,
    LoadingMask,
) {
    return BaseField.extend({
        fieldTemplate: _.template(fieldTemplate),
        events: {
            'change select': 'updateState'
        },
        empty: ['First, Save Credential'],
        data: ['Select Inventory Location'],

        /**
         * {@inheritdoc}
         */

        render: function () {
            var loadingMask = new LoadingMask();
            loadingMask.render().$el.appendTo(this.getRoot().$el).show();
            var self = this;
            
            fetcherRegistry.getFetcher('shopify-locations')
                .search({id: self.getFormData().configuration.shopCredential})
                .then(function (locations) {
                    loadingMask.hide().$el.remove();
                    self.config.options = locations;
                    model: self.getFormData();
                    self.$('.select2').select2();
                    BaseField.prototype.render.apply(self, arguments);
                }.bind(this));
        },

        /**
         * Get the field dom value
         *
         * @return {string}
         */
        getFieldValue: function () {
            return this.$('select').val();
        }
    });
});
