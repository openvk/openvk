let context_type = "entity_audios"
let context_id = 0

if(document.querySelector("#editPlaylistForm")) {
    context_type = "playlist_context"
    context_id = document.querySelector("#editPlaylistForm").dataset.id
}

if(document.querySelector(".showMoreAudiosPlaylist") && document.querySelector(".showMoreAudiosPlaylist").dataset.club != null) {
    context_type = "entity_audios"
    context_id = Number(document.querySelector(".showMoreAudiosPlaylist").dataset.club) * -1
}

let searcher = new playersSearcher(context_type, context_id)

searcher.successCallback = (response, thisc) => {
    let domparser = new DOMParser()
    let result = domparser.parseFromString(response, "text/html")
    let pagesCount = Number(result.querySelector("input[name='pagesCount']").value)
    let count = Number(result.querySelector("input[name='count']").value)

    result.querySelectorAll(".audioEmbed").forEach(el => {
        let id = Number(el.dataset.realid)
        let isAttached = (document.querySelector("input[name='audios']").value.includes(`${id},`))

        document.querySelector(".playlistAudiosContainer").insertAdjacentHTML("beforeend", `
            <div id="newPlaylistAudios">
                <div class="playerContainer">
                    ${el.outerHTML}
                </div>
                <div class="attachAudio addToPlaylist" data-id="${id}">
                    <span>${isAttached ? tr("remove_from_playlist") : tr("add_to_playlist")}</span>
                </div>
            </div>
        `)
    })

    if(count < 1)
        document.querySelector(".playlistAudiosContainer").insertAdjacentHTML("beforeend", `
            ${tr("no_results")}
        `)

    if(Number(thisc.page) >= pagesCount)
        u(".showMoreAudiosPlaylist").remove()
    else {
        if(document.querySelector(".showMoreAudiosPlaylist") != null) {
            document.querySelector(".showMoreAudiosPlaylist").setAttribute("data-page", thisc.page + 1)

            if(thisc.query != "") {
                document.querySelector(".showMoreAudiosPlaylist").setAttribute("data-query", thisc.query)
            }

            document.querySelector(".showMoreAudiosPlaylist").style.display = "block"
        } else {
            document.querySelector(".playlistAudiosContainer").parentNode.insertAdjacentHTML("beforeend", `
                <div class="showMoreAudiosPlaylist" data-page="2" 
                ${thisc.query != "" ? `"data-query="${thisc.query}"` : ""} 
                ${thisc.context_type == "entity_audios" ? `"data-playlist="${thisc.context_id}"` : ""}
                ${thisc.context_id < 0 ? `"data-club="${thisc.context_id}"` : ""}>
                    ${tr("show_more_audios")}
                </div>
            `)
        }
    }

    u("#loader").remove()
}

searcher.beforesendCallback = () => {
    document.querySelector(".playlistAudiosContainer").parentNode.insertAdjacentHTML("beforeend", `<img id="loader" src="/assets/packages/static/openvk/img/loading_mini.gif">`)

    if(document.querySelector(".showMoreAudiosPlaylist") != null)
        document.querySelector(".showMoreAudiosPlaylist").style.display = "none"
}

searcher.errorCallback = () => {
    fastError("Error when loading players")
}

searcher.clearContainer = () => {
    document.querySelector(".playlistAudiosContainer").innerHTML = ""
}

$(document).on("click", ".showMoreAudiosPlaylist", (e) => {
    searcher.movePage(Number(e.currentTarget.dataset.page))
})

$(document).on("change", "input#playlist_query", async (e) => {
    e.preventDefault()
    
    await new Promise(r => setTimeout(r, 500));

    if(e.currentTarget.value === document.querySelector("input#playlist_query").value) {
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
