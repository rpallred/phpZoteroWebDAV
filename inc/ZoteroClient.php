<?php

/**
 * Minimal Zotero Web API v3 (JSON) client.
 *
 * Replaces the old libZotero.php, which parsed Zotero's now-retired XML/Atom API
 * (DOMDocument + getElementsByTagName('feed'/'entry'/'content')). Zotero has served
 * plain JSON with a data/meta/links envelope, and no XML wrapper at all, for years.
 *
 * Keeps the same class name and public method signatures the old library had, so
 * index.php/details.php only needed their require_once line changed, not their logic.
 * Only implements what those two files actually call.
 */
class Zotero_Library {

    const API_BASE = 'https://api.zotero.org';

    private $libraryType;
    private $libraryID;
    private $librarySlug;
    private $apiKey;
    private $lastFeed;

    public function __construct($libraryType, $libraryID, $librarySlug, $apiKey) {
        $this->libraryType = $libraryType;
        $this->libraryID   = $libraryID;
        $this->librarySlug = $librarySlug;
        $this->apiKey      = $apiKey;
        $this->lastFeed    = (object) array('totalResults' => 0, 'links' => array());
    }

    // No-op: APC (the old cache backend) has been dead for years. Kept only so the
    // existing isset($apc_cache_ttl) && $apc_cache_ttl guarded calls in index.php/
    // details.php remain harmless without needing to touch those files.
    public function setCacheTtl($ttl) {
    }

    public function getLastFeed() {
        return $this->lastFeed;
    }

    public function fetchCollections($params = array()) {
        // Old behavior: always returns the FULL flat list of collections in the
        // library (both top-level and nested), regardless of any collectionKey
        // passed in — callers filter by ->parentCollectionKey themselves.
        $collections = array();
        $url = $this->buildUrl('/collections', array('limit' => 100));

        while ($url) {
            $body = $this->request($url);
            foreach ($body as $entry) {
                $collections[] = $this->mapCollection($entry);
            }
            $url = isset($this->lastFeed->links['next']) ? $this->lastFeed->links['next']['href'] : null;
        }

        return $collections;
    }

    public function fetchItemsTop($params = array()) {
        $collectionKey = !empty($params['collectionKey']) ? $params['collectionKey'] : false;
        $path = $collectionKey
            ? '/collections/' . $collectionKey . '/items/top'
            : '/items/top';

        $query = array();
        // Old param names (kept as index.php/details.php still pass them): 'order' held
        // the sort field, 'sort' held the direction. Current API's own param names are
        // 'sort' (field) and 'direction'.
        if (isset($params['order'])) $query['sort']      = $params['order'];
        if (isset($params['sort']))  $query['direction'] = $params['sort'];
        if (isset($params['limit'])) $query['limit']     = $params['limit'];
        if (isset($params['start'])) $query['start']     = $params['start'];
        // Title/creator/year search. The API's "titleCreatorYear" qmode (the default
        // when q is set) is the closest match to a title filter without excluding
        // reasonable near-misses like a creator's name.
        if (!empty($params['q'])) $query['q'] = $params['q'];

        $body = $this->request($this->buildUrl($path, $query));

        $items = array();
        foreach ($body as $entry) {
            $items[] = $this->mapItem($entry);
        }
        return $items;
    }

    public function fetchItem($itemKey) {
        $entry = $this->request($this->buildUrl('/items/' . $itemKey));
        return $this->mapItem($entry);
    }

    public function fetchItemChildren($item) {
        $body = $this->request($this->buildUrl('/items/' . $item->itemKey . '/children'));

        $children = array();
        foreach ($body as $entry) {
            $children[] = $this->mapItem($entry);
        }
        return $children;
    }

    private function libraryBase() {
        return self::API_BASE . '/' . $this->libraryType . 's/' . $this->libraryID;
    }

    private function buildUrl($path, array $query = array()) {
        if (empty($query)) {
            return $this->libraryBase() . $path;
        }
        return $this->libraryBase() . $path . '?' . http_build_query($query);
    }

    // Accepts either a path built by buildUrl() or an already-absolute "next" link
    // pulled from a previous response's Link header.
    private function request($url) {
        $ch = curl_init($url);
        // A blank Authorization header is rejected outright (400 "Invalid Authorization
        // header format") — only send it when a key is actually configured. Public
        // libraries work fine with no Authorization header at all.
        $headers = array('Zotero-API-Version: 3');
        if ($this->apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            throw new Exception("Error fetching $url: $error");
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $headerText = substr($response, 0, $headerSize);
        $body       = substr($response, $headerSize);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("Error fetching $url: HTTP $httpCode");
        }

        $totalResults = 0;
        $links = array();
        foreach (preg_split('/\r\n/', $headerText) as $headerLine) {
            if (stripos($headerLine, 'Total-Results:') === 0) {
                $totalResults = (int) trim(substr($headerLine, strlen('Total-Results:')));
            } elseif (stripos($headerLine, 'Link:') === 0) {
                $linkValue = trim(substr($headerLine, strlen('Link:')));
                if (preg_match_all('/<([^>]+)>\s*;\s*rel="([a-zA-Z]+)"/', $linkValue, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $links[$match[2]] = array('href' => $match[1]);
                    }
                }
            }
        }

        $this->lastFeed = (object) array(
            'totalResults' => $totalResults,
            'links'        => $links,
        );

        $decoded = json_decode($body, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error decoding API response from $url");
        }

        return $decoded;
    }

    private function mapItem($entry) {
        $data = isset($entry['data']) ? $entry['data'] : array();
        $meta = isset($entry['meta']) ? $entry['meta'] : array();

        $item = new stdClass();
        $item->itemKey        = isset($data['key']) ? $data['key'] : '';
        $item->numChildren    = isset($meta['numChildren']) ? (int) $meta['numChildren'] : 0;
        $item->dateAdded      = isset($data['dateAdded']) ? $data['dateAdded'] : '';
        $item->creatorSummary = isset($meta['creatorSummary']) ? $meta['creatorSummary'] : '';
        $item->title          = isset($data['title']) ? $data['title'] : '';
        $item->apiObject      = $data;

        return $item;
    }

    private function mapCollection($entry) {
        $data = isset($entry['data']) ? $entry['data'] : array();
        $meta = isset($entry['meta']) ? $entry['meta'] : array();

        $collection = new stdClass();
        $collection->collectionKey       = isset($data['key']) ? $data['key'] : '';
        $collection->name                = isset($data['name']) ? $data['name'] : '';
        $collection->numItems            = isset($meta['numItems']) ? (int) $meta['numItems'] : 0;
        $collection->parentCollectionKey = isset($data['parentCollection']) ? $data['parentCollection'] : false;

        return $collection;
    }
}
