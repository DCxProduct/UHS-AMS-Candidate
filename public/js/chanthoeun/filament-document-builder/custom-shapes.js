function registerCustomShapes() {
    if (typeof tinymce !== 'undefined') {
        tinymce.PluginManager.add('custom_shapes', function (editor, url) {
            editor.ui.registry.addMenuButton('custom_shapes', {
                text: 'Shapes',
                icon: 'insert-template',
                fetch: function (callback) {
                    var items = [
                        {
                            type: 'menuitem',
                            text: 'Circle Shape (Logo)',
                            onAction: function () {
                                editor.insertContent('<div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; border-radius: 50%; text-align: center; line-height: 80px;">LOGO</div>');
                            }
                        },
                        {
                            type: 'menuitem',
                            text: 'Square Box',
                            onAction: function () {
                                editor.insertContent('<div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; text-align: center; line-height: 80px;">BOX</div>');
                            }
                        },
                        {
                            type: 'menuitem',
                            text: 'Rectangle Photo Box (4x6)',
                            onAction: function () {
                                editor.insertContent('<div style="display: inline-block; width: 80px; height: 100px; border: 1px solid #000; text-align: center; padding-top: 30px; box-sizing: border-box;">រូបថត<br>៤x៦</div>');
                            }
                        }
                    ];
                    callback(items);
                }
            });

            editor.ui.registry.addMenuButton('insert_variable', {
                text: 'Insert Variable',
                icon: 'sourcecode',
                fetch: function (callback) {
                    var vars = editor.getParam('document_variables', []);
                    var items = [];
                    
                    if (vars.length === 0) {
                        items.push({
                            type: 'menuitem',
                            text: 'No model selected',
                            disabled: true,
                            onAction: function () {}
                        });
                    } else {
                        vars.forEach(function(v) {
                            items.push({
                                type: 'menuitem',
                                text: '{{ ' + v + ' }}',
                                onAction: function () {
                                    editor.insertContent('{{ ' + v + ' }}');
                                }
                            });
                        });
                    }
                    callback(items);
                }
            });

            editor.ui.registry.addMenuButton('insert_repeating_table', {
                text: editor.getParam('document_repeater_labels', {}).button || 'Repeating Table',
                icon: 'table',
                fetch: function (callback) {
                    var repeaters = editor.getParam('document_repeaters', []);
                    var labels = editor.getParam('document_repeater_labels', {});
                    var validPath = /^[a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*$/;
                    var encode = function (value) {
                        return String(value).replace(/[&<>"']/g, function (character) {
                            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
                        });
                    };
                    var items = [];

                    repeaters.forEach(function (repeater) {
                        if (typeof repeater.path !== 'string' || ! validPath.test(repeater.path) || ! Array.isArray(repeater.fields)) {
                            return;
                        }

                        var fields = repeater.fields.filter(function (field) {
                            return typeof field.name === 'string' && validPath.test(field.name);
                        });
                        if (fields.length === 0) {
                            return;
                        }

                        items.push({
                            type: 'menuitem',
                            text: repeater.label || repeater.path,
                            onAction: function () {
                                var alias = typeof repeater.alias === 'string' && /^[a-zA-Z0-9_]+$/.test(repeater.alias) ? repeater.alias : 'item';
                                var cellStyle = 'border: 1px solid #d1d5db; padding: 6px; text-align: left; vertical-align: top;';
                                var headers = fields.map(function (field) {
                                    return '<th style="' + cellStyle + ' font-weight: bold;">' + encode(field.label || field.name) + '</th>';
                                }).join('');
                                var cells = fields.map(function (field) {
                                    return '<td style="' + cellStyle + '">{{ ' + alias + '.' + field.name + ' }}</td>';
                                }).join('');

                                editor.insertContent('<table style="width: 100%; border-collapse: collapse;">'
                                    + '<thead><tr>' + headers + '</tr></thead><tbody>'
                                    + '<!-- {{#foreach ' + repeater.path + ' as ' + alias + '}} -->'
                                    + '<tr>' + cells + '</tr><!-- {{/foreach}} -->'
                                    + '</tbody></table><p><br></p>');
                            }
                        });
                    });

                    if (items.length === 0) {
                        items.push({
                            type: 'menuitem',
                            text: labels.empty || 'No repeating fields available',
                            disabled: true,
                            onAction: function () {}
                        });
                    }
                    callback(items);
                }
            });

            editor.ui.registry.addMenuButton('document_templates_btn', {
                text: 'Templates',
                fetch: function (callback) {
                    var templates = editor.getParam('templates', []);
                    var items = [];

                    if (templates.length === 0) {
                        items.push({
                            type: 'menuitem',
                            text: 'No templates available',
                            disabled: true,
                            onAction: function () {}
                        });
                    } else {
                        templates.forEach(function(tpl) {
                            items.push({
                                type: 'menuitem',
                                text: tpl.title,
                                onAction: function () {
                                    editor.insertContent(tpl.content || '');
                                }
                            });
                        });
                    }

                    callback(items);
                }
            });

            editor.ui.registry.addButton('insert_qrcode', {
                text: 'QR Code',
                icon: 'image',
                tooltip: 'Insert QR Code',
                onAction: function () {
                    var vars = editor.getParam('document_variables', []);

                    if (vars.length === 0) {
                        editor.notificationManager.open({
                            text: 'No variables available. Please select a model first.',
                            type: 'warning',
                            timeout: 3000
                        });
                        return;
                    }

                    editor.windowManager.open({
                        title: 'Insert QR Code',
                        body: {
                            type: 'panel',
                            items: [
                                {
                                    type: 'selectbox',
                                    name: 'variable',
                                    label: 'Variable',
                                    items: vars.map(function (variable) {
                                        return { value: variable, text: variable };
                                    })
                                },
                                {
                                    type: 'input',
                                    name: 'size',
                                    label: 'Size (px)',
                                    inputMode: 'numeric',
                                    placeholder: '100'
                                }
                            ]
                        },
                        buttons: [
                            { type: 'cancel', name: 'cancel', text: 'Cancel' },
                            { type: 'submit', name: 'submit', text: 'Insert', primary: true }
                        ],
                        initialData: { variable: vars[0], size: '100' },
                        onSubmit: function (api) {
                            var data = api.getData();
                            var size = Math.max(10, Math.min(1000, parseInt(data.size, 10) || 100));

                            editor.insertContent('{{#qrcode ' + data.variable + ' size=' + size + '}}');
                            api.close();
                        }
                    });
                }
            });

            editor.ui.registry.addButton('insert_barcode', {
                text: 'Barcode',
                icon: 'image',
                tooltip: 'Insert Barcode',
                onAction: function () {
                    var vars = editor.getParam('document_variables', []);

                    if (vars.length === 0) {
                        editor.notificationManager.open({
                            text: 'No variables available. Please select a model first.',
                            type: 'warning',
                            timeout: 3000
                        });
                        return;
                    }

                    var barcodeTypes = [
                        { value: 'C128', text: 'Code 128 (General)' },
                        { value: 'C128A', text: 'Code 128 A' },
                        { value: 'C128B', text: 'Code 128 B' },
                        { value: 'C128C', text: 'Code 128 C (Numeric)' },
                        { value: 'C39', text: 'Code 39' },
                        { value: 'EAN13', text: 'EAN-13' },
                        { value: 'EAN8', text: 'EAN-8' },
                        { value: 'UPCA', text: 'UPC-A' },
                        { value: 'UPCE', text: 'UPC-E' },
                        { value: 'I25', text: 'Interleaved 2 of 5' }
                    ];

                    editor.windowManager.open({
                        title: 'Insert Barcode',
                        body: {
                            type: 'panel',
                            items: [
                                {
                                    type: 'selectbox',
                                    name: 'variable',
                                    label: 'Variable',
                                    items: vars.map(function (variable) {
                                        return { value: variable, text: variable };
                                    })
                                },
                                { type: 'selectbox', name: 'type', label: 'Barcode Type', items: barcodeTypes },
                                { type: 'input', name: 'width', label: 'Width factor (px per bar)', inputMode: 'numeric', placeholder: '2' },
                                { type: 'input', name: 'height', label: 'Height (px)', inputMode: 'numeric', placeholder: '30' }
                            ]
                        },
                        buttons: [
                            { type: 'cancel', name: 'cancel', text: 'Cancel' },
                            { type: 'submit', name: 'submit', text: 'Insert', primary: true }
                        ],
                        initialData: { variable: vars[0], type: 'C128', width: '2', height: '30' },
                        onSubmit: function (api) {
                            var data = api.getData();
                            var width = Math.max(1, Math.min(10, parseInt(data.width, 10) || 2));
                            var height = Math.max(10, Math.min(200, parseInt(data.height, 10) || 30));
                            var type = data.type || 'C128';

                            editor.insertContent('{{#barcode ' + data.variable + ' type=' + type + ' width=' + width + ' height=' + height + '}}');
                            api.close();
                        }
                    });
                }
            });
        });
    } else {
        setTimeout(registerCustomShapes, 100);
    }
}
registerCustomShapes();
