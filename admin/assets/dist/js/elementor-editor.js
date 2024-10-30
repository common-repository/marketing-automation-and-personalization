"use strict";

(function ($, window) {
  function onFrontendInit() {
    elementor.hooks.addAction("panel/open_editor/widget/form", function (panel, model, view) {
      var Module = elementorModules.editor.utils.Module.extend({
        panel: null,
        getControl: function getControl(propertyName) {
          if (!this.panel) {
            return;
          }

          var control = this.panel.getCurrentPageView().collection.findWhere({
            name: propertyName
          });
          return control;
        },
        getControlView: function getControlView(propertyName) {
          if (!this.panel) {
            return;
          }

          var control = this.getControl(propertyName);
          var view = this.panel.getCurrentPageView().children.findByModelCid(control.cid);
          return view;
        },
        getControlValue: function getControlValue(id) {
          return this.getControlView(id).getControlValue();
        },
        getElementSettings: function getElementSettings(model, name) {
          if (!model) {
            return null;
          }

          var value = model.get('settings').get(name);
          return value instanceof window.Backbone.Collection ? value.toJSON() : value;
        }
      });
      var Form = Module.extend({
        onInit: function onInit() {
          elementor.channels.editor.on('section:activated', this.onSectionActivated);
        },
        getRepeaterItemsByLabel: function getRepeaterItemsByLabel(propertyName) {
          var items = {};
          var fieldItems = this.getElementSettings(this.model, propertyName);
          fieldItems.forEach(function (fieldItem) {
            items[fieldItem.custom_id] = fieldItem.field_label.length > 0 ? fieldItem.field_label : fieldItem.custom_id;
          });
          return items;
        }
      });
      var ConvesioConvert = Form.extend({
        panel: panel,
        model: model,
        action: 'convesioconvert',
        onSectionActivated: function onSectionActivated(activeSection, section) {
          var _this = this;

          if (activeSection !== "section_".concat(this.action)) {
            return;
          }

          if (section.model.id !== model.get('id')) {
            return;
          }

          this.getControlView('convesioconvert_mapping_fields').on('add:child', function () {
            _this.updateFieldMapping();
          });
          this.updateFieldMapping();
        },
        updateFieldMapping: function updateFieldMapping() {
          var _this2 = this;

          var fieldsMapControlView = this.getControlView('convesioconvert_mapping_fields');
          fieldsMapControlView.children.each(function (repeaterRow) {
            repeaterRow.children.each(function (repeaterRowField) {
              var fieldName = repeaterRowField.model.get('name');
              var fieldModel = repeaterRowField.model;

              if (fieldName === 'convesioconvert_attribute') {// fieldModel.set('options', this.getRemoteFields());
              } else if (fieldName === 'convesioconvert_form_field') {
                fieldModel.set('options', _this2.getFormFields());
              }

              repeaterRowField.render(); // Set -none- when a set field is removed.

              var $select = fieldsMapControlView.$el.find('select');
              Array.from($select).forEach(function (element) {
                if (element.selectedIndex < 0) {
                  element.selectedIndex = 0;
                }
              });
            });
          });
        },
        onElementChange: function onElementChange() {
          this.updateFieldMapping();
        },
        getRemoteFields: function getRemoteFields() {// return _.extend({}, { '': '- None -' }, this.getRepeaterItemsByLabel('form_fields'))
        },
        getFormFields: function getFormFields() {
          return _.extend({}, {
            '': '- None -'
          }, this.getRepeaterItemsByLabel('form_fields'));
        }
      });
      new ConvesioConvert({
        $element: view.$el
      });
    });
    elementor.hooks.addAction("panel/open_editor/widget/raven-form", function (panel, model, view) {
      var Module = elementorModules.editor.utils.Module.extend({
        panel: null,
        getControl: function getControl(propertyName) {
          if (!this.panel) {
            return;
          }

          var control = this.panel.getCurrentPageView().collection.findWhere({
            name: propertyName
          });
          return control;
        },
        getControlView: function getControlView(propertyName) {
          if (!this.panel) {
            return;
          }

          var control = this.getControl(propertyName);
          var view = this.panel.getCurrentPageView().children.findByModelCid(control.cid);
          return view;
        },
        getControlValue: function getControlValue(id) {
          return this.getControlView(id).getControlValue();
        },
        getElementSettings: function getElementSettings(model, name) {
          if (!model) {
            return null;
          }

          var value = model.get('settings').get(name);
          return value instanceof window.Backbone.Collection ? value.toJSON() : value;
        }
      });
      var Form = Module.extend({
        selectOptions: {
          "default": {
            '': 'Select one'
          },
          fetching: {
            fetching: 'Fetching...'
          },
          noList: {
            no_list: 'No list found'
          }
        },
        action: null,
        onInit: function onInit() {
          var _this3 = this;

          elementor.channels.editor.on('section:activated', this.onSectionActivated);

          if (this.onElementChange) {
            elementor.channels.editor.on('change', function (controlView, elementView) {
              _this3.onElementChange(controlView.model.get('name'), controlView, elementView);
            });
          }
        },
        updateList: function updateList(params) {
          var self = this; // Set fetching option.

          self.setOptions(this.selectOptions.fetching);
          self.setSelectedOption(); // Send AJAX request to fetch list.

          wp.ajax.send('raven_form_editor', {
            data: _.extend({}, {
              params: params
            }, {
              service: self.action,
              request: 'get_list'
            }),
            success: self.doSuccess
          });
        },
        updateFieldMapping: function updateFieldMapping() {
          var self = this;

          _.each(self.fields, function (field, fieldKey) {
            var control = self.getControl(fieldKey);
            var controlView = self.getControlView(fieldKey);
            var options = {};
            var fieldItems = self.getRepeaterItemsByLabel('fields', field.filter);

            _.extend(options, self.selectOptions["default"], fieldItems);

            self.setOptions(options, control, controlView);
          });
        },
        getListControl: function getListControl() {
          return this.getControl("".concat(this.action, "_list"));
        },
        getListControlView: function getListControlView() {
          return this.getControlView("".concat(this.action, "_list"));
        },
        setOptions: function setOptions(options) {
          var control = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;
          var controlView = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : null;

          if (control === null) {
            control = this.getListControl();
            controlView = this.getListControlView();
          }

          control.set('options', options);
          controlView.render();
        },
        setSelectedOption: function setSelectedOption() {
          var index = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 0;
          var controlView = this.getListControlView();
          controlView.$el.find('select').prop('selectedIndex', index);
        },
        getRepeaterItemsByLabel: function getRepeaterItemsByLabel(propertyName, filter) {
          var items = {};
          var fieldItems = this.getElementSettings(this.model, propertyName);

          _.filter(fieldItems, function (item) {
            if (filter && item.type !== filter) {
              return;
            }

            items[item._id] = item.label;
          });

          return items;
        }
      });
      var ConvesioConvert = Form.extend({
        panel: panel,
        model: model,
        action: 'convesioconvert',
        onSectionActivated: function onSectionActivated(activeSection, section) {
          var _this4 = this;

          if (activeSection !== "section_".concat(this.action)) {
            return;
          }

          if (section.model.id !== model.get('id')) {
            return;
          }

          this.getControlView('convesioconvert_mapping_fields').on('add:child', function () {
            _this4.updateFieldMapping();
          });
          this.updateFieldMapping();
        },
        updateFieldMapping: function updateFieldMapping() {
          var _this5 = this;

          var fieldsMapControlView = this.getControlView('convesioconvert_mapping_fields');
          fieldsMapControlView.children.each(function (repeaterRow) {
            repeaterRow.children.each(function (repeaterRowField) {
              var fieldName = repeaterRowField.model.get('name');
              var fieldModel = repeaterRowField.model;

              if (fieldName === 'convesioconvert_attribute') {// fieldModel.set('options', this.getRemoteFields());
              } else if (fieldName === 'convesioconvert_form_field') {
                fieldModel.set('options', _this5.getFormFields());
              }

              repeaterRowField.render(); // Set -none- when a set field is removed.

              var $select = fieldsMapControlView.$el.find('select');
              Array.from($select).forEach(function (element) {
                if (element.selectedIndex < 0) {
                  element.selectedIndex = 0;
                }
              });
            });
          });
        },
        onElementChange: function onElementChange() {
          this.updateFieldMapping();
        },
        getRemoteFields: function getRemoteFields() {// return _.extend({}, { '': '- None -' }, this.getRepeaterItemsByLabel('form_fields'))
        },
        getFormFields: function getFormFields() {
          return _.extend({}, {
            '': '- None -'
          }, this.getRepeaterItemsByLabel('fields'));
        }
      });
      new ConvesioConvert({
        $element: view.$el
      });
    });
  }

  function onElementorInit() {
    elementor.on('frontend:init', onFrontendInit);
  }

  $(window).on('elementor:init', onElementorInit);
})(jQuery, window);