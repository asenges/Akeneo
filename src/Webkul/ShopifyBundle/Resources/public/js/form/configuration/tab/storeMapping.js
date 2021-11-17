"use strict";

define(
    [
        'underscore',
        'oro/translator',
        'pim/form',
        'shopify/template/configuration/tab/storeMapping',
        'pim/fetcher-registry',
        'oro/loading-mask',
        
    ],
    function(
        _,
        __,
        BaseForm,
        template,
        FetcherRegistry,
        LoadingMask,              
    ) {
        return BaseForm.extend({
            isGroup: true,
            label: __('shopify.store_mapping'),
            template: _.template(template),
            code: 'shopify_connector_store_mapping',
            events: {
                'change select': 'updateModel',
                'change input': 'updateData',
                'change .default': 'setDefaultLocale',
                'click .default': 'setDefaultLocale',
                'click .ShopifyStoreMapping-remove': 'closeHint',
            },
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
            currencies: null,
            locales: null,
            hidden: false,
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
                $('.shopify-save-config').show();

                var loadingMask = new LoadingMask();
                loadingMask.render().$el.appendTo(this.getRoot().$el).show();
                var formData = this.getFormData();
               
                var selectedApiVersion = typeof(formData['apiVersion']) !== 'undefined'
                                         && formData['apiVersion'] ? formData['apiVersion'] : '2021_04';

                if(selectedApiVersion) {
                    formData.apiVersion = selectedApiVersion;
                }
                this.setData(formData);
                var self = this; 

                self.$el.html(self.template({
                    model: self.getFormData(),
                    controls: self.controls,
                    locales: self.locales,
                }));
                loadingMask.hide().$el.remove();

                this.delegateEvents();

                return BaseForm.prototype.render.apply(this, arguments);
            },
            /**
             * Update model after value change
             *
             * @param {Event} event
             */
            updateData: function (event) {
                var data = this.getFormData();
                if($(event.target).attr('name')) {
                    data[$(event.target).attr('name')] = $(event.target).val();                    
                }
                this.setData(data);
            },
            /**
             * Update model after value change
             *
             * @param {Event} event
             */
            updateModel: function (event) {
                var data = this.getFormData();
                data.apiVersion = event.target.value;
                
                this.setData(data);

                this.render();

            },
            /**
             * Set the default locale value after change
             * 
             * @param {Event} event
             */
            setDefaultLocale: function (event) {
                var data = this.getFormData();
                if(!data['defaultLocale']) {
                    data['defaultLocale'] = {};
                }
                data['defaultLocale'] = event.target.value;
                this.setData(data);
                // this.render();
            },

            closeHint: function (event) {
                this.hidden = true;
                this.render();
            },
            data: null,
        });
    }
);
