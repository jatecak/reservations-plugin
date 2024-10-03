aaaa<div class="res-container res-training">   
	<?php if ($subscribeEnabled): ?>
		<div class="res-subscribe-container">
			<a href="<?=esc_attr($subscribeUrl)?>" class="btn btn-primary res-subscribe"><?php _e('Subscribe', 'reservations');?></a>
			<div class="res-event-capacity"><?=$capacityFormatted?></div>
		</div>       
	<?php endif;?>

	<h2><?=esc_html($training->title)?></h2>

	<p class="res-time"><?=$timeText?></p>

	<?php if ($training->priceSingle): ?>
		<p class="res-price-single"><?php _ex('Price of single training: ', 'training view', 'reservations');?><strong><?=$training->priceSingle?> <?php _e('US$', 'reservations');?></strong><!--  (<a href="<?=$formUrl?>"><?php _e('download application form', 'reservations');?></a>) --></p>
	<?php endif;?>

	<p class="res-age-group"><?php _e('Age group: ', 'reservations');?><strong><?=$ageGroup?></strong></p>

	<div class="res-description"><?=$description?></div>

	<h3><?php _ex('Location', 'training view', 'reservations');?></h3>
	<p class="res-gym"><?=$gym->name?></p>
	<a title="<?php esc_attr_e('Open in Google Maps', 'reservations');?>" target="_blank" href="<?=esc_attr($mapsLink)?>" class="res-address"><?=$gym->address?></a>

	<?php if ($showInstructors): ?>
		<h3><?php _ex('Instructors', 'training view', 'reservations');?></h3>
		<ul class="res-instructors">
			<?php foreach ($instructors as $instructor): ?>
				<li><strong><?=$instructor->displayName?></strong><?=$instructor->experience ? " | " . $instructor->experience : ""?></li>
			<?php endforeach;?>
		</ul>
	<?php endif;?>

	<?php if ($showContact): ?>
		<h3><?php _ex('Contact', 'training view', 'reservations');?></h3>
		<p class="res-contact">
			<a href="mailto:<?=$contactEmail?>"><?=$contactEmail?></a><br>
			<?=$contactPhone?>
		</p>
	<?php endif;?>
</div>
