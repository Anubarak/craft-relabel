(function ($) {
    if (!window.Craft || !window.jQuery || Craft === 'undefined') {
        return false;
    }

    // change field source buttons
    var e = Craft.CustomizeSourcesModal.Source;
    Craft.CustomizeSourcesModal.Source = e.extend({
        createTableColumnOption: function (key, label, first, checked) {
            var elementIndex = Craft.elementIndex;
            if (elementIndex) {
                var selectedSource = Craft.elementIndex.$source;
                if (selectedSource) {
                    var sourceId = selectedSource.data('key');
                    if (sourceId && typeof Craft.RelabelSourceFields !== 'undefined' &&
                        typeof Craft.RelabelSourceFields[sourceId] !== 'undefined' &&
                        typeof Craft.RelabelSourceFields[sourceId][key] !== 'undefined') {
                        label = Craft.RelabelSourceFields[sourceId][key].label;
                    }
                }
            }
            var $option = $('<div class="customize-sources-table-column"/>')
                .append('<div class="icon move"/>')
                .append(
                    Craft.ui.createCheckbox({
                        label: label,
                        name: 'sources[' + this.sourceData.key + '][tableAttributes][]',
                        value: key,
                        checked: checked,
                        disabled: first
                    })
                );

            if (first) {
                $option.children('.move').addClass('disabled');
            }

            return $option;
        }
    });

    // change table index buttons
    Garnish.on(Craft.BaseElementIndex, 'updateElements', function(e, a){
        var elementIndex = Craft.elementIndex;
        if(elementIndex){
            var view = elementIndex.view;
            var selectedSource = elementIndex.$source;
            if(view && selectedSource.length){
                var sourceId = selectedSource.data('key');
                if(sourceId && typeof Craft.RelabelSourceFields !== 'undefined' &&
                typeof Craft.RelabelSourceFields[sourceId] !== 'undefined'){
                    var table = view.$table;
                    var cols = table.find('thead > tr > th');
                    $.each(cols, function(i, e){
                        var element = $(e);
                        var key = element.data('attribute');
                        if (typeof Craft.RelabelSourceFields[sourceId][key] !== 'undefined') {
                            element.html(Craft.RelabelSourceFields[sourceId][key].label);
                        }
                    })
                }
            }
        }
    });

    Craft.Relabel = Garnish.Base.extend({
        elementEditors: {},
        labelsForLayout: [],
        fields: null,
        labels: null,
        layouts: null,
        hud: null,
        currentFieldId: null,
        values: {},
        container: '',
        fieldLayoutIndex: 0,
        _refreshProxy: null,
        closeHud: function () {
            this.hud.hide();
            this.hud.destroy();
            delete this.hud;
        },
        refreshFieldLayout: function () {
            var self = this;
            var fieldLayouts = $('.fieldlayoutform');
            $.each(fieldLayouts, function (index, e) {
                var $layout = $(e);
                var fields = self.getFieldsForLayout($layout);
                var layoutId = self.getFieldLayoutId($layout);

                $.each(fields, function (index, item) {
                    self.values[item.fieldId + '-' + layoutId] = {
                        name: item.name,
                        instructions: item.instructions
                    };
                    self.getHiddenInput(item.fieldId, 'name', item.name, layoutId);
                    self.getHiddenInput(item.fieldId, 'instructions', item.instructions, layoutId);
                    self.toggleLineSpan(item.fieldId, self.values[item.fieldId + '-' + layoutId].name, $layout);
                });

                self.setup($layout, layoutId);
            });
        },
        init: function (data) {
            this.labels = data.labels;
            this.labelsForLayout = data.labelsForLayout;
            this.refreshFieldLayout();
            this._refreshProxy = $.proxy(this, 'refresh');
            if (this.labelsForLayout.length) {
                this.applyLabels(this.labelsForLayout, true);
            }
        },
        showCustomizeSourcesModal: function (e) {
            var target = typeof e.target !== 'undefined' ? e.target : null;
            if (target !== null) {
                var container = target.$sourceSettingsContainer;
                if (container.length !== 0) {
                    window.setTimeout(function () {

                        console.log(container);
                        debugger;
                        var cols = container.find('.customize-sources-table-column');
                        console.log(cols);
                    }.bind(this), 500);
                }
            }
        },
        changeEntryType: function (fields) {
            var self = this;
            this.labelsForLayout = fields;

            setTimeout(function () {
                self.applyLabels(fields, true);
            }, 20);
        },
        initElementEditor: function (fields, layoutId) {
            var self = this;
            var now = new Date().getTime(),
                doInitElementEditor = (function () {
                    var timestamp = new Date().getTime(),
                        $elementEditor = $('.elementeditor:last'),
                        $hud = $elementEditor.length > 0 ? $elementEditor.closest('.hud') : false,
                        elementEditor = $hud && $hud.length > 0 ? $hud.data('elementEditor') : false;
                    if (elementEditor && elementEditor.hud) {
                        this.elementEditors[elementEditor._namespace] = elementEditor;
                        elementEditor.hud.on('hide', $.proxy(this.destroyElementEditor, this, elementEditor));
                        setTimeout(function () {
                            self.applyLabels(fields, false);
                        }, 20);
                        //Garnish.requestAnimationFrame(this.applyLabels(fields).bind(this));
                    } else if (timestamp - now < 2000) { // Poll for 2 secs
                        Garnish.requestAnimationFrame(doInitElementEditor);
                    }
                }).bind(this);
            doInitElementEditor();
        },
        destroyElementEditor: function (elementEditor) {
            if (this.elementEditors.hasOwnProperty(elementEditor._namespace)) {
                delete this.elementEditors[elementEditor._namespace];
            }
        },
        refresh: function () {
            this.applyLabels(this.labelsForLayout, true);
        },
        applyLabels: function (labels, includeDescription) {
            var self = this;
            var $target;
            var target;

            // apply matrix handler
            var matrixFields = $('.matrix-field');
            $.each(matrixFields, function (index, item) {
                var element = $(item);
                Garnish.requestAnimationFrame(function () {
                    var matrixPlugin = element.data('matrix');
                    if (matrixPlugin) {
                        matrixPlugin.off('blockAdded', this._refreshProxy);
                        matrixPlugin.on('blockAdded', this._refreshProxy);
                    }
                }.bind(this));

            }.bind(this));

            if (this.elementEditors && Object.keys(this.elementEditors).length) {
                for (var key in this.elementEditors) {
                    target = this.elementEditors[key].$form;
                }
            }
            if (typeof target === 'undefined') {
                $target = $(this.getFieldContextSelector());
            } else {
                $target = target;
            }

            if (!$target || !$target.length) {
                return false;
            }

            // Add CpFieldLinks to regular fields
            var fieldData = {},
                $fields = $target.find('.field'),
                $field,
                fieldHandle;

            $fields.each(function (index, item) {
                $field = $(item);
                fieldHandle = self.getFieldHandleFromElement($field);

                if (fieldHandle) {
                    var span = $field.find('.heading:first label');
                    var newLabel = self.getLabelForField(fieldHandle, labels);
                    var data = this.getDataForField(fieldHandle, labels);
                    if (newLabel !== false) {
                        var spanContainer = span.contents();
                        if (spanContainer.length) {
                            spanContainer[0].data = newLabel + ' ';
                        }
                    }
                    var newInstruction = self.getInstructionForField(fieldHandle, labels);
                    if (newInstruction !== false && includeDescription) {
                        var instructionContainer = $field.find('.heading:first .instructions');
                        if (!instructionContainer.length) {
                            $field.find('.heading:first').append('<div class="instructions"></div>');
                            instructionContainer = $field.find('.heading:first .instructions');
                        }
                        instructionContainer.html(newInstruction);
                    }

                    // switch error message
                    if ($field.hasClass('has-errors') === true && data.name) {
                        var errors = $field.find('.errors > li');
                        $.each(errors, function (index, item) {
                            var $item = $(item);
                            var text = $item.text();
                            var newError = text.replace(data.oldName, data.name);
                            $item.text(newError);
                        });
                    }
                }
                $field.attr('data-relabel', true);
            }.bind(this));
            //}
        },
        getDataForField: function (handle, allFields) {
            var data = {};
            $.each(allFields, function (index, item) {
                if (item.handle === handle) {
                    data = item;
                    return data;
                }
            });
            return data;
        },
        getLabelForField: function (handle, allFields) {
            var newLabel = false;
            $.each(allFields, function (index, item) {
                if (item.handle === handle) {
                    newLabel = item.name;
                    return item.name;
                }
            });
            return newLabel;
        },
        getInstructionForField: function (handle, allFields) {
            var instruction = false;
            $.each(allFields, function (index, item) {
                if (item.handle === handle) {
                    instruction = item.instructions;
                    return item.instructions;
                }
            });
            return instruction;
        },
        /**
         *
         * @param $field
         * @return {string|boolean}
         */
        getFieldHandleFromElement: function ($field) {
            var value = $field.attr('id');
            if (!value) return false;
            value = value.split('-');

            /**
             * Neo Support - maybe useless.. Maybe I'll add it
             * if there are any future requests
             */
            if (value.length === 6) {
                var fieldHandle = value[1];
                var id = value[2];
                var input = $('[name="fields[' + value[1] + '][' + id + '][type]"]');
                if (input.length) {
                    var typeHandle = input.val();
                    return fieldHandle + '.' + typeHandle + '.' + value[4];
                }
            }

            // it's a variant
            if (value.length >= 4 && value[0] === 'variants') {
                return 'variants.' + value[3];
            }

            if (value.length < 3) return false;
            return value[value.length - 2];
        },
        onKeyDown: function (ev) {
            ev.preventDefault();
            var btn = $(ev.target);
            if (btn.data('menubtn')) {
                var menu = btn.data('menubtn');
                var options = menu.menu.$menuList;
                this.currentFieldId = btn.parent().data('id');
                if (options.find('.relabel').length === 0) {
                    options.append('<li><a data-id="' + this.currentFieldId + '" class="relabel" data-action="showRelabelMenu">Relabel</a></li>')
                }
            }
        },
        setup: function ($layout, layoutId) {
            var e = this;
            var designer = Craft.FieldLayoutDesigner;
            designer.prototype.initField = function ($field) {
                var $editBtn = $field.find('.settings'),
                    $menu = $('<div class="menu" data-align="center"/>').insertAfter($editBtn),
                    $ul = $('<ul/>').appendTo($menu);
                var fieldId = $editBtn.parent().data('id');
                // destroy the prev one

                if ($field.hasClass('fld-required')) {
                    $('<li><a data-action="toggle-required">' + Craft.t('app', 'Make not required') + '</a></li>').appendTo($ul);
                } else {
                    $('<li><a data-action="toggle-required">' + Craft.t('app', 'Make required') + '</a></li>').appendTo($ul);
                }

                $('<li><a data-action="remove">' + Craft.t('app', 'Remove') + '</a></li>').appendTo($ul);
                $('<li><a data-id="' + fieldId + '" class="relabel" data-action="showRelabelMenu">Relabel</a></li>').appendTo($ul);

                var button = new Garnish.MenuBtn($editBtn, {
                    onOptionSelect: $.proxy(this, 'onFieldOptionSelect')
                });

                var layout = this.$container;
                var layoutId = e.getFieldLayoutId(layout);

                button.on('optionSelect', function (option) {
                    var $option = $(option.option),
                        $field = $option.data('menu').$anchor.parent(),
                        action = $option.data('action');
                    var fieldId = $($field).data('id');
                    if (action === 'showRelabelMenu') {
                        e.showHoverMenu($field, fieldId, layout, layoutId);
                    }
                })
            };
            var icons = $layout.find('.icon.settings');
            $.each(icons, function (index, btn) {
                var btn = $(btn);
                if (btn.data('menubtn')) {
                    btn.on('click', function (event) {
                        var fieldId = btn.parent().data('id');
                        var list = btn.data('menubtn').menu.$menuList;
                        var relabelButton = list.find('.relabel[data-action="showRelabelMenu"]');
                        if (!relabelButton.length) {
                            var relabel = $('<li><a data-id="' + fieldId + '" class="relabel" data-action="showRelabelMenu">Relabel</a></li>').appendTo(list);
                            relabel.on('click', function () {
                                e.showHoverMenu($(this), fieldId, $layout, layoutId);
                            });
                        }
                    });
                }
            });
        },
        showHoverMenu: function (ev, fieldId, $layout, layoutId) {
            var e = this;
            this.currentFieldId = fieldId;
            var btn = $(ev);
            var $hudBody = $('<div/>');

            var $field, $inputContainer;

            // Add the Name field
            $field = $('<div class="field"><div class="heading"><label for="relabel-name">' + Craft.t('relabel', 'new label') + '</label></div></div>').appendTo($hudBody);
            $inputContainer = $('<div class="input"/>').appendTo($field);

            var value = '';
            var index = fieldId + '-' + layoutId;
            if (index in this.values) {
                value = this.values[index].name;
            }
            $('<input type="text" class="text fullwidth" name="relabel-name" id="relabel-name"/>').appendTo($inputContainer).val(value);

            // Add new Description Field
            $field = $('<div class="field"><div class="heading"><label for="relabel-instructions">' + Craft.t('relabel', 'new description') + '</label></div></div>').appendTo($hudBody);
            $inputContainer = $('<div class="input"/>').appendTo($field);

            value = '';
            if (index in this.values) {
                value = this.values[index].instructions;
            }
            $('<textarea class="text fullwidth" style="resize: both" name="relabel-name" id="relabel-instructions"/></textarea>').appendTo($inputContainer).val(value);

            // Add the button
            var $footer = $('<div class="hud-footer"/>').appendTo($hudBody),
                $buttonsContainer = $('<div class="buttons right"/>').appendTo($footer);
            this.$saveBtn = $('<input type="submit" class="btn submit" value="' + Craft.t('app', 'Save') + '"/>').appendTo($buttonsContainer);
            this.$closeBtn = $('<button type="button" class="btn" >' + Craft.t('app', 'Cancel') + '</button>').appendTo($buttonsContainer);
            this.$spinner = $('<div class="spinner hidden"/>').appendTo($buttonsContainer);
            this.$closeBtn.on('click', function () {
                e.closeHud();
            });
            this.hud = new Garnish.HUD(btn, $hudBody, {
                updatingSizeAndPosition: true,
                onSubmit: $.proxy(this, 'saveRelabel', {layout: $layout, layoutId: layoutId}),
                // auto focus the input
                onShow: function (e) {
                    var hud = e.target;
                    if (typeof hud !== 'undefined') {
                        hud.$main.find('#relabel-name').focus();
                    }
                }
            });
        },
        saveRelabel: function (data) {
            var $layout = data.layout;
            var layoutId = data.layoutId;

            var inputName = this.getHiddenInput(this.currentFieldId, 'name', undefined, layoutId);
            var inputDescription = this.getHiddenInput(this.currentFieldId, 'instructions', undefined, layoutId);
            var name = this.hud.$body.find('#relabel-name').val();
            var instructions = this.hud.$body.find('#relabel-instructions').val();
            this.values[this.currentFieldId + '-' + layoutId] = {
                name: name,
                instructions: instructions
            };
            inputName.val(name);
            inputDescription.val(instructions);
            this.toggleLineSpan(this.currentFieldId, name, $layout, layoutId);
            this.closeHud();
        },
        toggleLineSpan: function (fieldId, value, fieldLayout, layoutId) {
            var container = fieldLayout.find('.fld-field[data-id="' + fieldId + '"]');
            if (typeof value === 'undefined') {
                value = this.values[fieldId + '-' + layoutId];
                if (!'name' in value) {
                    value = null;
                }
            }

            if (value) {
                container.first().children().eq(1).css('text-decoration', 'line-through');
                container.first().addClass('layout-tooltip').append('<span class="tooltiptext">' + value + '</span>');
            } else {
                container.first().children().eq(1).css('text-decoration', '');
                container.first().removeClass('layout-tooltip');
                container.find('.tooltiptext').remove();
            }
        },
        getHiddenInput: function (id, type, value, layoutId) {

            var inputId = 'relabel-field-input-' + type + '-' + id + '-' + layoutId;
            var input = $('#' + inputId);
            if (typeof value === 'undefined') {
                value = this.hud.$body.find('#relabel-' + type).val();
            }
            if (input.length === 0) {
                input = $('<input value="' + value + '" type="hidden" id="' + inputId + '" name="relabel[' + layoutId + '][' + id + '][' + type + ']">').appendTo(Craft.cp.$primaryForm);
            }
            return input;
        },
        getFieldLayoutId: function ($layout) {
            var layoutId = $layout.find('[name=fieldLayoutId]').val();
            if (typeof layoutId === 'undefined') {
                layoutId = $layout.data('fieldLayoutId');
            }
            // maybe it's a commerce variant?
            var variantInput = $layout.find('[name="variant-layout[fieldLayoutId]"]');
            if (variantInput.length) {
                layoutId = variantInput.val();
            }

            if (typeof layoutId === 'undefined' || !layoutId) {
                layoutId = 'new' + this.fieldLayoutIndex;
                $layout.data('fieldLayoutId', layoutId);
                this.fieldLayoutIndex++;
            }

            return layoutId;
        },
        getFieldsForLayout: function ($layout) {
            var layoutId = this.getFieldLayoutId($layout);
            if (typeof layoutId !== 'undefined') {
                this.fields = this.labels.filter(function (obj) {
                    return parseInt(obj.fieldLayoutId) === parseInt(layoutId);
                });
            }
            return this.fields;
        },
        getFieldContextSelector: function () {
            if (typeof Craft.livePreview !== 'undefined' &&
                Craft.livePreview.inPreviewMode) {
                return '.lp-editor';
            }
            return '#main';
        }
    });

})(window.jQuery);
