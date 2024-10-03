<?php if (!$account->user): ?>
	<div class="res-account">
		<?=$nonceField?>

		<h3><?php _e('Want to speed up registration? Log in!', 'reservations');?></h3>

		<?php if ($account->userErrors): ?>
			<ul class="res-errors">
				<?php foreach ($account->userErrors as $error): ?>
					<li><?=$error?></li>
				<?php endforeach;?>
			</ul>
		<?php endif;?>

		<div class="res-field">
			<label><?php _e('Username or Email Address', 'reservations');?></label>
			<input type="text" name="user_login" value="<?=$val("user_login")?>">
		</div>

		<div class="res-field">
			<label><?php _e('Password', 'reservations');?></label>
			<input type="password" name="user_password">
		</div>

		<div class="res-field">
			<label>&nbsp;</label>
			<button type="submit" name="do" value="login" class="res-btn res-btn-primary" formnovalidate><?php _e('Log In', 'reservations');?></button>
			&nbsp;
			<a class="res-lost-password" href="<?=esc_attr($account->lostPasswordUrl)?>"><?php _e('Lost Password?', 'reservations');?></a>
		</div>
	</div>
<?php else: ?>
	<div class="res-account">
		<a href="<?=esc_attr($account->logoutUrl)?>" class="res-btn res-btn-outline-primary res-logout"><?php _e('Logout', 'reservations');?></a>

		<h3><?=sprintf(__('Logged in as %s', 'reservations'), $account->fullName)?></h3>

		<div class="res-clear"></div>

		<?php if ($account->userErrors): ?>
			<ul class="res-errors">
				<?php foreach ($account->userErrors as $error): ?>
					<li><?=$error?></li>
				<?php endforeach;?>
			</ul>
		<?php endif;?>

		<div class="res-field">
			<label><?php _e('Select Subscriber', 'reservations');?></label>
			<select name="load_subscriber">
				<option value=""><?php _e('&mdash; New Subscriber &mdash;', 'reservations');?></option>
				<?=$account->subscriberSelect?>
			</select>
		</div>

		<div class="res-field res-field-2">
			<label>&nbsp;</label>
			<button type="button" data-type="submit" name="do" value="load_subscriber" class="res-btn res-btn-primary" formnovalidate><?php _e('Load Information', 'reservations');?></button>
			<button type="button" data-type="submit" name="do" value="delete_subscriber" class="res-btn res-btn-outline-primary" formnovalidate><?php _e('Delete Subscriber', 'reservations');?></button>
		</div>

		<p class="res-info res-info-normal">
			<?php _e('If your child has already registered in the past, select him in the box above and click the \'Load Information\' button. Otherwise, select \'New Subscriber\' and continue with the form.', 'reservations');?>
		</p>
	</div>
<?php endif;?>
