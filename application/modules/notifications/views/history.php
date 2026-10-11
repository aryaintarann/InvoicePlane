<div class="panel panel-default">
    <div class="panel-heading"><?php echo html_escape(trans('whatsapp_history')); ?></div>
    <div class="panel-body">
        <p><?php echo html_escape(trans('whatsapp_provider_help')); ?></p>
        <p><?php echo html_escape(trans('whatsapp_retry_help')); ?></p>
        <?php if (empty($notifications)) { ?>
            <p><?php echo html_escape(trans('whatsapp_history_empty')); ?></p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead><tr>
                        <?php foreach (['date', 'type', 'mobile_number', 'status', 'whatsapp_attempts', 'whatsapp_provider_id', 'whatsapp_reason', 'options'] as $label) { ?>
                            <th scope="col"><?php echo html_escape(trans($label)); ?></th>
                        <?php } ?>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($notifications as $notification) { ?>
                            <tr>
                                <td><?php echo html_escape($notification->created_at); ?> UTC</td>
                                <td><?php echo html_escape(trans('whatsapp_' . $notification->event_type)); ?></td>
                                <td><?php echo html_escape($notification->recipient ?? ''); ?></td>
                                <td><?php echo html_escape(trans('whatsapp_status_' . $notification->status)); ?></td>
                                <td><?php echo html_escape($notification->total_attempts); ?></td>
                                <td><?php echo html_escape($notification->provider_id ?? ''); ?></td>
                                <td><?php echo html_escape($notification->failure_reason ? trans('whatsapp_reason_' . $notification->failure_reason) : ''); ?></td>
                                <td>
                                    <?php if (in_array($notification->status, ['failed', 'uncertain', 'skipped'], true)) { ?>
                                        <form method="post" action="<?php echo html_escape(site_url('notifications/retry/' . (int) $notification->notification_id)); ?>">
                                            <?php _csrf_field(); ?>
                                            <input type="hidden" name="invoice_id" value="<?php echo html_escape($invoice_id); ?>">
                                            <button type="submit" class="btn btn-default btn-sm"><?php echo html_escape(trans('whatsapp_retry')); ?></button>
                                        </form>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>
