const contentPage = document.querySelector(".page_content");
const rootElement = document.documentElement;

// охуенное название файла, КТО ЭТО ПРИДУМАЛ КРАСАВА Я ИЗ КОМНАТЫ С ЭТОГО УЛЕТЕЛ НАХУЙ

let scrolledAndHidden = false;

let smallBlockObserver = new IntersectionObserver(entries => {
    entries.forEach(x => {
        window.requestAnimationFrame(() => {
            let pastHeight = contentPage.getBoundingClientRect().height;
            if(x.isIntersecting)
                contentPage.classList.remove("overscrolled");
            else
                contentPage.classList.add("overscrolled");

            // let currentHeight = contentPage.getBoundingClientRect().height;
            // let ratio         = currentHeight / pastHeight;

            // rootElement.scrollTop *= ratio;

            // То что я задокументировал - работает мегакриво.
            // Пусть юзер и проскролливает какую-то часть контента, зато не получит
            // эпилепсии при использовании :)
        }, contentPage);
    });
}, {
    root: null, // screen
    rootMargin: "0px",
    threshold: 0
});

let smol = document.querySelector('div[class$="_small_block"]');
if(smol != null)
    smallBlockObserver.observe(smol);