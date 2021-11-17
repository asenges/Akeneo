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
                'click *': 'gotoJobs'
            },
            intialize: function() {
                this.render();
            },
            /**
             * {@inheritdoc}
             */
            render: function () {
                this.$el.html(this.template({
                    label: _.__('shopify.import.view.jobs')
                }));
                this.$el.find('.AknButton.AknButton--apply.save').css('margin', '0px 2px');
            },

            /**
             * {@inheritdoc}
             */
            gotoJobs: function() {
                sessionStorage.setItem('import-profile-grid.filters','s%5Blabel%5D=-1&f%5Bjob_name%5D%5Bvalue%5D%5B%5D=&f%5Bconnector%5D%5Bvalue%5D%5B%5D=Shopify+Connector&t=import-profile-grid');
                var route = '#'+Routing.generate('pim_importexport_import_profile_index', {});
                window.location = route;
            },
        });
    }
);