'use strict';

define(
    [
        'jquery',
        'underscore',
        'oro/translator',
        'backbone',
        'routing',
        'pim/form',
        'oro/loading-mask',
        'pim/router',
        'oro/messenger',
        'pim/template/form/creation/modal',
        'pim/common/property',
    ],
    function (
        $,
        _,
        __,
        Backbone,
        Routing,
        BaseForm,
        LoadingMask,
        router,
        messenger,
        template,
        propertyAccessor,
        Dialog
    ) {
        return BaseForm.extend({
            config: {},
            template: _.template(template),
            validationErrors: [],

            /**
             * {@inheritdoc}
             */
            initialize(meta) {
                this.config = meta.config;

                BaseForm.prototype.initialize.apply(this, arguments);
            },

            /**
             * {@inheritdoc}
             */
            render() {
                this.$el.html(this.template({
                    innerDescription: __(this.config.labels.content),
                    fields: null
                }));

                this.renderExtensions();

                return this;
            },

            /**
             * Opens the modal then instantiates the creation form inside it.
             * This function returns a rejected promise when the popin
             * is canceled and a resolved one when it's validated.
             *
             * @return {Promise}
             */
            open() {
                const deferred = $.Deferred();

                const modal = new Backbone.BootstrapModal({
                    title: __(this.config.labels.title),
                    subtitle: __(this.config.labels.subTitle),
                    picture: this.config.picture,
                    content: '',
                    okText: __('pim_common.save'),
                    okCloses: false,
                });
                modal.open();
                this.setElement(modal.$('.modal-body')).render();

                // TODO Find why this is used. Probably behats.
                modal.$('.modal-body').addClass('creation');

                modal.on('cancel', () => {
                    deferred.reject();
                    modal.remove();
                });

                modal.on('ok', this.confirmModal.bind(this, modal, deferred));

                return deferred.promise();
            },

            /**
             * Confirm the modal and redirect to route after save
             * @param  {Object} modal    The backbone view for the modal
             * @param  {Promise} deferred Promise to resolve
             */
            confirmModal(modal, deferred) {
                this.save().done(entity => {
                    modal.close();
                    modal.remove();
                    deferred.resolve();

                    let routerParams = {};

                    if (this.config.routerKey && this.config.entityIdentifierParamName) {
                        routerParams[this.config.routerKey] = propertyAccessor.accessProperty(
                            entity,
                            this.config.entityIdentifierParamName,
                            ''
                        );
                    } else if (this.config.routerKey) {
                        routerParams[this.config.routerKey] = entity[this.config.routerKey];
                    } else {
                        routerParams = {id: entity.meta.id};
                    }

                    messenger.notify('success', __(this.config.successMessage));

                    router.redirectToRoute(
                      this.config.editRoute,
                      routerParams
                  );
                });
            },

            /**
             * Normalize the path property for validation errors
             * @param  {Array} errors
             * @return {Array}
             */
            normalize(errors) {
                const values = errors.values || [];

                return values.map(error => {
                    if (!error.path) {
                        error.path = error.attribute;
                    }

                    return error;
                })
            },

            save() {
                this.validationErrors = {};

                const loadingMask = new LoadingMask();
                this.$el.empty().append(loadingMask.render().$el.show());

                let data = this.getFormData();

                return $.ajax({
                    url: Routing.generate(this.config.postUrl),
                    type: 'POST',
                    data: JSON.stringify(data)
                }).fail(function (response) {
                    this.validationErrors = response.responseJSON;
                    
                    if (!_.isEmpty(this.validationErrors)) {
                        messenger.notify('error', this.validationErrors.error);
                    }

                     this.getRoot().render();
                }.bind(this))
                .always(() => loadingMask.remove());
            }
        });
    }
);

