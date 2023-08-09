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
            let trans = new DataTransfer();
            trans.items.add(image);
            
            let fileSelect = document.querySelector("#post-buttons" + id + " input[name='_pic_attachment']");
            fileSelect.files = trans.files;
            
            u(fileSelect).trigger("change");
            u("#post-buttons" + id + " #write textarea").trigger("focusin");
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
        if(e.clipboardData.files.length === 1) {
            var input = u("#post-buttons" + id + " input[name=_pic_attachment]").nodes[0];
            input.files = e.clipboardData.files;
            
            u(input).trigger("change");
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

async function initGeo(tid) {
    MessageBox(tr("attach_geotag"), "<div id=\"osm-map\"></div>", ["Прикрепить", tr("cancel")], [(function () {
        let marker = {
            lat: currentMarker._latlng.lat,
            lng: currentMarker._latlng.lng,
            name: $(`#geo-name-input-${tid}`).val() ? $(`#geo-name-input-${tid}`).val() : $(`#geo-name-${tid}`).text()
        };
        $(`#post-buttons${tid} #geo`).val(JSON.stringify(marker));
        $(`#post-buttons${tid} .post-has-geo`).text(`${tr("geotag")}: ${marker.name}`);
        $(`#post-buttons${tid} .post-has-geo`).show();
    }), Function.noop]);

    const element = document.getElementById('osm-map');
    element.style = 'height: 600px;';

    let markerLayers = L.layerGroup();

    let map = L.map(element, {
        center: [55.322978, 38.673362],
        zoom: 10,
        attributionControl: false,
        width: 800
    });
    let currentMarker = null;
    markerLayers.addTo(map);

    map.on('click', (e) => {
        let lat = e.latlng.lat;
        let lng = e.latlng.lng;

        if (currentMarker) map.removeLayer(currentMarker);

        $.get({
            url: `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=jsonv2`,
            success: (response) => {
                markerLayers.clearLayers();

                currentMarker = L.marker([lat, lng]).addTo(map);

                let name = response?.name ?? response?.display_name ?? tr("geotag");
                let content = `<span onclick="let name = prompt('Введите название геоточки'); $('#geo-name-input-${tid}').val(name); $(this).text(name);" id="geo-name-${tid}">${name}</span>`;
                content += `<input type="hidden" id="geo-name-input-${tid}" value="" />`;

                currentMarker.bindPopup(content).openPopup();
                markerLayers.addLayer(currentMarker);
            }
        })
    });

    const geocoderControl = L.Control.geocoder({
        defaultMarkGeocode: false,
    }).addTo(map);

    geocoderControl.on('markgeocode', function (e) {
        console.log(e);
        let lat = e.geocode.properties.lat;
        let lng = e.geocode.properties.lon;
        let name = (e.geocode.properties?.name ?? e.geocode.properties.display_name);

        if (currentMarker) map.removeLayer(currentMarker);

        currentMarker = L.marker([lat, lng]).addTo(map);
        currentMarker.bindPopup(name).openPopup();

        console.log(`${tr("latitude")}: ${lat}, ${tr("longitude")}: ${lng}`);
        console.log(`${tr("name_of_the_place")}: ${name}`);

        let marker = {
            lat: lat,
            lng: lng,
            name: name
        };
        map.setView([lat, lng], 15);
    });

    L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    $(".ovk-diag-cont").width('50%');
    setTimeout(function(){ map.invalidateSize()}, 100);
}

function openGeo(data, owner_id, virtual_id) {
    MessageBox(tr("geotag"), "<div id=\"osm-map\"></div>", [tr("nearest_posts"), "OK"], [(function () {
        getNearPosts(owner_id, virtual_id);
    }), Function.noop]);

    let element = document.getElementById('osm-map');
    element.style = 'height: 600px;';

    let map = L.map(element, {attributionControl: false});
    let target = L.latLng(data.lat, data.lng);
    map.setView(target, 15);

    let marker = L.marker(target).addTo(map);
    marker.bindPopup(data.name ?? tr("geotag")).openPopup();

    L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    $(".ovk-diag-cont").width('50%');
    setTimeout(function(){ map.invalidateSize()}, 100);
}

function getNearPosts(owner_id, virtual_id) {
    $.ajax({
        type: "POST",
        url: `/wall${owner_id}_${virtual_id}/nearest`,
        success: (response) => {
            if (response.success) {
                openNearPosts(response);
            } else {
                MessageBox(tr("error"), `${tr("error_segmentation")}: ${(response?.error ?? "Unknown error")}`, ["OK"], [Function.noop]);
            }
        }
    });
}

function getPostPopup(post) {
    return `<a style="color: inherit; display: block; margin-bottom: 8px;" href="${post.url}" target="_blank">
            <table border="0" style="font-size: 11px;" class="post">
                <tbody>
                    <tr>
                        <td width="54" valign="top">
                            <a href="${post.owner.url}">
                                <img src="${post.owner.avatar_url}" width="50">
                            </a>
                        </td>
                        <td width="100%" valign="top">
                            <div class="post-author">
                                <a href="${post.owner.url}"><b>${post.owner.name}</b></a>
                                ${post.owner.verified ? `<img class="name-checkmark" src="/assets/packages/static/openvk/img/checkmark.png">` : ""}
                                ${post.owner.writes}
                                <br>
                                <a href="${post.url}" class="date">
                                    ${post.time}
                                </a>
                            </div>
                            <div class="post-content" id="2_28">
                                <div class="text" id="text2_28">
                                    ${post.preview}
                                </div>
                                <div style="padding: 4px;">
                                    <div style="border-bottom: #ECECEC solid 1px;"></div>
                                    <div style="cursor: pointer; padding: 4px;"><b>${tr("geotag")}</b>: ${post.geo.name ?? tr("admin_open")}</div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
       </a>`;
}

function openNearPosts(posts) {
    if (posts.posts.length > 0) {
        let MsgTxt = "<div id=\"osm-map\"></div>";
        MsgTxt += "<br /><br /><center style='color: grey;'>Показаны последние 25 постов за месяц</center>";

        MessageBox("Ближайшие посты", MsgTxt, ["OK"], [Function.noop]);

        let element = document.getElementById('osm-map');
        element.style = 'height: 600px;';

        let markerLayers = L.layerGroup();
        let map = L.map(element, {attributionControl: false});

        markerLayers.addTo(map);

        let markersBounds = [];
        let coords = [];

        posts.posts.forEach((post) => {
            if (coords.includes(`${post.geo.lat} ${post.geo.lng}`)) {
                markerLayers.getLayers().forEach((marker) => {
                    if (marker.getLatLng().lat === post.geo.lat && marker.getLatLng().lng === post.geo.lng) {
                        let content = marker.getPopup()._content += getPostPopup(post);
                        if (!content.startsWith(`<div style="max-height: 300px; overflow-y: auto;">`))
                            content = `<div style="max-height: 300px; overflow-y: auto;">${content}`;

                        marker.getPopup().setContent(content);
                    }
                });
            } else {
                let marker = L.marker(L.latLng(post.geo.lat, post.geo.lng)).addTo(map);
                marker.bindPopup(getPostPopup(post));
                markerLayers.addLayer(marker);
                markersBounds.push(marker.getLatLng());
            }

            coords.push(`${post.geo.lat} ${post.geo.lng}`);
        })

        let bounds = L.latLngBounds(markersBounds);
        map.fitBounds(bounds);

        L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        $(".ovk-diag-cont").width('50%');
        setTimeout(function () {
            map.invalidateSize()
        }, 100);
    } else {
        MessageBox("Ближайшие посты", "<center style='color: grey;'>Нет ближайших постов :(</center>", ["OK"], [Function.noop]);
    }
}
