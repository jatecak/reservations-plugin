<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title><?php _e('Event deposit payment confirmation and application form', 'reservations');?></title>
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

	<p>právě jste se stali součástí jedné z nejrozšířenějších organizací pro rozvoj parkouru v ČR!</p>
	<p>Děkujeme za Vámi provedenou registraci na <?=esc_html($type["labelLC"])?> <strong><?=esc_html($event->title)?></strong>, a taky za Vámi projevenou důvěru.</p>

	<p>Uděláme maximum pro to, aby Vaše dítě bylo spokojené a zdokonalilo své schopnosti a dovednosti!</p>

	<hr>

	<p>Potvrzujeme, že jste zaplatili zálohu <strong><?=esc_html($depositFormatted)?> Kč</strong>. <?php if ($needsFullPayment): ?>Před akcí bude ještě nutné doplatit <strong><?=esc_html($fullPaymentFormatted)?> Kč</strong>, připomeneme se Vám pár dní předem na tento e-mail.<?php endif;?></p>

	<p>V příloze toho mailu Vám <?php if ($needsFullPayment): ?>také <?php endif;?>posíláme <strong>přihlášku</strong>, kterou si můžete už teď zkontrolovat a podepsat, odevzdává se pak <strong>v den konání akce.</strong></p>

	<p>Společně s přihláškou jsou v příloze také <strong>obchodní podmínky</strong>, které Vám doporučujeme si přečíst.</p>

	<p><strong>SLEDUJTE NÁS NA SOCIÁLNÍCH SÍTÍCH!</strong> (Facebook, Instagram)</p>

	<p>Těšíme se na Vás,</p>

	<p>s přátelským pozdravem za celou organizaci</p>

	<p>Baum</p>
</body>
</html>
