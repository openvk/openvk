<?php declare(strict_types=1);

namespace openvk\Web\Presenters;

use openvk\Web\Models\Repositories\{Gifts, Users};
use openvk\Web\Models\Entities\Notifications\GiftNotification;

final class ImagesProxyPresenter extends OpenVKPresenter
{
    const CACHE_EXPIRATION = 1210000;

    private function image(string $contentType, $contentSize, string $raw): void
    {
        header("Content-Type: $contentType");
        header("Content-Size: $contentSize");
        header("Cache-Control: public, max-age=" . self::CACHE_EXPIRATION);
        header("X-Accel-Expires: " . self::CACHE_EXPIRATION);
        exit($raw);
    }

    private function placeholder(): void
    {
        $placeholder = file_get_contents(__DIR__ . "/../static/img/oof.apng");
        $this->image("image/png", strlen($placeholder), $placeholder);
    }

    public function renderIndex(): void
    {
        $this->assertUserLoggedIn();

        $url = $this->requestParam("url");
        if (OPENVK_ROOT_CONF["openvk"]["preferences"]["imagesProxy"]["settings"]["base64_decode_url"]) {
            $url = base64_decode($url);
        }

        $url = OPENVK_ROOT_CONF["openvk"]["preferences"]["imagesProxy"]["settings"]["url_prefix"] . $url;
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->placeholder();
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HEADER => 0,
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => OPENVK_ROOT_CONF["openvk"]["appearance"]["name"] . ' Images Proxy/1.0',
            CURLOPT_REFERER => "https://$_SERVER[SERVER_NAME]/",
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_BINARYTRANSFER => 1,
        ]);

        $raw = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $contentSize = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

        curl_close($ch);

        if ($raw && str_contains($contentType, "image")) {
            $this->image($contentType, $contentSize, $raw);
        } else {
            $this->placeholder();
        }
    }
}
