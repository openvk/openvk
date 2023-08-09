function convertFormToYAML(handler, csrfToken) {
    const form = $(".aui");
    let formData = [];

    form.find('input, select').each(function () {
        let key = $(this).attr("name");
        let value = $(this).val();
        let level = $(this).attr("level") ?? 0;

        if ($(this).is('select')) {
            value = (value === 'true');
        }

        if ($(this).attr("disabled")) {
            return;
        }

        if ($(this).attr("noValue")) {
            formData.push({indentation: level, key: key, value: value, noValue: true});
        } else {
            formData.push({indentation: level, key: key, value: value});
        }
    });

    const yaml = restoreYAMLFromJS(formData);
    $.ajax({
        type: "POST",
        url: handler,
        data: {
            yaml: yaml,
            hash: csrfToken
        },
        success: (data) => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(tr("error") + ': ' + data.error);
            }
        }
    })
}

function restoreYAMLFromJS(formData) {
    let yamlData = '';
    let stack = [];
    let currentIndent = 0;
    let addDashForNextKey = false;
    let nextSpacesOverride = null;

    formData.forEach((_key, i) => {
        let {indentation, key} = _key;
        let value = _key.value;
        const level = parseInt(indentation);

        console.log(_key);

        let spaces = ' '.repeat(nextSpacesOverride ?? (level * 4));
        nextSpacesOverride = null;

        while (level < currentIndent) {
            stack.pop();
            currentIndent -= 1;
        }

        if (_key?.noValue) {
            yamlData += `${spaces}${key}:\n`;
        } else {
            if (/\d$/.test(key) && value.length === 0) {
                addDashForNextKey = true;
            } else {
                if (addDashForNextKey) {
                    let _s = `${spaces}- ${key}: \"${value}\"\n`;
                    nextSpacesOverride = _s.split(key)[0].length;
                    yamlData += _s;
                    addDashForNextKey = false;
                } else if (/\d: ".*"/.test(`${key}: \"${value}\"`)) {
                    yamlData += `${spaces}- ${value}\n`;
                    addDashForNextKey = false;
                } else {
                    if (value.length === 0) {
                        yamlData += `${spaces}${key}: \"\"\n`;
                    } else if (typeof value === 'boolean') {
                        yamlData += `${spaces}${key}: ${value}\n`;
                    } else if (value === 'null' || value == " ") {
                        yamlData += `${spaces}${key}: null\n`;
                    } else if (value.startsWith('"') && value.endsWith('"')) {
                        yamlData += `${spaces}${key}: ${value}\n`;
                    } else {
                        yamlData += `${spaces}${key}: \"${value}\"\n`;
                        currentIndent = level + 1;

                        stack.push(yamlData);
                        stack.push(level + 1);
                        stack.push(key);
                    }
                }
            }
        }
    });

    return yamlData;
}
