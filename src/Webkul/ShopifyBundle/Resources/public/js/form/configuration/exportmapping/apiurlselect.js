'use strict';

define([
    'underscore',
    'oro/translator',    
    'pim/job/common/edit/field/field',
    'pim/template/export/common/edit/field/select',
    'pim/fetcher-registry',
    'jquery.select2',
], function (
    _,
    __,
    BaseField,
    fieldTemplate,
    fetcherRegistry
) {
    return BaseField.extend({
        fieldTemplate: _.template(fieldTemplate),
        events: {
            'change select': 'updateState'
        },

        /**
         * {@inheritdoc}
         */
        render: function () {
            var choices;

            if(!this.choices) {
                choices = fetcherRegistry.getFetcher('shopify-profiles').fetchAll();
            } else {
                choices = this.choices;
            }
            var self = this;

            Promise.all([choices]).then(function(values) {
                self.choices = values[0] ? values[0] : [];
                self.choices[0] = "Select API Url";
                self.config.options = self.choices;

                var data = this.getFormData();


                if(typeof data.apiUrl === 'undefined') {
                    data.apiUrl = ''
                    this.getFormModel().set('apiUrl', data.apiUrl);
                    sessionStorage.setItem('current_form_tab', 'pim-job-instance-credentials');
                    this.render();
                }
                BaseField.prototype.render.apply(self, arguments);
                self.$('.select2').select2();
            }.bind(this));
        },

        /**
         * Get the field dom value
         *
         * @return {string}
         */
        getFieldValue: function () {
            return this.$('select').val();
        },
        choices: null,

    });
});
