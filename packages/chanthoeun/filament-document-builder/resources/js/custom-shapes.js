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
        });
    } else {
        setTimeout(registerCustomShapes, 100);
    }
}
registerCustomShapes();
