<?php
// Disposable test fixture only; never distributed with qTrad.
add_filter('use_block_editor_for_post', function($use) { return isset($_GET['qtn_classic']) ? false : $use; });
add_action('edit_form_top', function() { if(isset($_GET['qtn_classic'])) echo '<input type="hidden" name="qtn_classic" value="1" />'; });
add_filter('redirect_post_location', function($url) { return isset($_POST['qtn_classic']) ? add_query_arg('qtn_classic',1,$url) : $url; });
add_action('init', function() { register_post_type('qtn_book', array('public'=>true,'show_in_rest'=>true,'rest_base'=>'books','supports'=>array('title','editor','excerpt'))); });
