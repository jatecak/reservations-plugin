<?php

namespace Reservations\Utils;

class Wordpress
{
    public static function getAdminId()
    {
        return get_users([
            "role"   => "administrator",
            "number" => 1,
        ])[0]->ID;
    }

    public static function getPageRelativeUrl($page) {
    	if(!is_object($page))
    		$page = get_post($page);

    	$ancestors = array_reverse(get_post_ancestors($page));
    	$names = [];

    	foreach($ancestors as $id) {
    		$names[] = get_post($id)->post_name;
    	}

    	$names[] = $page->post_name;

    	return implode("/", $names);
    }
}
