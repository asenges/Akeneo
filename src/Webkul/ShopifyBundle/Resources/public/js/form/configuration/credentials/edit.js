'use strict';

define([
    'underscore',
    'pim/controller/front',
    'pim/form-builder',
    'pim/fetcher-registry',
    'pim/user-context',
    'pim/page-title',
    'pim/i18n'
],
function (
    _,
    BaseController,
    FormBuilder,
    fetcherRegistry,
    UserContext,
    PageTitle,
    i18n
) {
    return BaseController.extend({
        /**
         * {@inheritdoc}
         */
        renderForm: function (route) {

            if (!this.active) {
                return;
            }
            PageTitle.set({'credential.label': 'credential Edit'});
              /**
             * {@inheritdoc}
             */
            return $.when(      
                          
                FormBuilder.build('webkul-shopify-credential-edit-form'),
                $.get(Routing.generate('webkul_shopify_credentials_get',{ id: route.params.id} ))
            ).then((form, response) => {
                this.on('pim:controller:can-leave', function (event) {
                    form.trigger('pim_enrich:form:can-leave', event);
                });
                
                form.setData(response[0]);
                form.setElement(this.$el).render();

                return form;
            });
        }
    });
});
