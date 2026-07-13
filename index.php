<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

require_once 'settings.php';
require_once 'inc/include.php';
require_once 'inc/ZoteroClient.php';

try {
	$zotero = new Zotero_Library( 'user', $user_ID, $user_name, $API_key );
} catch( Exception $e ) {
	include_once 'inc/header.php';
	die( 'Error activating Zotero client: ' . $e->getMessage() );
}

if( isset( $apc_cache_ttl ) && $apc_cache_ttl )
	$zotero->setCacheTtl( $apc_cache_ttl );

$ipp  = isset($_REQUEST['ipp']) ? (int)$_REQUEST['ipp'] : $def_ipp;
$sort = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : $def_sort;
$sortorder = isset($_REQUEST['sortorder']) ? $_REQUEST['sortorder'] : $def_sortorder;
$page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;
$collectionKey = isset( $_REQUEST['collection'] ) ? $_REQUEST['collection'] : false;
$q = isset( $_REQUEST['q'] ) ? trim( $_REQUEST['q'] ) : '';

$webdav_url=( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . "/webdav_server.php/zotero/";

$search_q  = $q;
$meta_html = 'Attachments: <strong>' . format_size( foldersize( get_real_path( $data_dir ) ) ) . '</strong>'
           . ' &nbsp;&middot;&nbsp; WebDAV: <a href="' . htmlspecialchars( $webdav_url ) . '">' . htmlspecialchars( $webdav_url ) . '</a>';

include_once 'inc/header.php';
include 'inc/topbar.php';

//purge old files from the cache
purge_cache( get_real_path( $cache_dir ), $cache_age );

// get first set of items from API
$start = ($page - 1) * $ipp;
if ($ipp > $fetchlimit) $limit = $fetchlimit; else $limit = $ipp;

// Include collections on index page for traversal
$collections = $zotero->fetchCollections( array( 'collectionKey' => $collectionKey ) );

$fetch_params = array(
    'order'         => $sort,
    'sort'          => $sortorder,
    'limit'         => $limit,
    'collectionKey' => $collectionKey,
    'q'             => $q,
);

$fetch_offset = $start;
$items = array();

do {

    $fetched = $zotero->fetchItemsTop( array_merge( $fetch_params, array( 'start' => $fetch_offset ) ) );

    $items = array_merge( $items, $fetched );
    $fetch_offset += count( $items );

} while( count( $items ) < $ipp && count( $fetched ) >= $fetchlimit );

$totalitems = $zotero->getLastFeed()->totalResults;

// param strings reused when building links below
$param_ipp        = ($ipp == $def_ipp) ? "" : "&ipp=$ipp";
$param_sort       = ($sort == $def_sort) ? "" : "&sort=$sort";
$param_sortorder  = ($sortorder == $def_sortorder) ? "" : "&sortorder=$sortorder";
$param_collection = ( $collectionKey ) ? "&collection=" . urlencode($collectionKey) : '';
$param_q          = ( $q !== '' ) ? "&q=" . urlencode($q) : '';
$carry_params     = $param_ipp . $param_sort . $param_sortorder . $param_collection . $param_q;

?>
<div class="page">

    <div class="sidebar">
        <h2>Collections</h2>
        <ul class="collection-list">
            <?php if ( $collectionKey ) : ?>
                <li><a href="index.php">&larr; All Items</a></li>
            <?php endif; ?>
            <?php
            $any = false;
            foreach ( $collections as $collection ) :
                if ( $collection->parentCollectionKey != $collectionKey ) continue;
                $any = true;
            ?>
                <li>
                    <a href="?collection=<?php echo urlencode($collection->collectionKey) ?>"
                       class="<?php echo ( $collectionKey === $collection->collectionKey ) ? 'current' : '' ?>">
                        <span><?php echo htmlspecialchars($collection->name) ?></span>
                        <span class="count"><?php echo (int) $collection->numItems ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if ( ! $any ) : ?>
                <li class="empty">No sub-collections</li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="main">
        <div class="panel">
            <div class="panel-header">
                <h2>
                    <?php
                    if ( $q !== '' ) {
                        echo 'Search results for &ldquo;' . htmlspecialchars($q) . '&rdquo;';
                    } elseif ( $collectionKey ) {
                        echo 'Items in this Collection';
                    } else {
                        echo 'All Items';
                    }
                    ?>
                </h2>
                <span class="count"><?php echo (int) $totalitems ?> total</span>
            </div>

            <?php if ( count( $items ) === 0 ) : ?>
                <div class="empty-state">No items found.</div>
            <?php else : ?>
            <table class="items">
                <thead>
                <tr>
                    <th class="col-attachments">Files</th>
                    <th class="col-added">Added</th>
                    <th class="col-creator">Creator</th>
                    <th class="col-date">Date</th>
                    <th>Title</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ( $items as $item ) : ?>
                    <tr>
                        <td class="col-attachments"><a href="details.php?itemkey=<?php echo urlencode($item->itemKey) ?>"><?php echo (int) $item->numChildren ?></a></td>
                        <td class="col-added"><a href="details.php?itemkey=<?php echo urlencode($item->itemKey) ?>"><?php echo format_date( $item->dateAdded ) ?></a></td>
                        <td class="col-creator"><a href="details.php?itemkey=<?php echo urlencode($item->itemKey) ?>"><?php echo htmlspecialchars($item->creatorSummary) ?></a></td>
                        <td class="col-date"><a href="details.php?itemkey=<?php echo urlencode($item->itemKey) ?>"><?php echo htmlspecialchars((string) ($item->apiObject['date'] ?? '')) ?></a></td>
                        <td><a href="details.php?itemkey=<?php echo urlencode($item->itemKey) ?>"><?php echo htmlspecialchars($item->title) ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <form class="controls" method="get" action="">
            <?php if ( $collectionKey ) : ?><input type="hidden" name="collection" value="<?php echo htmlspecialchars($collectionKey) ?>" /><?php endif; ?>
            <?php if ( $q !== '' ) : ?><input type="hidden" name="q" value="<?php echo htmlspecialchars($q) ?>" /><?php endif; ?>
            <input type="hidden" name="page" value="<?php echo (int) $page ?>" />

            <div class="group">
                <strong>Sort by</strong>
                <select name="sort">
                <?php
                $s_list = array ("dateAdded" => "Date Added", "title" => "Title", "creator" => "Creator", "itemType" => "Type", "date" => "Date", "publisher" => "Publisher", "publicationTitle" => "Publication", "journalAbbreviation" => "Journal Abbreviation", "language" => "Language", "dateModified" => "Date Modified", "accessDate" => "Access Date", "libraryCatalog" => "Library Catalog", "callNumber" => "Call Number", "rights" => "Rights", "addedBy" => "Added By", "numItems" => "Number of Items");
                foreach ( $s_list as $value => $label ) :
                ?>
                    <option value="<?php echo $value ?>" <?php echo ($sort === $value) ? 'selected' : '' ?>><?php echo $label ?></option>
                <?php endforeach; ?>
                </select>
            </div>

            <div class="group">
                <strong>Direction</strong>
                <select name="sortorder">
                    <option value="desc" <?php echo ($sortorder === 'desc') ? 'selected' : '' ?>>Descending</option>
                    <option value="asc" <?php echo ($sortorder === 'asc') ? 'selected' : '' ?>>Ascending</option>
                </select>
            </div>

            <div class="group">
                <strong>Per page</strong>
                <select name="ipp">
                <?php foreach ( array(10, 20, 50, 100, 200, 500) as $n ) : ?>
                    <option value="<?php echo $n ?>" <?php echo ($ipp == $n) ? 'selected' : '' ?>><?php echo $n ?></option>
                <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn">Apply</button>
        </form>

        <?php
        $pages = max( 1, (int) ceil( $totalitems / max($ipp, 1) ) );
        if ( $pages > 1 ) :
            // Show first 2, last 2, and a window around the current page; collapse the rest with "...".
            $page_numbers = array();
            for ( $i = 1; $i <= $pages; $i++ ) {
                if ( $i <= 2 || $i > $pages - 2 || abs( $i - $page ) <= 2 ) {
                    $page_numbers[] = $i;
                }
            }
        ?>
        <div class="controls">
            <div class="group">
                <strong>Page</strong>
                <?php $prev = 0; foreach ( $page_numbers as $i ) : ?>
                    <?php if ( $prev && $i - $prev > 1 ) : ?><span>&hellip;</span><?php endif; ?>
                    <?php if ( $i === $page ) : ?>
                        <span class="pill current"><?php echo $i ?></span>
                    <?php else : ?>
                        <a class="pill" href="?page=<?php echo $i ?><?php echo $carry_params ?>"><?php echo $i ?></a>
                    <?php endif; ?>
                    <?php $prev = $i; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include 'inc/footer.php'; ?>
</body>
</html>
