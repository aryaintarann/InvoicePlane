<section id="quote-rejection" class="panel panel-default">
    <div class="panel-heading"><?php echo htmlsc(trans('reject_this_quote')); ?></div>
    <div class="panel-body">
        <form method="post" action="<?php echo htmlsc($rejection_action); ?>">
            <?php _csrf_field(); ?>
            <div class="form-group">
                <label for="quote_rejection_reason"><?php echo htmlsc(trans('quote_rejection_reason')); ?></label>
                <textarea name="quote_rejection_reason" id="quote_rejection_reason" class="form-control" rows="3"
                          maxlength="2000" aria-describedby="quote-rejection-help"></textarea>
                <span id="quote-rejection-help" class="help-block"><?php echo htmlsc(trans('quote_rejection_reason_help')); ?></span>
            </div>
            <button type="submit" class="btn btn-default"><?php echo htmlsc(trans('reject_this_quote')); ?></button>
        </form>
    </div>
</section>
