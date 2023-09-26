$(document).on("click", ".ignoredSourcesLink", (e) => {
    let body = `
        <span id="ignoredClubersList">${tr("ignored_clubsers_list")}</span>
        <div class="ignorredList">
            <div class="list" style="padding-top: 7px;padding-left: 7px;"></div>
        </div>
    `
    MessageBox(tr("ignored_sources"), body, [tr("cancel")], [Function.noop]);

    document.querySelector(".ovk-diag-body").style.padding = "10px"
    document.querySelector(".ovk-diag-body").style.height = "330px"

    async function insertMoreSources(page) {
        document.querySelector(".ignorredList .list").insertAdjacentHTML("beforeend", `<img id="loader" src="/assets/packages/static/openvk/img/loading_mini.gif">`)
        let ar = await API.Wall.getIgnoredSources(page)
        u("#loader").remove()

        let pagesCount = Math.ceil(Number(ar.count) / 10)

        for(const a of ar.items) {
            document.querySelector(".ignorredList .list").insertAdjacentHTML("beforeend", `
                <div class="smolContent">
                    <a href="${a.url}" target="_blank"><img style="float: left;" src="${a.avatar}"></a>
                    <div style="float: left;margin-left: 6px;">
                        <a href="${a.url}" target="_blank">${ovk_proc_strtr(escapeHtml(a.name), 12)}</a><br>
                        <span>${ovk_proc_strtr(escapeHtml(a.additional), 40)}</span>
                    </div>
                </div>
            `)
        }

        if(ar.fact &&  document.querySelector("#ignoredClubersList").dataset.fact != 1) {
            document.querySelector("#ignoredClubersList").innerHTML += " "+tr("interesting_fact", Number(ar.fact))
            document.querySelector("#ignoredClubersList").setAttribute("data-fact", "1")
        }

        if(page < pagesCount) {
            document.querySelector(".ignorredList .list").insertAdjacentHTML("beforeend", `
            <div id="showMoreIgnors" data-pagesCount="${pagesCount}" data-page="${page + 1}" style="width: 99%;text-align: center;background: #d5d5d5;height: 22px;padding-top: 9px;cursor:pointer;">
                <span>more...</span>
            </div>`)
        }
    }

    insertMoreSources(1)

    $(".ignorredList .list").on("click", "#showMoreIgnors", (e) => {
        u(e.currentTarget).remove()
        insertMoreSources(Number(e.currentTarget.dataset.page))
    })
})

$(document).on("click", "#ignoreSomeone", (e) => {
    let xhr = new XMLHttpRequest()
    xhr.open("POST", "/wall/ignoreSource")

    xhr.onloadstart = () => {
        e.currentTarget.classList.add("lagged")
    }

    xhr.onerror = xhr.ontimeout = () => {
        MessageBox(tr("error"), "Unknown error occured", [tr("ok")], [Function.noop]);
    }

    xhr.onload = () => {
        let result = JSON.parse(xhr.responseText)
        e.currentTarget.classList.remove("lagged")
        if(result.success) {
            e.currentTarget.innerHTML = result.text
        } else {
            MessageBox(tr("error"), result.flash.message, [tr("ok")], [Function.noop]);

        }
    }

    let formdata = new FormData
    formdata.append("hash", u("meta[name=csrf]").attr("value"))
    formdata.append("source", e.currentTarget.dataset.id)

    xhr.send(formdata)
})
