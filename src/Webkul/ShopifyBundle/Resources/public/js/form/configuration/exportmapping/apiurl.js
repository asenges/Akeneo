define([
    'jquery',
    'underscore',
    'shopify/form/configuration/exportmapping/modal',
    'pim/form',
    'pim/user-context',
    'oro/translator',
    'pim/fetcher-registry',
    'shopify/template/configuration/exportmapping/apiurl',
    'pim/initselect2',
], function( 
    $,
    _,
    BaseModal,
    BaseForm,
    UserContext,
    __,
    FetcherRegistry,
    template,
    initSelect2
    ) {

    return BaseModal.extend({
        options: {},
        template: _.template(template), 

        events: {
            'change select': 'updateModel',
        } ,     
        updateModel(event) {
            const model = this.getFormModel();
            const credentialId = this.$('select').select2('val');
            model.set('credentialId', credentialId);
            model.set('apiUrl', this.$("select option:selected").text());
            this.parent.render();
        },
        render() {
            if (!this.configured) return this;
            const fetcher = FetcherRegistry.getFetcher('shopify-profiles');
            const selectedcredentialId = this.getFormModel().attributes.credentialId;
            fetcher.fetchAll().then(function (credentials) {
                credentials['0'] = "Select Credential";
                
                this.$el.html(this.template({
                    label: __('webkul_shopify_connector.form.configuration.export_mapping.properties.akeneo_apiUrl'),
                    model: typeof(this.getFormData().credentials != 'undefined') ? this.getFormData().credentials : [],
                    credentialId: selectedcredentialId,
                    credentials: credentials,
                    required: __('pim_enrich.form.required'),
                    errors: this.parent.validationErrors,
                    type: this.getFormData().type,        
                }));   
                initSelect2.init(this.$('select'))      
            }.bind(this));
            
            this.delegateEvents();
        }
    });
});
