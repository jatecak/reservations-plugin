<div class="res-container res-subscribe">
	<a class="btn btn-default res-back" href="<?=esc_attr($backLink)?>"><?php _e('Back', 'reservations');?></a>

	<h2 class="res-event-name"><?=esc_html($event->title)?></h3>

	<p class="res-spam-notice"><?php _e('If you don\'t receive the confirmation email after you submit the subscription form, please check your SPAM folder.', 'reservations');?></p>

	<?php if ($errors): ?>
		<ul class="res-errors">
			<?php foreach ($errors as $error): ?>
				<li><?=$error?></li>
			<?php endforeach;?>
		</ul>
	<?php endif;?>

	<form method="post">
		<?=$nonceField?>

		<h3><?php _e('Subscriber Details', 'reservations');?></h3>

		<div class="res-field">
			<label><?php _e('First Name', 'reservations');?></label>
			<input type="text" name="first_name" value="<?=esc_attr($val("first_name"))?>" required>
		</div>

		<div class="res-field">
			<label><?php _e('Last Name', 'reservations');?></label>
			<input type="text" name="last_name" value="<?=esc_attr($val("last_name"))?>" required>
		</div>

		<div class="res-field">
			<label><?php _e('Date of Birth', 'reservations');?></label>
			<input type="date" name="date_of_birth" value="<?=esc_attr($val("date_of_birth"))?>" required>
		</div>

		<div class="res-field res-field-2">
			<label><?php _e('Health Restrictions', 'reservations');?></label>
			<textarea name="health_restrictions" rows="4"><?=esc_html($val("health_restrictions"))?></textarea>
		</div>

		<div class="res-field">
			<label><?php _e('Health Insurance Company Code', 'reservations');?></label>
			<input type="text" name="health_insurance_code" value="<?=esc_attr($val("health_insurance_code"))?>" required>
		</div>

		<div class="res-field">
			<label><?php _e('Swimmer', 'reservations');?></label>
			<select name="swimmer">
				<?=$swimmerSelect?>
			</select>
		</div>

		<div class="res-field res-field-2">
			<label><?php _e('Used Medicine', 'reservations');?></label>
			<textarea name="used_medicine" rows="4"><?=esc_html($val("used_medicine"))?></textarea>
		</div>

		<div class="res-field">
			<label><?php _e('Shirt Size', 'reservations');?></label>
			<select name="shirt_size">
				<?=$shirtSizeSelect?>
			</select>
		</div>

		<h3><?php _e('Subscriber Representative Details', 'reservations');?></h3>

		<div class="res-field">
			<label><?php _e('First Name', 'reservations');?></label>
			<input type="text" name="rep_first_name" value="<?=esc_attr($val("rep_first_name"))?>" required>
		</div>

		<div class="res-field">
			<label><?php _e('Last Name', 'reservations');?></label>
			<input type="text" name="rep_last_name" value="<?=esc_attr($val("rep_last_name"))?>" required>
		</div>

		<div class="res-clear"></div>

		<div class="res-field res-field-2">
			<label><?php _ex('Address', 'subscribe', 'reservations');?></label>
			<input type="text" name="rep_address" value="<?=esc_attr($val("rep_address"))?>" required>
		</div>

		<div class="res-clear"></div>

		<div class="res-field">
			<label><?php _e('Email', 'reservations');?></label>
			<input type="email" name="contact_email" value="<?=esc_attr($val("contact_email"))?>" required>
		</div>

		<div class="res-field">
			<label><?php _e('Phone', 'reservations');?></label>
			<input type="tel" name="contact_phone" value="<?=esc_attr($val("contact_phone"))?>" required>
		</div>

		<div class="res-clear"></div>

		<h3><?php _e('Privacy Policy', 'reservations');?></h3>
		<div class="privacy-policy">
			<label><input type="checkbox" name="agree_pp" value="1" required<?php checked($val("agree_pp"));?>> <?php _e('I agree with the Privacy Policy', 'reservations');?> *</label>

			<h4>* <?php _e('Instruction on personal data processing', 'reservations');?></h4>
			<?=$gdprContent?>
		</div>

		<p class="res-over-capacity res-hidden"><?php _e('This event is alrady full. You can register yourself as a replacement instead.', 'reservations');?></p>

		<button type="submit" name="do" value="submit" class="res-submit"><?=$buyText?></button>
		<button type="submit" name="do" value="submit-replacement" class="res-submit-replacement res-hidden"><?php _e('Register as replacement', 'reservations');?></button>

		<div class="res-logos">
			<?php foreach ($logos as $logo): ?>
				<img src="<?=$logo?>">
			<?php endforeach;?>
		</div>

	</form>
</div>
