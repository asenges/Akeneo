"use strict";

define(
    [
        'underscore',
        'oro/translator',
        'pim/form',
        'shopify/template/configuration/tab/exportsettings',
        'pim/router',
        'jquery',
        'routing',
        'pim/fetcher-registry',
        'pim/user-context',
        'oro/loading-mask',
        'pim/initselect2'
    ],
    function(
        _,
        __,
        BaseForm,
        template,
        router,
        $,
        Routing,
        FetcherRegistry,
        UserContext,
        LoadingMask,
        initSelect2
    ) {
        return BaseForm.extend({
            isGroup: true,
            label: __('shopify.exportsettings.tab'),
            template: _.template(template),
            code: 'shopify_connector_settings',
            errors: [],
            events: {
                'click .field-checkbox-input': 'toggleMultiselect',
                'change .AknFormContainer-Mappings input': 'updateModel',
                'change .shopify-settings select.label-field': 'updateModel',
                'click  .shopify-settings .AknCatalogVolume-hint .AknButton--mapping': 'redirectToMappingPage'
            },
            fields: null,
            attributes: null,
            fieldsUrl: 'webkul_shopify_connector_configuration_action',
            currencies: [],
            associations: [],

            /**
             * {@inheritdoc}
             */
            configure: function () {
                this.listenTo(
                    this.getRoot(),
                    'pim_enrich:form:entity:bad_request',
                    this.setValidationErrors.bind(this)
                );

                this.listenTo(
                    this.getRoot(),
                    'pim_enrich:form:entity:pre_save',
                    this.resetValidationErrors.bind(this)
                );

                this.listenTo(
                    this.getRoot(),
                    'pim_enrich:form:entity:post_fetch',
                    this.render.bind(this)
                );

                this.trigger('tab:register', {
                    code: this.code,
                    label: this.label
                });

                return BaseForm.prototype.configure.apply(this, arguments);
            },

            redirectToMappingPage: function(event) {
                event.preventDefault();

                router.redirectToRoute('webkul_shopify_data_grid');
            },

            /**
             * {@inheritdoc}
             */
            render: function () {
                var loadingMask = new LoadingMask();
                loadingMask.render().$el.appendTo(this.getRoot().$el).show();
                var fields;
                var attributes;
                var currencies;
                var associations;

                if(this.fields && this.attributes) {
                    fields = this.fields;
                    attributes = this.attributes;
                    associations = this.associations;
                } else {
                    var self = this;
                    fields = FetcherRegistry.getFetcher('shopify-fields').fetchAll()
                    attributes = FetcherRegistry.getFetcher('attribute').search({options: {'page': 1, 'limit': 10000 } });
                    associations = FetcherRegistry.getFetcher('association-type').search({options: {'page': 1, 'limit': 10000 } });
                }

                currencies = FetcherRegistry.getFetcher('shopify-quickcurrencies').fetchAll();
 
                var self = this;
                Promise.all([fields, attributes, currencies, associations]).then(function (values) {
                    $('#container .AknButtonList[data-drop-zone="buttons"] div:nth-of-type(1)').show();
                    self.fields = values[0];
                    self.attributes = self.getExtendedAttributes(values[1]);
                    self.currencies = values[2];
                    self.associations = values[3];
                    var imageAttrs = self.sortImages(values[1], self.getFormData());
                    
                    self.$el.html(self.template({
                        fields: self.updateFieldsType(self.fields),
                        model: self.getFormData(),
                        errors: self.errors,
                        attributes: self.attributes,
                        imageAttrs: imageAttrs,
                        currencies: self.currencies,
                        currentLocale: UserContext.get('uiLocale'),
                        associations: self.associations
                    }));

                    $('.shopify-settings .select2').each(function(key, select) {
                        if($(select).attr('readonly')) {
                            $(select).select2().select2('readonly', true);
                        } else {
                            $(select).select2();
                        }
                    });
                    $('.shopify-save-config').show();

                    self.delegateEvents();

                    $('.shopify-settings *[data-toggle="tooltip"]').tooltip();

                    loadingMask.hide().$el.remove();
                }.bind(self));

                return BaseForm.prototype.render.apply(self, arguments);
            },

            dataWrappers: [ 'defaults', 'settings', 'others', 'quicksettings' ],
            /**
             * Update model after value change
             *
             * @param {Event} event
             */
            updateModel: function (event) {
                var index = $(event.target).attr('data-wrapper') ? $(event.target).attr('data-wrapper') : 'others';
                var data = this.getFormData();

                $.each(this.dataWrappers, function(key, value) {
                    if(typeof(data[value]) === 'undefined' || typeof(data[value]) !== 'object' || data[value] instanceof Array) {
                        data[value] = {};
                    }
                });

                if($(event.target).hasClass('quicksettings')){
                    index = 'quicksettings';
                }

                if(['defaults', 'settings'].indexOf(index) !== -1) {
                    var target = $(event.target);
                    var selectorStr = (index == 'defaults') ? 'settings' : 'defaults';
                    var otherElem = $('*[name=' + $(event.target).attr("name") + '][data-wrapper=' + selectorStr + ']');
                    /* if value is set  */
                    if('undefined' !== typeof(target.val()) && (target.val() || 0 === target.val()) && (target.val().indexOf(' ') === -1 || index === 'defaults') ) {
                        if(otherElem.is('select')) {
                            otherElem.select2('readonly', true);
                        } else {
                            otherElem.attr('readonly', 'readonly');
                        }

                        if(index === 'defaults') {
                            data['settings'][$(event.target).attr('name')] = '';
                        } else if(index === 'settings') {
                            data['defaults'][$(event.target).attr('name')] = '';
                        }
                    } else {
                        /* if value is unset  */
                        $(event.target).val('');
                        let selectField = $(event.target).attr('name');
                        var defaultVal = _.find(this.fields, function(field) {
                            if(field.name === selectField) {
                                return field;
                            }
                        });

                        if(typeof defaultVal != 'undefined' && defaultVal.default) {
                        if(otherElem.is('select')) {
                            otherElem.select2('readonly', false);
                        } else {
                            otherElem.removeAttr('readonly');
                        }
                    }
                }
            }

                var attrValue;

                if($(event.target).hasClass('select2') && $(event.target).select2('data') instanceof Array) {
                    attrValue = $(event.target).select2('data')
                    attrValue = attrValue.map(function(obj) { return obj.id });
                } else if($(event.target).hasClass('meta_fields_shopify')) {
                    attrValue = $(event.target).val() ? $(event.target).val() : [];
                } else {
                    attrValue = $(event.target).val();
                }

                data[index][$(event.target).attr('name')] = attrValue;

                if(typeof(data['settings']) == 'undefined' || typeof(data['settings']['handle']) == 'undefined' || data['settings']['handle'] == '' || data['settings']['handle'] == null) {

                    if(typeof(data['others']) != 'undefined' && typeof(data['others']['meta_fields_associations']) != 'undefined' && data['others']['meta_fields_associations'].length != 0 ) {
                        data['others']['meta_fields_associations'] = [];
                    }
                }

                this.setData(data);
                this.render();
            },

            sortImages: function(data,  fields) {
                var imageData = [];
                var alreadyImages = (typeof(fields.others) !== 'undefined' && typeof(fields.others.images) !== 'undefined') ? fields.others.images : [];
                for(var i=0; i < data.length; i++) {
                    if(data[i].type === 'pim_catalog_image') {
                        imageData.push(data[i]);
                    }
                }
                imageData.sort(function(a, b) {
                    var textA = alreadyImages.indexOf(a.code);
                    var textB = alreadyImages.indexOf(b.code);
                    return (textA < textB) ? -1 : (textA > textB) ? 1 : 0;
                });
                return imageData;
            },

            /**
             * Sets errors
             *
             * @param {Object} errors
             */
            setValidationErrors: function (errors) {
                this.errors = errors.response;
                this.render();
            },

            /**
             * Resets errors
             */
            resetValidationErrors: function () {
                this.errors = {};
                this.render();
            },

            /**
             *  change the multiselect to toggle form true/false
             */
            toggleMultiselect: function (event) {
               var newfield =  _.find(this.fields, function(f){
                    if(f.name ===  $(event.target).attr("name")) {
                        return f;
                    }
                })
                if(newfield) {
                    newfield.multiselect = !newfield.multiselect;
                }
                var data = this.getFormData();

                if(!newfield.multiselect && _.isArray(data['settings'][newfield.name]) ) {
                    data['settings'][newfield.name] = _.last(data['settings'][newfield.name]);
                }

                if(typeof data['multiselect'] === 'undefined' || typeof data['multiselect'] !== 'object' || data['multiselect'] instanceof Array) {
                    data['multiselect'] = {};
                }


                data['multiselect'][newfield.name] = newfield.multiselect;
                this.setData(data);
                this.render();
            },

            /**
             * return the attributes with add extended attributes
             */
            getExtendedAttributes:function(attributes) {

                var extendedAttributes = [
                    { code : "family", labels : {en_US: "Family"},type : "pim_catalog_simpleselect" },
                    { code : "GroupCode", labels : {en_US: "GroupCode"},type : "pim_catalog_simpleselect" },
                    // { code : "GroupLabel", labels : {en_US: "GroupLabel"},type : "pim_catalog_simpleselect" },
                ];
                this.mergeAttributesByProperty(extendedAttributes, attributes , 'code');

                return extendedAttributes;
            },

            /**
             * merge attributes based on properties
             */
            mergeAttributesByProperty: function(arr1, arr2, prop) {
                _.each(arr2, function(arr2obj) {
                    var arr1obj = _.find(arr1, function(arr1obj) {
                        return arr1obj[prop] === arr2obj[prop];
                    });

                    arr1obj ? _.extend(arr1obj, arr2obj) : arr1.push(arr2obj);
                });
            },

            /**
             * updated fields type if multiselect enable
             */
            updateFieldsType: function(fields) {
                var newFields = $.extend(true,{},fields);

                return _.map(newFields, function(f) {
                    if(f.multiselect) {
                        f.types = [
                            'pim_catalog_text',
                            'pim_catalog_textarea',
                            'pim_catalog_date',
                            'pim_catalog_metric',
                            'pim_catalog_multiselect',
                            'pim_catalog_number',
                            'pim_catalog_simpleselect',
                            'pim_catalog_boolean',
                            'pim_catalog_price_collection',
                            'pim_catalog_identifier',
                        ];
                        f.tooltip = 'supported attributes types: textarea, text, price, date, metric, select, multiselect, number, yes/no, identifier';

                    }

                    return f;
                });
            },

        });
    }
);
