
function expand_wall_textarea(id) {
    var el = document.getElementById('post-buttons'+id);
    var wi = document.getElementById('wall-post-input'+id);
    el.style.display = "block";
    wi.className = "expanded-textarea";
}

function expand_comment_textarea(id) {
    var el = document.getElementById('commentTextArea'+id);
    var wi = document.getElementById('wall-post-input'+id);
    el.style.display = "block";
    wi.focus();
}

function edit_post(id, wid) {
    var el = document.getElementById('text'+wid+'_'+id);
    var ed = document.getElementById('text_edit'+wid+'_'+id);
    if (el.style.display == "none") {
        el.style.display = "block";
        ed.style.display = "none";
    } else {
        el.style.display = "none";
        ed.style.display = "block";
    }
}


function hidePanel(panel, count = 0)
{
    $(panel).toggleClass("content_title_expanded content_title_unexpanded");
    $(panel).next('div').slideToggle(300);
    if(count != 0){
        if($(panel).hasClass("content_title_expanded"))
            $(panel).html($(panel).html().replaceAll(" ("+count+")", ""));
        else
            $(panel).html($(panel).html() + " ("+count+")");
    }

}

function parseAjaxResponse(responseString) {
    try {
        const response = JSON.parse(responseString);
        if(response.flash)
            NewNotification(response.flash.title, response.flash.message || "", null);

        return response.success || false;
    } catch(error) {
        if(responseString === "Хакеры? Интересно...") {
            location.reload();
            return false;
        } else {
            throw error;
        }
    }
}

function toggleMenu(id) {
    if($(`#post-buttons${id} #wallAttachmentMenu`).is('.hidden')) {
        $(`#post-buttons${id} #wallAttachmentMenu`).css({ opacity: 0 });
        $(`#post-buttons${id} #wallAttachmentMenu`).toggleClass('hidden').fadeTo(250, 1);
    } else {
        $(`#post-buttons${id} #wallAttachmentMenu`).fadeTo(250, 0, function () {
            $(this).toggleClass('hidden');
        });
    }
}
document.addEventListener("DOMContentLoaded", function() { //BEGIN

    $(document).on("click", "#_photoDelete", function(e) {
        var formHtml = "<form id='tmpPhDelF' action='" + u(this).attr("href") + "' >";
        formHtml    += "<input type='hidden' name='hash' value='" + u("meta[name=csrf]").attr("value") + "' />";
        formHtml    += "</form>";
        u("body").append(formHtml);
        
        MessageBox(tr('warning'), tr('question_confirm'), [
            tr('yes'),
            tr('no')
        ], [
            (function() {
                u("#tmpPhDelF").nodes[0].submit();
            }),
            (function() {
                u("#tmpPhDelF").remove();
            }),
        ]);
        
        return e.preventDefault();
    });
    /* @rem-pai why this func wasn't named as "#_deleteDialog"? It looks universal IMO */

    u("#_noteDelete").on("click", function(e) {
        var formHtml = "<form id='tmpPhDelF' action='" + u(this).attr("href") + "' >";
        formHtml    += "<input type='hidden' name='hash' value='" + u("meta[name=csrf]").attr("value") + "' />";
        formHtml    += "</form>";
        u("body").append(formHtml);
        
        MessageBox(tr('warning'), tr('question_confirm'), [
            tr('yes'),
            tr('no')
        ], [
            (function() {
                u("#tmpPhDelF").nodes[0].submit();
            }),
            (function() {
                u("#tmpPhDelF").remove();
            }),
        ]);
        
        return e.preventDefault();
    });

    u("#_pinGroup").on("click", async function(e) {
        e.preventDefault();

        let link = u(this).attr("href");
        let thisButton = u(this);
        let groupName = u(this).attr("data-group-name");
        let groupUrl = u(this).attr("data-group-url");
        let list = u('#_groupListPinnedGroups');
        
        thisButton.nodes[0].classList.add('loading');
        thisButton.nodes[0].classList.add('disable');

        let req = await ky(link);
        if(req.ok == false) {
            NewNotification(tr('error'), tr('error_1'), null);
            thisButton.nodes[0].classList.remove('loading');
            thisButton.nodes[0].classList.remove('disable');
            return;
        }

        if(!parseAjaxResponse(await req.text())) {
            thisButton.nodes[0].classList.remove('loading');
            thisButton.nodes[0].classList.remove('disable');
            return;
        }

        // Adding a divider if not already there
        if(list.nodes[0].children.length == 0) {
            list.nodes[0].append(u('<div class="menu_divider"></div>').first());
        }

        // Changing the button name
        if(thisButton.html().trim() == tr('remove_from_left_menu')) {
            thisButton.html(tr('add_to_left_menu'));
            for(let i = 0; i < list.nodes[0].children.length; i++) {
                let element = list.nodes[0].children[i];
                if(element.pathname == groupUrl) {
                    element.remove();
                }
            }
        }else{
            thisButton.html(tr('remove_from_left_menu'));
            list.nodes[0].append(u('<a href="' + groupUrl + '" class="link group_link">' + groupName + '</a>').first());
        }

        // Adding the group to the left group list
        if(list.nodes[0].children[0].className != "menu_divider" || list.nodes[0].children.length == 1) {
            list.nodes[0].children[0].remove();
        }
        
        thisButton.nodes[0].classList.remove('loading');
        thisButton.nodes[0].classList.remove('disable');

        return false;
    });

}); //END ONREADY DECLS

async function repostPost(id, hash) {
    uRepostMsgTxt  = `
    <b>${tr('auditory')}:</b> <br/>
    <input type="radio" name="type" onchange="signs.setAttribute('hidden', 'hidden');document.getElementById('groupId').setAttribute('hidden', 'hidden')" value="wall" checked>${tr("in_wall")}<br/>
    <input type="radio" name="type" onchange="signs.removeAttribute('hidden');document.getElementById('groupId').removeAttribute('hidden')" value="group" id="group">${tr("in_group")}<br/>
    <select style="width:50%;" id="groupId" name="groupId" hidden>
    </select><br/>
    <b>${tr('your_comment')}:</b> 
    <textarea id='uRepostMsgInput_${id}'></textarea>
    <div id="signs" hidden>
    <label><input onchange="signed.checked ? signed.checked = false : null" type="checkbox" id="asgroup" name="asGroup">${tr('post_as_group')}</label><br>
    <label><input onchange="asgroup.checked = true" type="checkbox" id="signed" name="signed">${tr('add_signature')}</label>
    </div>
    <br/><br/>`;
    let clubs = [];
    repostsCount = document.getElementById("repostsCount"+id)
    prevVal = repostsCount != null ? Number(repostsCount.innerHTML) : 0;

    MessageBox(tr('share'), uRepostMsgTxt, [tr('send'), tr('cancel')], [
        (function() {
            text = document.querySelector("#uRepostMsgInput_"+id).value;
            type = "user";
            radios = document.querySelectorAll('input[name="type"]')
            for(const r of radios)
            {
                if(r.checked)
                {
                    type = r.value;
                    break;
                }
            }
            groupId = document.querySelector("#groupId").value;
            asGroup = asgroup.checked == true ? 1 : 0;
            signed  = signed.checked == true ? 1 : 0;
            hash = encodeURIComponent(hash);
            
            xhr = new XMLHttpRequest();
            xhr.open("POST", "/wall"+id+"/repost?hash="+hash, true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.onload = (function() {
                if(xhr.responseText.indexOf("wall_owner") === -1)
                    MessageBox(tr('error'), tr('error_repost_fail'), [tr('ok')], [Function.noop]);
                else {
                    let jsonR = JSON.parse(xhr.responseText);
                    NewNotification(tr('information_-1'), tr('shared_succ'), null, () => {window.location.href = "/wall" + jsonR.wall_owner});
                    repostsCount != null ?
                    repostsCount.innerHTML = prevVal+1 :
                    document.getElementById("reposts"+id).insertAdjacentHTML("beforeend", "(<b id='repostsCount"+id+"'>1</b>)") //для старого вида постов
                }
                });
            xhr.send('text='+encodeURI(text) + '&type='+type + '&groupId='+groupId + "&asGroup="+asGroup + "&signed="+signed);
        }),
        Function.noop
    ]);
    
    try
    {
        clubs = await API.Groups.getWriteableClubs();
        for(const el of clubs) {
            document.getElementById("groupId").insertAdjacentHTML("beforeend", `<option value="${el.id}">${escapeHtml(el.name)}</option>`)
        }

    } catch(rejection) {
        console.error(rejection)
        document.getElementById("group").setAttribute("disabled", "disabled")
    }
}

function setClubAdminComment(clubId, adminId, hash) {
    MessageBox("Изменить комментарий к администратору", `
        <form action="/club${clubId}/setAdmin" method="post" id="uClubAdminCommentForm_${clubId}_${adminId}">
            <input type="hidden" name="user" value="${adminId}">
            <input type="hidden" name="hash" value="${hash}">
            <input type="hidden" name="removeComment" id="uClubAdminCommentRemoveCommentInput_${clubId}_${adminId}" value="0">
            <textarea name="comment" id="uClubAdminCommentTextArea_${clubId}_${adminId}"></textarea><br><br>
        </form>
    `, [tr('edit_action'), tr('cancel')], [
        () => {
            if (document.querySelector(`#uClubAdminCommentTextArea_${clubId}_${adminId}`).value === "") {
                document.querySelector(`#uClubAdminCommentRemoveCommentInput_${clubId}_${adminId}`).value = "1";
            }

            document.querySelector(`#uClubAdminCommentForm_${clubId}_${adminId}`).submit();
        },
        Function.noop
    ]);
}

function showCoinsTransferDialog(coinsCount, hash) {
    MessageBox(tr("transfer_poins"), `
        <div class="messagebox-content-header">
            ${tr("points_transfer_dialog_header_1")}
            ${tr("points_transfer_dialog_header_2")} <b>${tr("points_amount", coinsCount)}</b>
        </div>
        <form action="/coins_transfer" method="post" id="coins_transfer_form" style="margin-top: 30px">
            <table cellspacing="7" cellpadding="0" border="0" align="center">
                <tbody>
                    <tr>
                        <td width="120" valign="top">
                            <span class="nobold">${tr("receiver_address")}:</span>
                        </td>
                        <td>
                            <input type="text" name="receiver" style="width: 100%;" />
                        </td>
                    </tr>
                    <tr>
                        <td width="120" valign="top">
                            <span class="nobold">${tr("coins_count")}:</span>
                        </td>
                        <td>
                            <input type="text" name="value" style="width: 100%;" />
                        </td>
                    </tr>
                    <tr>
                        <td width="120" valign="top">
                            <span class="nobold">${tr("message")}:</span>
                        </td>
                        <td>
                            <textarea name="message" style="width: 100%;"></textarea>
                        </td>
                    </tr>
                </tbody>
            </table>
            <input type="hidden" name="hash" value="${hash}" />
        </form>
    `, [tr("transfer_poins_button"), tr("cancel")], [
        () => {
            document.querySelector("#coins_transfer_form").submit();
        },
        Function.noop
    ]);
}

function chunkSubstr(string, size) {
    const numChunks = Math.ceil(string.length / size);
    const chunks = new Array(numChunks);

    for (let i = 0, o = 0; i < numChunks; ++i, o += size) {
        chunks[i] = string.substr(o, size);
    }

    return chunks;
}

function autoTab(original, next, previous) {
    if(original.getAttribute && original.value.length == original.getAttribute("maxlength") && next !== undefined)
        next.focus();
    else if(original.value.length == 0 && previous !== undefined)
        previous.focus();
}

function showSupportFastAnswerDialog(answers) {
    let html = "";
    for(const [index, answer] of Object.entries(answers)) {
        html += `
            <div class="hover-box" onclick="supportFastAnswerDialogOnClick(fastAnswers[${index}])">
                ${answer.replace(/\n/g, "<br />")}
            </div>
        `;
    }

    MessageBox(tr("fast_answers"), html, [tr("close")], [
        Function.noop
    ]);
}

function supportFastAnswerDialogOnClick(answer) {
    u("body").removeClass("dimmed");
    u(".ovk-diag-cont").remove();

    const answerInput = document.querySelector("#answer_text");
    answerInput.value = answer;
    answerInput.focus();
}

function ovk_proc_strtr(string, length = 0) {
    const newString = string.substring(0, length);
    return newString + (string !== newString ? "…" : "");
}

function showProfileDeactivateDialog(hash) {
    MessageBox(tr("profile_deactivate"), `
        <div class="messagebox-content-header">
            ${tr("profile_deactivate_header")}
        </div>
        <form action="/settings/deactivate" method="post" id="profile_deactivate_dialog" style="margin-top: 30px">
            <h4>${tr("profile_deactivate_reason_header")}</h4>
            <table>
                <tbody>
                    <tr>
                        <td><input type="radio" name="deactivate_type" id="deactivate_r_1" data-text="${tr("profile_deactivate_reason_1_text")}"></td>
                        <td><label for="deactivate_r_1">${tr("profile_deactivate_reason_1")}</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="deactivate_type" id="deactivate_r_2" data-text="${tr("profile_deactivate_reason_2_text")}"></td>
                        <td><label for="deactivate_r_2">${tr("profile_deactivate_reason_2")}</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="deactivate_type" id="deactivate_r_3" data-text="${tr("profile_deactivate_reason_3_text")}"></td>
                        <td><label for="deactivate_r_3">${tr("profile_deactivate_reason_3")}</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="deactivate_type" id="deactivate_r_4" data-text="${tr("profile_deactivate_reason_4_text")}"></td>
                        <td><label for="deactivate_r_4">${tr("profile_deactivate_reason_4")}</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="deactivate_type" id="deactivate_r_5" data-text="${tr("profile_deactivate_reason_5_text")}"></td>
                        <td><label for="deactivate_r_5">${tr("profile_deactivate_reason_5")}</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="deactivate_type" id="deactivate_r_6" data-text=""></td>
                        <td><label for="deactivate_r_6">${tr("profile_deactivate_reason_6")}</label></td>
                    </tr>
                </tbody>
            </table>
            <textarea name="deactivate_reason" id="deactivate_reason" placeholder="${tr("gift_your_message")}"></textarea><br><br>
            <input type="checkbox" name="deactivate_share" id="deactivate_share" checked>
            <label for="deactivate_share">${tr("share_with_friends")}</label>
            <input type="hidden" name="hash" value="${hash}" />
        </form>
    `, [tr("profile_deactivate_button"), tr("cancel")], [
        () => {
            $("#profile_deactivate_dialog").submit();
        },
        Function.noop
    ]);

    $('[id^="deactivate_r_"]').on("click", function () {
        $('#deactivate_reason').val($(this).data("text"));
    });
}

function showIncreaseRatingDialog(coinsCount, userUrl, hash) {
    MessageBox(tr("increase_rating"), `
        <div class="messagebox-content-header">
            ${tr("you_have_unused_votes", coinsCount)} <br />
            <a href="/settings?act=finance.top-up">${tr("apply_voucher")} &raquo;</a>
        </div>
        <form action="/increase_social_credits" method="post" id="increase_rating_form" style="margin-top: 30px">
            <table cellspacing="7" cellpadding="0" border="0" align="center">
                <tbody>
                    <tr>
                        <td width="120" valign="top">
                            <span class="nobold">${tr("to_whom")}:</span>
                        </td>
                        <td>
                            <input type="text" name="receiver" style="width: 100%;" value="${userUrl}" />
                        </td>
                    </tr>
                    <tr>
                        <td width="120" valign="top">
                            <span class="nobold">${tr("increase_by")}:</span>
                        </td>
                        <td>
                            <input id="value_input" type="text" name="value" style="width: 100%;" />
                        </td>
                    </tr>
                    <tr>
                        <td width="120" valign="top">
                            <span class="nobold">${tr("message")}:</span>
                        </td>
                        <td>
                            <textarea name="message" style="width: 100%;"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div class="menu_divider"></div>
                        </td>
                    </tr>
                    <tr>
                        <td width="120" valign="top">
                            <span class="nobold">${tr("price")}:</span>
                        </td>
                        <td>
                            <span id="rating_price">${tr("points_amount", 0)}</span> <small class="nobold" style="float: right;">(1% = ${tr("points_amount_one", 1)})</small>
                        </td>
                    </tr>
                </tbody>
            </table>
            <input type="hidden" name="hash" value="${hash}" />
        </form>
    `, [tr("increase_rating_button"), tr("cancel")], [
        () => {
            document.querySelector("#increase_rating_form").submit();
        },
        Function.noop
    ]);

    document.querySelector("#value_input").oninput = function () {
        let value = Number(this.value);
        value = isNaN(value) ? "?" : ovk_proc_strtr(String(value), 7);
        if(!value.endsWith("…") && value != "?")
            value = Number(value);

        if(typeof value === "number")
            document.querySelector("#rating_price").innerHTML = tr("points_amount", value);
        else
            document.querySelector("#rating_price").innerHTML = value + " " + tr("points_amount_other").replace("$1 ", "");
    };
}

function escapeHtml(text) {
    var map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function addAvatarImage(groupStrings = false, groupId = 0)
{
    let inputname = groupStrings == true ? 'ava' : 'blob';
    let body = `
    <div id="avatarUpload">
        <p>${groupStrings == true ? tr('groups_avatar') : tr('friends_avatar')}</p>
        <p>${tr('formats_avatar')}</p><br>
        <img style="margin-left:46.3%;display:none" id="loader" src="/assets/packages/static/openvk/img/loading_mini.gif">
        <label class="button" style="margin-left:45%;user-select:none" id="uploadbtn">${tr("browse")}
        <input accept="image/*" type="file" name="${inputname}" hidden id="${inputname}" style="display: none;" onchange="uploadAvatar(${groupStrings}, ${groupStrings == true ? groupId : null})">
        </label><br><br>
        <p>${tr('troubles_avatar')}</p>
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
}

function uploadAvatar(group = false, group_id = 0)
{
    loader.style.display = "block";
    uploadbtn.setAttribute("hidden", "hidden")
    let xhr = new XMLHttpRequest();
    let formData = new FormData();
    let bloborava = group == false ? "blob" : "ava"
    formData.append(bloborava, document.getElementById(bloborava).files[0]);
    formData.append("ava", 1)
    formData.append("hash", u("meta[name=csrf]").attr("value"))
    xhr.open("POST", group == true ? "/club"+group_id+"/al_avatar" : "/al_avatars")
    xhr.onload = () => {
        let json = JSON.parse(xhr.responseText);
        document.getElementById(group == false ? "thisUserAvatar" : "thisGroupAvatar").src = json["url"];
        u("body").removeClass("dimmed");
        u(".ovk-diag-cont").remove();
        if(document.getElementsByClassName("text_add_image")[0] == undefined)
        {
            document.getElementById("upl").href = "javascript:deleteAvatar('"+json["id"]+"', '"+u("meta[name=csrf]").attr("value")+"')"
        }
        //console.log(json["id"])
        NewNotification(tr("update_avatar_notification"), tr("update_avatar_description"), json["url"], () => {window.location.href = "/photo" + json["id"]});
        if(document.getElementsByClassName("text_add_image")[0] != undefined)
        {
            //ожидание чтобы в уведомлении была аватарка
            let promise = new Promise((resolve, reject) => {
                setTimeout(() => {
                    location.reload()
                }, 500);
              });
        }
    }
    xhr.send(formData)
}

function deleteAvatar(avatar)
{
    let body = `
        <p>${tr("deleting_avatar_sure")}</p>
    `
    let msg = MessageBox(tr('deleting_avatar'), body, [
        tr('yes'),
        tr('cancel')
    ], [
        (function() {
            let xhr = new XMLHttpRequest();
            xhr.open("POST", "/photo"+avatar+"/delete")
            xhr.onload = () => {
                //не люблю формы
                NewNotification(tr("deleted_avatar_notification"), "");
                location.reload()
            }
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.send("hash="+u("meta[name=csrf]").attr("value"))
        }),
        (function() {
            u("#tmpPhDelF").remove();
        }),
    ]);
}

function expandSearch()
{
    // console.log("search expanded")
    let els = document.querySelectorAll("div.dec")
    for(const element of els)
    {
        element.style.display = "none"
    }
    
    document.querySelector(".whatFind").style.display = "block";
    document.querySelector(".whatFind").style.marginRight = "-80px";
    document.getElementById("searchInput").style.width = "627px";
    document.getElementById("searchInput").style.background = "none";
    document.getElementById("searchInput").style.backgroundColor = "#fff";
    document.getElementById("searchInput").style.paddingLeft = "6px";
    srch.classList.add("nodivider")
}

async function decreaseSearch()
{
    // чтобы люди успели выбрать что искать и поиск не скрывался сразу
    await new Promise(r => setTimeout(r, 4000));

    // console.log("search decreased")
    if(document.activeElement !== searchInput && document.activeElement !== typer)
    {
        srcht.setAttribute("hidden", "hidden")
        document.getElementById("searchInput").style.background = "url('/assets/packages/static/openvk/img/search_icon.png') no-repeat 3px 4px";
        document.getElementById("searchInput").style.backgroundColor = "#fff";
        document.getElementById("searchInput").style.paddingLeft = "18px";
        document.getElementById("searchInput").style.width = "120px";
        document.querySelector(".whatFind").style.display = "none";

        await new Promise(r => setTimeout(r, 300));
        srch.classList.remove("nodivider")

        let els = document.querySelectorAll("div.dec")
        for(const element of els)
        {
            element.style.display = "inline-block"
        }
    }
}

function hideParams(name)
{
    $("#s_"+name).slideToggle(250, "swing");

    if($(`#n_${name} img`).attr("src") == "/assets/packages/static/openvk/img/hide.png")
    {
        $("#n_"+name+" img").attr("src", "/assets/packages/static/openvk/img/show.png");
    } else {
        $("#n_"+name+" img").attr("src", "/assets/packages/static/openvk/img/hide.png");
    }
}

function resetSearch()
{
    let inputs = document.querySelectorAll("input")
    let selects = document.querySelectorAll("select")

    for(const input of inputs)
    {
        if(input != dnt && input != gend && input != gend1 && input != gend2) {
            input.value = ""
        }
    }

    for(const select of selects)
    {
        if(select != sortyor && select != document.querySelector(".whatFind")) {
            select.value = 0
        }
    }
}

async function checkSearchTips()
{
    let query = searchInput.value;

    await new Promise(r => setTimeout(r, 1000));

    let type = typer.value;
    let smt  = type == "users" || type == "groups" || type == "videos";

    if(query.length > 3 && query == searchInput.value && smt) {
        srcht.removeAttribute("hidden")
        let etype = type

        try {
            let results = await API.Search.fastSearch(escapeHtml(query), etype)
            
            srchrr.innerHTML = ""

            for(const el of results["items"]) {
                srchrr.insertAdjacentHTML("beforeend", `
                    <tr class="restip" onmouseup="if (event.which === 2) { window.open('${el.url}', '_blank'); } else {location.href='${el.url}'}">
                        <td>
                            <img src="${el.avatar}" width="30">
                        </td>
                        <td valign="top">
                            <p class="nameq" style="margin-top: -2px;text-transform:none;">${escapeHtml(el.name)}</p>
                            <p class="desq" style="text-transform:none;">${escapeHtml(el.description)}</p>
                        </td>
                    </tr>
                    `)
            }
        } catch(rejection) {
            srchrr.innerHTML = tr("no_results")
        }
    }
}

$(document).on("scroll", () => {
    if($(document).scrollTop() > $(".sidebar").height() + 50) {
        $(".floating_sidebar")[0].classList.add("show");
    } else if($(".floating_sidebar")[0].classList.contains("show")) {
        $(".floating_sidebar")[0].classList.remove("show");
        $(".floating_sidebar")[0].classList.add("hide_anim");
        setTimeout(() => {
            $(".floating_sidebar")[0].classList.remove("hide_anim");
        }, 250);
    }
})