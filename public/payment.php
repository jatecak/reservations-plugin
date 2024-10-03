<div class="res-container res-payment">
	<?php if ($isEvent): ?>
		<h2 class="res-event-name"><?=esc_html($event->title)?></h2>
	<?php elseif ($isTrainingGroup): ?>
		<h2 class="res-tgroup-name"><?=esc_html($tgroup->name)?></h2>
	<?php endif;?>

	<?php if ($error): ?>
		<div class="res-message res-error">
			<p><?=$error?></p>
		</div>
	<?php else: ?>
		<table>
			<tr>
				<th><?php _e('Subscriber name: ', 'reservations');?></th>
				<td><?=esc_html($subscriber->fullName)?></td>
			</tr>
			<tr>
				<th><?php _e('Total amount: ', 'reservations');?></th>
				<td><?=$totalAmountFormatted?> <?php _e('US$', 'reservations');?></td>
			</tr>
			<tr>
				<th><?php _e('Already paid: ', 'reservations');?></th>
				<td><?=$paidAmountFormatted?> <?php _e('US$', 'reservations');?></td>
			</tr>
		</table>

		<div class="res-payment-status">
			<h3><?php _e('Payment Status', 'reservations');?></h3>

			<p><?=$paymentStatus?></p>

			<a class="res-pay res-button-primary" href="<?=esc_attr($createPaymentUrl)?>">
				<?php if ($initial): ?><?=sprintf(_x('Pay %s US$', 'payment', 'reservations'), $paymentToPayAmountFormatted);?>
				<?php else: ?><?=sprintf(__('Pay up %s US$', 'reservations'), $paymentToPayAmountFormatted);?><?php endif;?>
			</a>
		</div>
	<?php endif;?>
</div>
