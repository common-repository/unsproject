<?php

use UNSProjectApp\Helpers\View;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
/**
 * @var $name string
 * @var $email string
 * @var $phone string
 **/
?>
<h1>Register Site TO UNS Project</h1>
<form method="POSt" class="uns-register-form">
    <input type="hidden" name="action" value="register"/>
    <label for="name">Name <span class="required">*</span></label><br/>
    <input type="text" name="name" id="name" required="required" value="<?php echo esc_html($name); ?>"
           placeholder="Contact Name" autocomplete="off"/>
    <br/>
    <br/>

    <label for="phone">Phone Number <span class="required">*</span></label><br/>
    <input type="text" name="phone" id="phone" required="required" value="<?php echo esc_html($phone); ?>"
           placeholder="Phone number" autocomplete="off"/>
    <br/>
    <br/>
    <label for="email">Email Address <span class="required">*</span></label><br/>
    <input type="text" name="email" id="email" required="required" value="<?php echo esc_html($email); ?>" autocomplete="off"/>
    <br/>
    <br/>
    <input type="checkbox" id="terms" required="required" name="terms" value="1"> <span class="required">*</span>
    <label for="terms" class="terms_of_service">I agree with the UNS API <a href="javascript:void(0)" class="terms_of_service_link">Terms of Service</a></label>
    <br />
    <br />
    <input type="submit" class="uns-button blue-button" value="Submit"/>
</form>

<div class="uns-project-terms-popup" style="display: none;">
    <?php
     echo View::load('terms_of_service.php');
    ?>
</div>