<?php
// Disposable test fixture only; never distributed with qTrad.
add_filter('use_block_editor_for_post', function($use) { return isset($_GET['qtrad_classic']) ? false : $use; });
add_action('edit_form_top', function() { if(isset($_GET['qtrad_classic'])) echo '<input type="hidden" name="qtrad_classic" value="1" />'; });
add_filter('redirect_post_location', function($url) { return isset($_POST['qtrad_classic']) ? add_query_arg('qtrad_classic',1,$url) : $url; });
add_action('init', function() { register_post_type('qtrad_book', array('public'=>true,'show_in_rest'=>true,'rest_base'=>'books','supports'=>array('title','editor','excerpt'))); });
