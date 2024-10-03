<div class="res-container res-subscribe">
	<a class="btn btn-default res-back" href="<?=esc_attr($backLink)?>"><?php _e('Back', 'reservations');?></a>

	<h2 class="res-event-name"><?=esc_html($event->title)?></h3>

	<?php if ($errors): ?>
		<ul class="res-errors">
			<?php foreach ($errors as $error): ?>
				<li><?=$error?></li>
			<?php endforeach;?>
		</ul>
	<?php endif;?>

	<form method="post">
		<?=$nonceField?>

		<?php include dirname(__FILE__) . "/../account-box.php";?>

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

		<div class="res-field res-field-3">
			<label><?php _ex('Address', 'subscribe', 'reservations');?></label>
			<input type="text" name="address" value="<?=esc_attr($val("address"))?>" required>
		</div>

		<div class="res-field res-field-2">
			<label><?php _e('Health Restrictions', 'reservations');?></label>
			<textarea name="health_restrictions" rows="4"><?=esc_html($val("health_restrictions"))?></textarea>
		</div>

		<div class="res-field">
			<label><?php _e('Personal Number', 'reservations');?></label>
			<input type="text" name="personal_number" value="<?=esc_attr($val("personal_number"))?>" required>
		</div>

		<div class="res-field">
			<label><?php _e('Health Insurance Company Code', 'reservations');?></label>
			<input type="text" name="health_insurance_code" value="<?=esc_attr($val("health_insurance_code"))?>" required>
		</div>

		<?php if ($eventType === "camp"): ?>
			<div class="res-field res-field-2">
				<label><?php _e('Used Medicine', 'reservations');?></label>
				<textarea name="used_medicine" rows="4"><?=esc_html($val("used_medicine"))?></textarea>
			</div>

			<div class="res-field">
				<label><?php _e('Swimmer', 'reservations');?></label>
				<select name="swimmer">
					<?=$swimmerSelect?>
				</select>
			</div>

			<div class="res-field">
				<label><?php _e('Shirt Size', 'reservations');?></label>
				<select name="shirt_size">
					<?=$shirtSizeSelect?>
				</select>
			</div>
		<?php else: ?>
			<div class="res-field res-field-3">
				<label><?php _e('Used Medicine', 'reservations');?></label>
				<textarea name="used_medicine" rows="4"><?=esc_html($val("used_medicine"))?></textarea>
			</div>
		<?php endif;?>

		<h3><?php _e('Subscriber Representative Details', 'reservations');?></h3>

		<div class="res-field">
			<label><?php _e('First Name', 'reservations');?></label>
			<input type="text" name="rep_first_name" value="<?=esc_attr($val("rep_first_name"))?>" required>
		</div>

		<div class="res-field">
			<label><?php _e('Last Name', 'reservations');?></label>
			<input type="text" name="rep_last_name" value="<?=esc_attr($val("rep_last_name"))?>" required>
		</div>

		<div class="res-field">
			<label><?php _e('Date of Birth', 'reservations');?> *</label>
			<input type="date" name="rep_date_of_birth" value="<?=esc_attr($val("rep_date_of_birth"))?>" required>
		</div>

		<div class="res-field res-field-3">
			<label><?php _ex('Address', 'subscribe', 'reservations');?></label>
			<input type="text" name="rep_address" value="<?=esc_attr($val("rep_address"))?>" required>
		</div>

		<div class="res-clear"></div>

		<div class="res-field">
			<label><?php _e('Email', 'reservations');?></label>
			<input type="email" name="contact_email" value="<?=esc_attr($val("contact_email"))?>" required>
		</div>

		<div class="res-field">
			<label><?php _e('Phone (mother)', 'reservations');?></label>
			<input type="tel" name="contact_phone" value="<?=esc_attr($val("contact_phone"))?>" required>
		</div>

		<div class="res-field">
			<label><?php _e('Phone (father)', 'reservations');?></label>
			<input type="tel" name="contact_phone_2" value="<?=esc_attr($val("contact_phone_2"))?>" required>
		</div>

		<p class="res-info">
			* &ndash; <?php _e('required for statutory declaration', 'reservations');?><br>
		</p>

		<?php if ($eventType === "workshop"): ?>
			<h3><?php _e('Catering', 'reservations');?></h3>

			<div class="res-field">
				<label><?php _e('Catering Options', 'reservations');?></label>
				<select name="catering">
					<?=$cateringSelect?>
				</select>
			</div>

			<div class="res-field catering-meal res-hidden">
				<label><?php _e('Selected Meal', 'reservations');?> *</label>
				<select name="meal">
					<?=$mealSelect?>
				</select>
			</div>

			<p class="res-info">
				* &ndash; <?php _e('menu is subject to change', 'reservations');?><br>
			</p>

			<h3><?php _e('Carpool', 'reservations');?></h3>

			<div class="res-section-description"><?=$carpoolDescription?></div>

			<div class="res-field">
				<label><?php _e('Carpool Options', 'reservations');?></label>
				<select name="carpool">
					<?=$carpoolSelect?>
				</select>
			</div>

			<div class="res-field carpool-seats res-hidden">
				<label><?php _e('Number of Requested/Offered Seats', 'reservations');?></label>
				<input name="carpool_seats" type="number" min="1" step="1" value="<?=esc_attr($val("carpool_seats"))?>">
			</div>

			<div class="res-field carpool-contact res-hidden">
				<label><?php _ex('Contact Phone', 'carpool', 'reservations');?></label>
				<input name="carpool_contact" type="text" value="<?=esc_attr($val("carpool_contact"))?>">
			</div>
		<?php endif;?>

		<h3><?php _e('Details for Organisers', 'reservations');?></h3>

		<div class="res-field">
			<label><?php _e('Referrer', 'reservations');?></label>
			<select name="referrer">
				<?=$referrerSelect?>
			</select>

			<input type="text" class="res-hidden" name="referrer_other" value="<?=esc_attr($val("referrer_other"))?>">
		</div>

		<div class="res-field">
			<?php if ($eventType === "camp"): ?>
				<label><?php _ex('Reason', 'camp', 'reservations');?></label>
			<?php else: ?>
				<label><?php _e('Reason', 'reservations');?></label>
			<?php endif;?>
			<select name="reason">
				<?=$reasonSelect?>
			</select>

			<input type="text" class="res-hidden" name="reason_other" value="<?=esc_attr($val("reason_other"))?>">
		</div>

		<h3><?php _e('Save Details', 'reservations');?></h3>

		<label class="res-checkbox"><input type="checkbox" name="save_details" value="1"<?php checked($val("save_details"));?>>
			<?php if ($account && $account->subscriberLoaded): ?>
				<?php _e('Update stored subscriber details', 'reservations');?>
			<?php else: ?>
				<?php _e('Store details for future subscriptions', 'reservations');?>
			<?php endif;?>
		</label>

		<?php if (!$account->user): ?>
			<p class="res-info res-info-normal">
				<?php _e('After you submit the form, an account will be created for you and its (generated) password will be sent to your email. In the future, you can use this account to fill this form more quickly.', 'reservations');?>
			</p>
		<?php endif;?>

		<div class="res-clear"></div>

		<h3><?php _e('Privacy Policy', 'reservations');?></h3>
		<div class="privacy-policy">
			<label class="res-checkbox"><input type="checkbox" name="agree_pp" value="1" required<?php checked($val("agree_pp"));?>> <?php _e('I agree with the Privacy Policy', 'reservations');?> **</label>

			<h4>** <?php _e('Instruction on personal data processing', 'reservations');?></h4>
			<?=$gdprContent?>

			<h4><?php _e('Instruction of the possibility to raise objections', 'reservations');?></h4>
			<?=$gdprContent2?>
		</div>

		<p class="res-over-capacity res-hidden"><?php _e('This event is alrady full. You can register yourself as a replacement instead.', 'reservations');?></p>

		<button type="submit" name="do" value="submit" class="res-submit"><?=$buyText?></button>
		<button type="submit" name="do" value="submit-replacement" class="res-submit-replacement res-hidden"><?php _e('Register as replacement', 'reservations');?></button>

		<p class="res-fees-notice"><?php _ex('Price includes transaction fee.', 'event', 'reservations');?></p>

		<div class="res-logos">
			<?php foreach ($logos as $logo): ?>
				<img src="<?=$logo?>">
			<?php endforeach;?>
		</div>

	</form>
</div>
