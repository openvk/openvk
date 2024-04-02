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

function trimNum(string, num) {
    var newStr = string.substring(0, num);
    if(newStr.length !== string.length)
        newStr += "…";

    return newStr;
}

function handleUpload(id) {
    console.warn("блять...");
    
    u("#post-buttons" + id + " .postFileSel").not("#" + this.id).each(input => input.value = null);
    
    var indicator = u("#post-buttons" + id + " .post-upload");
    var file      = this.files[0];
    if(typeof file === "undefined") {
        indicator.attr("style", "display: none;");
    } else {
        u("span", indicator.nodes[0]).text(trim(file.name) + " (" + humanFileSize(file.size, false) + ")");
        indicator.attr("style", "display: block;");
    }

    document.querySelector("#post-buttons" + id + " #wallAttachmentMenu").classList.add("hidden");
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

$(document).on("click", ".post-like-button", function(e) {
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

u(document).on("input", "textarea", function(e) {
    var boost             = 5;
    var textArea          = e.target;
    textArea.style.height = "5px";
    var newHeight = textArea.scrollHeight;
    textArea.style.height = newHeight + boost + "px";
    return;
    
    // revert to original size if it is larger (possibly changed by user)
    // textArea.style.height = (newHeight > originalHeight ? (newHeight + boost) : originalHeight) + "px";
});

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
            <center style="margin-bottom: 8pt; position: relative;">
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
        document.getElementById("note").value = nid
        let noteObj = document.querySelector("#nd"+nid)
    
        let nortd = document.querySelector("#post-buttons"+textareaId+" .post-has-note");
        nortd.style.display = "block"
    
        nortd.innerHTML = `${tr("note")} ${escapeHtml(noteObj.dataset.name)}`
    } else {
        document.getElementById("note").value = "none"

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

            if(document.getElementById("note").value != "none") {
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

// Оконный плеер

$(document).on("click", "#videoOpen", async (e) => {
    e.preventDefault()

    document.getElementById("ajloader").style.display = "block"

    if(document.querySelector(".ovk-fullscreen-dimmer") != null) {
        u(".ovk-fullscreen-dimmer").remove()
    }

    let target   = e.currentTarget
    let videoId  = target.dataset.id
    let videoObj = null;

    try {
        videoObj = await API.Video.getVideo(Number(videoId))
    } catch(e) {
        console.error(e)
        document.getElementById("ajloader").style.display = "none"
        MessageBox(tr("error"), tr("video_access_denied"), [tr("cancel")], [
        function() {
            Function.noop
        }]);
        return 0;
    }

    document.querySelector("html").style.overflowY = "hidden"

    let player = null;

    if(target.dataset.dontload == null) {
        document.querySelectorAll("video").forEach(vid => vid.pause())
        if(videoObj.type == 0) {
            if(videoObj.isProcessing) {
                player = `
                    <span class="gray">${tr("video_processing")}</span>
                `
            } else {
                player = `
                <div class="bsdn media" data-name="${escapeHtml(videoObj.title)}" data-author="${escapeHtml(videoObj.name)}">
                    <video class="media" src="${videoObj.url}"></video>
                </div>`
            }
        } else {
            player = videoObj.embed
        }
    } else {
        player = ``
    }


    let dialog = u(
        `
        <div class="ovk-fullscreen-dimmer">
            <div class="ovk-fullscreen-player">
                ${videoObj.prevVideo != null ?
                `<div class="right-arrow" id="videoOpen" data-id="${videoObj.prevVideo}">
                    <img src="/assets/packages/static/openvk/img/right_arr.png" draggable="false">
                </div>` : ""}
                ${videoObj.nextVideo != null ? `
                <div class="left-arrow" id="videoOpen" data-id="${videoObj.nextVideo}" style="margin-left: 820px;">
                    <img src="/assets/packages/static/openvk/img/left_arr.png" draggable="false">
                </div>` : ""}
                <div class="inner-player">
                    <div class="top-part">
                        <span class="top-part-name">${escapeHtml(videoObj.title)}</span>
                        <div class="top-part-buttons">
                            <span class="clickable" id="minimizePlayer" data-name="${escapeHtml(videoObj.title)}" data-id="${videoObj.id}">${tr("hide_player")}</span>
                            <span>|</span>
                            <span class="clickable" id="closeFplayer">${tr("close_player")}</span>
                        </div>
                        <div class="top-part-player-subdiv">
                            ${target.dataset.dontload == null ?`
                            <div class="fplayer">
                                ${player}
                            </div>` : ""}
                        </div>
                        <div class="top-part-bottom-buttons">
                            <span class="clickable" id="showComments" data-id="${videoObj.id}" data-owner="${videoObj.owner}" data-pid="${videoObj.pretty_id}">${tr("show_comments")}</span>
                            <span>|</span>
                            <span class="clickable" id="gotopage" data-id="/video${videoObj.pretty_id}">${tr("to_page")}</span>
                            ${ videoObj.type == 0 && videoObj.isProcessing == false ? `<span>|</span>
                            <a class="clickable" href="${videoObj.url}" download><span class="clickable">${tr("download_video")}</span></a>` : ""}
                        </div>
                    </div>
                </div>
                <div class="bottom-part">
                    <div class="left_block">
                        <div class="description" style="margin-bottom: 5px;">
                            <span>${videoObj.description != null ? escapeHtml(videoObj.description) : "(" + tr("no_description") + ")"}</span>
                        </div>
                        <div class="bottom-part-info" style="display: flex;">
                            <span class="gray">${tr("added")} ${videoObj.published}&nbsp;</span><span>|</span>
                            <div class="like_wrap" style="float:unset;">
                                <a href="/video${videoObj.pretty_id}/like?hash=${encodeURIComponent(u("meta[name=csrf]").attr("value"))}" class="post-like-button" data-liked="${videoObj.has_like ? 1 : 0}" data-likes="${videoObj.likes}">
                                    <div class="heart" id="${videoObj.has_like ? "liked" : ""}"></div>
                                    <span class="likeCnt" style="margin-top: -2px;">${videoObj.likes > 0 ? videoObj.likes : ""}</span>
                                </a>
                            </div>
                        </div>
                        <div id="vidComments"></div>
                    </div>
                    <div class="right_block">
                        <div class="views">
                            <!--prosmoters are not implemented((-->
                            <span class="gray">${tr("x_views", 0)}</span>
                        </div>
                        
                        <div class="v_author">
                            <span class="gray">${tr("video_author")}:</span><br>
                            <a href="/id${videoObj.owner}"><span style="color:unset;">${videoObj.author}</span></a>
                        </div>
                        <div class="actions" style="margin-top: 10px;margin-left: -3px;">
                        ${videoObj.canBeEdited ? `
                            <a href="/video${videoObj.pretty_id}/edit" class="profile_link" style="display:block;width:96%;font-size: 13px;">
                                ${tr("edit")}
                            </a>
                            <a href="/video${videoObj.pretty_id}/remove" class="profile_link" style="display:block;width:96%;font-size: 13px;">
                                ${tr("delete")}
                            </a>`
                         : ""}
                            <a id="shareVideo" class="profile_link" id="shareVideo" data-owner="${videoObj.owner}" data-vid="${videoObj.virtual_id}" style="display:block;width:96%;font-size: 13px;">
                                ${tr("share")}
                            </a>
                         </div>
                    </div>
                </div>
            </div>
        </div>`);

    u("body").addClass("dimmed").append(dialog);

    if(target.dataset.dontload != null) {
        let oldPlayer = document.querySelector(".miniplayer-video .fplayer")
        let newPlayer = document.querySelector(".top-part-player-subdiv")

        newPlayer.append(oldPlayer)
    }

    if(videoObj.type == 0 && videoObj.isProcessing == false) {
        bsdnInitElement(document.querySelector(".fplayer .bsdn"))
    }

    document.getElementById("ajloader").style.display = "none"
    u(".miniplayer").remove()
})

$(document).on("click", "#closeFplayer", async (e) => {
    u(".ovk-fullscreen-dimmer").remove();
    document.querySelector("html").style.overflowY = "scroll"
    u("body").removeClass("dimmed")
})

$(document).on("click", "#minimizePlayer", async (e) => {
    let targ = e.currentTarget

    let player    = document.querySelector(".fplayer")

    let dialog = u(`
        <div class="miniplayer">
            <span class="miniplayer-name">${escapeHtml(trimNum(targ.dataset.name, 26))}</span>
            <div class="miniplayer-actions">
                <img src="/assets/packages/static/openvk/img/miniplayer_open.png" id="videoOpen" data-dontload="true" data-id="${targ.dataset.id}">
                <img src="/assets/packages/static/openvk/img/miniplayer_close.png" id="closeMiniplayer">
            </div>
            <div class="miniplayer-video">
            
            </div>
        </div>
    `);

    u("body").append(dialog);
    $('.miniplayer').draggable({cursor: "grabbing", containment: "body", cancel: ".miniplayer-video"});

    let newPlayer = document.querySelector(".miniplayer-video")
    newPlayer.append(player)

    document.querySelector(".miniplayer").style.top = window.scrollY;
    document.querySelector("#closeFplayer").click()
})

$(document).on("click", "#closeMiniplayer", async (e) => {
    u(".miniplayer").remove()
})

$(document).on("mouseup", "#gotopage", async (e) => {
    if(e.originalEvent.which === 1) {
        location.href = e.currentTarget.dataset.id
    } else if (e.originalEvent.which === 2) { 
        window.open(e.currentTarget.dataset.id, '_blank')
    }

})

$(document).keydown(function(e) {
    if(document.querySelector(".top-part-player-subdiv .bsdn") != null && document.activeElement.tagName == "BODY") {
        let video = document.querySelector(".top-part-player-subdiv video")

        switch(e.keyCode) {
            // Пробел вроде
            case 32:
                document.querySelector(".top-part-player-subdiv .bsdn_teaserButton").click()
                break
            // Стрелка вниз, уменьшение громкости
            case 40:
                oldVolume = video.volume

                if(oldVolume - 0.1 > 0) {
                    video.volume = oldVolume - 0.1
                } else {
                    video.volume = 0
                }

                break;
            // Стрелка вверх, повышение громкости
            case 38:
                oldVolume = video.volume

                if(oldVolume + 0.1 < 1) {
                    video.volume = oldVolume + 0.1
                } else {
                    video.volume = 1
                }

                break
            // стрелка влево, отступ на 2 секунды назад
            case 37:
                oldTime = video.currentTime
                video.currentTime = oldTime - 2
                break
            // стрелка вправо, отступ на 2 секунды вперёд
            case 39:
                oldTime = document.querySelector(".top-part-player-subdiv video").currentTime
                document.querySelector(".top-part-player-subdiv video").currentTime = oldTime + 2
                break
        }
    }
});

$(document).keyup(function(e) {
    if(document.querySelector(".top-part-player-subdiv .bsdn") != null && document.activeElement.tagName == "BODY") {
        let video = document.querySelector(".top-part-player-subdiv video")

        switch(e.keyCode) {
            // Escape, закрытие плеера
            case 27:
                document.querySelector("#closeFplayer").click()
                break
            // Блять, я перепутал лево и право, пиздец я долбаёб конечно
            // Ну короче стрелка влево
            case 65:
                if(document.querySelector(".right-arrow") != null) {
                    document.querySelector(".right-arrow").click()
                } else {
                    console.info("No left arrow bro")
                }
                break
            // Фуллскрин
            case 70:
                document.querySelector(".top-part-player-subdiv .bsdn_fullScreenButton").click()
                break
            // стрелка вправо
            case 68:
                if(document.querySelector(".left-arrow") != null) {
                    document.querySelector(".left-arrow").click()
                } else {
                    console.info("No right arrow bro")
                }
                break;
            // S: Показать инфо о видео (не комментарии)
            case 83:
                document.querySelector(".top-part-player-subdiv #showComments").click()
                break
            // Мут (M)
            case 77:
                document.querySelector(".top-part-player-subdiv .bsdn_soundIcon").click()
                break;
            // Escape, выход из плеера
            case 192:
                document.querySelector(".top-part-buttons #minimizePlayer").click()
                break
            // Бля не помню сори
            case 75:
                document.querySelector(".top-part-player-subdiv .bsdn_playButton").click()
                break
            // Home, переход в начало видосика
            case 36:
                video.currentTime = 0
                break
            // End, переход в конец видосика
            case 35:
                video.currentTime = video.duration
                break;
        }
    }
});

$(document).on("click", "#showComments", async (e) => {
    if(document.querySelector(".bottom-part").style.display == "none" || document.querySelector(".bottom-part").style.display == "") {
        if(document.getElementById("vidComments").innerHTML == "") {
            let xhr = new XMLHttpRequest
            xhr.open("GET", "/video"+e.currentTarget.dataset.pid)
            xhr.onloadstart = () => {
                document.getElementById("vidComments").innerHTML = `<img src="/assets/packages/static/openvk/img/loading_mini.gif">`
            }

            xhr.timeout = 10000;

            xhr.onload = () => {
                let parser = new DOMParser();
                let body   = parser.parseFromString(xhr.responseText, "text/html");
                let comms  = body.getElementById("comments")
                let commsHTML = comms.innerHTML.replace("expand_wall_textarea(11)", "expand_wall_textarea(999)")
                                                .replace("wall-post-input11", "wall-post-input999")
                                                .replace("post-buttons11", "post-buttons999")
                                                .replace("toggleMenu(11)", "toggleMenu(999)")
                                                .replace("toggleMenu(11)", "toggleMenu(999)")
                                                .replace(/ons11/g, "ons999")
                document.getElementById("vidComments").innerHTML = commsHTML
            }

            xhr.onerror = () => {
                document.getElementById("vidComments").innerHTML = `<span>${tr("comments_load_timeout")}</span>`
            }

            xhr.ontimeout = () => {
                document.getElementById("vidComments").innerHTML = `<span>${tr("comments_load_timeout")}</span>`
            };

            xhr.send()
        }

        document.querySelector(".bottom-part").style.display = "flex"
        e.currentTarget.innerHTML = tr("close_comments")
    } else {
        document.querySelector(".bottom-part").style.display = "none"
        e.currentTarget.innerHTML = tr("show_comments")
    }
})

$(document).on("click", "#shareVideo", async (e) => {
    let owner_id   = e.currentTarget.dataset.owner
    let virtual_id = e.currentTarget.dataset.vid
    let body = `
        <b>${tr('auditory')}:</b> <br/>
        <input type="radio" name="type" onchange="signs.setAttribute('hidden', 'hidden');document.getElementById('groupId').setAttribute('hidden', 'hidden')" value="0" checked>${tr("in_wall")}<br/>
        <input type="radio" name="type" onchange="signs.removeAttribute('hidden');document.getElementById('groupId').removeAttribute('hidden')" value="1" id="group">${tr("in_group")}<br/>
        <select style="width:50%;" id="groupId" name="groupId" hidden>
        </select><br/>
        <b>${tr('your_comment')}:</b> 
        <textarea id='uRepostMsgInput'></textarea>
        <div id="signs" hidden>
        <label><input onchange="signed.checked ? signed.checked = false : null" type="checkbox" id="asgroup" name="asGroup">${tr('post_as_group')}</label><br>
        <label><input onchange="asgroup.checked = true" type="checkbox" id="signed" name="signed">${tr('add_signature')}</label>
        </div>
    `
    MessageBox(tr("share_video"), body, [tr("share"), tr("cancel")], [
        (async function() {
            let type   = $('input[name=type]:checked').val()
            let club   = document.getElementById("groupId").value

            let asGroup = document.getElementById("asgroup").checked
            let signed  = document.getElementById("signed").checked

            let repost = null;

            try {
                repost = await API.Video.shareVideo(Number(owner_id), Number(virtual_id), Number(type), uRepostMsgInput.value, Number(club), signed, asGroup)
                NewNotification(tr('information_-1'), tr('shared_succ_video'), null, () => {window.location.href = "/wall" + repost.pretty_id});
            } catch(e) {
                console.log("tudu")
            }
        }), (function() {
                Function.noop
        })], false);

    try {
        clubs = await API.Groups.getWriteableClubs();
        for(const el of clubs) {
            document.getElementById("groupId").insertAdjacentHTML("beforeend", `<option value="${el.id}">${escapeHtml(el.name)}</option>`)
        }
    } catch(rejection) {
        console.error(rejection)
        document.getElementById("group").setAttribute("disabled", "disabled")
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

$(document).on("click", "#add_image", (e) => {
    let isGroup = e.currentTarget.closest(".avatar_block").dataset.club != null
    let group = isGroup ? e.currentTarget.closest(".avatar_block").dataset.club : 0

    let body = `
    <div id="avatarUpload">
        <p>${isGroup == true ? tr('groups_avatar') : tr('friends_avatar')}</p>
        <p>${tr('formats_avatar')}</p><br>

        <label class="button" style="margin-left:45%;user-select:none" id="uploadbtn">
            ${tr("browse")}
            <input accept="image/*" type="file" id="_avaInput" name="blob" hidden style="display: none;">
        </label>

        <br><br>

        <p>${tr('troubles_avatar')}</p>
        <p>${tr('webcam_avatar')}</p>
    </div>
    `

    let msg = MessageBox(tr('uploading_new_image'), body, [
        tr('cancel')
    ], [
        (function() {
            u("#tmpPhDelF").remove();
        }),
    ]);

    msg.attr("style", "width: 600px;");
    document.querySelector(".ovk-diag-body").style.padding = "13px"

    $("#avatarUpload input").on("change", (ev) => {
        let image = URL.createObjectURL(ev.currentTarget.files[0])
        $(".ovk-diag-body")[0].innerHTML = `
            <span>${!isGroup ? tr("selected_area_user") : tr("selected_area_club")}</span>

            <p style="margin-bottom: 10px;">${tr("selected_area_rotate")}</p>

            <div class="cropper-image-cont" style="max-height: 274px;">
                <img src="${image}" id="temp_uploadPic">

                <div class="rotateButtons">
                    <div class="_rotateLeft hoverable"></div>
                    <div class="_rotateRight hoverable"></div>
                </div>
            </div>

            <label style="margin-top: 14px;display: block;">
                <input id="publish_on_wall" type="checkbox" checked>${tr("publish_on_wall")}
            </label>
        `

        document.querySelector(".ovk-diag-action").insertAdjacentHTML("beforeend", `
            <button class="button" style="margin-left: 4px;" id="_uploadImg">${tr("upload_button")}</button>
        `)
        
        const image_div = document.getElementById('temp_uploadPic');
        const cropper = new Cropper(image_div, {
            aspectRatio: NaN,
            zoomable: true,
            minCropBoxWidth: 150,
            minCropBoxHeight: 150,
            dragMode: 'move',
            background: false,
            center: false,
            guides: false,
            modal: true,
            viewMode: 2,
            cropstart(event) {
                document.querySelector(".cropper-container").classList.add("moving")
            },
            cropend(event) {
                document.querySelector(".cropper-container").classList.remove("moving")
            },
        });

        msg.attr("style", "width: 487px;");

        document.querySelector("#_uploadImg").onclick = (evv) => {
            cropper.getCroppedCanvas({
                fillColor: '#fff',
                imageSmoothingEnabled: false,
                imageSmoothingQuality: 'high',
            }).toBlob((blob) => {
                document.querySelector("#_uploadImg").classList.add("lagged")
                let formdata = new FormData()
                formdata.append("blob", blob)
                formdata.append("ajax", 1)
                formdata.append("on_wall", Number(document.querySelector("#publish_on_wall").checked))
                formdata.append("hash", u("meta[name=csrf]").attr("value"))
        
                $.ajax({
                    type: "POST",
                    url: isGroup ? "/club" + group + "/al_avatar" : "/al_avatars",
                    data: formdata,
                    processData: false,
                    contentType: false,
                    error: (response) => {
                        fastError(response.flash.message)
                    },
                    success: (response) => {
                        document.querySelector("#_uploadImg").classList.remove("lagged")
                        u("body").removeClass("dimmed");
                        document.querySelector("html").style.overflowY = "scroll"
                        u(".ovk-diag-cont").remove();

                        if(!response.success) {
                            fastError(response.flash.message)
                            return
                        }
                        
                        document.querySelector("#bigAvatar").src = response.url
                        document.querySelector("#bigAvatar").parentNode.href = "/photo" + response.new_photo
                    
                        document.querySelector(".add_image_text").style.display = "none"
                        document.querySelector(".avatar_controls").style.display = "block"
                    }
                })
            })
        }

        $(".ovk-diag-body ._rotateLeft").on("click", (e) => {
            cropper.rotate(90)
        })

        $(".ovk-diag-body ._rotateRight").on("click", (e) => {
            cropper.rotate(-90)
        })
    })

    $(".ovk-diag-body #_takeSelfie").on("click", (e) => {
        $("#avatarUpload")[0].style.display = "none"

        $(".ovk-diag-body")[0].insertAdjacentHTML("beforeend", `
            <div id="_takeSelfieFrame" style="text-align: center;">
                <video style="max-width: 100%;max-height: 479px;"></video>
                <canvas id="_tempCanvas" style="position: absolute;">
            </div>
        `)

        let video = document.querySelector("#_takeSelfieFrame video")

        if(!navigator.mediaDevices) {
            // ех вот бы месседжбоксы были бы классами
            u("body").removeClass("dimmed");
            document.querySelector("html").style.overflowY = "scroll"
            u(".ovk-diag-cont").remove();

            fastError(tr("your_browser_doesnt_support_webcam"))

            return
        }

        navigator.mediaDevices
        .getUserMedia({ video: true, audio: false })
        .then((stream) => {
            video.srcObject = stream;
            video.play()

            window._cameraStream = stream
        })
        .catch((err) => {
            u("body").removeClass("dimmed");
            document.querySelector("html").style.overflowY = "scroll"
            u(".ovk-diag-cont").remove();

            fastError(err)
        });
        
        function __closeConnection() {
            window._cameraStream.getTracks().forEach(track => track.stop())
        }

        document.querySelector(".ovk-diag-action").insertAdjacentHTML("beforeend", `
            <button class="button" style="margin-left: 4px;" id="_takeSnap">${tr("take_snapshot")}</button>
        `)

        document.querySelector(".ovk-diag-action button").onclick = (evv) => {
            __closeConnection()
        }

        document.querySelector("#_takeSnap").onclick = (evv) => {
            let canvas = document.getElementById('_tempCanvas')
            let context = canvas.getContext('2d')

            canvas.setAttribute("width", video.clientWidth)
            canvas.setAttribute("height", video.clientHeight)
            context.drawImage(video, 0, 0, video.clientWidth, video.clientHeight);
            canvas.toBlob((blob) => {
                $("#_takeSnap").remove()
                
                let file = new File([blob], "snapshot.jpg", {type: "image/jpeg", lastModified: new Date().getTime()})
                let dt = new DataTransfer();
                dt.items.add(file);

                $("#_avaInput")[0].files = dt.files
                $("#_avaInput").trigger("change")
                $("#_takeSelfieFrame").remove()

                __closeConnection()
            })
        }
    })
})

$(document).on("click", ".avatarDelete", (e) => {
    let isGroup = e.currentTarget.closest(".avatar_block").dataset.club != null
    let group = isGroup ? e.currentTarget.closest(".avatar_block").dataset.club : 0

    let body = `
        <span>${tr("deleting_avatar_sure")}</span>
    `

    let msg = MessageBox(tr('deleting_avatar'), body, [
        tr('yes'),
        tr('no')
    ], [
        (function() {
            let formdata = new FormData()
            formdata.append("hash", u("meta[name=csrf]").attr("value"))

            $.ajax({
                type: "POST",
                url: isGroup ? "/club" + group + "/delete_avatar" : "/delete_avatar",
                data: formdata,
                processData: false,
                contentType: false,
                beforeSend: () => {
                    document.querySelector(".avatarDelete").classList.add("lagged")
                },
                error: (response) => {
                    fastError(response.flash.message)
                },
                success: (response) => {
                    if(!response.success) {
                        fastError(response.flash.message)
                        return
                    }

                    document.querySelector(".avatarDelete").classList.remove("lagged")
                    
                    u("body").removeClass("dimmed");
                    document.querySelector("html").style.overflowY = "scroll"
                    u(".ovk-diag-cont").remove();

                    document.querySelector("#bigAvatar").src = response.url
                    document.querySelector("#bigAvatar").parentNode.href = response.new_photo ? ("/photo" + response.new_photo) : "javascript:void(0)"
                    
                    if(!response.has_new_photo) {
                        document.querySelector(".avatar_controls").style.display = "none"
                        document.querySelector(".add_image_text").style.display = "block"
                    }
                }
            })
        }),
        (function() {
            u("#tmpPhDelF").remove();
        }),
    ]);
})
