<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title><?php _e('Event pay up confirmation', 'reservations');?></title>
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

	<p>touto zprávou potvrzujeme, že jsme od Vás obdrželi doplatek za <?=esc_html($type["labelLC"])?> <strong><?=esc_html($event->title)?></strong>. Nyní již tedy máte vše zaplaceno a můžete Vaše děti začít připravovat na zážitky, které je na této akci jistě neminou.</p>

	<p>V příloze toho mailu Vám opět posíláme <strong>přihlášku</strong>. Pokud jste tak již neučinili, tak ji je potřeba podepsat a poté <strong>v den konání akce odevzdat.</strong></p>

	<p>Společně s přihláškou jsou také přiloženy <strong>obchodní podmínky</strong>, které Vám doporučujeme si přečíst.</p>

	<p><strong>SLEDUJTE NÁS NA SOCIÁLNÍCH SÍTÍCH!</strong> (Facebook, Instagram)</p>

	<p>Těšíme se na Vás,</p>

	<p>s přátelským pozdravem za celou organizaci</p>

	<p>Baum</p>
</body>
</html>
