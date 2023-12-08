function endSuggestAction(new_count, post_node) {
    if(document.getElementById("cound") != null)
        document.getElementById("cound").innerHTML = tr("suggested_posts_in_group", new_count)
    else
        document.getElementById("cound_r").innerHTML = tr("suggested_by_everyone", new_count)
        
    if(document.querySelector("object a[href='"+location.pathname+"'] b") != null) {
        document.querySelector("object a[href='"+location.pathname+"'] b").innerHTML = new_count
        
        if(new_count < 1) {
            u("object a[href='"+location.pathname+"']").remove()
        }
    }

    if(new_count < 1 && document.querySelector(".sugglist") != null) {
        $(".sugglist a").click()
        $(".sugglist").remove()
    }

    post_node.style.transition = "opacity 300ms ease-in-out";
    post_node.style.opacity = "0";
    post_node.classList.remove("post")

    setTimeout(() => {post_node.outerHTML = ""}, 300)

    if(document.querySelectorAll("#postz .post").length < 1 && new_count > 0 && document.querySelector(".paginator") != null)
        loadMoreSuggestedPosts()
}

// "Опубликовать запись"
$(document).on("click", "#publish_post", async (e) => {
    let id = Number(e.currentTarget.dataset.id)
    let post;
    let body = `
        <textarea id="pooblish" style="max-height:500px;resize:vertical;min-height:54px;"></textarea>
        <label><input type="checkbox" id="signatr" checked>${tr("add_signature")}</label>
    `

    MessageBox(tr("publishing_suggested_post"), body, [tr("publish"), tr("cancel")], [(async () => {
        let id = Number(e.currentTarget.dataset.id)
        let post;

        let formData = new FormData()
        formData.append("id", id)
        formData.append("sign", document.getElementById("signatr").checked ? 1 : 0)
        formData.append("new_content", document.getElementById("pooblish").value)
        formData.append("hash", u("meta[name=csrf]").attr("value"))

        ky.post("/wall/accept", {
            hooks: {
                beforeRequest: [
                    (_request) => {
                        e.currentTarget.classList.add("loaded")
                        e.currentTarget.setAttribute("value", "")
                        e.currentTarget.setAttribute("id", "")
                    }
                ],
                afterResponse: [
                    async (_request, _options, response) => {
                        json = await response.json()

                        if(json.success) {
                            NewNotification(tr("suggestion_succefully_published"), tr("suggestion_press_to_go"), null, () => {window.location.assign("/wall" + json.id)});
                            endSuggestAction(json.new_count, e.currentTarget.closest("table"))
                        } else {
                            MessageBox(tr("error"), json.flash.message, [tr("ok")], [Function.noop]);
                        }

                        e.currentTarget.setAttribute("value", tr("publish_suggested"))
                        e.currentTarget.classList.remove("loaded")
                        e.currentTarget.setAttribute("id", "publish_post")
                    }
                ]
            },
            body: formData
        })
    }), Function.noop]);

    document.getElementById("pooblish").innerHTML = e.currentTarget.closest("table").querySelector(".really_text").dataset.text
    document.querySelector(".ovk-diag-body").style.padding = "9px";
})

// "Отклонить"
$(document).on("click", "#decline_post", async (e) => {
    let id = Number(e.currentTarget.dataset.id)

    let formData = new FormData()
    formData.append("id", id)
    formData.append("hash", u("meta[name=csrf]").attr("value"))

    ky.post("/wall/decline", {
        hooks: {
            beforeRequest: [
                (_request) => {
                    e.currentTarget.classList.add("loaded")
                    e.currentTarget.setAttribute("value", "")
                    e.currentTarget.setAttribute("id", "")
                }
            ],
            afterResponse: [
                async (_request, _options, response) => {
                    json = await response.json()

                    if(json.success) {
                        endSuggestAction(json.new_count, e.currentTarget.closest("table"))
                    } else {
                        MessageBox(tr("error"), json.flash.message, [tr("ok")], [Function.noop]);
                    }

                    e.currentTarget.setAttribute("value", tr("decline_suggested"))
                    e.currentTarget.setAttribute("id", "decline_post")
                    e.currentTarget.classList.remove("loaded")
                }
            ]
        },
        body: formData
    })
})

function loadMoreSuggestedPosts() {
    let link = location.href

    if(!link.includes("/suggested")) {
        link += "/suggested"
    }

    ky.get(link, {
        hooks: {
            beforeRequest: [
                (_request) => {
                    document.getElementById("postz").innerHTML = `<img src="/assets/packages/static/openvk/img/loading_mini.gif">`
                }
            ],
            afterResponse: [
                async (_request, _options, response) => {
                    let text = await response.text()
                    let parser = new DOMParser()
                    let body = parser.parseFromString(text, "text/html")

                    if(body.querySelectorAll(".post").length < 1) {
                        let url = new URL(location.href)
                        url.searchParams.set("p", url.searchParams.get("p") - 1)
            
                        if(url.searchParams.get("p") < 1) {
                            return 0;
                        }

                        history.pushState({}, "", url)
            
                        loadMoreSuggestedPosts()
                    }

                    body.querySelectorAll(".bsdn").forEach(bsdnInitElement)
                    document.getElementById("postz").innerHTML = body.getElementById("postz").innerHTML
                }
            ]
        }
    })
}

// нажатие на "x предложенных записей"
$(document).on("click", ".sugglist a", (e) => {
    e.preventDefault()
    
    if(e.currentTarget.getAttribute("data-toogled") == null || e.currentTarget.getAttribute("data-toogled") == "false") {
        e.currentTarget.setAttribute("data-toogled", "true")
        document.getElementById("underHeader").style.display = "none"
        document.querySelector(".insertThere").style.display = "block"
        document.querySelector(".insertThere").classList.add("infContainer")
        history.pushState({}, "", e.currentTarget.href)

        // если ещё ничего не подгружалось
        if(document.querySelector(".insertThere").innerHTML == "") {
            ky(e.currentTarget.href, {
                hooks: {
                    beforeRequest: [
                        (_request) => {
                            document.querySelector(".insertThere").insertAdjacentHTML("afterbegin", `<img src="/assets/packages/static/openvk/img/loading_mini.gif">`)
                        }
                    ],
                    afterResponse: [
                        async (_request, _options, response) => {
                            let parser = new DOMParser
                            let result = parser.parseFromString(await response.text(), 'text/html').querySelector(".infContainer")
                            
                            result.querySelectorAll(".bsdn").forEach(bsdnInitElement)
                            document.querySelector(".insertThere").innerHTML = result.innerHTML
                        }
                    ]
                }
            })
        }
    } else {
        // переключение на нормальную стену
        e.currentTarget.setAttribute("data-toogled", "false")
        document.getElementById("underHeader").style.display = "block"
        document.querySelector(".insertThere").style.display = "none"
        document.querySelector(".insertThere").classList.remove("infContainer")
        history.pushState({}, "", e.currentTarget.href.replace("/suggested", ""))
    }
})

// нажатие на пагинатор у постов предложки
$(document).on("click", "#postz .paginator a", (e) => {
    e.preventDefault()
    
    ky(e.currentTarget.href, {
        hooks: {
            beforeRequest: [
                (_request) => {
                    if(document.querySelector(".sugglist") != null) {
                        document.querySelector(".sugglist").scrollIntoView({behavior: "smooth"})
                    } else {
                        document.querySelector(".infContainer").scrollIntoView({behavior: "smooth"})
                    }
            
                    setTimeout(() => {document.getElementById("postz").innerHTML = `<img src="/assets/packages/static/openvk/img/loading_mini.gif">`}, 500)
                }
            ],
            afterResponse: [
                async (_request, _options, response) => {
                    let result = (new DOMParser).parseFromString(await response.text(), "text/html").querySelector(".infContainer")
                    result.querySelectorAll(".bsdn").forEach(bsdnInitElement)
            
                    document.getElementById("postz").innerHTML = result.innerHTML
                    history.pushState({}, "", e.currentTarget.href)
                }
            ]
        }
    })
})
