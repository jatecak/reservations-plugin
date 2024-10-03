<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title><?php _e('Event pay up reminder', 'reservations');?></title>
	<style type="text/css">
		hr {
			border: 0;
			border-top: 1px solid #000;
			height: 0;
		}
		.center {
			text-align: center;
		}
		.hl {
			color: #BD1F34;
		}
		.bold {
			font-weight: bold;
		}
	</style>
</head>
<body>
	<p>Dobrý den <strong><?=esc_html($name)?></strong>,</p>

	<p>připomínáme Vám, že Vaše dítě <strong><?=esc_html($subscriberName)?></strong> je zaregistrováno na <?=esc_html($type["labelLC"])?> <strong><?=esc_html($event->title)?></strong>. Zatím jste ale zaplatili pouze zálohu <strong><?=esc_html($depositFormatted)?> Kč</strong>.</p>

	<p>Nyní je ještě třeba doplatit <strong><?=esc_html($fullPaymentFormatted)?> Kč.</strong> Tak lze učinit pomocí online platební brány, kterou naleznete na odkazu níže.</p>

	<p class="h1"><a href="<?=esc_attr($payUpUrl)?>">ZAPLATIT DOPLATEK <?=esc_html($fullPaymentFormatted)?> KČ</a></p>

	<p><strong>SLEDUJTE NÁS NA SOCIÁLNÍCH SÍTÍCH!</strong> (Facebook, Instagram)</p>

	<p>Těšíme se na Vás,</p>

	<p>s přátelským pozdravem za celou organizaci</p>

	<p>Baum</p>
</body>
</html>
