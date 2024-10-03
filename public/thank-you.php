<div class="res-container res-thank-you">
	<?php if ($error): ?>
		<div class="res-message res-error">
			<p><?=$error?></p>
		</div>
	<?php else: ?>
		<div class="res-message res-success">
			<p><?php _e('Thank You!', 'reservations');?></p>
		</div>

		<?php if ($paid): ?>
			<h3 class="res-status">
				<?php _e('Your payment has been completed successfully.', 'reservations');?><br>
				<?=sprintf(__('We\'ve sent additional information to your email %s.', 'reservations'), '<strong>' . $subscriber->contact_email . '</strong>');?>
			</h3>

			<?php if ($formUrl): ?>
				<div class="res-center">
					<a href="<?=esc_attr($formUrl)?>" class="btn btn-primary btn-lg res-download"><?php _e('Download Application Form', 'reservations');?></a>
				</div>
			<?php endif;?>
		<?php elseif ($replacement): ?>
			<h3 class="res-status"><?php _e('You have been registered as replacement.<br> We\'ll notify you on your email when the gym becomes available.', 'reservations');?></h3>
		<?php else: ?>
			<h3 class="res-status"><?php _e('Your payment is being processed.<br> We\'ll notify you on your email when it\'s finished.', 'reservations');?></h3>
		<?php endif;?>
	<?php endif;?>
</div>
