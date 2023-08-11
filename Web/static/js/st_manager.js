function executeScripts(html) {
    let tempContainer = document.createElement('div');
    tempContainer.innerHTML = html;

    let scripts = tempContainer.querySelectorAll('script');
    scripts.forEach((script) => {
        if (!isScriptAlreadyLoaded(script)) {
            let newScript = document.createElement('script');
            if (script.src) {
                newScript.src = script.src;
            } else {
                newScript.textContent = script.textContent;
            }
            document.body.appendChild(newScript);
        }
    });
}

function isScriptAlreadyLoaded(script) {
    if (script.src) {
        let existingScript = document.querySelector(`script[src="${script.src.split('?')[0]}"]`);
        return !!existingScript;
    }
    return false;
}

// Функция для выполнения стилей в переданном HTML-коде
function executeStyles(html) {
    let tempContainer = document.createElement('div');
    tempContainer.innerHTML = html;

    let styles = tempContainer.querySelectorAll('link[rel="stylesheet"]');
    styles.forEach((style) => {
        if (!isStyleAlreadyLoaded(style)) {
            let newStyle = document.createElement('link');
            newStyle.rel = 'stylesheet';
            newStyle.href = style.href;
            document.head.appendChild(newStyle);
        }
    });
}

function isStyleAlreadyLoaded(style) {
    if (style.href) {
        let styleHref = style.href.split('?')[0]; // Извлечь URL без query параметров
        let existingStyle = document.querySelector(`link[rel="stylesheet"][href="${styleHref}"]`);
        return !!existingStyle;
    }
    return false;
}

function cleanAndLoadContent(html) {
    let tempContainer = document.createElement('div');
    tempContainer.innerHTML = html;

    let scripts = tempContainer.querySelectorAll('script');
    let styles = tempContainer.querySelectorAll('link[rel="stylesheet"]');

    scripts.forEach((script) => {
        if (isScriptAlreadyLoaded(script)) {
            script.parentNode.removeChild(script);
        }
    });

    styles.forEach((style) => {
        if (isStyleAlreadyLoaded(style)) {
            style.parentNode.removeChild(style);
        }
    });
    return tempContainer.innerHTML;
}

async function goto(url, ps = true, isRedirect = false) {
    $("#ajaxLoader").show();

    try {
        await $.ajax({
            url: url,
            data: {
                al: 1
            },
            dataType: 'html',
            complete: async (data, textStatus) => {
                console.log(data);
                let cleanedContent = cleanAndLoadContent(data.responseText);
                document.getElementById("bodyContent").innerHTML = cleanedContent;
                const redirectUrl = data.getResponseHeader('Location');
                if (redirectUrl) {
                    console.log("REDIRECT", redirectUrl);
                    goto(redirectUrl, true);
                } else {
                    initForms();
                    executeScripts(data.responseText);
                    executeStyles(data.responseText);

                    $("title").text($(data.responseText).filter('title').text());
                }
            }
        });
    } catch (error) {
        console.error("goto", url, ps, "ERROR", error);
        MessageBox("Ошибка", "Не удалось загрузить эту страницу", ["OK"], [Function.noop]);
    }

    if (ps) {
        history.pushState(null, null, ($("#__current_url").val().replace(/[?&]al=1(?=$|&)/, '') || url));
    }

    $("#ajaxLoader").hide();
}

document.addEventListener("click", async (e) => {
    console.log(e);
    let target = e.target;

    while (target && target.tagName !== "A") {
        target = target.parentNode;
    }

    if (target) {
        const url = target.getAttribute("href");
        if (url) {
            if (target.tagName.toLowerCase() === "a" && target.getAttribute("target") !== "_blank" && !url.startsWith("javascript:")) {
                e.preventDefault();
                goto(url);
            }
        }
    }
})

async function submitAjaxForm(url, formData) {
    if (!url) url = window.location.href;
    const separator = url.includes('?') ? '&' : '?';
    const newUrl = url + separator + 'al=1';

    try {
        await $.ajax({
            type: 'POST',  // Может быть GET, POST, и т.д.
            url: newUrl,
            data: formData,
            processData: false,
            contentType: false,
            success: async (data, textStatus, jqXHR) => {
                const redirectUrl = jqXHR.getResponseHeader('Location') || (jqXHR.status === 301 || jqXHR.status === 302);
                if (redirectUrl) {
                    history.pushState(null, null, redirectUrl);
                    submitAjaxForm(redirectUrl, formData);
                } else {
                    const bodyContent = $("#bodyContent");
                    bodyContent.html(data);
                    $("#ajaxLoader").hide();

                    history.pushState(null, null, $("#__current_url").val().replace(/[?&]al=1(?=$|&)/, ''));
                    $("title").text($(data.responseText).filter('title').text());
                }
            },
            error: async (error) => {
                console.error(error);
                // Обработка ошибки
                MessageBox("Ошибка", "При загрузке страницы произошла ошибка", ["OK :("], [Function.noop]);
            }
        });
    } catch (error) {
        console.error("submitAjaxForm ERROR", error);
        MessageBox("Ошибка", "При загрузке страницы произошла ошибка", ["OK :("], [Function.noop]);
    }
}

function initForms() {
    // Удаляем старые обработчики событий на формах
    $("form").off("submit");

    // Обходим все формы и добавляем обработчик на событие отправки
    $("form").on("submit", async function (event) {
        event.preventDefault();
        const form = event.target;
        const url = form.getAttribute("action");

        const formData = new FormData(form);

        $("#ajaxLoader").show();

        await submitAjaxForm(url, formData);
    });
}

// Обработка события popstate (браузерная навигация назад/вперед)
window.addEventListener('popstate', async function (event) {
    event.preventDefault();
    goto(location.href, false);
});