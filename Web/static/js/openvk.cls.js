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

    u("#_photoDelete").on("click", function(e) {
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

function repostPost(id, hash) {
	uRepostMsgTxt  = tr('your_comment') + ": <textarea id='uRepostMsgInput_"+id+"'></textarea><br/><br/>";
	
	MessageBox(tr('share'), uRepostMsgTxt, [tr('send'), tr('cancel')], [
		(function() {
			text = document.querySelector("#uRepostMsgInput_"+id).value;
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
				}
			});
			xhr.send('text=' + encodeURI(text));
		}),
		Function.noop
	]);
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

function showBtStatusChangeDialog(report, currentBalance, hash) {
    MessageBox("Изменить статус", `<form action="/bug${report}/setStatus" method="post" id="status_change_dialog">
            <table>
                <tbody>
                    <tr>
                        <td><input type="radio" name="status" value="0"></td>
                        <td><label for="status_1">Открыт</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="status" value="1"></td>
                        <td><label for="status_2">На рассмотрении</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="status" value="2"></td>
                        <td><label for="status_3">В работе</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="status" value="3"></td>
                        <td><label for="status_4">Исправлен</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="status" value="4"></td>
                        <td><label for="status_5">Закрыт</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="status" value="5"></td>
                        <td><label for="status_6">Требует корректировки</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="status" value="6"></td>
                        <td><label for="status_7">Заблокирован</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="status" value="7"></td>
                        <td><label for="status_8">Отклонён</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="status" value="8"></td>
                        <td><label for="status_9">Переоткрыт</label></td>
                    </tr>
                </tbody>
            </table>
            <br>
            <h4>Вы можете прокомментировать изменение статуса</h4>
            <textarea name="text" style="width: 100%;resize: vertical;"></textarea>
            <br><br>
            У тестировщика сейчас ${currentBalance} голосов.
            <br>
            <div style="display: inline;">
                Вы можете начислить &nbsp;
                <input style="width: 45px; height: 9px;" type="number" name="points-count" value="0" min="0">
                &nbsp;голосов
            </div>
            <input type="hidden" name="hash" value="${hash}" />
        </form>
    `, ["Сохранить", "Отмена"], [
        () => {
            $("#status_change_dialog").submit();
        },
        Function.noop
    ]);
}

function showBtPriorityChangeDialog(report, currentBalance, hash) {
    MessageBox("Изменить приоритет", `<form action="/bug${report}/setPriority" method="post" id="priority_change_dialog">
            <table>
                <tbody>
                    <tr>
                        <td><input type="radio" name="priority" value="0"></td>
                        <td><label for="priority_1">Пожелание</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="priority" value="1"></td>
                        <td><label for="priority_2">Низкий</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="priority" value="2"></td>
                        <td><label for="priority_3">Средний</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="priority" value="3"></td>
                        <td><label for="priority_4">Высокий</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="priority" value="4"></td>
                        <td><label for="priority_5">Критический</label></td>
                    </tr>
                    <tr>
                        <td><input type="radio" name="priority" value="5"></td>
                        <td><label for="priority_6">Уязвимость</label></td>
                    </tr>
                </tbody>
            </table>
            <br>
            <h4>Вы можете прокомментировать изменение приоритета</h4>
            <textarea name="text" style="width: 100%;resize: vertical;"></textarea>
            <br><br>
            У тестировщика сейчас ${currentBalance} голосов.
            <br>
            <div style="display: inline;">
                Вы можете начислить &nbsp;
                <input style="width: 45px; height: 9px;" type="number" name="points-count" value="0" min="0">
                &nbsp;голосов
            </div>
            <input type="hidden" name="hash" value="${hash}" />
        </form>
    `, ["Сохранить", "Отмена"], [
        () => {
            $("#priority_change_dialog").submit();
        },
        Function.noop
    ]);
}

function showBtGiveProductAccessDialog(product, hash) {
    MessageBox("Выдать доступ", `<form action="/bt_product${product[0]}/giveAccess" method="post" id="give_product_access_dialog">
        <div>
            Выдать пользователю <b>ID</b>&nbsp
            <input style="width: 45px; height: 9px;" type="number" name="uid" value="1" min="1">
            &nbsp; доступ к продукту <b>${product[1]}</b> (#${product[0]}).
        </div>
        <input type="hidden" name="hash" value="${hash}" />
    </form>`, ["Продолжить", tr("cancel")], [
        () => {
            $("#give_product_access_dialog").submit();
        },
        Function.noop
    ]);
}

function showBtRevokeProductAccessDialog(product, hash) {
    MessageBox("Забрать доступ", `<form action="/bt_product${product[0]}/revokeAccess" method="post" id="revoke_product_access_dialog">
        <div>
            Забрать у пользователя <b>ID</b>&nbsp
            <input style="width: 45px; height: 9px;" type="number" name="uid" value="1" min="1">
            &nbsp; доступ к продукту <b>${product[1]}</b> (#${product[0]}).
        </div>
        <input type="hidden" name="hash" value="${hash}" />
    </form>`, ["Продолжить", tr("cancel")], [
        () => {
            $("#revoke_product_access_dialog").submit();
        },
        Function.noop
    ]);
}

function showBtProductAccessDialog(product, hash) {
    MessageBox(`Доступ к ${product[1]} (#${product[0]})`, `<form action="/bt_product${product[0]}/manageAccess" method="post" id="give_product_access_dialog">
        <table>
            <tbody>
                <tr>
                    <td><input type="radio" name="action" value="give"></td>
                    <td><label for="priority_1">Выдать</label></td>
                </tr>
                <tr>
                    <td><input type="radio" name="action" value="revoke"></td>
                    <td><label for="priority_2">Забрать</label></td>
                </tr>
            </tbody>
        </table>
        <br>
        <div>
            <b>ID</b> пользователя&nbsp
            <input style="width: 45px; height: 9px;" type="number" name="uid" value="1" min="1">
        </div>
        <input type="hidden" name="hash" value="${hash}" />
    </form>`, ["Продолжить", tr("cancel")], [
        () => {
            $("#give_product_access_dialog").submit();
        },
        Function.noop
    ]);
}

function showBtPrivateProductDialog(product, hash) {
    MessageBox(`Настройки продукта ${product[1]} (#${product[0]})`, `<form action="/bt_product${product[0]}/managePrivacy" method="post" id="give_product_access_dialog">
        <table>
            <tbody>
                <tr>
                    <td><input type="radio" name="action" value="open"></td>
                    <td><label for="priority_1">Открытый</label></td>
                </tr>
                <tr>
                    <td><input type="radio" name="action" value="private"></td>
                    <td><label for="priority_2">Приватный</label></td>
                </tr>
            </tbody>
        </table>
        <input type="hidden" name="hash" value="${hash}" />
    </form>`, ["Продолжить", tr("cancel")], [
        () => {
            $("#give_product_access_dialog").submit();
        },
        Function.noop
    ]);
}

function showBtProductStatusDialog(product, hash) {
    MessageBox(`Статус продукта ${product[1]} (#${product[0]})`, `<form action="/bt_product${product[0]}/manageStatus" method="post" id="give_product_access_dialog">
        <table>
            <tbody>
                <tr>
                    <td><input type="radio" name="action" value="open"></td>
                    <td><label for="priority_1">Открытый</label></td>
                </tr>
                <tr>
                    <td><input type="radio" name="action" value="closed"></td>
                    <td><label for="priority_2">Закрытый</label></td>
                </tr>
            </tbody>
        </table>
        <input type="hidden" name="hash" value="${hash}" />
    </form>`, ["Продолжить", tr("cancel")], [
        () => {
            $("#give_product_access_dialog").submit();
        },
        Function.noop
    ]);
}

function showBtKickUserDialog(user, hash) {
    MessageBox("Исключить из программы", `<form action="/bt_reporter${user[0]}/kick" method="post" id="kick_from_ovk_testers_dialog">
    <div>Вы действительно хотите исключить тестировщика <b>${user[1]}</b> из программы OVK Testers?</div>
    <br>
    <h4>Комментарий модератора</h4>
    <textarea name="comment" style="width: 100%;resize: vertical;"></textarea>
    <input type="checkbox" name="ban_reports" id="ban_reports">
    <label for="ban_reports">Заблокировать все его отчёты</label>
    <input type="hidden" name="hash" value="${hash}" />
`, ["Продолжить", tr("cancel")], [
        () => {
            $("#kick_from_ovk_testers_dialog").submit();
        },
        Function.noop
    ]);
}

function showBtUnbanUserDialog(user, hash) {
    MessageBox("Вернуть в программу", `<form action="/bt_reporter${user[0]}/unban" method="post" id="unban_ovk_testers_dialog">
    <div>Вы действительно хотите вернуть тестировщика <b>${user[1]}</b> в программу OVK Testers?</div>
    <br>
    <div>Он был исключён по причине: <b>${user[2]}</b></div>
     <input type="checkbox" name="unban_reports" id="unban_reports">
    <label for="ban_reports">Переоткрыть все его отчёты</label>
    <input type="hidden" name="hash" value="${hash}" />
`, ["Вернуть", tr("cancel")], [
        () => {
            $("#unban_ovk_testers_dialog").submit();
        },
        Function.noop
    ]);
}