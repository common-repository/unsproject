<?php

use UNSProjectApp\UnsApp;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
?>
<div class="reset_plugin_data_container">
    <form method="POST">
        <input type="hidden" name="clear" value="<?php UnsApp::CLEAR_PLUGIN_DATA_CODE;?>"/>
        <input type="submit" value="Clear Plugin data" class="btn btn-primary"/>
    </form>
</div>