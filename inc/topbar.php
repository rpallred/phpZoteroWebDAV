<?php
// Expects (optionally, set by the including page before requiring this file):
//   $search_q   - current search box value
//   $meta_html  - extra HTML shown at the right of the bar (e.g. storage size / WebDAV URL)
if (!isset($search_q))  $search_q = '';
if (!isset($meta_html)) $meta_html = '';
?>
<div class="topbar">
    <div class="topbar-inner">
        <h1><a href="index.php">phpZoteroWebDAV</a></h1>
        <form class="search-form" action="index.php" method="get">
            <input type="text" name="q" value="<?php echo htmlspecialchars($search_q) ?>" placeholder="Search by title, creator, year&hellip;" />
            <button type="submit">Search</button>
        </form>
        <?php if ($meta_html !== '') : ?>
        <div class="meta-line"><?php echo $meta_html ?></div>
        <?php endif; ?>
    </div>
</div>
