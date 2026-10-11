<?php if ((int) $quote->quote_status_id === 5 && trim((string) ($quote->quote_rejection_reason ?? '')) !== '') { ?>
    <div class="alert alert-warning">
        <strong><?php echo htmlsc(trans('quote_rejection_reason')); ?></strong>
        <div style="overflow-wrap: anywhere;"><?php echo nl2br(htmlsc($quote->quote_rejection_reason)); ?></div>
    </div>
<?php } ?>
