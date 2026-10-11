<?php
require_once APPPATH . 'modules/notifications/libraries/Whatsapp_policy.php';
$switches = [
    'whatsapp_issued_enabled'     => 'whatsapp_issued',
    'whatsapp_reminder_enabled'   => 'whatsapp_reminder',
    'whatsapp_payment_enabled'    => 'whatsapp_payment',
    'whatsapp_automatic_on_recur' => 'whatsapp_automatic_on_recur',
];
$fields = [
    'whatsapp_reminder_days' => 'whatsapp_reminder_days',
    'whatsapp_send_time'     => 'whatsapp_send_time',
    'whatsapp_timezone'      => 'whatsapp_timezone',
];
?>
<div class="row">
    <div class="col-xs-12 col-md-8 col-md-offset-2">
        <div class="panel panel-default">
            <div class="panel-heading">WhatsApp (Fonnte)</div>
            <div class="panel-body">
                <p><?php echo html_escape(trans('whatsapp_setup_help')); ?></p>
                <?php foreach ($switches as $key => $label) { ?>
                    <div class="form-group">
                        <label for="<?php echo html_escape($key); ?>"><?php echo html_escape(trans($label)); ?></label>
                        <select class="form-control" id="<?php echo html_escape($key); ?>" name="settings[<?php echo html_escape($key); ?>]">
                            <?php foreach (['0' => 'no', '1' => 'yes'] as $value => $text) { ?>
                                <option value="<?php echo html_escape($value); ?>" <?php echo (string) get_setting($key, '0') === (string) $value ? 'selected' : ''; ?>><?php echo html_escape(trans($text)); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>
                <p class="help-block"><?php echo html_escape(trans('whatsapp_recur_help')); ?></p>
                <?php foreach ($fields as $key => $label) { ?>
                    <div class="form-group">
                        <label for="<?php echo html_escape($key); ?>"><?php echo html_escape(trans($label)); ?></label>
                        <input class="form-control" type="<?php echo $key === 'whatsapp_send_time' ? 'time' : 'text'; ?>"
                               id="<?php echo html_escape($key); ?>" name="settings[<?php echo html_escape($key); ?>]"
                               value="<?php echo html_escape(get_setting($key, Whatsapp_policy::DEFAULTS[$key])); ?>" required>
                    </div>
                <?php } ?>
                <p class="help-block"><?php echo html_escape(trans('whatsapp_schedule_help')); ?></p>
                <p><?php echo html_escape(trans('whatsapp_placeholders_help')); ?></p>
                <?php foreach (['issued', 'reminder', 'payment'] as $type) {
                    $key = 'whatsapp_' . $type . '_template'; ?>
                    <div class="form-group">
                        <label for="<?php echo html_escape($key); ?>"><?php echo html_escape(trans('whatsapp_' . $type . '_template')); ?></label>
                        <textarea class="form-control" rows="4" maxlength="4000" id="<?php echo html_escape($key); ?>" name="settings[<?php echo html_escape($key); ?>]" required><?php echo html_escape(get_setting($key, Whatsapp_policy::DEFAULTS[$key])); ?></textarea>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
