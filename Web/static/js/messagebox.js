Function.noop = () => {};

function MessageBox(title, body, buttons, callbacks) {
    if(u(".ovk-diag-cont").length > 0) return false;
    
    document.querySelector("html").style.overflowY = "hidden"
    let dialog = u(
    `<div class="ovk-diag-cont">
        <div class="ovk-diag">
            <div class="ovk-diag-head">${title}</div>
            <div class="ovk-diag-body">${body}</div>
            <div class="ovk-diag-action"></div>
        </div>
    </div>`);
    u("body").addClass("dimmed").append(dialog);
    
    buttons.forEach((text, callback) => {
        u(".ovk-diag-action").append(u(`<button class="button">${text}</button>`));
        let button = u(u(".ovk-diag-action > button.button").last());
        
        button.on("click", function(e) {
            let __closeDialog = () => {
              
                if(document.querySelector(".ovk-photo-view-dimmer") == null && document.querySelector(".ovk-fullscreen-player") == null) {
                    u("body").removeClass("dimmed");
                    document.querySelector("html").style.overflowY = "scroll"
                }
                
                u(".ovk-diag-cont").remove();
            };
            
            Reflect.apply(callbacks[callback], {
                closeDialog: () => __closeDialog(),
                $dialog:     () => u(".ovk-diag-cont")
            }, [e]);
        
            __closeDialog();
        });
    });
    
    return u(".ovk-diag-cont");
}
