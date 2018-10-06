(function ($) {
    if (!window.Craft || !window.jQuery || Craft === 'undefined') {
        return false;
    }

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
        closeHud: function () {
            this.hud.hide();
            this.hud.destroy();
            delete this.hud;
        },
        init: function (data) {
            var e = this;
            this.labels = data.labels;
            this.labelsForLayout = data.labelsForLayout;
            var fields = this.getFieldsForLayout();
            $.each(fields, function (index, item) {
                e.values[item.fieldId] = {
                    name: item.name,
                    instructions: item.instructions
                };
                e.getHiddenInput(item.fieldId, 'name', item.name);
                e.getHiddenInput(item.fieldId, 'instructions', item.instructions);
                e.toggleLineSpan(item.fieldId, e.values[item.fieldId].name);
            });
            this.setup();
            if (this.labelsForLayout.length) {
                this.applyLabels(this.labelsForLayout, true);
            }
        },
        changeEntryType: function (fields) {
            var self = this;
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
        applyLabels: function (labels, includeDescription) {
            var self = this, $target;
            var target;
            if (this.elementEditors && Object.keys(this.elementEditors).length) {
                for (var key in this.elementEditors) {
                    target = this.elementEditors[key].$form;
                }
            }
            if (typeof target === 'undefined') {
                $target = $("#main");
            } else {
                $target = target;
            }

            if (!$target || !$target.length) {
                return false;
            }

            // Add CpFieldLinks to regular fields
            var fieldData = {},
                $fields = $target.find('.field').not('.matrixblock .field'),
                $field,
                fieldHandle;
            $fields.each(function (index, item) {
                $field = $(item);
                fieldHandle = self.getFieldHandleFromAttribute($field.attr('id'));
                if (fieldHandle) {
                    var span = $field.find('.heading:first label');
                    var newLabel = self.getLabelForField(fieldHandle, labels);
                    var data = this.getDataForField(fieldHandle, labels);
                    if (newLabel !== false) {
                        span.contents()[0].data = newLabel + ' ';
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
                    if($field.hasClass('has-errors') === true && data.name){
                        var errors = $field.find('.errors > li');
                        $.each(errors, function(index, item){
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
        getDataForField: function(handle, allFields){
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
        getFieldHandleFromAttribute: function (value) {
            if (!value) return false;
            value = value.split('-');
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
        setup: function () {
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
                }
                else {
                    $('<li><a data-action="toggle-required">' + Craft.t('app', 'Make required') + '</a></li>').appendTo($ul);
                }

                $('<li><a data-action="remove">' + Craft.t('app', 'Remove') + '</a></li>').appendTo($ul);
                $('<li><a data-id="' + fieldId + '" class="relabel" data-action="showRelabelMenu">Relabel</a></li>').appendTo($ul);

                var button = new Garnish.MenuBtn($editBtn, {
                    onOptionSelect: $.proxy(this, 'onFieldOptionSelect')
                });

                button.on('optionSelect', function (option) {
                    var $option = $(option.option),
                        $field = $option.data('menu').$anchor.parent(),
                        action = $option.data('action');
                    var fieldId = $($field).data('id');
                    if (action === 'showRelabelMenu') {
                        e.showHoverMenu($field, fieldId);
                    }
                })
            };
            var icons = $('#fieldlayoutform').find('.icon.settings');
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
                                e.showHoverMenu($(this), fieldId);
                            });
                        }
                    });
                }

            });
        },
        showHoverMenu: function (ev, fieldId) {
            var e = this;
            this.currentFieldId = fieldId;
            var btn = $(ev);
            var $hudBody = $('<div/>');

            var $field, $inputContainer;

            // Add the Name field
            $field = $('<div class="field"><div class="heading"><label for="relabel-name">' + Craft.t('relabel', 'new label') +  '</label></div></div>').appendTo($hudBody);
            $inputContainer = $('<div class="input"/>').appendTo($field);

            var value = '';
            if (fieldId in this.values) {
                value = this.values[fieldId].name;
            }
            $('<input type="text" class="text fullwidth" name="relabel-name" id="relabel-name"/>').appendTo($inputContainer).val(value);

            // Add new Description Field
            $field = $('<div class="field"><div class="heading"><label for="relabel-instructions">' + Craft.t('relabel', 'new description') + '</label></div></div>').appendTo($hudBody);
            $inputContainer = $('<div class="input"/>').appendTo($field);

            value = '';
            if (fieldId in this.values) {
                value = this.values[fieldId].instructions;
            }
            $('<textarea type="" class="text fullwidth" name="relabel-name" id="relabel-instructions"/></textarea>').appendTo($inputContainer).val(value);

            // Add the button
            var $footer = $('<div class="hud-footer"/>').appendTo($hudBody),
                $buttonsContainer = $('<div class="buttons right"/>').appendTo($footer);
            this.$saveBtn = $('<input type="submit" class="btn submit" value="' + Craft.t('app', 'Save') + '"/>').appendTo($buttonsContainer);
            this.$closeBtn = $('<button type="button" class="btn" >' + Craft.t('app', 'Abbrechen') + '</button>').appendTo($buttonsContainer);
            this.$spinner = $('<div class="spinner hidden"/>').appendTo($buttonsContainer);
            this.$closeBtn.on('click', function () {
                e.closeHud();
            });
            this.hud = new Garnish.HUD(btn, $hudBody, {
                onSubmit: $.proxy(this, 'saveRelabel')
            });
        },
        saveRelabel: function () {
            var inputName = this.getHiddenInput(this.currentFieldId, 'name');
            var inputDescription = this.getHiddenInput(this.currentFieldId, 'instructions');
            var name = this.hud.$body.find('#relabel-name').val();
            var instructions = this.hud.$body.find('#relabel-instructions').val();
            this.values[this.currentFieldId] = {
                name: name,
                instructions: instructions
            };
            inputName.val(name);
            inputDescription.val(instructions);
            this.toggleLineSpan(this.currentFieldId, name);
            this.closeHud();
        },
        toggleLineSpan: function (fieldId, value) {
            var container = $('.fld-field[data-id="' + fieldId + '"]');
            if (typeof value === 'undefined') {
                value = this.values[fieldId];
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
        getHiddenInput: function (id, type, value) {
            var inputId = 'relabel-field-input-' + type + '-' + id;
            var input = $('#' + inputId);
            if (typeof value === 'undefined') {
                value = this.hud.$body.find('#relabel-' + type).val();
            }
            if (input.length === 0) {
                input = $('<input value="' + value + '" type="hidden" id="' + inputId + '" name="relabel[' + id + '][' + type + ']">').appendTo(Craft.cp.$primaryForm);
            }
            return input;
        },
        getFieldLayoutId: function () {
            return $("#fieldlayoutform").find('[name=fieldLayoutId]').val();
        },
        getFieldsForLayout: function (layoutId) {
            if (typeof layoutId === 'undefined') {
                layoutId = this.getFieldLayoutId();
            }
            if (typeof layoutId !== 'undefined') {
                this.fields = this.labels.filter(function (obj) {
                    return parseInt(obj.fieldLayoutId) === parseInt(layoutId);
                });
            }
            return this.fields;
        },
        getFieldContextSelector: function () {
            if (this.isLivePreview) {
                return '.lp-editor';
            }
            return '#main';
        }
    });

})(window.jQuery);