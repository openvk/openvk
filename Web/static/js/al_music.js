function fmtTime(time) {
    const mins = String(Math.floor(time / 60)).padStart(2, '0');
    const secs = String(Math.floor(time % 60)).padStart(2, '0');
    return `${ mins}:${ secs}`;
}

function fastError(message) {
    MessageBox(tr("error"), message, [tr("ok")], [Function.noop])
}

// elapsed это вроде прошедшие, а оставшееся это remaining но ладно уже
function getElapsedTime(fullTime, time) {
    let timer = fullTime - time

    if(timer < 0) return "-00:00"

    return "-" + fmtTime(timer)
}

window.savedAudiosPages = {}

class playersSearcher {
    constructor(context_type, context_id) {
        this.context_type = context_type
        this.context_id = context_id
        this.searchType = "by_name"
        this.query = ""
        this.page = 1
        this.successCallback = () => {}
        this.errorCallback = () => {}
        this.beforesendCallback = () => {}
        this.clearContainer = () => {}
    }

    execute() {
        $.ajax({
            type: "POST",
            url: "/audios/context",
            data: {
                context: this.context_type,
                hash: u("meta[name=csrf]").attr("value"),
                page: this.page,
                query: this.query,
                context_entity: this.context_id,
                type: this.searchType,
                returnPlayers: 1,
            },
            beforeSend: () => {
                this.beforesendCallback()
            },
            error: () => {
                this.errorCallback()
            },
            success: (response) => {
                this.successCallback(response, this)
            }
        })
    }

    movePage(page) {
        this.page = page
        this.execute()
    }
}

class bigPlayer {
    tracks = {
        currentTrack: null,
        nextTrack: null,
        previousTrack: null,
        tracks: []
    }

    context = {
        context_type: null,
        context_id: 0,
        pagesCount: 0,
        playedPages: [],
        object: [],
    }

    nodes = {
        dashPlayer: null,
        audioPlayer: null,
        thisPlayer: null,
        playButtons: null,
    }

    timeType = 0

    findTrack(id) {
        return this.tracks["tracks"].find(item => item.id == id)
    }

    constructor(context, context_id, page = 1) {
        this.context["context_type"] = context
        this.context["context_id"] = context_id
        this.context["playedPages"].push(String(page))

        this.nodes["thisPlayer"] = document.querySelector(".bigPlayer")
        this.nodes["thisPlayer"].classList.add("lagged")
        this.nodes["audioPlayer"] = document.createElement("audio")

        this.player = () => { return this.nodes["audioPlayer"] }
        this.nodes["playButtons"] = this.nodes["thisPlayer"].querySelector(".playButtons")
        this.nodes["dashPlayer"] = dashjs.MediaPlayer().create()

        let formdata = new FormData()
        formdata.append("context", context)
        formdata.append("context_entity", context_id)
        formdata.append("query", context_id)
        formdata.append("hash", u("meta[name=csrf]").attr("value"))
        formdata.append("page", page)
    
        ky.post("/audios/context", {
            hooks: {
                afterResponse: [
                    async (_request, _options, response) => {
                        if(response.status !== 200) {
                            fastError(tr("unable_to_load_queue"))
                            return
                        }

                        let contextObject = await response.json()

                        if(!contextObject.success) {
                            fastError(tr("unable_to_load_queue"))
                            return
                        }

                        this.nodes["thisPlayer"].classList.remove("lagged")
                        this.tracks["tracks"] = contextObject["items"]
                        this.context["pagesCount"] = contextObject["pagesCount"]

                        if(localStorage.lastPlayedTrack != null && this.tracks["tracks"].find(item => item.id == localStorage.lastPlayedTrack) != null) {
                            this.setTrack(localStorage.lastPlayedTrack)
                            this.pause()
                        }

                        console.info("Context is successfully loaded")
                    }
                ]
            }, 
            body: formdata,
            timeout: 20000,
        })

        u(this.nodes["playButtons"].querySelector(".playButton")).on("click", (e) => {
            if(this.player().paused)
                this.play()
            else
                this.pause()
        })

        u(this.player()).on("timeupdate", (e) => {
            const time = this.player().currentTime;
            const ps = ((time * 100) / this.tracks["currentTrack"].length).toFixed(3)
            this.nodes["thisPlayer"].querySelector(".time").innerHTML = fmtTime(time)
            this.timeType == 0 ? this.nodes["thisPlayer"].querySelector(".elapsedTime").innerHTML = getElapsedTime(this.tracks["currentTrack"].length, time)
                : null

            if (ps <= 100)
                this.nodes["thisPlayer"].querySelector(".selectableTrack .slider").style.left = `${ ps}%`;

        })

        u(this.player()).on("volumechange", (e) => {
            const volume = this.player().volume;
            const ps = Math.ceil((volume * 100) / 1);

            if (ps <= 100)
                this.nodes["thisPlayer"].querySelector(".volumePanel .selectableTrack .slider").style.left = `${ ps}%`;
            
            localStorage.volume = volume
        })

        u(".bigPlayer .track > div").on("click mouseup", (e) => {
            if(this.tracks["currentTrack"] == null)
                return

            let rect  = this.nodes["thisPlayer"].querySelector(".selectableTrack").getBoundingClientRect();
            
            const width = e.clientX - rect.left;
            const time = Math.ceil((width * this.tracks["currentTrack"].length) / (rect.right - rect.left));

            this.player().currentTime = time;
        })

        u(".bigPlayer .trackPanel .selectableTrack").on("mousemove", (e) => {
            if(this.tracks["currentTrack"] == null)
                return

            let rect  = this.nodes["thisPlayer"].querySelector(".selectableTrack").getBoundingClientRect();
            
            const width = e.clientX - rect.left;
            const time = Math.ceil((width * this.tracks["currentTrack"].length) / (rect.right - rect.left));

            document.querySelector(".bigPlayer .track .bigPlayerTip").style.display = "block"
            document.querySelector(".bigPlayer .track .bigPlayerTip").innerHTML = fmtTime(time)
            document.querySelector(".bigPlayer .track .bigPlayerTip").style.left = `min(${width - 15}px, 315.5px)`
        })

        u(".bigPlayer .nextButton").on("mouseover mouseleave", (e) => {
            if(this.tracks["currentTrack"] == null)
                return

            if(e.type == "mouseleave") {
                $(".nextTrackTip").remove()
                return
            }

            e.currentTarget.parentNode.insertAdjacentHTML("afterbegin", `
                <div class="bigPlayerTip nextTrackTip" style="left: 5%;">
                    ${ovk_proc_strtr(escapeHtml(this.findTrack(this.tracks["previousTrack"]).name), 20) ?? ""}
                </div>
            `)

            document.querySelector(".nextTrackTip").style.display = "block"
        })

        u(".bigPlayer .backButton").on("mouseover mouseleave", (e) => {
            if(this.tracks["currentTrack"] == null)
                return

            if(e.type == "mouseleave") {
                $(".previousTrackTip").remove()
                return
            }

            e.currentTarget.parentNode.insertAdjacentHTML("afterbegin", `
                <div class="bigPlayerTip previousTrackTip" style="left: 8%;">
                    ${ovk_proc_strtr(escapeHtml(this.findTrack(this.tracks["nextTrack"]).name), 20) ?? ""}
                </div>
            `)

            document.querySelector(".previousTrackTip").style.display = "block"
        })

        u(".bigPlayer .trackPanel .selectableTrack").on("mouseleave", (e) => {
            if(this.tracks["currentTrack"] == null)
                return
            
            document.querySelector(".bigPlayer .track .bigPlayerTip").style.display = "none"
        })

        u(".bigPlayer .volumePanel > div").on("click mouseup mousemove", (e) => {
            if(this.tracks["currentTrack"] == null)
                return

            if(e.type == "mousemove") {
                let buttonsPresseed = _bsdnUnwrapBitMask(e.buttons)
                if(!buttonsPresseed[0])
                    return;
            }

            let rect  = this.nodes["thisPlayer"].querySelector(".volumePanel .selectableTrack").getBoundingClientRect();
            
            const width = e.clientX - rect.left;
            const volume = Math.max(0, (width * 1) / (rect.right - rect.left));

            this.player().volume = volume;
        })

        u(".bigPlayer .elapsedTime").on("click", (e) => {
            if(this.tracks["currentTrack"] == null)
                return

            this.timeType == 0 ? (this.timeType = 1) : (this.timeType = 0)

            localStorage.playerTimeType = this.timeType

            this.nodes["thisPlayer"].querySelector(".elapsedTime").innerHTML = this.timeType == 1 ? 
                fmtTime(this.tracks["currentTrack"].length) 
                : getElapsedTime(this.tracks["currentTrack"].length, this.player().currentTime)
        })

        u(".bigPlayer .additionalButtons .repeatButton").on("click", (e) => {
            if(this.tracks["currentTrack"] == null)
                return

            e.currentTarget.classList.toggle("pressed")

            if(e.currentTarget.classList.contains("pressed"))
                this.player().loop = true
            else
                this.player().loop = false
        })

        u(".bigPlayer .additionalButtons .shuffleButton").on("click", (e) => {
            if(this.tracks["currentTrack"] == null)
                return

            this.tracks["tracks"].sort(() => Math.random() - 0.59)
            this.setTrack(this.tracks["tracks"].at(0).id)
        })

        // хз что она делала в самом вк, но тут сделаем вид что это просто мут музыки
        u(".bigPlayer .additionalButtons .deviceButton").on("click", (e) => {
            if(this.tracks["currentTrack"] == null)
                return

            e.currentTarget.classList.toggle("pressed")

            this.player().muted = e.currentTarget.classList.contains("pressed")
        })

        u(".bigPlayer .arrowsButtons .nextButton").on("click", (e) => {
            this.showPreviousTrack()
        })

        u(".bigPlayer .arrowsButtons .backButton").on("click", (e) => {
            this.showNextTrack()
        })

        u(".bigPlayer .trackInfo b").on("click", (e) => {
            window.location.assign(`/search?query=${e.currentTarget.innerHTML}&type=audios&only_performers=on`)
        })

        u(document).on("keydown", (e) => {
            if(["ArrowUp", "ArrowDown", "ArrowLeft", "ArrowRight", " "].includes(e.key)) {
                if(document.querySelector(".ovk-diag-cont") != null)
                    return

                e.preventDefault()
            }

            switch(e.key) {
                case "ArrowUp":
                    this.player().volume = Math.min(0.99, this.player().volume + 0.1)
                    break
                case "ArrowDown":
                    this.player().volume = Math.max(0, this.player().volume - 0.1)
                    break
                case "ArrowLeft":
                    this.player().currentTime = this.player().currentTime - 3
                    break
                case "ArrowRight":
                    this.player().currentTime = this.player().currentTime + 3
                    break
                // буквально
                case " ":
                    if(this.player().paused)
                        this.play()
                    else 
                        this.pause()

                    break;
            }
        })

        u(document).on("keyup", (e) => {
            if([87, 65, 83, 68, 82, 77].includes(e.keyCode)) {
                if(document.querySelector(".ovk-diag-cont") != null)
                    return

                e.preventDefault()
            }

            switch(e.keyCode) {
                case 87:
                case 65:
                    this.showPreviousTrack()
                    break
                case 83:
                case 68:
                    this.showNextTrack()
                    break
                case 82:
                    document.querySelector(".bigPlayer .additionalButtons .repeatButton").click()
                    break
                case 77:
                    document.querySelector(".bigPlayer .additionalButtons .deviceButton").click()
                    break
            }
        })

        u(this.player()).on("ended", (e) => {
            e.preventDefault()
            
            // в начало очереди
            if(!this.tracks.nextTrack) {
                if(!this.context["playedPages"].includes("1")) {
                    $.ajax({
                        type: "POST",
                        url: "/audios/context",
                        data: {
                            context: this["context"].context_type,
                            context_entity: this["context"].context_id,
                            hash: u("meta[name=csrf]").attr("value"),
                            page: 1
                        },
                        success: (response_2) => {
                            this.tracks["tracks"] = response_2["items"].concat(this.tracks["tracks"])
                            this.context["playedPages"].push(String(1))

                            this.setTrack(this.tracks["tracks"][0].id)
                        }
                    })
                } else {
                    this.setTrack(this.tracks.tracks[0].id)
                }
                
                return
            }

            this.showNextTrack()
        })
        
        u(this.player()).on("loadstart", (e) => {
            let playlist = this.context.context_type == "playlist_context" ? this.context.context_id : null

            let tempThisId = this.tracks.currentTrack.id
            setTimeout(() => {
                if(tempThisId != this.tracks.currentTrack.id) return

                $.ajax({
                    type: "POST",
                    url: `/audio${this.tracks["currentTrack"].id}/listen`,
                    data: {
                        hash: u("meta[name=csrf]").attr("value"),
                        playlist: playlist
                    },
                    success: (response) => {
                        if(response.success) {
                            console.info("Listen is counted.")
        
                            if(response.new_playlists_listens)
                                document.querySelector("#listensCount").innerHTML = tr("listens_count", response.new_playlists_listens)
                        } else
                            console.info("Listen is not counted.")
                    }
                })
            }, 2000)
        })

        if(localStorage.volume != null && localStorage.volume < 1 && localStorage.volume > 0)
            this.player().volume = localStorage.volume
        else
            this.player().volume = 0.75

        if(localStorage.playerTimeType == 'null' || localStorage.playerTimeType == null)
            this.timeType = 0
        else
            this.timeType = localStorage.playerTimeType

        navigator.mediaSession.setActionHandler('play', () => { this.play() });
        navigator.mediaSession.setActionHandler('pause', () => { this.pause() });
        navigator.mediaSession.setActionHandler('previoustrack', () => { this.showPreviousTrack() });
        navigator.mediaSession.setActionHandler('nexttrack', () => { this.showNextTrack() });
        navigator.mediaSession.setActionHandler("seekto", (details) => {
            this.player().currentTime = details.seekTime;
        });
    }

    play() {
        if(this.tracks["currentTrack"] == null)
            return

        document.querySelectorAll('audio').forEach(el => el.pause());
        document.querySelector(`.audioEmbed[data-realid='${this.tracks["currentTrack"].id}'] .audioEntry .playerButton .playIcon`) != null ? document.querySelector(`.audioEmbed[data-realid='${this.tracks["currentTrack"].id}'] .audioEntry .playerButton .playIcon`).classList.add("paused") : void(0)
        this.player().play()
        this.nodes["playButtons"].querySelector(".playButton").classList.add("pause")
        document.querySelector('link[rel="icon"], link[rel="shortcut icon"]').setAttribute("href", "/assets/packages/static/openvk/img/favicons/favicon24_paused.png")
    
        navigator.mediaSession.playbackState = "playing"
    }
    
    pause() {
        if(this.tracks["currentTrack"] == null) 
            return

        document.querySelector(`.audioEmbed[data-realid='${this.tracks["currentTrack"].id}'] .audioEntry .playerButton .playIcon`) != null ? document.querySelector(`.audioEmbed[data-realid='${this.tracks["currentTrack"].id}'] .audioEntry .playerButton .playIcon`).classList.remove("paused") : void(0)
        this.player().pause()
        this.nodes["playButtons"].querySelector(".playButton").classList.remove("pause")
        document.querySelector('link[rel="icon"], link[rel="shortcut icon"]').setAttribute("href", "/assets/packages/static/openvk/img/favicons/favicon24_playing.png")
    
        navigator.mediaSession.playbackState = "paused"
    }

    showPreviousTrack() {
        if(this.tracks["currentTrack"] == null || this.tracks["previousTrack"] == null)
            return

        this.setTrack(this.tracks["previousTrack"])
    }

    showNextTrack() {
        if(this.tracks["currentTrack"] == null || this.tracks["nextTrack"] == null)
            return
        
        this.setTrack(this.tracks["nextTrack"])
    }

    updateButtons() {
        // перепутал некст и бек.
        let prevButton = this.nodes["thisPlayer"].querySelector(".nextButton")
        let nextButton = this.nodes["thisPlayer"].querySelector(".backButton")

        if(this.tracks["previousTrack"] == null)
            prevButton.classList.add("lagged")
        else
            prevButton.classList.remove("lagged")

        if(this.tracks["nextTrack"] == null)
            nextButton.classList.add("lagged")
        else 
            nextButton.classList.remove("lagged")

        if(document.querySelector(".nextTrackTip") != null) {
            let track = this.findTrack(this.tracks["previousTrack"])
            document.querySelector(".nextTrackTip").innerHTML = `
                ${track != null ? ovk_proc_strtr(escapeHtml(track.name), 20) : ""}
            `
        }

        if(document.querySelector(".previousTrackTip") != null) {
            let track = this.findTrack(this.tracks["nextTrack"])
            document.querySelector(".previousTrackTip").innerHTML = `
                ${track != null ? ovk_proc_strtr(escapeHtml(track.name ?? ""), 20) : ""}
            `
        }
    }

    setTrack(id) {
        if(this.tracks["tracks"] == null) {
            console.info("Context is not loaded yet. Wait please")
            return 0;
        }

        document.querySelectorAll(".audioEntry.nowPlaying").forEach(el => el.classList.remove("nowPlaying"))
        let obj = this.tracks["tracks"].find(item => item.id == id)

        if(obj == null) {
            fastError("No audio in context")
            return
        }

        this.nodes["thisPlayer"].querySelector(".trackInfo span").innerHTML = escapeHtml(obj.name) 
        this.nodes["thisPlayer"].querySelector(".trackInfo b").innerHTML = escapeHtml(obj.performer)
        this.nodes["thisPlayer"].querySelector(".trackInfo .time").innerHTML = fmtTime(obj.length)
        this.tracks["currentTrack"] = obj

        let indexOfCurrentTrack = this.tracks["tracks"].indexOf(obj) ?? 0
        this.tracks["nextTrack"] = this.tracks["tracks"].at(indexOfCurrentTrack + 1) != null ? this.tracks["tracks"].at(indexOfCurrentTrack + 1).id : null

        if(indexOfCurrentTrack - 1 >= 0)
            this.tracks["previousTrack"] = this.tracks["tracks"].at(indexOfCurrentTrack - 1).id
        else
            this.tracks["previousTrack"] = null

        if(this.tracks["nextTrack"] == null && Math.max(...this.context["playedPages"]) < this.context["pagesCount"]
            || this.tracks["previousTrack"] == null && (Math.min(...this.context["playedPages"]) > 1)) {
            
            // idk how it works
            let lesser = this.tracks["previousTrack"] == null ? (Math.min(...this.context["playedPages"]) > 1)
                        : Math.max(...this.context["playedPages"]) > this.context["pagesCount"]
            
            let formdata = new FormData()
            formdata.append("context", this.context["context_type"])
            formdata.append("context_entity", this.context["context_id"])
            formdata.append("hash", u("meta[name=csrf]").attr("value"))

            if(lesser)
                formdata.append("page", Math.min(...this.context["playedPages"]) - 1)
            else
                formdata.append("page", Number(Math.max(...this.context["playedPages"])) + 1)
            
            ky.post("/audios/context", {
                hooks: {
                    afterResponse: [
                        async (_request, _options, response) => {
                            let newArr = await response.json()

                            if(lesser)
                                this.tracks["tracks"] = newArr["items"].concat(this.tracks["tracks"])
                            else
                                this.tracks["tracks"] = this.tracks["tracks"].concat(newArr["items"])

                            this.context["playedPages"].push(String(newArr["page"]))

                            if(lesser)
                                this.tracks["previousTrack"] = this.tracks["tracks"].at(this.tracks["tracks"].indexOf(obj) - 1).id
                            else
                                this.tracks["nextTrack"] = this.tracks["tracks"].at(indexOfCurrentTrack + 1) != null ? this.tracks["tracks"].at(indexOfCurrentTrack + 1).id : null
                            
                            this.updateButtons()
                            console.info("Context is successfully loaded")
                        }
                    ]
                }, 
                body: formdata
            })
        }

        if(this.tracks["currentTrack"].available == false || this.tracks["currentTrack"].withdrawn)
            this.showNextTrack()

        this.updateButtons()

        const protData = {
            "org.w3.clearkey": {
                "clearkeys": obj.keys
            }
        };

        this.nodes["dashPlayer"].initialize(this.player(), obj.url, false);
        this.nodes["dashPlayer"].setProtectionData(protData);

        this.play()

        let playerAtPage = document.querySelector(`.audioEmbed[data-realid='${this.tracks["currentTrack"].id}'] .audioEntry`)
        if(playerAtPage != null)
            playerAtPage.classList.add("nowPlaying")

        document.querySelectorAll(`.audioEntry .playerButton .playIcon.paused`).forEach(el => el.classList.remove("paused"))
        
        localStorage.lastPlayedTrack = this.tracks["currentTrack"].id

        if(this.timeType == 1)
            this.nodes["thisPlayer"].querySelector(".elapsedTime").innerHTML = fmtTime(this.tracks["currentTrack"].length)

        let album = document.querySelector(".playlistBlock")

        navigator.mediaSession.metadata = new MediaMetadata({
            title: obj.name,
            artist: obj.performer,
            album: album == null ? "OpenVK Audios" : album.querySelector(".playlistInfo h4").innerHTML,
            artwork: [{ src: album == null ? "/assets/packages/static/openvk/img/song.jpg" : album.querySelector(".playlistCover img").src }],
        });

        navigator.mediaSession.setPositionState({
            duration: this.tracks["currentTrack"].length
        })
    }
}

document.addEventListener("DOMContentLoaded", function() {
    if(document.querySelector(".bigPlayer") != null) {
        let context = document.querySelector("input[name='bigplayer_context']")

        if(!context)
            return
        
        let type = context.dataset.type
        let entity = context.dataset.entity
        window.player = new bigPlayer(type, entity, context.dataset.page)

        let bigplayer = document.querySelector('.bigPlayerDetector')

        let bigPlayerObserver = new IntersectionObserver(entries => {
            entries.forEach(x => {
                if(x.isIntersecting) {
                    document.querySelector('.bigPlayer').classList.remove("floating")
                    //document.querySelector('.searchOptions .searchList').classList.remove("floating")
                    document.querySelector('.bigPlayerDetector').style.marginTop = "0px"
                } else {
                    //document.querySelector('.searchOptions .searchList').classList.add("floating")
                    document.querySelector('.bigPlayer').classList.add("floating")
                    document.querySelector('.bigPlayerDetector').style.marginTop = "46px"
                }
            });
        }, {
            root: null,
            rootMargin: "0px",
            threshold: 0
        });

        if(bigplayer != null)
            bigPlayerObserver.observe(bigplayer);
    }

    $(document).on("mouseover mouseleave", `.audioEntry .mediaInfo`, (e) => {
        const info = e.currentTarget.closest(".mediaInfo")
        const overfl = info.querySelector(".info")

        if(e.originalEvent.type == "mouseleave" || e.originalEvent.type == "mouseout") {
            info.classList.add("noOverflow")
            info.classList.remove("overflowedName")
        } else {
            info.classList.remove("noOverflow")
            info.classList.add("overflowedName")
        }
    })
})

$(document).on("click", ".audioEmbed > *", (e) => {
    const player = e.currentTarget.closest(".audioEmbed")

    if(player.classList.contains("inited")) return

    initPlayer(player.id.replace("audioEmbed-", ""), 
    JSON.parse(player.dataset.keys), 
    player.dataset.url, 
    player.dataset.length)

    if(e.target.classList.contains("playIcon"))
        e.target.click()
})

function initPlayer(id, keys, url, length) {
    document.querySelector(`#audioEmbed-${ id}`).classList.add("inited")
    const audio = document.querySelector(`#audioEmbed-${ id} .audio`);
    const playButton = u(`#audioEmbed-${ id} .playerButton > .playIcon`);
    const trackDiv = u(`#audioEmbed-${ id} .track > div > div`);
    const volumeSpan = u(`#audioEmbed-${ id} .volume span`);
    const rect = document.querySelector(`#audioEmbed-${ id} .selectableTrack`).getBoundingClientRect();
    
    const playerObject = document.querySelector(`#audioEmbed-${ id}`)

    if(document.querySelector(".bigPlayer") != null) {
        playButton.on("click", () => {
            if(window.player.tracks["tracks"] == null)
                return

            if(window.player.tracks["currentTrack"] == null || window.player.tracks["currentTrack"].id != playerObject.dataset.realid)
                window.player.setTrack(playerObject.dataset.realid)
            else
                document.querySelector(".bigPlayer .playButton").click()
        })

        return
    }
    
    const protData = {
        "org.w3.clearkey": {
            "clearkeys": keys
        }
    };

    const player = dashjs.MediaPlayer().create();
    player.initialize(audio, url, false);
    player.setProtectionData(protData);

    playButton.on("click", () => {
        if (audio.paused) {
            document.querySelectorAll('audio').forEach(el => el.pause());
            audio.play();
        } else {
            audio.pause();
        }
    });

    u(audio).on("timeupdate", () => {
        const time = audio.currentTime;
        const ps = ((time * 100) / length).toFixed(3);
        volumeSpan.html(fmtTime(Math.floor(time)));

        if (ps <= 100)
            playerObject.querySelector(".lengthTrack .slider").style.left = `${ ps}%`;
    });

    u(audio).on("volumechange", (e) => {
        const volume = audio.volume;
        const ps = Math.ceil((volume * 100) / 1);

        if (ps <= 100)
            playerObject.querySelector(".volumeTrack .slider").style.left = `${ ps}%`;
    })

    const playButtonImageUpdate = () => {
        if (!audio.paused) {
            playButton.addClass("paused")
            document.querySelector('link[rel="icon"], link[rel="shortcut icon"]').setAttribute("href", "/assets/packages/static/openvk/img/favicons/favicon24_paused.png")
        } else {
            playButton.removeClass("paused")
            document.querySelector('link[rel="icon"], link[rel="shortcut icon"]').setAttribute("href", "/assets/packages/static/openvk/img/favicons/favicon24_playing.png")
        }
        
        if(!$(`#audioEmbed-${ id}`).hasClass("havePlayed")) {
            $(`#audioEmbed-${ id}`).addClass("havePlayed")

            $(`#audioEmbed-${ id} .track`).toggle()

            $.post(`/audio${playerObject.dataset.realid}/listen`, {
                hash: u("meta[name=csrf]").attr("value")
            });
        }
    };

    const hideTracks = () => {
        $(`#audioEmbed-${ id} .track`).toggle()
        $(`#audioEmbed-${ id}`).removeClass("havePlayed")
    }

    u(audio).on("play", playButtonImageUpdate);
    u(audio).on(["pause", "suspended"], playButtonImageUpdate);
    u(audio).on("ended", (e) => {
        let thisPlayer = e.target.closest(".audioEmbed")
        let nextPlayer = null
        if(thisPlayer.closest(".attachment") != null) {
            try {
                nextPlayer = thisPlayer.closest(".attachment").nextElementSibling.querySelector(".audioEmbed")
            } catch(e) {return}
        } else if(thisPlayer.closest(".audio") != null) {
            try {
                nextPlayer = thisPlayer.closest(".audio").nextElementSibling.querySelector(".audioEmbed")
            } catch(e) {return}
        } else {
            nextPlayer = thisPlayer.nextElementSibling
        }

        playButtonImageUpdate()

        if(!nextPlayer) return

        initPlayer(nextPlayer.id.replace("audioEmbed-", ""), 
            JSON.parse(nextPlayer.dataset.keys), 
            nextPlayer.dataset.url, 
            nextPlayer.dataset.length)
        
        nextPlayer.querySelector(".playIcon").click()
        hideTracks()
    })

    u(`#audioEmbed-${ id} .lengthTrack > div`).on("click", (e) => {
        let rect  = document.querySelector("#audioEmbed-" + id + " .selectableTrack").getBoundingClientRect();
        const width = e.clientX - rect.left;
        const time = Math.ceil((width * length) / (rect.right - rect.left));

        audio.currentTime = time;
    });

    u(`#audioEmbed-${ id} .volumeTrack > div`).on("click mouseup mousemove", (e) => {
        if(e.type == "mousemove") {
            let buttonsPresseed = _bsdnUnwrapBitMask(e.buttons)
            if(!buttonsPresseed[0])
                return;
        }

        let rect = document.querySelector("#audioEmbed-" + id + " .volumeTrack").getBoundingClientRect();
        
        const width = e.clientX - rect.left;
        const volume = (width * 1) / (rect.right - rect.left);

        audio.volume = volume;
    });

    audio.volume = localStorage.volume ?? 0.75
    u(audio).trigger("volumechange")
}

$(document).on("click", ".musicIcon.edit-icon", (e) => {
    let player = e.currentTarget.closest(".audioEmbed")
    let id = Number(player.dataset.realid)
    let performer = e.currentTarget.dataset.performer
    let name = e.currentTarget.dataset.title
    let genre = player.dataset.genre
    let lyrics = e.currentTarget.dataset.lyrics
    
    MessageBox(tr("edit_audio"), `
        <div>
            ${tr("performer")}
            <input name="performer" maxlength="256" type="text" value="${performer}">
        </div>

        <div style="margin-top: 11px">
            ${tr("audio_name")}
            <input name="name" maxlength="256" type="text" value="${name}">
        </div>

        <div style="margin-top: 11px">
            ${tr("genre")}
            <select name="genre"></select>
        </div>

        <div style="margin-top: 11px">
            ${tr("lyrics")}
            <textarea name="lyrics" maxlength="5000" style="max-height: 200px;">${lyrics ?? ""}</textarea>
        </div>

        <div style="margin-top: 11px">
            <label><input type="checkbox" name="explicit" ${e.currentTarget.dataset.explicit == 1 ? "checked" : ""}>${tr("audios_explicit")}</label><br>
            <label><input type="checkbox" name="searchable" ${e.currentTarget.dataset.searchable == 1 ? "checked" : ""}>${tr("searchable")}</label>
            <hr>
            <a id="_fullyDeleteAudio">${tr("fully_delete_audio")}</a>
        </div>
    `, [tr("save"), tr("cancel")], [
        function() {
            let t_name   = $(".ovk-diag-body input[name=name]").val();
            let t_perf   = $(".ovk-diag-body input[name=performer]").val();
            let t_genre  = $(".ovk-diag-body select[name=genre]").val();
            let t_lyrics = $(".ovk-diag-body textarea[name=lyrics]").val();
            let t_explicit = document.querySelector(".ovk-diag-body input[name=explicit]").checked;
            let t_unlisted = document.querySelector(".ovk-diag-body input[name=searchable]").checked;

            $.ajax({
                type: "POST",
                url: `/audio${id}/action?act=edit`,
                data: {
                    name: t_name,
                    performer: t_perf,
                    genre: t_genre,
                    lyrics: t_lyrics,
                    unlisted: Number(t_unlisted),
                    explicit: Number(t_explicit),
                    hash: u("meta[name=csrf]").attr("value")
                },
                success: (response) => {
                    if(response.success) {
                        let perf = player.querySelector(".performer a")
                        perf.innerHTML = escapeHtml(response.new_info.performer)
                        perf.setAttribute("href", "/search?query=&type=audios&sort=id&only_performers=on&query="+response.new_info.performer)
                        
                        e.target.setAttribute("data-performer", escapeHtml(response.new_info.performer))
                        
                        let name = player.querySelector(".title")
                        name.innerHTML = escapeHtml(response.new_info.name)

                        e.target.setAttribute("data-title", escapeHtml(response.new_info.name))
                        
                        if(response.new_info.lyrics_unformatted != "") {
                            if(player.querySelector(".lyrics") != null) {
                                player.querySelector(".lyrics").innerHTML = response.new_info.lyrics
                                player.querySelector(".title").classList.add("withLyrics")
                            } else {
                                player.insertAdjacentHTML("beforeend", `
                                    <div class="lyrics">
                                        ${response.new_info.lyrics}
                                    </div>
                                `)
    
                                player.querySelector(".title").classList.add("withLyrics")
                            }
                        } else {
                            $(player.querySelector(".lyrics")).remove()
                            player.querySelector(".title").classList.remove("withLyrics")
                        }

                        e.target.setAttribute("data-lyrics", response.new_info.lyrics_unformatted)
                        e.target.setAttribute("data-explicit", Number(response.new_info.explicit))

                        if(Number(response.new_info.explicit) == 1) {
                            if(!player.querySelector(".mediaInfo .explicitMark"))
                                player.querySelector(".mediaInfo").insertAdjacentHTML("beforeend", `
                                    <div class="explicitMark"></div>
                                `)
                        } else {
                            $(player.querySelector(".mediaInfo .explicitMark")).remove()
                        }

                        e.target.setAttribute("data-searchable", Number(!response.new_info.unlisted))
                        player.setAttribute("data-genre", response.new_info.genre)

                        let url = new URL(location.href)
                        let page = "1"

                        if(url.searchParams.p != null)
                            page = String(url.searchParams.p)
                        
                        window.savedAudiosPages[page] = null
                    } else
                        fastError(response.flash.message)
                }
            });
        },

        Function.noop
    ]);

    window.openvk.audio_genres.forEach(elGenre => {
        document.querySelector(".ovk-diag-body select[name=genre]").insertAdjacentHTML("beforeend", `
            <option value="${elGenre}" ${elGenre == genre ? "selected" : ""}>${elGenre}</option>
        `)
    })

    u(".ovk-diag-body #_fullyDeleteAudio").on("click", (e) => {
        u("body").removeClass("dimmed");
        document.querySelector("html").style.overflowY = "scroll"

        u(".ovk-diag-cont").remove();

        $.ajax({
            type: "POST",
            url: `/audio${id}/action?act=delete`,
            data: {
                hash: u("meta[name=csrf]").attr("value")
            },
            success: (response) => {
                if(response.success)
                    u(player).remove()
                else
                    fastError(response.flash.message)
            }
        });
    })
})

$(document).on("click", ".title.withLyrics", (e) => {
    let parent = e.currentTarget.closest(".audioEmbed")

    parent.querySelector(".lyrics").classList.toggle("showed")
})

$(document).on("click", ".musicIcon.remove-icon", (e) => {
    e.stopImmediatePropagation()

    let id = e.currentTarget.dataset.id

    let formdata = new FormData()
    formdata.append("hash", u("meta[name=csrf]").attr("value"))

    ky.post(`/audio${id}/action?act=remove`, {
        hooks: {
            beforeRequest: [
                (_request) => {
                    e.target.classList.add("lagged")
                }
            ],
            afterResponse: [
                async (_request, _options, response) => {
                    let json = await response.json()

                    if(json.success) {
                        e.target.classList.remove("remove-icon")
                        e.target.classList.add("add-icon")
                        e.target.classList.remove("lagged")

                        let withd = e.target.closest(".audioEmbed.withdrawn")

                        if(withd != null)
                            u(withd).remove()
                    } else
                        fastError(json.flash.message)
                }
            ]
        }, body: formdata
    })
})

$(document).on("click", ".musicIcon.remove-icon-group", (e) => {
    e.stopImmediatePropagation()
    
    let id = e.currentTarget.dataset.id

    let formdata = new FormData()
    formdata.append("hash", u("meta[name=csrf]").attr("value"))
    formdata.append("club", e.currentTarget.dataset.club)

    ky.post(`/audio${id}/action?act=remove_club`, {
        hooks: {
            beforeRequest: [
                (_request) => {
                    e.currentTarget.classList.add("lagged")
                }
            ],
            afterResponse: [
                async (_request, _options, response) => {
                    let json = await response.json()

                    if(json.success)
                        $(e.currentTarget.closest(".audioEmbed")).remove()
                    else
                        fastError(json.flash.message)
                }
            ]
        }, body: formdata
    })
})

$(document).on("click", ".musicIcon.add-icon-group", async (ev) => {
    let body = `
        ${tr("what_club_add")}
        <div style="margin-top: 4px;">
            <select id="addIconsWindow" style="width: 59%;"></select>
            <input name="addButton" type="button" class="button" value="${tr("add")}">
        </div>
        <span class="errorPlace"></span>
    `
    MessageBox(tr("add_audio_to_club"), body, [tr("close")], [Function.noop])

    document.querySelector(".ovk-diag-body").style.padding = "11px"

    if(window.openvk.writeableClubs == null) {
        try {
            window.openvk.writeableClubs = await API.Groups.getWriteableClubs()
        } catch (e) {
            document.querySelector(".errorPlace").innerHTML = tr("no_access_clubs")
            document.querySelector(".ovk-diag-body input[name='addButton']").classList.add("lagged")

            return
        }
    }

    window.openvk.writeableClubs.forEach(el => {
        document.querySelector("#addIconsWindow").insertAdjacentHTML("beforeend", `
            <option value="${el.id}">${ovk_proc_strtr(el.name, 20)}</option>
        `)
    })

    $(".ovk-diag-body").on("click", "input[name='addButton']", (e) => {
        $.ajax({
            type: "POST",
            url: `/audio${ev.target.dataset.id}/action?act=add_to_club`,
            data: {
                hash: u("meta[name=csrf]").attr("value"),
                club: document.querySelector("#addIconsWindow").value
            },
            beforeSend: () => {
                e.target.classList.add("lagged")
                document.querySelector(".errorPlace").innerHTML = ""
            },
            success: (response) => {
                if(!response.success)
                    document.querySelector(".errorPlace").innerHTML = response.flash.message

                e.currentTarget.classList.remove("lagged")
            }
        })
    })
})

$(document).on("click", ".musicIcon.add-icon", (e) => {
    let id = e.currentTarget.dataset.id

    let formdata = new FormData()
    formdata.append("hash", u("meta[name=csrf]").attr("value"))

    ky.post(`/audio${id}/action?act=add`, {
        hooks: {
            beforeRequest: [
                (_request) => {
                    e.target.classList.add("lagged")
                }
            ],
            afterResponse: [
                async (_request, _options, response) => {
                    let json = await response.json()

                    if(json.success) {
                        e.target.classList.remove("add-icon")
                        e.target.classList.add("remove-icon")
                        e.target.classList.remove("lagged")
                    } else
                       fastError(json.flash.message)
                }
            ]
        }, body: formdata
    })
})

$(document).on("click", "#_deletePlaylist", (e) => {
    let id = e.currentTarget.dataset.id

    MessageBox(tr("warning"), tr("sure_delete_playlist"), [tr("yes"), tr("no")], [() => {
        $.ajax({
            type: "POST",
            url: `/playlist${id}/action?act=delete`,
            data: {
                hash: u("meta[name=csrf]").attr("value"),
            },
            beforeSend: () => {
                e.currentTarget.classList.add("lagged")
            },
            success: (response) => {
                if(response.success) {
                    window.location.assign("/playlists" + response.id)
                } else {
                    fastError(response.flash.message)
                }
            }
        })
    }, Function.noop])
})

$(document).on("click", "#_audioAttachment", (e) => {
    let form = e.currentTarget.closest("form")
    let body = `
        <div class="searchBox">
            <input name="query" type="text" maxlength="50" placeholder="${tr("header_search")}">
            <select name="perf">
                <option value="by_name">${tr("by_name")}</option>
                <option value="by_performer">${tr("by_performer")}</option>
            </select>
        </div>

        <div class="audiosInsert"></div>
    `
    MessageBox(tr("select_audio"), body, [tr("ok")], [Function.noop])

    document.querySelector(".ovk-diag-body").style.padding = "0"
    document.querySelector(".ovk-diag-cont").style.width = "580px"
    document.querySelector(".ovk-diag-body").style.height = "335px"

    let searcher = new playersSearcher("entity_audios", 0)
    searcher.successCallback = (response, thisc) => {
        let domparser = new DOMParser()
        let result = domparser.parseFromString(response, "text/html")

        let pagesCount = result.querySelector("input[name='pagesCount']").value
        let count = Number(result.querySelector("input[name='count']").value)

        if(count < 1) {
            document.querySelector(".audiosInsert").innerHTML = thisc.context_type == "entity_audios" ? tr("no_audios_thisuser") : tr("no_results")
            return
        }

        result.querySelectorAll(".audioEmbed").forEach(el => {
            let id = el.dataset.prettyid
            let name = el.dataset.name
            let isAttached = (form.querySelector("input[name='audios']").value.includes(`${id},`))
            document.querySelector(".audiosInsert").insertAdjacentHTML("beforeend", `
                <div style="display: table;width: 100%;clear: both;">
                    <div style="width: 72%;float: left;">${el.outerHTML}</div>
                    <div class="attachAudio" data-attachmentdata="${id}" data-name="${name}">
                        <span>${isAttached ? tr("detach_audio") : tr("attach_audio")}</span>
                    </div>
                </div>
            `)
        })

        u("#loader").remove()

        if(thisc.page < pagesCount) {
            document.querySelector(".audiosInsert").insertAdjacentHTML("beforeend", `
            <div id="showMoreAudios" data-pagesCount="${pagesCount}" data-page="${thisc.page + 1}" class="showMore">
                <span>${tr("show_more_audios")}</span>
            </div>`)
        }
    }

    searcher.errorCallback = () => {
        fastError("Error when loading players.")
    }

    searcher.beforesendCallback = () => {
        document.querySelector(".audiosInsert").insertAdjacentHTML("beforeend", `<img id="loader" src="/assets/packages/static/openvk/img/loading_mini.gif">`)
    }

    searcher.clearContainer = () => {
        document.querySelector(".audiosInsert").innerHTML = ""
    }

    searcher.movePage(1)

    $(".audiosInsert").on("click", "#showMoreAudios", (e) => {
        u(e.currentTarget).remove()
        searcher.movePage(Number(e.currentTarget.dataset.page))
    })

    $(".searchBox input").on("change", async (e) => {
        await new Promise(r => setTimeout(r, 500));

        if(e.currentTarget.value === document.querySelector(".searchBox input").value) {
            searcher.clearContainer()

            if(e.currentTarget.value == "") {
                searcher.context_type = "entity_audios"
                searcher.context_id = 0
                searcher.query = ""

                searcher.movePage(1)

                return
            }

            searcher.context_type = "search_context"
            searcher.context_id = 0
            searcher.query = e.currentTarget.value

            searcher.movePage(1)
            return;
        }
    })

    $(".searchBox select").on("change", async (e) => {
        searcher.clearContainer()
        searcher.searchType = e.currentTarget.value

        $(".searchBox input").trigger("change")
        return;
    })

    function insertAttachment(id) {
        let audios = form.querySelector("input[name='audios']") 

        if(!audios.value.includes(id + ",")) {
            if(audios.value.split(",").length > 10) {
                NewNotification(tr("error"), tr("max_attached_audios"))
                return false
            }

            form.querySelector("input[name='audios']").value += (id + ",")

            return true
        } else {
            form.querySelector("input[name='audios']").value = form.querySelector("input[name='audios']").value.replace(id + ",", "")

            return false
        }
    }

    $(".audiosInsert").on("click", ".attachAudio", (ev) => {
        if(!insertAttachment(ev.currentTarget.dataset.attachmentdata)) {
            u(`.post-has-audios .post-has-audio[data-id='${ev.currentTarget.dataset.attachmentdata}']`).remove()
            ev.currentTarget.querySelector("span").innerHTML = tr("attach_audio")
        } else {
            ev.currentTarget.querySelector("span").innerHTML = tr("detach_audio")

            form.querySelector(".post-has-audios").insertAdjacentHTML("beforeend", `
                <div class="post-has-audio" id="unattachAudio" data-id="${ev.currentTarget.dataset.attachmentdata}">
                    <span>${ovk_proc_strtr(escapeHtml(ev.currentTarget.dataset.name), 40)}</span>
                </div>
            `)

            u(`#unattachAudio[data-id='${ev.currentTarget.dataset.attachmentdata}']`).on("click", (e) => {
                let id = ev.currentTarget.dataset.attachmentdata
                form.querySelector("input[name='audios']").value = form.querySelector("input[name='audios']").value.replace(id + ",", "")

                u(e.currentTarget).remove()
            })
        }
    })
})

$(document).on("click", ".audioEmbed.processed", (e) => {
    MessageBox(tr("error"), tr("audio_embed_processing"), [tr("ok")], [Function.noop])
})

$(document).on("click", ".audioEmbed.withdrawn", (e) => {
    MessageBox(tr("error"), tr("audio_embed_withdrawn"), [tr("ok")], [Function.noop])
})

$(document).on("click", ".musicIcon.report-icon", (e) => {
    MessageBox(tr("report_question"), `
        ${tr("going_to_report_audio")}
        <br/>${tr("report_question_text")}
        <br/><br/><b> ${tr("report_reason")}</b>: <input type='text' id='uReportMsgInput' placeholder='${tr("reason")}' />`, [tr("confirm_m"), tr("cancel")], [(function() {
        
        res = document.querySelector("#uReportMsgInput").value;
        xhr = new XMLHttpRequest();
        xhr.open("GET", "/report/" + e.target.dataset.id + "?reason=" + res + "&type=audio", true);
        xhr.onload = (function() {
        if(xhr.responseText.indexOf("reason") === -1)
            MessageBox(tr("error"), tr("error_sending_report"), ["OK"], [Function.noop]);
        else
           MessageBox(tr("action_successfully"), tr("will_be_watched"), ["OK"], [Function.noop]);
        });
        xhr.send(null)
    }),

    Function.noop])
})

$(document).on("click", ".audiosContainer .paginator a", (e) => {
    e.preventDefault()
    let url = new URL(e.currentTarget.href)
    let page = url.searchParams.get("p")

    function searchNode(id) {
        let node = document.querySelector(`.audioEmbed[data-realid='${id}'] .audioEntry`)

        if(node != null) {
            node.classList.add("nowPlaying")
        }
    }

    if(window.savedAudiosPages[page] != null) {
        history.pushState({}, "", e.currentTarget.href)
        document.querySelector(".audiosContainer").innerHTML = window.savedAudiosPages[page].innerHTML
        searchNode(window.player["tracks"].currentTrack != null ? window.player["tracks"].currentTrack.id : 0)

        return
    }

    e.currentTarget.parentNode.classList.add("lagged")
    $.ajax({
        type: "GET",
        url: e.currentTarget.href,
        success: (response) => {
            let domparser = new DOMParser()
            let result = domparser.parseFromString(response, "text/html")

            document.querySelector(".audiosContainer").innerHTML = result.querySelector(".audiosContainer").innerHTML
            history.pushState({}, "", e.currentTarget.href)
            window.savedAudiosPages[page] = result.querySelector(".audiosContainer")
            searchNode(window.player["tracks"].currentTrack != null ? window.player["tracks"].currentTrack.id : 0)

            if(!window.player.context["playedPages"].includes(page)) {
                $.ajax({
                    type: "POST",
                    url: "/audios/context",
                    data: {
                        context: window.player["context"].context_type,
                        context_entity: window.player["context"].context_id,
                        hash: u("meta[name=csrf]").attr("value"),
                        page: page
                    },
                    success: (response_2) => {
                        window.player.tracks["tracks"] = window.player.tracks["tracks"].concat(response_2["items"])
                        window.player.context["playedPages"].push(String(page))
                        console.info("Page is switched")
                    }
                })
            }
        }
    })
})

$(document).on("click", ".addToPlaylist", (e) => {
    let audios = document.querySelector("input[name='audios']") 
    let id = e.currentTarget.dataset.id

    if(!audios.value.includes(id + ",")) {
        document.querySelector("input[name='audios']").value += (id + ",")
        e.currentTarget.querySelector("span").innerHTML = tr("remove_from_playlist")
    } else {
        document.querySelector("input[name='audios']").value = document.querySelector("input[name='audios']").value.replace(id + ",", "")
        e.currentTarget.querySelector("span").innerHTML = tr("add_to_playlist")
    }
})

$(document).on("click", "#bookmarkPlaylist, #unbookmarkPlaylist", (e) => {
    let target = e.currentTarget
    let id = target.id

    $.ajax({
        type: "POST",
        url: `/playlist${e.currentTarget.dataset.id}/action?act=${id == "unbookmarkPlaylist" ? "unbookmark" : "bookmark"}`,
        data: {
            hash: u("meta[name=csrf]").attr("value"),
        },
        beforeSend: () => {
            e.currentTarget.classList.add("lagged")
        },
        success: (response) => {
            if(response.success) {
                e.currentTarget.setAttribute("id", id == "unbookmarkPlaylist" ? "bookmarkPlaylist" : "unbookmarkPlaylist")
                e.currentTarget.innerHTML = id == "unbookmarkPlaylist" ? tr("bookmark") : tr("unbookmark")
                e.currentTarget.classList.remove("lagged")
            } else
                fastError(response.flash.message)
        }
    })
})
