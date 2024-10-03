<?php
global $wpdb; // Globální objekt WordPress databáze

// Naèti trénink podle ID (pøizpùsob název tabulky a sloupce svému systému)
$training_id = (int) $_GET['training_id'];
$table_name = $wpdb->prefix . 'trainings'; // Zde zmìò 'trainings' na skuteèný název tabulky
$training = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $training_id));

if ($training) {
    // Trénink byl nalezen, vykresli detaily
    echo '<h2>' . esc_html($training->title) . '</h2>';
    echo '<p>' . esc_html($training->description) . '</p>';
    // Pøidej další informace o tréninku podle potøeby
} else {
    echo 'Trénink nebyl nalezen.';
}
?>

<div class="res-container res-subscribe"> 
	<a class="btn btn-default res-back" href="<?=esc_attr($backLink)?>"><?php _e('Back', 'reservations');?></a>

	<h2 class="res-tgroup-name"><?=esc_html($training->name)?></h2> <!-- Zobrazení názvu tréninku -->

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

		<!-- Zde pokraèuje formuláø registrace stejnì jako pøedtím, nicménì detaily tréninku a další specifické informace mohou být zobrazeny dynamicky podle $training -->
        
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

		<!-- Další pole formuláøe zde... -->
		
		<h3><?php _e('List of Trainings', 'reservations');?></h3>

		<p><?php _e('You are subscribing to this training:', 'reservations');?></p>

		<ul class="res-trainings-list">
			<li>
				<div class="res-tl-gym-name"><?=esc_html($training->gym_name)?></div> <!-- Zobrazení gymu -->
				<ul>
					<li><?=esc_html($training->title)?> &ndash; <?php _e('age group:', 'reservations')?> <strong><?=$training->ageGroupLabel?></strong> &ndash; <?=$training->timeText?></li> <!-- Detail tréninku -->
				</ul>
			</li>
		</ul>

		<h3><?php _e('Subscription Tariff', 'reservations');?></h3>

		<!-- Zde pokraèuje zbytek formuláøe pro volbu tarifù a dalších detailù -->
		
	</form>
</div>
