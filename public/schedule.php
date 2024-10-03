<div class="res-container res-schedule">
	<?php if ($subscribeEnabled && $buttonPosition === "top"): ?>
		<a href="<?=esc_attr($subscribeUrl)?>" class="btn btn-primary res-subscribe"><?php _e('Subscribe', 'reservations');?></a>
	<?php endif;?>

	<h2 class="res-city-name"><?=esc_html($gym->city->name)?></h3>
	<h3 class="res-gym-name"><?=esc_html($gym->name)?>, <?=esc_html($gym->address)?></h2>

	<dl class="res-legend-2">
		<?php foreach ($legend as $group): ?>
			<dt class="<?=$group["class"]?>"></dt>
			<dd><?=$group["label"]?></dd>
		<?php endforeach;?>
	</dl>

	<table class="res-schedule-table">
		<thead><tr>
			<th class="res-time"></th>
			<th><?php _e('Monday', 'reservations');?><span><?=$dates[0]?></span></th>
			<th><?php _e('Tuesday', 'reservations');?><span><?=$dates[1]?></span></th>
			<th><?php _e('Wednesday', 'reservations');?><span><?=$dates[2]?></span></th>
			<th><?php _e('Thursday', 'reservations');?><span><?=$dates[3]?></span></th>
			<th><?php _e('Friday', 'reservations');?><span><?=$dates[4]?></span></th>
			<th><?php _e('Saturday', 'reservations');?><span><?=$dates[5]?></span></th>
			<th><?php _e('Sunday', 'reservations');?><span><?=$dates[6]?></span></th>
		</tr></thead>

		<?php foreach ($table as $row): ?>
			<tr<?=$row["class"] ? ' class="' . $row["class"] . '"' : ""?>>
				<?php if ($row["timeEmpty"]): ?>
					<th></th>
				<?php elseif ($row["time"]): ?>
					<th rowspan="2"><?=$row["time"]?></th>
				<?php endif;?>

				<?php foreach ($row["days"] as $day): ?>
					<?php if ($day["event"]): ?>
					<?php $event = $day["event"];?>
						<td rowspan="<?=$day["span"]?>" class="res-event <?=$event->class?>">
							<a href="<?=$event->permalink?>">
								<h3><?=$event->title?></h3>
								<div class="res-event-time"><?=$day["timeslot"]["time_formatted"]?></div>
								<div class="res-event-capacity"><?=$event->capacityFormatted?></div>
							</a>
						</td>
					<?php elseif ($day["empty"]): ?>
						<td rowspan="<?=$day["span"]?>">&nbsp;</td>
					<?php endif;?>
				<?php endforeach;?>
			</tr>
		<?php endforeach;?>
	</table>

	<!-- <h4>Legenda</h4>
	<dl class="res-legend">
		<dt class="res-junior"></dt>
		<dd><?php _e('Junior', 'reservations');?></dd>
		<dt class="res-mature"></dt>
		<dd><?php _e('Mature', 'reservations');?></dd>
	</dl> -->

	<?php if ($subscribeEnabled && $buttonPosition === "bottom"): ?>
		<a href="<?=esc_attr($subscribeUrl)?>" class="btn btn-primary res-subscribe"><?php _e('Subscribe', 'reservations');?></a>
	<?php endif;?>
</div>
