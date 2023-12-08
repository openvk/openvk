function _bsdnUnwrapBitMask(number) {
    return number.toString(2).split("").reverse().map(x => x === "1");
}

function _bsdnToHumanTime(time) {
    time = Math.ceil(time);
    let mins = Math.floor(time / 60);
    let secs = (time - (mins * 60));

    if(secs < 10)
        secs = "0" + secs;
    if(mins < 10)
        mins = "0" + mins;

    return mins + ":" + secs;
}

function _bsdnTpl(name, author) {
    name   = escapeHtml(name);
    author = escapeHtml(author);

    return `
            <div class="bsdn_contextMenu" style="display: none;">
                <span class="bsdn_contextMenuElement bsdn_copyVideoUrl">Copy video link to clipboard</span>
                <hr/>
                <span class="bsdn_contextMenuElement">OpenVK BSDN///Player 0.1</span>
                <hr/>
                <span class="bsdn_contextMenuElement">Developers:</span>
                <span class="bsdn_contextMenuElement" onclick="window.open('https://github.com/celestora');">
                    - celestora
                </span>
                <hr/>
                <span class="bsdn_contextMenuElement" onclick="window.open('https://github.com/openvk/openvk/issues/new');">
                    Report a problem...
                </span>
                <span class="bsdn_contextMenuElement" onclick="window.open('https://www.youtube.com/watch?v=4Hq53bN34_w');">About Adobe Flash Player...</span>
            </div>

            <div class="bsdn_controls">
                <div>
                    <button class="bsdn_playButton">
                        <img src="/assets/packages/static/openvk/img/bsdn/play.png" style="padding-right: 2px; padding-top: 3px;">
                    </button>
                </div>

                <div class="bsdn_terebilkaWrap">
                    <div class="bsdn_terebilkaUpperWrap">
                        <img class="bsdn_logo" src="/assets/packages/static/openvk/img/bsdn/logo.gif" style="opacity: 0; /* TODO add logo xdd */" />
                        <p class="bsdn_timeWrap">
                            <time class="bsdn_timeReal">--:--</time>
                            <time class="bsdn_timeFull">--:--</time>
                        </p>
                    </div>

                    <div class="bsdn_terebilkaLowerWrap">
                        <div class="bsdn_terebilkaBrick"></div>
                    </div>
                </div>

                <div>
                    <img class="bsdn_soundIcon" src="/assets/packages/static/openvk/img/bsdn/speaker.gif" />
                </div>

                <div class="bsdn_soundControl">
                    <div class="bsdn_soundControlPadding"></div>
                    <div class="bsdn_soundControlSubWrap">
                        <div class="bsdn_soundControlBrick" style="left: calc(100% - 10px);"></div>
                    </div>
                </div>

                <div>
                    <div class="bsdn_repeatButton">
                        <img src="/assets/packages/static/openvk/img/bsdn/repeat.gif" />
                    </div>
                </div>

                <div>
                    <div class="bsdn_fullScreenButton">
                        <img src="/assets/packages/static/openvk/img/bsdn/fullscreen.gif" />
                    </div>
                </div>
            </div>

            <div class="bsdn_teaserWrap">
                <div class="bsdn_teaser">
                    <div class="bsdn_teaserTitleBox">
                        <b>${name}</b>
                        <span>${author}</span>
                    </div>

                    <div class="bsdn_teaserButton">
                        <img src="/assets/packages/static/openvk/img/bsdn/play.png" />
                    </div>
                </div>
            </div>
        `;
}

function _bsdnTerebilkaEventFactory(el, terebilka, callback, otherListeners) {
    let terebilkaSize = () => el.querySelector(terebilka).getBoundingClientRect().width; // чтобы просралось

    let listeners = {
        mousemove: [
            e => {
                let buttonsPresseed = _bsdnUnwrapBitMask(e.buttons);
                if(!buttonsPresseed[0])
                    return; // user doesn't click so nothing should be done

                let offset    = e.offsetX;
                let percents  = Math.max(0, Math.min(100, offset / (terebilkaSize() / 100)));

                return callback(percents);
            }
        ],
        mousedown: [
            e => {
                let offset   = e.offsetX;
                let percents = Math.max(0, Math.min(100, offset / (terebilkaSize() / 100)));

                return callback(percents);
            }
        ]
    };

    for(eventName in (otherListeners || {})) {
        if(listeners.hasOwnProperty(eventName))
            listeners[eventName] = otherListeners[eventName].concat(listeners[eventName]);
        else
            listeners[eventName] = otherListeners[eventName];
    }

    return listeners;
}

function _bsdnEventListenerFactory(el, v) {
    return {
        ".bsdn-player": {
            click: [
                e => {
                    if(el.querySelector(".bsdn_controls").contains(e.target) || el.querySelector(".bsdn_teaser").contains(e.target) || el.querySelector(".bsdn_contextMenu").contains(e.target))
                        return;

                    if(el.querySelector(".bsdn_contextMenu").style.display !== "none") {
                        el.querySelector(".bsdn_contextMenu").style.display = "none";
                        return;
                    }

                    if(v.paused)
                        v.play();
                    else
                        v.pause();
                }
            ],
            contextmenu: [
                e => {
                    e.preventDefault();
                    if(el.querySelector(".bsdn_controls").contains(e.target) || el.querySelector(".bsdn_contextMenu").contains(e.target))
                        return;

                    let rect = el.querySelector(".bsdn-player").getBoundingClientRect();
                    let h = rect.height, w = rect.width;
                    let x, y;
                    if(document.fullscreen) {
                        x = e.screenX;
                        y = e.screenY;
                    } else {
                        let rx = rect.x + window.scrollX, ry = rect.y + window.scrollY;
                        x = e.pageX - rx;
                        y = e.pageY - ry;
                    }

                    if(h - y < 169)
                        y = Math.max(0, y - 169);

                    if(w - x < 238)
                        x = Math.max(0, x - 238);

                    let menu = el.querySelector(".bsdn_contextMenu");
                    menu.style.top     = y + "px";
                    menu.style.left    = x + "px";
                    menu.style.display = "unset";
                }
            ]
        },

        ".bsdn_contextMenuElement": {
            click: [ () => el.querySelector(".bsdn_contextMenu").style.display = "none" ]
        },

        ".bsdn_copyVideoUrl": {
            click: [
                async () => {
                    let videoUrl = el.querySelector(".bsdn_video > video").src;
                    let fallback = () => {
                        prompt("URL:", videoUrl);
                    };

                    if(typeof navigator.clipboard == "undefined") {
                        fallback();
                    } else {
                        try {
                            await navigator.clipboard.writeText(videoUrl);
                            confirm("👍🏼");
                        } catch(e) {
                            fallback();
                        }
                    }
                }
            ]
        },

        ".bsdn_video > video": {
            play: [
                () => {
                    if(!el.querySelector(".bsdn-player").classList.contains("bsdn-dirty"))
                        el.querySelector(".bsdn-player").classList.add("bsdn-dirty")

                    el.querySelector(".bsdn_playButton").innerHTML = "<img src='/assets/packages/static/openvk/img/bsdn/pause.gif' style='padding-right: 3px; padding-top: 3px;' />";
                    el.querySelector(".bsdn-player").classList.add("_bsdn_playing");
                    el.querySelector(".bsdn_teaserWrap").style.display = "none";
                }
            ],
            pause: [
                () => {
                    el.querySelector(".bsdn_playButton").innerHTML = "<img src='/assets/packages/static/openvk/img/bsdn/play.png' style='padding-right: 2px; padding-top: 3px; height: 19px;' />";
                    el.querySelector(".bsdn-player").classList.remove("_bsdn_playing");
                    el.querySelector(".bsdn_teaserWrap").style.display = "flex";
                }
            ],
            timeupdate: [
                () => {
                    el.querySelector(".bsdn_timeReal").innerHTML = _bsdnToHumanTime(v.currentTime);

                    let terebilkaSize = el.querySelector(".bsdn_terebilkaLowerWrap").getBoundingClientRect().width;
                    let brickSize     = 15;
                    let percents      = Math.ceil(v.currentTime / (v.duration / 100));
                    let offset        = ((terebilkaSize - brickSize) / 100) * percents;
                    el.querySelector(".bsdn_terebilkaBrick").style.left = `min(calc(100% - 15px), ${offset}px`; // смешной мясной костыль ибо мне лень делать onresize
                }
            ],
            volumechange: [
                () => {
                    if(v.volume === 0)
                        el.querySelector(".bsdn_soundIcon").src = "/assets/packages/static/openvk/img/bsdn/speaker_muted.gif";
                    else
                        el.querySelector(".bsdn_soundIcon").src = "/assets/packages/static/openvk/img/bsdn/speaker.gif";

                    let scSize    = el.querySelector(".bsdn_soundControlSubWrap").getBoundingClientRect().width;
                    let brickSize = 10;
                    let offset    = (scSize - brickSize) * v.volume;
                    el.querySelector(".bsdn_soundControlBrick").style.left = offset + "px";
                }
            ],
            loadedmetadata: [
                () => {
                    el.querySelector(".bsdn_timeFull").innerHTML = _bsdnToHumanTime(v.duration);
                }
            ]
        },

        ".bsdn_repeatButton": {
            click: [
                () => {
                    if(!v.loop) {
                        v.loop = true
                        el.querySelector(".bsdn_repeatButton").classList.add("pressed")

                        if(v.currentTime == v.duration) {
                            v.currentTime = 0
                            v.play()
                        }

                    } else {
                        v.loop = false
                        el.querySelector(".bsdn_repeatButton").classList.remove("pressed")
                    }
                }
            ]
        },

        ".bsdn_fullScreenButton": {
            click: [
                () => {
                    if(document.fullscreen) {
                        document.exitFullscreen();
                    } else {
                        el.querySelector(".bsdn-player").requestFullscreen();
                    }
                }
            ]
        },

        ".bsdn_teaserButton|.bsdn_playButton": {
            click: [
                () => {
                    if(v.paused)
                        v.play();
                    else
                        v.pause();
                }
            ]
        },

        ".bsdn_terebilkaLowerWrap": _bsdnTerebilkaEventFactory(el, ".bsdn_terebilkaLowerWrap", function(p) {
            let time = (v.duration / 100) * p;
            setTimeout(() => {
                v.currentTime = time;
                if(v.currentTime === 0) {
                    console.warn("[!] Хромог момент");
                    console.warn("Теребилка не работает в хроме если сервер не реализует HTTP полностью.");
                    console.warn("Встроенный сервер РНР не возвращает заголовки Accept-Range из-за чего хром отказывается seek'ать. Google как всегда.");
                    console.warn("Установите Firefox для лучшей безопасности в сети: https://www.mozilla.org/ru/firefox/enterprise/#download");
                }
            }, 0);
        }, {
            mousedown: [
                e => v.pause()
            ],
            mouseup: [
                e => v.play()
            ]
        }),

        ".bsdn_soundControlSubWrap": _bsdnTerebilkaEventFactory(el, ".bsdn_soundControlSubWrap", function(p) {
            let volume = p / 100;
            v.volume   = volume;
        }),

        ".bsdn_soundIcon": {
            click: [
                e => v.volume = v.volume === 0 ? 0.75 : 0
            ]
        }
    }
}

function _bsdnApplyBindings(el, v) {
    let listeners = _bsdnEventListenerFactory(el, v);

    for(key in listeners) {
        let selectors = key.split("|");
        selectors.forEach(sel => {
            for(eventName in listeners[key]) {
                listeners[key][eventName].forEach(listener => {
                    el.querySelectorAll(sel).forEach(target => {
                        target.addEventListener(eventName, listener, {
                            passive: (["contextmenu"]).indexOf(eventName) === -1
                        });
                    });
                });
            }
        });
    }
}

function bsdnInitElement(el) {
    if(el.querySelector(".bdsn-hydrated") != null) {
        console.debug(el, " is already hydrated.");
        return;
    }

    let video = el.querySelector("video");
    if(!video) {
        console.warning(el, " does not contain any <video>s.");
        return;
    }

    el.innerHTML = `
        <div class="bsdn-player bdsn-hydrated">
            ${_bsdnTpl(el.dataset.name, el.dataset.author)}
            <div class="bsdn_video">
                ${video.outerHTML}
            </div>
        </div>
    `;

    video = el.querySelector(".bsdn_video > video");
    _bsdnApplyBindings(el, video);
    video.volume = 0.75;
}

function bsdnHydrate() {
    document.querySelectorAll(".bsdn").forEach(bsdnInitElement);
}
