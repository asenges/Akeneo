define([
    'jquery',
    'underscore',
    'shopify/form/configuration/create/modal',
    'pim/user-context',
    'oro/translator',
    'pim/fetcher-registry',
    'pim/initselect2',
    'shopify/template/configuration/tab/credential',
    'routing',
    'oro/messenger',
    'oro/loading-mask'
], function(
    $,
    _,
    BaseModal,
    UserContext,
    __,
    FetcherRegistry,
    initSelect2,
    template,
    Routing,
    messenger,
    LoadingMask
    ) {
 
    return BaseModal.extend({
        loadingMask: null,
            updateFailureMessage: __('error to fetch token'),
            updateSuccessMessage: __('pim_enrich.entity.info.update_successful'),
            isGroup: true,
            label: __('custom.credential.tab'),
            template: _.template(template),
            code: 'custom_connector_credential',
            controls: [{
                'label' : 'shopify.form.properties.shop_url.title',
                'name': 'shopUrl',
                'type': 'text'
            }, {
                'label' : 'shopify.form.properties.api_key.title',
                'name': 'apiKey',
                'type': 'password'
            }, {
                'label' : 'shopify.form.properties.password.title',
                'name': 'apiPassword',
                'type': 'password'
            },{
                'label' : 'shopify.form.properties.api.version',
                'name': 'apiVersion',
                'type': 'select',
                'options': { 
                    '2021_04': '2021-04'
                }
            }],
 
            errors: [],
            events: {
                'change .AknFormContainer-Credential input, select': 'updateModel',
            },
            
             /**
             * {@inheritdoc}
             */
            render: function () {
                 
                self = this;
                var controls;
                var controls2;
                var formData = this.getFormData();
               
                var selectedApiVersion = typeof(formData['apiVersion']) !== 'undefined'
                                         && formData['apiVersion'] ? formData['apiVersion'] : '2021_04';

                if(selectedApiVersion) {
                    formData.apiVersion = selectedApiVersion;
                }
                this.setData(formData);   
                this.$el.html(this.template({
                    controls: self.controls,
                    controls2: self.controls2,
                    credentials: self.getFormData(),
                    errors: this.parent.validationErrors
                }));
                initSelect2.init(this.$('select'))
                this.delegateEvents();
            },
            /**
             * Update model after value change
             *
             * @param {Event} event
             */
            updateModel: function (event) {
                var data = this.getFormData();
                switch(event.target.id) {
                    case 'pim_enrich_entity_form_shopUrl':
                        data['shopUrl'] = event.target.value
                        break;
                    case 'pim_enrich_entity_form_apiPassword':
                        data['apiPassword'] = event.target.value
                        break;
                    case 'pim_enrich_entity_form_apiKey':
                        data['apiKey'] = event.target.value
                        break;
                    case 'pim_enrich_entity_form_apiVersion':
                            data['apiVersion'] = event.target.value
                        break;
                }
                 
                this.setData(data);
            },
             
            stringify: function(formData) {
                if('undefined' != typeof(formData['mapping']) && formData['mapping'] instanceof Array) {
                    formData['mapping'] = $.extend({}, formData['mapping']);
                }
 
                return JSON.stringify(formData);                
            },
 
            /**
             * {@inheritdoc}
             */
            // getSaveUrl: function () {
            //     var route = Routing.generate('webkul_shopify_connector_configuration_post');
            //     return route;
            // },
            /**
             * Sets errors
             *
             * @param {Object} errors
             */
            setValidationErrors: function (errors) {
                this.parent.validationErrors = errors.response;
                this.render();
            },
 
            /**
             * Resets errors
             */
            resetValidationErrors: function () {
                this.parent.validationErrors = {};
                this.render();
            },
 
                        /**
             * Show the loading mask
             */
            showLoadingMask: function () {
                this.loadingMask = new LoadingMask();
                this.loadingMask.render().$el.appendTo(this.getRoot().$el).show();
            },
 
            /**
             * Hide the loading mask
             */
            hideLoadingMask: function () {
                this.loadingMask.hide().$el.remove();
            },
          
    });
});
