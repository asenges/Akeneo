"use strict";

define(
    [
        'underscore',
        'oro/translator',
        'pim/form',
        'shopify/template/configuration/tab/credential',
    ],
    function(
        _,
        __,
        BaseForm,
        template,
    ) {
        return BaseForm.extend({
            isGroup: true,
            label: __('shopify.credentials.tab'),
            code: 'shopify_connector_credential',
            /**
             * {@inheritdoc}
             */
            configure: function () {
                this.trigger('tab:register', {
                    code: this.code,
                    label: this.label
                });
                
                return BaseForm.prototype.configure.apply(this, arguments);
            },

            /**
             * {@inheritdoc}
             */
            render: function () {
                $('.shopify-save-config').hide();
  
                this.delegateEvents();
                return BaseForm.prototype.render.apply(this, arguments);
            },

        });
    }
);
