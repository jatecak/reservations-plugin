<div class="res-container res-password-form">
	<?php if ($isEvent): ?>
		<h2 class="res-event-name"><?=esc_html($event->title)?></h2>
	<?php elseif ($isTrainingGroup): ?>
		<h2 class="res-tgroup-name"><?=esc_html($tgroup->name)?></h2>
	<?php endif;?>

	<?php if ($error): ?>
		<ul class="res-errors">
			<li><?=$error?></li>
		</ul>
	<?php endif;?>

	<form method="post">
		<p><?php _e('Subscription form is password protected. Please enter password to continue.', 'reservations');?></p>

		<div class="fields-wrap">
			<label for="pp_password"><?php _e('Password:', 'reservations');?></label>
			<input type="password" name="password" id="pp_password">

			<button type="submit" class="res-button"><?php _e('Submit', 'reservations');?></button>
		</div>
	</form>
</div>
