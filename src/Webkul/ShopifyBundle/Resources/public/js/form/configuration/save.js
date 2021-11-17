'use strict';

define([
        'underscore',
        'jquery',
        'routing',
        'pim/form/common/save',
        'pim/template/form/save'
    ],
    function(
        _,
        $,
        Routing,
        SaveForm,
        template
    ) {
        return SaveForm.extend({
            template: _.template(template),
            currentKey: 'current_form_tab',
            events: {
                'click .save': 'save'
            },


            initialize: function (config) {
                this.config = config.config;
            },


            /**
             * {@inheritdoc}
             */
            render: function () {
                this.$el.html(this.template({
                    label: _.__('pim_enrich.entity.save.label')
                }));

                var saveBtn = this.$('.save');
                saveBtn.addClass('shopify-save-config');
                
                if(sessionStorage && sessionStorage.getItem(this.currentKey) && 
                    ["webkul-shopify-connector-configuration-tab-documentation", "webkul-shopify-connector-configuration-tab-credential"].indexOf(sessionStorage.getItem(this.currentKey)) !== -1) {
                    saveBtn.hide();
                } else {
                    saveBtn.show();
                }

            },

            /**
             * {@inheritdoc}
             */
            save: function () {
                this.getRoot().trigger('pim_enrich:form:entity:pre_save', this.getFormData());
                this.showLoadingMask();

                var data = this.stringify(this.getFormData());
                $.ajax({
                    method: 'POST',
                    url: this.getSaveUrl(),
                    contentType: 'application/json',
                    data: data
                })
                .then(this.postSave.bind(this))
                .fail(this.fail.bind(this))
                .always(this.hideLoadingMask.bind(this));
            },
            /**
             * @var dataWrapper
             */
            dataWrapper: 'credentials',

            stringify: function(formData) {
                if('undefined' != typeof(formData['settings']) && formData['settings'] instanceof Array) {
                    formData['settings'] = $.extend({}, formData['settings']);
                }
                if(typeof(formData[this.dataWrapper]) === 'undefined' || typeof(formData[this.dataWrapper]) !== 'object' || formData[this.dataWrapper] instanceof Array) {
                    formData[this.dataWrapper] = {};
                }

                return JSON.stringify(formData);
            },

            /**
             * {@inheritdoc}
             */
            getSaveUrl: function () {
                var tab = null;
                switch(sessionStorage.getItem(this.currentKey)) {
                    case 'webkul-shopify-connector-configuration-tab-credential':
                        tab = 'credential';
                        break;
                    case 'webkul-shopify-connector-configuration-tab-exportsettings':
                        tab = 'exportMapping';
                        break;
                    case 'webkul-shopify-connector-configuration-tab-importsettings':
                        tab = 'importMapping';
                        break;
                    case 'webkul-shopify-connector-configuration-tab-other-settings':
                        tab = 'otherSettings';
                        break;

                }
                var route = Routing.generate(__moduleConfig.route);
                (sessionStorage.getItem(this.currentKey));

                return tab ? route + '/'+ tab: route;
            },

            /**
             * {@inheritdoc}
             */
            postSave: function (data) {

                this.setData(data);
                this.getRoot().trigger('pim_enrich:form:entity:post_fetch', data);
                SaveForm.prototype.postSave.apply(this, arguments);
            }
        });``
    }
);
