<?php

namespace TheiNaD\DSMediatheken\Mediatheken;

use TheiNaD\DSMediatheken\Utils\Mediathek;
use TheiNaD\DSMediatheken\Utils\Result;

/**
 * @author Daniel Gehn <me@theinad.com>
 * @copyright 2017-2019 Daniel Gehn
 * @license http://opensource.org/licenses/MIT Licensed under MIT License
 */
class WDR extends Mediathek
{

    protected static $SUPPORT_MATCHER = ['wdr.de/mediathek', 'one.ard.de/mediathek'];

    public function getDownloadInfo($url, $username = '', $password = '')
    {
        $result = new Result();

        $mediaObjectUrl = $this->getMediaObjectUrl($url);
        if ($mediaObjectUrl === null) {
            return null;
        }

        $mediaObject = $this->getMediaObject($mediaObjectUrl);
        if ($mediaObject === null) {
            return null;
        }

        $bestQualityUrl = $this->getBestQualityUrl($mediaObject);
        if ($bestQualityUrl === null) {
            return null;
        }

        $result->setUri($this->getTools()->addProtocolFromUrlIfMissing($bestQualityUrl, $url));
        $result->setTitle($mediaObject->trackerData->trackerClipSubcategory);
        $result->setEpisodeTitle($mediaObject->trackerData->trackerClipTitle);

        return $result;
    }

    private function getMediaObjectUrl($url)
    {
        $html = $this->getTools()->curlRequestMobile($url);
        $matches =
            $this->getTools()->pregMatchAllDefault('#data-extension=["\']{(.*?)}["\']#i', $html, []);
        foreach ($matches as $match) {
            $fixedMatch = '{' . str_replace("'", '"', $match) . '}';
            $dataExtension = json_decode($fixedMatch);

            if (property_exists($dataExtension->mediaObj, 'url')) {
                return $dataExtension->mediaObj->url;
            }
        }

        return null;
    }

    private function getMediaObject($mediaObjectUrl)
    {
        $html = $this->getTools()->curlRequestMobile($mediaObjectUrl);
        return json_decode($this->getTools()
            ->pregMatchDefault('#\$mediaObject\.jsonpHelper\.storeAndPlay\((.*?)\);#i', $html));
    }

    private function getBestQualityUrl($mediaObject)
    {
        if ($mediaObject->mediaResource->alt->mediaFormat !== 'mp4') {
            return null;
        }
        $altUrl = $mediaObject->mediaResource->alt->videoURL;
        $bestQualityId = $this->getBestQualityId($mediaObject->mediaResource->dflt->videoURL);

        if ($bestQualityId === null) {
            return $altUrl;
        }

        $baseUrl = substr($altUrl, 0, strrpos($altUrl, '/') + 1);
        return $baseUrl . $bestQualityId . '.mp4';
    }

    private function getBestQualityId($videoURL, $index = 2)
    {
        $startIndex = strpos($videoURL, '/,') + 2;
        $endIndex = strrpos($videoURL, ',.mp4');
        $length = $endIndex - $startIndex;
        $qualities = explode(',', substr($videoURL, $startIndex, $length));

        return isset($qualities[$index]) ? $qualities[$index] : null;
    }
}
