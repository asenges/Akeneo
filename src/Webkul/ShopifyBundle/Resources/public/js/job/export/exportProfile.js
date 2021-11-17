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
                
                self.choices['0'] = "Select Credential";
                self.config.options = self.choices;

                var data = this.getFormData();
                if(typeof data.configuration.exportProfile === 'undefined') {
                    data.configuration.exportProfile = 0
                    this.getFormModel().set('configuration', data.configuration);
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
            if(this.$('select').val() == 0) {
                return '';
            }
            
            return this.$('select').val();
        },
        choices: null,

    });
});
