<div class="res-trainings">
	<div class="res-container">
		<h2 class="res-select-city"><?php _e('Select City', 'reservations');?></h2>
		<h2 class="res-select-gym res-hidden"><?php _e('Select Gym', 'reservations');?> <a class="btn btn-default res-back" href="#"><?php _e('Back', 'reservations');?></a></h2>
	</div>

	<?php if ($showMap): ?>
		<div id="res_map" class="res-map"></div>
	<?php endif;?>

	<h2 class="res-gym-list-title res-title"><?php _e('Gym List', 'reservations');?></h2>
	<h2 class="res-gym-list-title-filtered res-title res-hidden"><?php _e('Gyms in %s', 'reservations');?> <a class="btn btn-default res-back" href="#"><?php _e('Back', 'reservations');?></a></h2>

	<ul class="res-gym-list res-container">
		<?php if (!count($cities)): ?>
			<li class="res-empty"><?php _e('No trainings.', 'reservations');?></li>
		<?php endif;?>

		<?php foreach ($cities as $city): ?>
			<li class="res-city" data-city-id="<?=esc_attr($city->id)?>">
				<a href="#" class="res-city-title res-btn res-btn-primary"><?=esc_html($city->name)?></a>

				<ul class="res-city-gyms">
					<?php foreach ($city->gyms as $gym): ?>
						<li class="res-gym">
							<h3><?=$gym->name?></h3>
							<div class="res-age-groups">
								<?php if ($gym->ageGroupsFormatted): ?>
									<span><?php _e('Age Groups:', 'reservations');?></span> <strong><?=esc_html($gym->ageGroupsFormatted)?></strong>
								<?php endif;?>
							</div>

							<div class="res-buttons">
								<?php if($gym->url): ?>
									<a href="<?=esc_attr($gym->url)?>" class="res-more res-btn res-btn-outline-primary"><?php _e('More', 'reservations');?></a>
								<?php endif; ?>
								<?php if ($gym->subscribeLinkDisplay): ?>
									<a class="res-subscribe res-btn res-btn-primary" href="<?=esc_attr($gym->subscribeLinkDisplay)?>"><?php _e('Subscribe', 'reservations');?></a>
								<?php endif;?>
							</div>
						</li>
					<?php endforeach;?>
				</ul>
			</li>
		<?php endforeach;?>
	</ul>
</div>
