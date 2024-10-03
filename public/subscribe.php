<div class="res-container res-subscribe"> 
	<a class="btn btn-default res-back" href="<?=esc_attr($backLink)?>"><?php _e('Back', 'reservations');?></a>

	<h2 class="res-tgroup-name"><?=esc_html($tgroup->name)?></h2>

	<?php if ($errors): ?>
		<ul class="res-errors">
			<?php foreach ($errors as $error): ?>
				<li><?=$error?></li>
			<?php endforeach;?>
		</ul>
	<?php endif;?>

	<form method="post">   
		<?=$nonceField?>

		<?php include dirname(__FILE__) . "/account-box.php";?>

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
			<label><?php _ex('Address', 'subscribe', 'reservations');?></label>
			<input type="text" name="address" value="<?=esc_attr($val("address"))?>" required>
		</div>

		<div class="res-field">
			<label><?php _e('Personal Number', 'reservations');?></label>
			<input type="text" name="personal_number" value="<?=esc_attr($val("personal_number"))?>" required>
		</div>

		<div class="res-field res-field-2">
			<label><?php _e('Health Restrictions', 'reservations');?></label>
			<textarea name="health_restrictions" rows="4"><?=esc_html($val("health_restrictions"))?></textarea>
		</div>

		<div class="res-field">
			<label><?php _e('Age Group', 'reservations');?></label>
			<select name="age_group">
				<?=$ageGroupSelect?>
            </select>
		</div>

		<div class="res-field">
			<label><?php _e('Facebook', 'reservations');?> **</label>
			<input type="text" name="facebook" value="<?=esc_attr($val("facebook"))?>">
		</div>

		<div class="res-field">
			<label><?php _e('Preferred Level', 'reservations');?></label>
			<select name="preferred_level">
				<?php foreach ($preferredLevelSelect as $ageGroup => $options): ?>
					<optgroup data-age-group="<?=esc_attr($ageGroup)?>"><?=$options?></optgroup>
				<?php endforeach;?>
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

		<div class="res-field">
			<label><?php _e('Date of Birth', 'reservations');?> *</label>
			<input type="date" name="rep_date_of_birth" value="<?=esc_attr($val("rep_date_of_birth"))?>" required>
		</div>

		<div class="res-field res-field-2">
			<label><?php _ex('Address', 'subscribe', 'reservations');?></label>
			<input type="text" name="rep_address" value="<?=esc_attr($val("rep_address"))?>" required>
		</div>

		<div class="res-field">
			<label><?php _e('Personal Number', 'reservations');?></label>
			<input type="text" name="rep_personal_number" value="<?=esc_attr($val("rep_personal_number"))?>" required>
		</div>

		<div class="res-clear"></div>

		<div class="res-field">
			<label><?php _e('Email', 'reservations');?> **</label>
			<input type="email" name="contact_email" value="<?=esc_attr($val("contact_email"))?>" required>
		</div>

		<div class="res-field">
			<label><?php _e('Phone', 'reservations');?> ***</label>
			<input type="tel" name="contact_phone" value="<?=esc_attr($val("contact_phone"))?>" required>
		</div>

		<p class="res-info">
			* &ndash; <?php _e('required for statutory declaration', 'reservations');?><br>
			** &ndash; <?php _e('used for informing about trainings and related activities', 'reservations');?><br>
			*** &ndash; <?php _e('used in case of emergencies', 'reservations');?>
		</p>

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

		<h3><?php _e('List of Trainings', 'reservations');?></h3>

		<p><?php _e('You are subscribing to these trainings:', 'reservations');?></p>

		<ul class="res-trainings-list">
			<?php foreach ($subscribedGyms as $gymName => $trainings): ?>
			<li>
				<div class="res-tl-gym-name"><?=esc_html($gymName)?></div>
				<ul>
					<?php foreach ($trainings as $training): ?>
						<li><a href="<?=esc_url($training->permalink)?>"><?=esc_html($training->title)?></a> &ndash; <?php _e('age group:', 'reservations')?> <strong><?=$training->ageGroupLabel?></strong> &ndash; <?=$training->timeText?></li>
					<?php endforeach;?>
				</ul>
			</li>
			<?php endforeach;?>
		</ul>

		<h3><?php _e('Subscription Tariff', 'reservations');?></h3>

		<?php if ($annualEnabled): ?>
			<label class="res-subscription res-biannual">
				<div class="res-right res-price"><?=esc_html($priceFormatted["annual"])?></div>

				<input type="radio" name="subscription_type" value="annual"<?php checked($activeType, "annual");?>>
				<h4><?php _e('Annual Subscription', 'reservations');?></h4>
				<p><?php _e('Subscription for the whole school year.', 'reservations');?></p>
			</label>
		<?php endif;?>

		<?php if ($biannualEnabled): ?>
			<label class="res-subscription res-biannual">
				<div class="res-right res-price"><?=esc_html($priceFormatted["biannual"])?></div>

				<input type="radio" name="subscription_type" value="biannual"<?php checked($activeType, "biannual");?>>
				<h4><?php _e('Biannual Subscription', 'reservations');?></h4>
				<p><?php _e('Subscription for the whole school term.', 'reservations');?></p>
			</label>
		<?php endif;?>

		<?php if ($monthlyEnabled): ?>
			<label class="res-subscription res-monthly">
				<div class="res-right">
					<div class="res-price"><?=esc_html($priceFormatted["monthly"])?></div>
					<div class="res-price res-price-total res-hidden">
						<?php _e('total: ', 'reservations');?><span></span> <?php _e('US$', 'reservations');?>
						<div class="res-price-initial res-hidden">(<?php _e('deposit', 'reservations');?> <span></span> <?php _e('US$', 'reservations');?>)</div>
					</div>
				</div>

				<input type="radio" name="subscription_type" value="monthly"<?php checked($activeType, "monthly");?>>
				<h4><?php _e('Monthly Subscription', 'reservations');?></h4>
				<p><?php _e('Number of months:', 'reservations');?> <input type="number" name="num_months" value="<?=esc_attr($val("num_months") ?: "1")?>" class="res-num-months"></p>
			</label>
		<?php endif;?>

		<h3><?php _e('Subscription Period', 'reservations');?></h3>
		<div class="res-field">
			<label><?php _e('Subscription Start', 'reservations');?></label>
			<input type="date" name="start_date" value="<?=esc_attr($startDate)?>"<?=($startDateDisabled ? " disabled" : "")?> required>
		</div>

		<div class="res-field">
			<label><?php _e('Subscription End', 'reservations');?></label>
			<input type="date" name="end_date" value="<?=esc_attr($endDate)?>" disabled>
		</div>

		<p class="res-over-capacity res-hidden"><?php _e('This training is full on the selected date. Please try a different one.', 'reservations');?></p>
		<p class="res-start-date-invalid res-hidden"><?php _e('Subscription start date must be in the future.', 'reservations');?></p>
		<p class="res-end-date-invalid res-hidden"><?php _e('Subscription end date must be before the end of the current term.', 'reservations');?></p>

		<div class="res-clear"></div>

		<h3><?php _e('Privacy Policy', 'reservations');?></h3>
		<div class="privacy-policy">
			<label><input type="checkbox" name="agree_pp" value="1" required<?php checked($val("agree_pp"));?>> <?php _e('I agree with the Privacy Policy', 'reservations');?> ****</label>

			<h4>**** <?php _e('Instruction on personal data processing', 'reservations');?></h4>
			<?=$gdprContent?>

			<h4><?php _e('Instruction of the possibility to raise objections', 'reservations');?></h4>
			<?=$gdprContent2?>
		</div>

		<button type="submit" name="do" value="submit" class="res-submit">
			<span class="res-no-deposit<?=($totalAmount !== $initialAmount ? " res-hidden" : "")?>">
				<?=sprintf(__('Pay %s US$', 'reservations'), "<span>$totalAmount</span>")?>
			</span>
			<span class="res-deposit<?=($totalAmount === $initialAmount ? " res-hidden" : "")?>">
				<?=sprintf(_x('Pay deposit %s US$ (total price: %s US$)', 'trainings', 'reservations'), "<span>$initialAmount</span>", "<span>$totalAmount</span>")?>
			</span>
		</button>
		<button type="submit" name="do" value="submit-replacement" class="res-submit-replacement res-hidden"><?php _e('Register as replacement', 'reservations');?></button>

		<p class="res-fees-notice"><?php _e('Price includes transaction fee.', 'reservations');?></p>

		<div class="res-logos">
			<?php foreach ($logos as $logo): ?>
				<img src="<?=$logo?>">
			<?php endforeach;?>
		</div>

	</form>
</div>
