<?php
/**
 * @var array $siteOption
 * @var array $roles
 * @var array $attestationTypes
 */

use UNSProjectApp\UnsApp;
use UNSProjectApp\UnsWordPressAPI;

?>
<button class="btn btn-primary" id="save_attestation">Save</button>

<div id="unsproject-connection_status" class="connection_status">
    <div class="custom-container top-round">
        <h2>Site Settings</h2>
        <div class="row">
            <label>Email</label>
            <input type="text" readonly="readonly" class="form-control"
                   value="<?php echo(isset($siteOption['email']) ? esc_html($siteOption['email']) : ''); ?>"/>
        </div>

        <div class="row">
            <label>Phone Number</label>
            <input type="text" readonly="readonly" class="form-control"
                   value="<?php echo(isset($siteOption['phoneNumber']) ? esc_html($siteOption['phoneNumber']) : ''); ?>"/>
        </div>

        <div class="row">
            <label>Contact Name</label>
            <input type="text" readonly="readonly" class="form-control"
                   value="<?php echo(isset($siteOption['contactName']) ? esc_html($siteOption['contactName']) : ''); ?>"/>
        </div>

        <div class="row">
            <label>Public Key</label>
            <textarea class="form-control"
                      readonly="readonly"><?php echo(isset($siteOption['public_key']) ? esc_html(base64_decode($siteOption['public_key'])) : ''); ?></textarea>
        </div>

        <div class="row">
            <label>Private Key</label>
            <textarea class="form-control"
                      readonly="readonly"><?php echo(isset($siteOption['private_key']) ? esc_html(base64_decode($siteOption['private_key'])) : ''); ?></textarea>
        </div>

        <div class="row">
            <label>Site URL</label>
            <input type="text" readonly="readonly" class="form-control"
                   value="<?php echo(isset($siteOption['site_url']) ? esc_html($siteOption['site_url']) : ''); ?>"/>
        </div>

        <div class="row">
            <label>Unique ID</label>
            <input type="text" readonly="readonly" class="form-control"
                   value="<?php echo(isset($siteOption['uniqueId']) ? esc_html($siteOption['uniqueId']) : ''); ?>"/>
        </div>
    </div>
    <div class="custom-container no-top-border">
        <h2> UNSproject Credentials</h2>
        <div class="row">
            <label>Validation Code</label>
            <input type="text" readonly="readonly" class="form-control"
                   value="<?php echo(isset($siteOption['validationCode']) ? esc_html($siteOption['validationCode']) : ''); ?>"/>
        </div>

        <div class="row">
            <label>Gatekeeper public key</label>
            <textarea class="form-control"
                      readonly="readonly"><?php echo(isset($siteOption['gatekeeperPublicKey']) ? str_replace('\n',"\n",base64_decode($siteOption['gatekeeperPublicKey'])) : ''); ?></textarea>
        </div>
    </div>


    <div class="custom-container bottom-round no-top-border">
        <h2>Default Attestations</h2>
        <form method="POST" id="default_attestation">
            <input type="hidden" name="action" value="update_attestation"/>
            <?php
            foreach ($attestationTypes as $key => $label) {
                ?>
                <div class="uns-checkbox-container">
                    <input type="checkbox" name="default_attestation_type"
                           class="single-checkbox"
                           id="attestation_<?php echo $key; ?>"
                           value="<?php echo $key; ?>"
                        <?php
                        echo(
                        isset($siteOption['default_attestation_type']) && $siteOption['default_attestation_type'] === $key
                        || !isset($siteOption['default_attestation_type']) && $key === UnsApp::DEFAULT_ATTESTATION_TYPE
                            ? 'checked="checked"'
                            : ''
                        ) ?>
                    />
                    <label for="attestation_<?php echo $key; ?>">
                        <?php echo $label; ?>
                    </label>
                </div>

                <?php
            }
            ?>
            <h2>Check authorization interval</h2>
            <input
                    type="text"
                    name="authorization_interval"
                    value="<?php echo isset($siteOption['authorization_interval']) ? (int)$siteOption['authorization_interval'] : UnsWordPressAPI::DEFAULT_AUTHORIZATION_INTERVAL ?>"
            /> milliseconds
            <br/>
            <br/>
            <button class="btn btn-primary">Save</button>
        </form>
    </div>
</div>
