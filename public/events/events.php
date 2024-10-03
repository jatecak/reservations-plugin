<div class="res-events">
	<?php if ($showMap): ?>
		<div id="res_map" class="res-map"></div>
	<?php endif;?>

	<?php if ($isUnified): ?>
		<h2 class="res-event-list-title res-title"><?php _e('Event list', 'reservations');?></h2>
		<h2 class="res-event-list-title-filtered res-title res-hidden"><?php _e('Events in %s', 'reservations');?> <a class="btn btn-default res-back" href="#"><?php _e('Back', 'reservations');?></a></h2>

		<ul class="res-event-types res-buttons res-container">
			<li class="res-active">
				<a href="#" class="button" data-type=""><?php _e('All', 'reservations');?></a>
			</li>

			<?php foreach ($eventTypes as $type): ?>
				<li>
					<a href="#" class="button" data-type="<?=esc_attr($type["id"])?>"><?=esc_html($type["labelPlural"])?></a>
				</li>
			<?php endforeach;?>
		</ul>
	<?php else: ?>
		<h2 class="res-event-list-title res-title"><?=esc_html($eventType["listTitle"])?></h2>
		<h2 class="res-event-list-title-filtered res-title res-hidden"><?=esc_html($eventType["listTitleFiltered"])?> <a class="btn btn-default res-back" href="#"><?php _e('Back', 'reservations');?></a></h2>
	<?php endif;?>

	<ul class="res-event-list res-container">
		<?php if ($isUnified): ?>
			<li class="res-empty"<?=(count($events) ? ' style="display:none"' : '')?>"><?php _e('No events.', 'reservations');?></li>
		<?php elseif (!count($events)): ?>
			<li class="res-empty"><?=esc_html($eventType["listEmpty"])?></li>
		<?php endif;?>

		<?php foreach ($events as $event): ?>
			<li data-city-id="<?=esc_attr($event->city()->id)?>" data-type="<?=esc_attr($event->eventType["id"])?>">
				<div class="res-event-summary">
					<h3><?=esc_html($event->title)?></h3>
					<div class="res-location">
						<span><?php _e('Where:', 'reservations');?></span> <strong><?=esc_html($event->location)?></strong>
					</div>
					<div class="res-date">
						<span><?php _e('When:', 'reservations');?></span> <strong><?=esc_html($event->dateFormatted)?></strong>
					</div>
					<div class="res-buttons">
						<button type="button" class="res-expand res-btn res-btn-outline-primary"><?php _e('More', 'reservations');?></button>
						<?php if ($event->subscribeLinkDisplay): ?>
							<a class="res-subscribe res-btn res-btn-primary" href="<?=esc_attr($event->subscribeLinkDisplay)?>"><?php _e('Subscribe', 'reservations');?></a>
						<?php else: ?>
							<a class="res-subscribe res-btn res-btn-primary res-btn-disabled" href="#"><?php _e('Coming Soon', 'reservations');?></a>
						<?php endif;?>
					</div>
				</div>

				<div class="res-event-expanded">
					<div class="res-info-left">
						<?=$event->description?>
					</div>
					<div class="res-info-right">
						<?php if ($event->showCampType): ?>
							<h4><?php _ex('Camp Type:', 'event frontend', 'reservations');?></h4>
							<strong class="res-camp-type"><?=esc_html($event->campTypeFormatted)?></strong>
						<?php endif;?>

						<?php if ($event->showAddress): ?>
							<h4><?php _e('Address:', 'reservations');?></h4>
							<address class="res-address"><?=nl2br(esc_html($event->address))?></address>
						<?php endif;?>

						<?php if ($event->showTime): ?>
							<h4><?php _ex('Time:', 'event frontend', 'reservations');?></h4>
							<strong class="res-time"><?=esc_html($event->timeFormatted)?></strong>
						<?php endif;?>

						<?php if ($event->showPrice): ?>
							<div class="res-price">
								<h4><?php _ex('Price:', 'event frontend', 'reservations');?></h4>
								<strong><?=esc_html($event->priceFormatted)?> <?php _e('US$', 'reservations');?></strong>
							</div>
						<?php endif;?>
					</div>
				</div>
			</li>
		<?php endforeach;?>
	</ul>
</div>
