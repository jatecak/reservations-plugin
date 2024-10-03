<?php
global $wpdb; // Glob�ln� objekt WordPress datab�ze

// Na�ti tr�nink podle ID (p�izp�sob n�zev tabulky a sloupce sv�mu syst�mu)
$training_id = (int) $_GET['training_id'];
$table_name = $wpdb->prefix . 'trainings'; // Zde zm�� 'trainings' na skute�n� n�zev tabulky
$training = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $training_id));

if ($training) {
    // Tr�nink byl nalezen, vykresli detaily
    echo '<h2>' . esc_html($training->title) . '</h2>';
    echo '<p>' . esc_html($training->description) . '</p>';
    // P�idej dal�� informace o tr�ninku podle pot�eby
} else {
    echo 'Tr�nink nebyl nalezen.';
}
?>

<div class="res-container res-subscribe"> 
	<a class="btn btn-default res-back" href="<?=esc_attr($backLink)?>"><?php _e('Back', 'reservations');?></a>

	<h2 class="res-tgroup-name"><?=esc_html($training->name)?></h2> <!-- Zobrazen� n�zvu tr�ninku -->

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

		<!-- Zde pokra�uje formul�� registrace stejn� jako p�edt�m, nicm�n� detaily tr�ninku a dal�� specifick� informace mohou b�t zobrazeny dynamicky podle $training -->
        
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

		<!-- Dal�� pole formul��e zde... -->
		
		<h3><?php _e('List of Trainings', 'reservations');?></h3>

		<p><?php _e('You are subscribing to this training:', 'reservations');?></p>

		<ul class="res-trainings-list">
			<li>
				<div class="res-tl-gym-name"><?=esc_html($training->gym_name)?></div> <!-- Zobrazen� gymu -->
				<ul>
					<li><?=esc_html($training->title)?> &ndash; <?php _e('age group:', 'reservations')?> <strong><?=$training->ageGroupLabel?></strong> &ndash; <?=$training->timeText?></li> <!-- Detail tr�ninku -->
				</ul>
			</li>
		</ul>

		<h3><?php _e('Subscription Tariff', 'reservations');?></h3>

		<!-- Zde pokra�uje zbytek formul��e pro volbu tarif� a dal��ch detail� -->
		
	</form>
</div>
