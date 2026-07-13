<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

require_once 'settings.php';
require_once 'inc/include.php';
require_once 'inc/ZoteroClient.php';
include_once 'inc/header.php';
include 'inc/topbar.php';

$zotero = new Zotero_Library( 'user', $user_ID, $user_name, $API_key );
$itemkey = $_REQUEST['itemkey'];

if( isset( $apc_cache_ttl ) && $apc_cache_ttl )
    $zotero->setCacheTtl( $apc_cache_ttl );

//purge old files from the cache
purge_cache( get_real_path( $cache_dir ), $cache_age);

// reading item details from API
$item = $zotero->fetchItem( $itemkey );

// Render a single item-detail field value into safe HTML.
function render_field_value( $field_name, $field ) {
    switch ( $field_name ) {
        case 'itemType':
            return htmlspecialchars( un_camel( $field ) );

        case 'tags':
            return implode( ', ', array_map(
                fn($val) => htmlspecialchars( $val['tag'] ?? '' ),
                $field
            ) );

        case 'creators':
            return implode( '<br />', array_map( function ( $val ) {
                $name = $val['name'] ?? trim( ( $val['firstName'] ?? '' ) . ' ' . ( $val['lastName'] ?? '' ) );
                return htmlspecialchars( un_camel( $val['creatorType'] ?? '' ) . ': ' . $name );
            }, $field ) );

        default:
            if ( is_array( $field ) ) {
                return implode( ', ', array_map( 'htmlspecialchars', array_filter( $field, 'is_scalar' ) ) );
            }
            return htmlspecialchars( (string) $field );
    }
}
?>
<div class="page single-column">
    <div class="main">
        <div class="breadcrumb"><a href="index.php">&larr; Back to Library</a></div>

        <div class="panel">
            <div class="panel-header"><h2>Item Details</h2></div>
            <dl class="fields">
                <?php foreach ( $item->apiObject as $field_name => $field ) : ?>
                    <dt><?php echo htmlspecialchars( un_camel( $field_name ) ) ?></dt>
                    <dd><?php echo render_field_value( $field_name, $field ) ?></dd>
                <?php endforeach; ?>
            </dl>
        </div>

        <div class="panel" style="margin-top: 20px;">
            <div class="panel-header"><h2>Attachments</h2><span class="count"><?php echo (int) $item->numChildren ?></span></div>
            <?php
            $child_items = $zotero->fetchItemChildren( $item );
            if ( ! $child_items ) :
            ?>
                <div class="empty-state">No attachments.</div>
            <?php else : foreach ( $child_items as $child_item ) : ?>
                <div class="attachment-card">
                    <h3><?php echo htmlspecialchars( $child_item->apiObject['title'] ?? '(untitled)' ) ?></h3>
                    <dl class="fields">
                        <?php foreach ( $child_item->apiObject as $field_name => $field ) : ?>
                            <dt><?php echo htmlspecialchars( un_camel( $field_name ) ) ?></dt>
                            <dd><?php echo render_field_value( $field_name, $field ) ?></dd>
                        <?php endforeach; ?>
                    </dl>
                    <div class="link">
                        <?php if ( in_array( $child_item->apiObject['linkMode'] ?? '', array( 'linked_file', 'linked_url' ) ) ) : ?>
                            <a href="<?php echo htmlspecialchars( $child_item->apiObject['url'] ?? '' ) ?>"><?php echo htmlspecialchars( $child_item->apiObject['url'] ?? '' ) ?></a>
                        <?php else : ?>
                            <a class="btn" href="attachment.php?itemkey=<?php echo urlencode( $child_item->itemKey ) ?>&mime=<?php echo urlencode( $child_item->apiObject['contentType'] ?? '' ) ?>">Open Attachment</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
<?php include 'inc/footer.php'; ?>
</body>
</html>
