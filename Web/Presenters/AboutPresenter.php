<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Themes\Themepacks;
use openvk\Web\Models\Repositories\{Users, Managers, Clubs, Posts};
use openvk\Web\Util\Localizator;
use Chandler\Session\Session;

final class AboutPresenter extends OpenVKPresenter
{
    protected $banTolerant = true;
    protected $activationTolerant = true;
    protected $deactivationTolerant = true;
    
    function renderIndex(): void
    {
        if(!is_null($this->user)) {
            if($this->user->identity->getMainPage())
                $this->redirect("/feed");
            else
                $this->redirect($this->user->identity->getURL());
        }
        
        if($_SERVER['REQUEST_URI'] == "/id0") {
            $this->redirect("/");
        }
        
        $this->template->stats = (new Users)->getStatistics();
    }
    
    function renderRules(): void
    {
        $this->pass("openvk!Support->knowledgeBaseArticle", "rules");
    }
    
    function renderHelp(): void
    {}
    
    function renderBB(): void
    {}

    function renderTour(): void
    {}
    
    function renderInvite(): void
    {
        $this->assertUserLoggedIn();
    }
    
    function renderDonate(): void
    {
        $this->pass("openvk!Support->knowledgeBaseArticle", "donate");
    }
    
    function renderPrivacy(): void
    {
        $this->pass("openvk!Support->knowledgeBaseArticle", "privacy");
    }
    
    function renderVersion(): void
    {
        $this->template->themes = Themepacks::i()->getAllThemes();
        $this->template->languages = getLanguages();
    }

    function renderAboutInstance(): void
    {
        $this->template->usersStats   = (new Users)->getStatistics();
        $this->template->clubsCount   = (new Clubs)->getCount();
        $this->template->postsCount   = (new Posts)->getCount();
        $this->template->popularClubs = [];
        $this->template->admins       = iterator_to_array((new Users)->getInstanceAdmins());
    }
    
    function renderLanguage(): void
    {
        $this->template->languages = getLanguages();
        
        if(!is_null($_GET['lg'])){
            $this->assertNoCSRF();
            setLanguage($_GET['lg']);
        }

        if(!is_null($_GET['jReturnTo']))
            $this->redirect(rawurldecode($_GET['jReturnTo']));
    }

    function renderExportJSLanguage($lg = NULL): void
    {
        $localizer = Localizator::i();
        $lang      = $lg;
        if(is_null($lg))
            $this->throwError(404, "Not found", "Language is not found");
        header("Content-Type: application/javascript");
        echo "window.lang = " . json_encode($localizer->export($lang)) . ";"; # привет хардкод :DDD
        exit;
    }

    function renderSandbox(): void
    {
        $this->template->languages = getLanguages();
    }

    function renderRobotsTxt(): void
    {
        $text = "# robots.txt file for openvk\n"
        . "#\n"
        . "# this includes only those links that are not in any way\n"
        . "# covered from unauthorized persons (for example, due to\n"
        . "# lack of rights to access the admin panel)\n\n"
        . "User-Agent: *\n"
        . "Disallow: /albums/create\n"
        . "Disallow: /assets/packages/static/openvk/img/banned.jpg\n"   
        . "Disallow: /assets/packages/static/openvk/img/camera_200.png\n"   
        . "Disallow: /assets/packages/static/openvk/img/flags/\n"   
        . "Disallow: /assets/packages/static/openvk/img/oof.apng\n"  
        . "Disallow: /videos/upload\n"
        . "Disallow: /invite\n"
        . "Disallow: /groups_create\n"
        . "Disallow: /notifications\n"
        . "Disallow: /settings\n"
        . "Disallow: /edit\n"
        . "Disallow: /gifts\n"
        . "Disallow: /support\n"
        . "Disallow: /rpc\n"
        . "Disallow: /language\n"
        . "Disallow: /badbrowser.php\n"
        . "Disallow: /logout\n"
        . "Disallow: /away.php\n"
        . "Disallow: /im?\n"
        . "Disallow: *query=\n"
        . "Disallow: *?lg=\n"
        . "Disallow: *hash=\n"
        . "Disallow: *?jReturnTo=\n"
        . "Disallow: /method/*\n"
        . "Disallow: /token*";
        header("Content-Type: text/plain");
        exit($text);
    }

    function renderHumansTxt(): void
    {
        # :D
        $this->redirect("https://github.com/openvk/openvk#readme");
    }

    function renderDev(): void
    {
        $this->redirect("https://docs.openvk.uk/");
    }
}
