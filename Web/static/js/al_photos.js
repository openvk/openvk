$(document).on("change", "#uploadButton", (e) => {
    let iterator = 0

    if(e.currentTarget.files.length > 10) {
        MessageBox(tr("error"), tr("too_many_pictures"), [tr("ok")], [() => {Function.noop}])
        return;
    }

    for(const file of e.currentTarget.files) {
        if(!file.type.startsWith('image/')) {
            MessageBox(tr("error"), tr("only_images_accepted", escapeHtml(file.name)), [tr("ok")], [() => {Function.noop}])
            return;
        }

        if(file.size > 5 * 1024 * 1024) {
            MessageBox(tr("error"), tr("max_filesize", 5), [tr("ok")], [() => {Function.noop}])
            return;
        }
    }

    if(document.querySelector(".whiteBox").style.display == "block") {
        document.querySelector(".whiteBox").style.display = "none"
        document.querySelector(".insertThere").append(document.getElementById("fakeButton"));
    }

    let photos = new FormData()
    for(file of e.currentTarget.files) {
        photos.append("photo_"+iterator, file)
        iterator += 1
    }

    photos.append("count", e.currentTarget.files.length)
    photos.append("hash", u("meta[name=csrf]").attr("value"))

    let xhr = new XMLHttpRequest()
    xhr.open("POST", "/photos/upload?album="+document.getElementById("album").value)

    xhr.onloadstart = () => {
        document.querySelector(".insertPhotos").insertAdjacentHTML("beforeend", `<img id="loader" src="/assets/packages/static/openvk/img/loading_mini.gif">`)
    }

    xhr.onload = () => {
        let result = JSON.parse(xhr.responseText)

        if(result.success) {
            u("#loader").remove()
            let photosArr = result.photos

            for(photo of photosArr) {
                let table = document.querySelector(".insertPhotos")

                table.insertAdjacentHTML("beforeend", `
                <div id="photo" class="insertedPhoto" data-id="${photo.id}">
                    <div class="uploadedImageDescription" style="float: left;">
                        <span style="color: #464646;position: absolute;">${tr("description")}:</span>
                        <textarea style="margin-left: 62px; resize: none;" maxlength="255"></textarea>
                    </div>
                    <div class="uploadedImage">
                        <a href="${photo.link}" target="_blank"><img width="125" src="${photo.url}"></a>
                        <a class="profile_link" style="width: 125px;" id="deletePhoto" data-id="${photo.vid}" data-owner="${photo.owner}">${tr("delete")}</a>
                        <!--<div class="smallFrame" style="margin-top: 6px;">
                            <div class="smallBtn">${tr("album_poster")}</div>
                        </div>-->
                    </div>
                </div>
                `)
            }

            document.getElementById("endUploading").style.display = "block"
        } else {
            u("#loader").remove()
            MessageBox(tr("error"), escapeHtml(result.flash.message) ?? tr("error_uploading_photo"), [tr("ok")], [() => {Function.noop}])
        }
    }

    xhr.send(photos)
})

$(document).on("click", "#endUploading", (e) => {
    let table = document.querySelector("#photos")
    let data  = new FormData()
    let arr   = new Map();
    for(el of table.querySelectorAll("div#photo")) {
        arr.set(el.dataset.id, el.querySelector("textarea").value)
    }

    data.append("photos", JSON.stringify(Object.fromEntries(arr)))
    data.append("hash", u("meta[name=csrf]").attr("value"))

    let xhr = new XMLHttpRequest()
    // в самом вк на каждое изменение описания отправляется свой запрос, но тут мы экономим запросы
    xhr.open("POST", "/photos/upload?act=finish&album="+document.getElementById("album").value)

    xhr.onloadstart = () => {
        e.currentTarget.setAttribute("disabled", "disabled")
    }

    xhr.onerror = () => {
        MessageBox(tr("error"), tr("error_uploading_photo"), [tr("ok")], [() => {Function.noop}])
    }

    xhr.onload = () => {
        let result = JSON.parse(xhr.responseText)

        if(!result.success) {
            MessageBox(tr("error"), escapeHtml(result.flash.message), [tr("ok")], [() => {Function.noop}])
        } else {
            document.querySelector(".page_content .insertPhotos").innerHTML = ""
            document.getElementById("endUploading").style.display = "none"
    
            NewNotification(tr("photos_successfully_uploaded"), tr("click_to_go_to_album"), null, () => {window.location.assign(`/album${result.owner}_${result.album}`)})
            
            document.querySelector(".whiteBox").style.display = "block"
            document.querySelector(".insertAgain").append(document.getElementById("fakeButton"))
        }

        e.currentTarget.removeAttribute("disabled")
    }

    xhr.send(data)
})

$(document).on("click", "#deletePhoto", (e) => {
    let data  = new FormData()
    data.append("hash", u("meta[name=csrf]").attr("value"))

    let xhr = new XMLHttpRequest()
    xhr.open("POST", `/photo${e.currentTarget.dataset.owner}_${e.currentTarget.dataset.id}/delete`)

    xhr.onloadstart = () => {
        e.currentTarget.closest("div#photo").classList.add("lagged")
    }

    xhr.onerror = () => {
        MessageBox(tr("error"), tr("unknown_error"), [tr("ok")], [() => {Function.noop}])
    }

    xhr.onload = () => {
        u(e.currentTarget.closest("div#photo")).remove()

        if(document.querySelectorAll("div#photo").length < 1) {
            document.getElementById("endUploading").style.display = "none"
            document.querySelector(".whiteBox").style.display = "block"
            document.querySelector(".insertAgain").append(document.getElementById("fakeButton"))
        }
    }

    xhr.send(data)
})

$(document).on("dragover drop", (e) => {
    e.preventDefault()

    return false;
})

$(".container_gray").on("dragover", (e) => {
    e.preventDefault()
    document.querySelector("#fakeButton").classList.add("dragged")
    document.querySelector("#fakeButton").value = tr("drag_files_here")
})

$(".container_gray").on("dragleave", (e) => {
    e.preventDefault()
    document.querySelector("#fakeButton").classList.remove("dragged")
    document.querySelector("#fakeButton").value = tr("upload_picts")
})

$(".container_gray").on("drop", (e) => {
    e.originalEvent.dataTransfer.dropEffect = 'move';
    e.preventDefault()

    $(".container_gray").trigger("dragleave")

    let files = e.originalEvent.dataTransfer.files

    for(const file of files) {
        if(!file.type.startsWith('image/')) {
            MessageBox(tr("error"), tr("only_images_accepted", escapeHtml(file.name)), [tr("ok")], [() => {Function.noop}])
            return;
        }

        if(file.size > 5 * 1024 * 1024) {
            MessageBox(tr("error"), tr("max_filesize", 5), [tr("ok")], [() => {Function.noop}])
            return;
        }
    }

    document.getElementById("uploadButton").files = files
    u("#uploadButton").trigger("change")
})

u(".container_gray").on("paste", (e) => {
    if(e.clipboardData.files.length > 0 && e.clipboardData.files.length < 10) {
        document.getElementById("uploadButton").files = e.clipboardData.files;
        u("#uploadButton").trigger("change")
    }
})
