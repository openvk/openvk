function humanFileSize(bytes, si) {
    var thresh = si ? 1000 : 1024;
    if(Math.abs(bytes) < thresh) {
        return bytes + ' B';
    }
    var units = si
        ? ['kB','MB','GB','TB','PB','EB','ZB','YB']
        : ['KiB','MiB','GiB','TiB','PiB','EiB','ZiB','YiB'];
    var u = -1;
    do {
        bytes /= thresh;
        ++u;
    } while(Math.abs(bytes) >= thresh && u < units.length - 1);
    return bytes.toFixed(1)+' '+units[u];
}

function trim(string) {
    var newStr = string.substring(0, 10);
    if(newStr.length !== string.length)
        newStr += "…";
    
    return newStr;
}

function initGraffiti(id) {
    let canvas = null;
    let msgbox = MessageBox(tr("draw_graffiti"), "<div id='ovkDraw'></div>", [tr("save"), tr("cancel")], [function() {
        canvas.getImage({includeWatermark: false}).toBlob(blob => {
            let fName = "Graffiti-" + Math.ceil(performance.now()).toString() + ".jpeg";
            let image = new File([blob], fName, {type: "image/jpeg", lastModified: new Date().getTime()});
            
            fastUploadImage(id, image)
        }, "image/jpeg", 0.92);
        
        canvas.teardown();
    }, function() {
        canvas.teardown();
    }]);
    
    let watermarkImage = new Image();
    watermarkImage.src = "/assets/packages/static/openvk/img/logo_watermark.gif";
    
    msgbox.attr("style", "width: 750px;");
    canvas = LC.init(document.querySelector("#ovkDraw"), {
        backgroundColor: "#fff",
        imageURLPrefix: "/assets/packages/static/openvk/js/node_modules/literallycanvas/lib/img",
        watermarkImage: watermarkImage,
        imageSize: {
            width: 640,
            height: 480
        }
    });
}

function fastUploadImage(textareaId, file) {
    // uploading images

    if(!file.type.startsWith('image/')) {
        MessageBox(tr("error"), tr("only_images_accepted", escapeHtml(file.name)), [tr("ok")], [() => {Function.noop}])
        return;
    }

    // 🤓🤓🤓
    if(file.size > 5 * 1024 * 1024) {
        MessageBox(tr("error"), tr("max_filesize", 5), [tr("ok")], [() => {Function.noop}])
        return;
    }

    let imagesCount = document.querySelector("#post-buttons" + textareaId + " input[name='photos']").value.split(",").length

    if(imagesCount > 10) {
        MessageBox(tr("error"), tr("too_many_photos"), [tr("ok")], [() => {Function.noop}])
        return
    }

    let xhr = new XMLHttpRequest
    let data = new FormData

    data.append("photo_0", file)
    data.append("count", 1)
    data.append("hash", u("meta[name=csrf]").attr("value"))

    xhr.open("POST", "/photos/upload")

    xhr.onloadstart = () => {
        document.querySelector("#post-buttons"+textareaId+" .upload").insertAdjacentHTML("beforeend", `<img id="loader" src="/assets/packages/static/openvk/img/loading_mini.gif">`)
    }

    xhr.onload = () => {
        let response = JSON.parse(xhr.responseText)

        appendImage(response, textareaId)
    }

    xhr.send(data)
}

// append image after uploading via /photos/upload
function appendImage(response, textareaId) {
    if(!response.success) {
        MessageBox(tr("error"), (tr("error_uploading_photo") + response.flash.message), [tr("ok")], [() => {Function.noop}])
    } else {
        let form        = document.querySelector("#post-buttons"+textareaId)
        let photosInput = form.querySelector("input[name='photos']")
        let photosIndicator = form.querySelector(".upload")
        
        for(const phot of response.photos) {
            let id = phot.owner + "_" + phot.vid

            photosInput.value += (id + ",")

            u(photosIndicator).append(u(`
                <div class="upload-item" id="aP" data-id="${id}">
                    <a class="upload-delete">×</a>
                    <img src="${phot.url}">
                </div>
            `))

            u(photosIndicator.querySelector(`.upload #aP[data-id='${id}'] .upload-delete`)).on("click", () => {
                photosInput.value = photosInput.value.replace(id + ",", "")
                u(form.querySelector(`.upload #aP[data-id='${id}']`)).remove()
            })
        }
    }
    u(`#post-buttons${textareaId} .upload #loader`).remove()
}

u(".post-like-button").on("click", function(e) {
    e.preventDefault();
    
    var thisBtn = u(this).first();
    var link    = u(this).attr("href");
    var heart   = u(".heart", thisBtn);
    var counter = u(".likeCnt", thisBtn);
    var likes   = counter.text() === "" ? 0 : counter.text();
    var isLiked = heart.attr("id") === 'liked';
    
    ky(link);
    heart.attr("id", isLiked ? '' : 'liked');
    counter.text(parseInt(likes) + (isLiked ? -1 : 1));
    if (counter.text() === "0") {
        counter.text("");
    }
    
    return false;
});

function setupWallPostInputHandlers(id) {
    u("#wall-post-input" + id).on("paste", function(e) {
        // Если вы находитесь на странице с постом с id 11, то копирование произойдёт джва раза.
        // Оч ржачный баг, но вот как его исправить, я, если честно, не знаю.

        if(e.clipboardData.files.length === 1) {
            fastUploadImage(id, e.clipboardData.files[0])
            return;
        }
    });
    
    u("#wall-post-input" + id).on("input", function(e) {
        var boost             = 5;
        var textArea          = e.target;
        textArea.style.height = "5px";
        var newHeight = textArea.scrollHeight;
        textArea.style.height = newHeight + boost + "px";
        return;
        
        // revert to original size if it is larger (possibly changed by user)
        // textArea.style.height = (newHeight > originalHeight ? (newHeight + boost) : originalHeight) + "px";
    });

    u("#wall-post-input" + id).on("dragover", function(e) {
        e.preventDefault()

        // todo add animation
        return;
    });

    $("#wall-post-input" + id).on("drop", function(e) {
        e.originalEvent.dataTransfer.dropEffect = 'move';
        fastUploadImage(id, e.originalEvent.dataTransfer.files[0])
        return;
    });
}

function OpenMiniature(e, photo, post, photo_id, type = "post") {
    /*
    костыли но смешные однако
    */
    e.preventDefault();

    if(u(".ovk-photo-view").length > 0) u(".ovk-photo-view-dimmer").remove();

    // Значения для переключения фоток

    let json;

    let imagesCount = 0;
    let imagesIndex = 0;

    let tempDetailsSection = [];
    
    let dialog = u(
    `<div class="ovk-photo-view-dimmer">
        <div class="ovk-photo-view">
            <div class="photo_com_title">
                <text id="photo_com_title_photos">
                    <img src="/assets/packages/static/openvk/img/loading_mini.gif">
                </text>
                <div>
                    <a id="ovk-photo-close">${tr("close")}</a>
                </div>
            </div>
            <center style="margin-bottom: 8pt;">
                <div class="ovk-photo-slide-left"></div>
                <div class="ovk-photo-slide-right"></div>
                <img src="${photo}" style="max-width: 100%; max-height: 60vh; user-select:none;" id="ovk-photo-img">
            </center>
            <div class="ovk-photo-details">
                <img src="/assets/packages/static/openvk/img/loading_mini.gif">
            </div>
        </div>
    </div>`);
    u("body").addClass("dimmed").append(dialog);
    document.querySelector("html").style.overflowY = "hidden"
    
    let button = u("#ovk-photo-close");

    button.on("click", function(e) {
        let __closeDialog = () => {
            u("body").removeClass("dimmed");
            u(".ovk-photo-view-dimmer").remove();
            document.querySelector("html").style.overflowY = "scroll"
        };
        
        __closeDialog();
    });

    function __reloadTitleBar() {
        u("#photo_com_title_photos").last().innerHTML = imagesCount > 1 ? tr("photo_x_from_y", imagesIndex, imagesCount) : tr("photo");
    }

    function __loadDetails(photo_id, index) {
        if(tempDetailsSection[index] == null) {
            u(".ovk-photo-details").last().innerHTML = '<img src="/assets/packages/static/openvk/img/loading_mini.gif">';
            ky("/photo" + photo_id, {
                hooks: {
                    afterResponse: [
                        async (_request, _options, response) => {
                            let parser = new DOMParser();
                            let body = parser.parseFromString(await response.text(), "text/html");

                            let element = u(body.getElementsByClassName("ovk-photo-details")).last();

                            tempDetailsSection[index] = element.innerHTML;

                            if(index == imagesIndex) {
                                u(".ovk-photo-details").last().innerHTML = element.innerHTML;
                            }

                            document.querySelectorAll(".ovk-photo-details .bsdn").forEach(bsdnInitElement)
                            document.querySelectorAll(".ovk-photo-details script").forEach(scr => {
                                // stolen from #953
                                let newScr = document.createElement('script')

                                if(scr.src) {
                                    newScr.src = scr.src
                                } else {
                                    newScr.textContent = scr.textContent
                                }

                                document.querySelector(".ovk-photo-details").appendChild(newScr);
                            })
                        }
                    ]
                }
            });
        } else {
            u(".ovk-photo-details").last().innerHTML = tempDetailsSection[index];
        }
    }

    function __slidePhoto(direction) {
        /* direction = 1 - right
           direction = 0 - left */
        if(json == undefined) {
            console.log("Да подожди ты. Куда торопишься?");
        } else {
            if(imagesIndex >= imagesCount && direction == 1) {
                imagesIndex = 1;
            } else if(imagesIndex <= 1 && direction == 0) {
                imagesIndex = imagesCount;
            } else if(direction == 1) {
                imagesIndex++;
            } else if(direction == 0) {
                imagesIndex--;
            }

            let photoURL = json.body[imagesIndex - 1].url;

            u("#ovk-photo-img").last().src = photoURL;
            __reloadTitleBar();
            __loadDetails(json.body[imagesIndex - 1].id, imagesIndex);
        }
    }

    let slideLeft = u(".ovk-photo-slide-left");

    slideLeft.on("click", (e) => {
        __slidePhoto(0);
    });

    let slideRight = u(".ovk-photo-slide-right");

    slideRight.on("click", (e) => {
        __slidePhoto(1);
    });

    let data = new FormData()
    data.append('parentType', type);
    ky.post("/iapi/getPhotosFromPost/" + (type == "post" ? post : "1_"+post), {
        hooks: {
            afterResponse: [
                async (_request, _options, response) => {
                    json = await response.json();

                    imagesCount = json.body.length;
                    imagesIndex = 0;
                    // Это всё придётся правда на 1 прибавлять
                    
                    json.body.every(element => {
                        imagesIndex++;
                        if(element.id == photo_id) {
                            return false;
                        } else {
                            return true;
                        }
                    });

                    __reloadTitleBar();
                    __loadDetails(json.body[imagesIndex - 1].id, imagesIndex);                }
            ]
        },
        body: data
    });

    return u(".ovk-photo-view-dimmer");
}

u("#write > form").on("keydown", function(event) {
    if(event.ctrlKey && event.keyCode === 13)
        this.submit();
});

var tooltipClientTemplate = Handlebars.compile(`
    <table>
        <tr>
            <td width="54" valign="top">
                <img src="{{img}}" width="54" />
            </td>
            <td width="1"></td>
            <td width="150" valign="top">
                <text>
                    {{app_tr}}: <b>{{name}}</b>
                </text><br/>
                <a href="{{url}}">${tr("learn_more")}</a>
            </td>
        </tr>
    </table>
`);

var tooltipClientNoInfoTemplate = Handlebars.compile(`
    <table>
        <tr>
            <td width="150" valign="top">
                <text>
                    {{app_tr}}: <b>{{name}}</b>
                </text><br/>
            </td>
        </tr>
    </table>
`);

tippy(".client_app", {
    theme: "light vk",
    content: "⌛",
    allowHTML: true,
    interactive: true,
    interactiveDebounce: 500,

    onCreate: async function(that) {
        that._resolvedClient = null;
    },

    onShow: async function(that) {
        let client_tag = that.reference.dataset.appTag;
        let client_name = that.reference.dataset.appName;
        let client_url = that.reference.dataset.appUrl;
        let client_img = that.reference.dataset.appImg;
        
        if(client_name != "") {
            let res = {
                'name':   client_name,
                'url':    client_url,
                'img':    client_img,
                'app_tr': tr("app") 
            };
    
            that.setContent(tooltipClientTemplate(res));
        } else {
            let res = {
                'name': client_tag,
                'app_tr': tr("app") 
            };
    
            that.setContent(tooltipClientNoInfoTemplate(res));
        }
    }
});

function addNote(textareaId, nid)
{
    if(nid > 0) {
        note.value = nid
        let noteObj = document.querySelector("#nd"+nid)
    
        let nortd = document.querySelector("#post-buttons"+textareaId+" .post-has-note");
        nortd.style.display = "block"
    
        nortd.innerHTML = `${tr("note")} ${escapeHtml(noteObj.dataset.name)}`
    } else {
        note.value = "none"

        let nortd = document.querySelector("#post-buttons"+textareaId+" .post-has-note");
        nortd.style.display = "none"

        nortd.innerHTML = ""
    }

    u("body").removeClass("dimmed");
    u(".ovk-diag-cont").remove();
    document.querySelector("html").style.overflowY = "scroll"
}

async function attachNote(id)
{
    let notes = await API.Wall.getMyNotes()
    let body  = ``

    if(notes.closed < 1) {
        body = `${tr("notes_closed")}`
    } else {
        if(notes.items.length < 1) {
            body = `${tr("no_notes")}`
        } else {
            body = `
                ${tr("select_or_create_new")}
                <div id="notesList">`

            if(note.value != "none") {
                body += `
                <div class="ntSelect" onclick="addNote(${id}, 0)">
                    <span>${tr("do_not_attach_note")}</span>
                </div>`
            }

            for(const note of notes.items) {
                body += `
                    <div data-name="${note.name}" class="ntSelect" id="nd${note.id}" onclick="addNote(${id}, ${note.id})">
                        <span>${escapeHtml(note.name)}</span>
                    </div>
                `
            }
         
            body += `</div>`
        }    
    }

    let frame = MessageBox(tr("select_note"), body, [tr("cancel")], [Function.noop]);

    document.querySelector(".ovk-diag-body").style.padding = "10px"
}

async function showArticle(note_id) {
    u("body").addClass("dimmed");
    let note = await API.Notes.getNote(note_id);
    u("#articleAuthorAva").attr("src", note.author.ava);
    u("#articleAuthorName").text(note.author.name);
    u("#articleAuthorName").attr("href", note.author.link);
    u("#articleTime").text(note.created);
    u("#articleLink").attr("href", note.link);
    u("#articleText").html(`<h1 class="articleView_nameHeading">${note.title}</h1>` + note.html);
    u("body").removeClass("dimmed");
    u("body").addClass("article");
}

if(document.querySelector("input[name='set_source']") != null) {
    document.querySelector("input[name='set_source']").checked = false
}

$(document).on("change", "input[name='set_source']", (e) => {
    if(e.currentTarget.checked) {
        document.getElementById("sourceSet").style.display = "block"
        e.currentTarget.parentNode.querySelector("span").innerHTML = tr("source") + ":"
    } else {
        document.getElementById("sourceSet").style.display = "none"
        e.currentTarget.parentNode.querySelector("span").innerHTML = tr("set_source")
    }
})

$(document).on("click", "#videoAttachment", async (e) => {
    e.preventDefault()
    
    let body = `
        <div class="topGrayBlock">
            <div style="padding-top: 11px;padding-left: 12px;">
                <a href="/videos/upload">${tr("upload_new_video")}</a>
                <input type="text" id="vquery" maxlength="20" placeholder="${tr("header_search")}" style="float: right;width: 160px;margin-right: 17px;margin-top: -2px;">
            </div>
        </div>

        <div class="videosInsert" style="padding: 5px;height: 287px;overflow-y: scroll;"></div>
    `

    let form = e.currentTarget.closest("form")

    MessageBox(tr("selecting_video"), body, [tr("close")], [Function.noop]);

    // styles for messageboxx
    document.querySelector(".ovk-diag-body").style.padding = "0"
    document.querySelector(".ovk-diag-cont").style.width = "580px"
    document.querySelector(".ovk-diag-body").style.height = "335px"

    async function insertVideos(page, query = "") {
        document.querySelector(".videosInsert").insertAdjacentHTML("beforeend", `<img id="loader" src="/assets/packages/static/openvk/img/loading_mini.gif">`)

        let vidoses
        let noVideosText = tr("no_videos")
        if(query == "") {
            vidoses = await API.Wall.getVideos(page)
        } else {
            vidoses = await API.Wall.searchVideos(page, query)
            noVideosText = tr("no_videos_results")
        }
        
        if(vidoses.count < 1) {
            document.querySelector(".videosInsert").innerHTML = `<span>${noVideosText}</span>`
        }

        let pagesCount = Math.ceil(Number(vidoses.count) / 8)
        u("#loader").remove()
        let insert = document.querySelector(".videosInsert")

        for(const vid of vidoses.items) {
            let isAttached = (form.querySelector("input[name='videos']").value.includes(`${vid.video.owner_id}_${vid.video.id},`))

            insert.insertAdjacentHTML("beforeend", `
            <div class="content" style="padding: unset;">
                <table>
                    <tbody>
                        <tr>
                            <td valign="top">
                                <a href="/video${vid.video.owner_id}_${vid.video.id}">
                                    <div class="video-preview" style="height: 75px;width: 133px;overflow: hidden;">
                                        <img src="${vid.video.image[0].url}" alt="${escapeHtml(vid.video.title)}" style="max-width: 133px; height: 75px; margin: auto;">
                                    </div>
                                </a>
                            </td>
                            <td valign="top" style="width: 100%">
                                <a href="/video${vid.video.owner_id}_${vid.video.id}">
                                    <b>
                                        ${ovk_proc_strtr(escapeHtml(vid.video.title), 30)}
                                    </b>
                                </a>
                                <br>
                                <p>
                                    <span>${ovk_proc_strtr(escapeHtml(vid.video.description ?? ""), 140)}</span>
                                </p>
                                <span><a href="/id${vid.video.owner_id}" target="_blank">${escapeHtml(vid.video.author_name ?? "")}</a></span>
                            </td>
                            <td valign="top" class="action_links" style="width: 150px;">
                                <a class="profile_link" id="attachvid" data-name="${escapeHtml(vid.video.title)}" data-attachmentData="${vid.video.owner_id}_${vid.video.id}">${!isAttached ? tr("attach") : tr("detach")}</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            `)
        }

        if(page < pagesCount) {
            document.querySelector(".videosInsert").insertAdjacentHTML("beforeend", `
            <div id="showMoreVideos" data-pagesCount="${pagesCount}" data-page="${page + 1}" style="width: 100%;text-align: center;background: #d5d5d5;height: 22px;padding-top: 9px;cursor:pointer;">
                <span>more...</span>
            </div>`)
        }
    }

    $(".videosInsert").on("click", "#showMoreVideos", (e) => {
        u(e.currentTarget).remove()
        insertVideos(Number(e.currentTarget.dataset.page), document.querySelector(".topGrayBlock #vquery").value)
    })

    $(".topGrayBlock #vquery").on("change", async (e) => {
        await new Promise(r => setTimeout(r, 1000));

        if(e.currentTarget.value === document.querySelector(".topGrayBlock #vquery").value) {
            document.querySelector(".videosInsert").innerHTML = ""
            insertVideos(1, e.currentTarget.value)
            return;
        } else {
            console.info("skipping")
        }
    })

    insertVideos(1)

    function insertAttachment(id) {
        let videos = form.querySelector("input[name='videos']") 

        if(!videos.value.includes(id + ",")) {
            if(videos.value.split(",").length > 10) {
                NewNotification(tr("error"), tr("max_attached_videos"))
                return false
            }

            form.querySelector("input[name='videos']").value += (id + ",")

            console.info(id + " attached")
            return true
        } else {
            form.querySelector("input[name='videos']").value = form.querySelector("input[name='videos']").value.replace(id + ",", "")

            console.info(id + " detached")
            return false
        }
    }

    $(".videosInsert").on("click", "#attachvid", (ev) => {
        // откреплено от псто
        if(!insertAttachment(ev.currentTarget.dataset.attachmentdata)) {
            u(`.post-has-videos .post-has-video[data-id='${ev.currentTarget.dataset.attachmentdata}']`).remove()
            ev.currentTarget.innerHTML = tr("attach")
        } else {
            ev.currentTarget.innerHTML = tr("detach")

            form.querySelector(".post-has-videos").insertAdjacentHTML("beforeend", `
                <div class="post-has-video" id="unattachVideo" data-id="${ev.currentTarget.dataset.attachmentdata}">
                    <span>${tr("video")} <b>"${ovk_proc_strtr(escapeHtml(ev.currentTarget.dataset.name), 20)}"</b></span>
                </div>
            `)

            u(`#unattachVideo[data-id='${ev.currentTarget.dataset.attachmentdata}']`).on("click", (e) => {
                let id = ev.currentTarget.dataset.attachmentdata
                form.querySelector("input[name='videos']").value = form.querySelector("input[name='videos']").value.replace(id + ",", "")
                
                console.info(id + " detached")
               
                u(e.currentTarget).remove()
            })
        }
    })
})

$(document).on("click", "#editPost", (e) => {
    let post = e.currentTarget.closest("table")
    let content = post.querySelector(".text")
    let text = content.querySelector(".really_text")

    if(content.querySelector("textarea") == null) {
        content.insertAdjacentHTML("afterbegin", `
            <div class="editMenu">
                <div id="wall-post-input999"> 
                    <textarea id="new_content">${text.dataset.text}</textarea>
                    <input type="button" class="button" value="${tr("save")}" id="endEditing">
                    <input type="button" class="button" value="${tr("cancel")}" id="cancelEditing">
                </div>
                ${e.currentTarget.dataset.nsfw != null ? `
                    <div class="postOptions">
                        <label><input type="checkbox" id="nswfw" ${e.currentTarget.dataset.nsfw == 1 ? `checked` : ``}>${tr("contains_nsfw")}</label>
                    </div>
                ` : ``}
                ${e.currentTarget.dataset.fromgroup != null ? `
                <div class="postOptions">
                    <label><input type="checkbox" id="fromgroup" ${e.currentTarget.dataset.fromgroup == 1 ? `checked` : ``}>${tr("post_as_group")}</label>
                </div>
            ` : ``}
            </div>
        `)

        u(content.querySelector("#cancelEditing")).on("click", () => {post.querySelector("#editPost").click()})
        u(content.querySelector("#endEditing")).on("click", () => {
            let nwcntnt = content.querySelector("#new_content").value
            let type = "post"

            if(post.classList.contains("comment")) {
                type = "comment"
            }

            let xhr = new XMLHttpRequest()
            xhr.open("POST", "/wall/edit")

            xhr.onloadstart = () => {
                content.querySelector(".editMenu").classList.add("loading")
            }

            xhr.onerror = () => {
                MessageBox(tr("error"), "unknown error occured", [tr("ok")], [() => {Function.noop}])
            }

            xhr.ontimeout = () => {
                MessageBox(tr("error"), "Try to refresh page", [tr("ok")], [() => {Function.noop}])
            }

            xhr.onload = () => {
                let result = JSON.parse(xhr.responseText)

                if(result.error == "no") {
                    post.querySelector("#editPost").click()
                    content.querySelector(".really_text").innerHTML = result.new_content
    
                    if(post.querySelector(".editedMark") == null) {
                        post.querySelector(".date").insertAdjacentHTML("beforeend", `
                            <span class="edited editedMark">(${tr("edited_short")})</span>
                        `)
                    }

                    if(e.currentTarget.dataset.nsfw != null) {
                        e.currentTarget.setAttribute("data-nsfw", result.nsfw)

                        if(result.nsfw == 0) {
                            post.classList.remove("post-nsfw")
                        } else {
                            post.classList.add("post-nsfw")
                        }
                    }

                    if(e.currentTarget.dataset.fromgroup != null) {
                        e.currentTarget.setAttribute("data-fromgroup", result.from_group)
                    }

                    post.querySelector(".post-avatar").setAttribute("src", result.author.avatar)
                    post.querySelector(".post-author-name").innerHTML = result.author.name
                    post.querySelector(".really_text").setAttribute("data-text", result.new_text)
                } else {
                    MessageBox(tr("error"), result.error, [tr("ok")], [Function.noop])
                    post.querySelector("#editPost").click()
                }
            }

            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.send("postid="+e.currentTarget.dataset.id+
                    "&newContent="+nwcntnt+
                    "&hash="+encodeURIComponent(u("meta[name=csrf]").attr("value"))+
                    "&type="+type+
                    "&nsfw="+(content.querySelector("#nswfw") != null ? content.querySelector("#nswfw").checked : 0)+
                    "&fromgroup="+(content.querySelector("#fromgroup") != null ? content.querySelector("#fromgroup").checked : 0))
        })

        u(".editMenu").on("keydown", (e) => {
            if(e.ctrlKey && e.keyCode === 13)
                content.querySelector("#endEditing").click()
        });

        text.style.display = "none"
        setupWallPostInputHandlers(999)
    } else {
        u(content.querySelector(".editMenu")).remove()
        text.style.display = "block"
    }
})

// copypaste from videos picker
$(document).on("click", "#photosAttachments", async (e) => {
    let body = `
        <div class="topGrayBlock">
            <div style="padding-top: 7px;padding-left: 12px;">
                ${tr("upload_new_photo")}:
                <input type="file" multiple accept="image/*" id="fastFotosUplod" style="display:none">
                <input type="button" class="button" value="${tr("upload_button")}" onclick="fastFotosUplod.click()">
                <select id="albumSelect" style="width: 154px;float: right;margin-right: 17px;">
                    <option value="0">${tr("all_photos")}</option>
                </select>
            </div>
        </div>

        <div class="photosInsert" style="padding: 5px;height: 287px;overflow-y: scroll;">
            <div style="position: fixed;z-index: 1007;width: 92%;background: white;margin-top: -5px;padding-top: 6px;"><h4>${tr("is_x_photos", 0)}</h4></div>
            <div class="photosList album-flex" style="margin-top: 20px;"></div>
        </div>
    `

    let form = e.currentTarget.closest("form")

    MessageBox(tr("select_photo"), body, [tr("close")], [Function.noop]);

    document.querySelector(".ovk-diag-body").style.padding = "0"
    document.querySelector(".ovk-diag-cont").style.width = "630px"
    document.querySelector(".ovk-diag-body").style.height = "335px"

    async function insertPhotos(page, album = 0) {
        u("#loader").remove()

        let insertPlace = document.querySelector(".photosInsert .photosList")
        document.querySelector(".photosInsert").insertAdjacentHTML("beforeend", `<img id="loader" style="max-height: 8px;max-width: 36px;" src="/assets/packages/static/openvk/img/loading_mini.gif">`)
        
        let photos;

        try {
            photos = await API.Photos.getPhotos(page, Number(album))
        } catch(e) {
            document.querySelector(".photosInsert h4").innerHTML = tr("is_x_photos", -1)
            insertPlace.innerHTML = "Invalid album"
            console.error(e)
            u("#loader").remove()
            return;
        }

        document.querySelector(".photosInsert h4").innerHTML = tr("is_x_photos", photos.count)

        let pagesCount = Math.ceil(Number(photos.count) / 24)
        u("#loader").remove()

        for(const photo of photos.items) {
            let isAttached = (form.querySelector("input[name='photos']").value.includes(`${photo.owner_id}_${photo.id},`))

            insertPlace.insertAdjacentHTML("beforeend", `
            <div style="width: 14%;margin-bottom: 7px;margin-left: 13px;" class="album-photo" data-attachmentdata="${photo.owner_id}_${photo.id}" data-preview="${photo.photo_130}">          
                <a href="/photo${photo.owner_id}_${photo.id}">
                    <img class="album-photo--image" src="${photo.photo_130}" alt="..." style="${isAttached ? "background-color: #646464" : ""}">
                </a>
            </div>
            `)
        }

        if(page < pagesCount) {
            insertPlace.insertAdjacentHTML("beforeend", `
            <div id="showMorePhotos" data-pagesCount="${pagesCount}" data-page="${page + 1}" style="width: 100%;text-align: center;background: #f0f0f0;height: 22px;padding-top: 9px;cursor:pointer;">
                <span>more...</span>
            </div>`)
        }
    }

    insertPhotos(1)

    let albums = await API.Photos.getAlbums(Number(e.currentTarget.dataset.club ?? 0))
    
    for(const alb of albums.items) {
        let sel = document.querySelector(".ovk-diag-body #albumSelect")

        sel.insertAdjacentHTML("beforeend", `<option value="${alb.id}">${ovk_proc_strtr(escapeHtml(alb.name), 20)}</option>`)
    }

    $(".photosInsert").on("click", "#showMorePhotos", (e) => {
        u(e.currentTarget).remove()
        insertPhotos(Number(e.currentTarget.dataset.page), document.querySelector(".topGrayBlock #albumSelect").value)
    })

    $(".topGrayBlock #albumSelect").on("change", (evv) => {
        document.querySelector(".photosInsert .photosList").innerHTML = ""

        insertPhotos(1, evv.currentTarget.value)
    })

    function insertAttachment(id) {
        let photos = form.querySelector("input[name='photos']") 

        if(!photos.value.includes(id + ",")) {
            if(photos.value.split(",").length > 10) {
                NewNotification(tr("error"), tr("max_attached_photos"))
                return false
            }

            form.querySelector("input[name='photos']").value += (id + ",")

            console.info(id + " attached")
            return true
        } else {
            form.querySelector("input[name='photos']").value = form.querySelector("input[name='photos']").value.replace(id + ",", "")

            console.info(id + " detached")
            return false
        }
    }

    $(".photosList").on("click", ".album-photo", (ev) => {
        ev.preventDefault()

        if(!insertAttachment(ev.currentTarget.dataset.attachmentdata)) {
            u(form.querySelector(`.upload #aP[data-id='${ev.currentTarget.dataset.attachmentdata}']`)).remove()
            ev.currentTarget.querySelector("img").style.backgroundColor = "white"
        } else {
            ev.currentTarget.querySelector("img").style.backgroundColor = "#646464"
            let id = ev.currentTarget.dataset.attachmentdata

            u(form.querySelector(`.upload`)).append(u(`
                <div class="upload-item" id="aP" data-id="${ev.currentTarget.dataset.attachmentdata}">
                    <a class="upload-delete">×</a>
                    <img src="${ev.currentTarget.dataset.preview}">
                </div>
            `));

            u(`.upload #aP[data-id='${ev.currentTarget.dataset.attachmentdata}'] .upload-delete`).on("click", () => {
                form.querySelector("input[name='photos']").value = form.querySelector("input[name='photos']").value.replace(id + ",", "")
                u(form.querySelector(`.upload #aP[data-id='${ev.currentTarget.dataset.attachmentdata}']`)).remove()
            })
        }
    })

    u("#fastFotosUplod").on("change", (evn) => {
        let xhr = new XMLHttpRequest()
        xhr.open("POST", "/photos/upload")

        let formdata = new FormData()
        let iterator = 0

        for(const fille of evn.currentTarget.files) {
            if(!fille.type.startsWith('image/')) {
                continue;
            }

            if(fille.size > 5 * 1024 * 1024) {
                continue;
            }

            if(evn.currentTarget.files.length >= 10) {
                NewNotification(tr("error"), tr("max_attached_photos"))
                return;
            }

            formdata.append("photo_"+iterator, fille)
            iterator += 1
        }
        
        xhr.onloadstart = () => {
            evn.currentTarget.parentNode.insertAdjacentHTML("beforeend", `<img id="loader" style="max-height: 8px;max-width: 36px;" src="/assets/packages/static/openvk/img/loading_mini.gif">`)
        }

        xhr.onload = () => {
            let result = JSON.parse(xhr.responseText)

            u("#loader").remove()
            if(result.success) {
                for(const pht of result.photos) {
                    let id = pht.owner + "_" + pht.vid

                    if(!insertAttachment(id)) {
                        return
                    }
                    
                    u(form.querySelector(`.upload`)).append(u(`
                        <div class="upload-item" id="aP" data-id="${pht.owner + "_" + pht.vid}">
                            <a class="upload-delete">×</a>
                            <img src="${pht.url}">
                        </div>
                    `));

                    u(`.upload #aP[data-id='${pht.owner + "_" + pht.vid}'] .upload-delete`).on("click", () => {
                        form.querySelector("input[name='photos']").value = form.querySelector("input[name='photos']").value.replace(id + ",", "")
                        u(form.querySelector(`.upload #aP[data-id='${id}']`)).remove()
                    })
                }

                u("body").removeClass("dimmed");
                u(".ovk-diag-cont").remove();
                document.querySelector("html").style.overflowY = "scroll"
            } else {
                // todo: https://vk.com/wall-32295218_78593
                alert(result.flash.message)
            }
        }

        formdata.append("hash", u("meta[name=csrf]").attr("value"))
        formdata.append("count", iterator)
        
        xhr.send(formdata)
    })
})
