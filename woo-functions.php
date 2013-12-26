<?php
/**
 * Functions used by plugins
 */
if ( ! class_exists( 'WC_Dependencies' ) )
	require_once 'class-wc-dependencies.php';

/**
 * WC Detection
 */
if ( ! function_exists( 'is_woocommerce_active' ) ) {
	function is_woocommerce_active() {
		return WC_Dependencies::woocommerce_active_check();
	}
}

/**
 * Queue updates for the WooUpdater
 */
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	function woothemes_queue_update( $file, $file_id, $product_id ) {
		global $woothemes_queued_updates;

		if ( ! isset( $woothemes_queued_updates ) )
			$woothemes_queued_updates = array();

		$plugin             = new stdClass();
		$plugin->file       = $file;
		$plugin->file_id    = $file_id;
		$plugin->product_id = $product_id;

		$woothemes_queued_updates[] = $plugin;
	}
}

/**
 * Load installer for the WooThemes Updater.
 * @return $api Object
 */
if ( ! class_exists( 'WooThemes_Updater' ) && ! function_exists( 'woothemes_updater_install' ) ) {
	function woothemes_updater_install( $api, $action, $args ) {
		$download_url = 'http://woodojo.s3.amazonaws.com/downloads/woothemes-updater/woothemes-updater.zip';

		if ( 'plugin_information' != $action ||
			false !== $api ||
			! isset( $args->slug ) ||
			'woothemes-updater' != $args->slug
		) return $api;

		$api = new stdClass();
		$api->name = 'WooThemes Updater';
		$api->version = '1.0.0';
		$api->download_link = esc_url( $download_url );
		return $api;
	}

	add_filter( 'plugins_api', 'woothemes_updater_install', 10, 3 );
}

/**
 * WooUpdater Installation Prompts
 */
if ( ! class_exists( 'WooThemes_Updater' ) && ! function_exists( 'woothemes_updater_notice' ) ) {

	/**
	 * Display a notice if the "WooThemes Updater" plugin hasn't been installed.
	 * @return void
	 */
	function woothemes_updater_notice() {
		$active_plugins = apply_filters( 'active_plugins', get_option('active_plugins' ) );
		if ( in_array( 'woothemes-updater/woothemes-updater.php', $active_plugins ) ) return;

		$slug = 'woothemes-updater';
		$install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . $slug ), 'install-plugin_' . $slug );
		$activate_url = 'plugins.php?action=activate&plugin=' . urlencode( 'woothemes-updater/woothemes-updater.php' ) . '&plugin_status=all&paged=1&s&_wpnonce=' . urlencode( wp_create_nonce( 'activate-plugin_woothemes-updater/woothemes-updater.php' ) );

		$message = '<a href="' . esc_url( $install_url ) . '">Install the WooThemes Updater plugin</a> to get updates for your WooThemes plugins.';
		$is_downloaded = false;
		$plugins = array_keys( get_plugins() );
		foreach ( $plugins as $plugin ) {
			if ( strpos( $plugin, 'woothemes-updater.php' ) !== false ) {
				$is_downloaded = true;
				$message = '<a href="' . esc_url( admin_url( $activate_url ) ) . '">Activate the WooThemes Updater plugin</a> to get updates for your WooThemes plugins.';
			}
		}
		echo '<div class="updated fade"><p>' . $message . '</p></div>' . "\n";
	}

	add_action( 'admin_notices', 'woothemes_updater_notice' );
}

/**
 * Prevent conflicts with older versions
 */
if ( ! class_exists( 'WooThemes_Plugin_Updater' ) ) {
	class WooThemes_Plugin_Updater { function init() {} }
}


/**
* Custom filter for exporting goji order in e3pl format
*/
if (! function_exists( 'orderlist_e3pl_style' ) ) {

	function orderlist_e3pl_style($orders) {
		$order_data = array('OrderList' => array('UniqueKey' => date("YmdHis"), 'Order' => $orders['Orders']['Order']));
	 	return $order_data;
	}
	add_filter( 'wc_customer_order_xml_export_suite_order_export_format', 'orderlist_e3pl_style', 10, 2);
}


/**
* Custom filter for exporting goji order data
*/
if (! function_exists( 'orders_e3pl_style' ) ) {

	function orders_e3pl_style ($order_format, $order) {
		$order_data = array(
				'OrderNo'			 => $order_format['OrderId'],
				'Receiver'           => array('Company'   	=> $order->shipping_company,
											  'FirstName' 	=> $order->shipping_first_name,
											  'LastName'  	=> $order->shipping_last_name,
											  'Address1'  	=> $order->shipping_address_1,
											  'Address2'  	=> $order->shipping_address_2,
											  'ZIP'       	=> $order->shipping_postcode,
											  'City'        => $order->shipping_city,
											  'CellPhoneNo' => str_replace(' ', '', $order->billing_phone),
											  'Email'		=> $order->billing_email
											  ),
				'Forwarder'			 => getForwarder($order),
				'Item'				 => getLineItems($order)
		);
	 	return $order_data;
	}

	add_filter( 'wc_customer_order_xml_export_suite_order_export_order_list_format', 'orders_e3pl_style', 10, 2);
}

function getLineItems($order) {
	foreach( $order->get_items() as $item_id => $item_data ) {
		$product = $order->get_product_from_item( $item_data );
		$items[] = array(
			'ManufactSKU'		=> $product->get_sku(),
			'SKU'				=> $product->get_sku(),
			'ProductName'		=> getProductName($product->get_title(), $product->get_sku()),
			'Qty'				=> getQty($item_data['Storlek'], $item_data['qty'], $product->get_sku())
		);
	}
	return $items;
}

function getProductName($title, $sku) {
	return html_entity_decode($title) . " (" . getBagSize($sku) . getUnit($sku) . ")";
}

function getQty($meta_weight, $qty, $sku) {
	$weight = preg_replace("/[^0-9]/","",$meta_weight);
	$tot_qty = $weight/getBagSize($sku) * $qty;
	return $tot_qty;
}

function getUnit($sku) {
	if ('7052' == $sku || 
		'5012' == $sku || 
		'4012' == $sku || 
		'2012' == $sku ||
		'1012' == $sku ||
		'6439006' == $sku || //Naturligtvis Presentlåda 5 x 30 ml
		'643070'  == $sku || //Naturligtvis Balsam 250ml
		'643060'  == $sku || //Naturligtvis Schampo 250ml
		'643037'  == $sku || //Naturligtvis Sockerskrubb 175 ml
		'643032'  == $sku || //Naturligtvis Duschkräm 175 ml
		'643035'  == $sku || //Naturligtvis Duschtvål 250ml
		'643026'  == $sku || //Naturligtvis Hudkräm 175ml
		'643022'  == $sku || //Naturligtvis Handtvål 300ml
		'643020'  == $sku || //Naturligtvis Handkräm 75 ml
		'643006'  == $sku || //Naturligtvis Ansiktskräm torr hy 50ml
		'643004'  == $sku || //Naturligtvis Ansiktskräm normal hy 50ml
		'643001'  == $sku  
		)	 {
		return 'ml';
	} else {
		return 'g';
	}
}

function getBagSize($sku) {
	//Bag sizes for the products
	$bag_sizes = array(
		'100001' => 250, //Ekologiska Gojibär (250g)
		'100002' => 250, //Ekologiskt Bipollen (250g)
		'100003' => 250, //Ekologiskt Vetegräs (250g)
		'100004' => 250, //Ekologisk Maca (250g)
		'100005' => 250, //Ekologiska Krossade Kakaobönor (250g)
		'100006' => 250, //Ekologiskt Korngräs (250g)
		'100007' => 250, //Ekologiska Mullbär (250g)
		'100008' => 250, //Ekologiskt Kakaopulver (250g)
		'100009' => 125, //Ekologisk Acai (125g)
		'100010' => 250, //Ekologisk Spirulina (250g)
		'100011' => 60, //Ekologiskt Vaniljpulver (60g)
		'100012' => 250, //Ekologiska Chiafrön 250g
		'100013' => 250, //Ekologiskt Hampapulver 250g
		'100014' => 125, //Ekologiskt Chlorellapulver 125g
		'100015' => 250, //Ekologisk Mandel 250g
		'100016' => 250, //Ekologiska Hasselnötter 250g
		'100017' => 250, //Ekologiska Cashewnötter 250g
		'100018' => 250, //Ekologiska Valnötter 250g
		'100019' => 250, //Soltorkade Gojibär (250g)
		'100020' => 250, //Ekologiska Pekannötter (250g)
		'100021' => 250, //Ekologiska Pinjenötter (250g)
		'100022' => 250, //Ekologiska Paranötter (250g)
		'100023' => 250, //Macadamianötter (250g)
		'100024' => 250, //Psylliumfrön (250g)
		'100025' => 250, //Nyponpulver (250g)
		'100026' => 125, //Ekologisk Ginseng (125g)
		'100027' => 250, //Rosenrot (250g)
		'100028' => 125, //Chagapulver (125g)
		'100029' => 250, //Shiitakepulver (250g)
		'100030' => 250, //Reishipulver (250g)
		'100031' => 250, //Ekologiskt Mandelsmör (250g)
		'100032' => 250, //Ekologiskt Choklad- och Mandelsmör (250g)
		'100033' => 170, //Ekologiskt Hasselnötssmör (170g)
		'100034' => 400, //Ekologisk Kokosolja (400g)
		'100035' => 250, //Ekologiskt Kakaosmör (250g)
		'100036' => 250, //Stevia (250g)
		'100037' => 230, //Ekologisk GFM Manukahonung (230g)
		'100038' => 500, //Kokossocker (500g)
		'100039' => 250, //Björksocker (Xylitol) (250g)
		'100040' => 60,  //Ekologiskt Camu Camu pulver (60g)
		'100041' => 125, //Rosenrot (125g)
		'7052'	 => 237, //Ginesis Moisturizing Lotion 237ml
		'5012'   => 237, //Ginesis F&B Scrub 237ml
		'4012'   => 237, //Ginesis Baby Schampo 237ml
		'2012'	 => 237, //Ginesis Balsam 237ml
		'1012'   => 237, //Ginesis Schampo 237ml
		'6439006' => 150, //Naturligtvis Presentlåda 5 x 30 ml
		'643070'  => 250, //Naturligtvis Balsam 250ml
		'643060'  => 250, //Naturligtvis Schampo 250ml
		'643037'  => 175, //Naturligtvis Sockerskrubb 175 ml
		'643032'  => 175, //Naturligtvis Duschkräm 175 ml
		'643035'  => 250, //Naturligtvis Duschtvål 250ml
		'643026'  => 175, //Naturligtvis Hudkräm 175ml
		'643022'  => 300, //Naturligtvis Handtvål 300ml
		'643020'  => 75, //Naturligtvis Handkräm 75 ml
		'643006'  => 50, //Naturligtvis Ansiktskräm torr hy 50ml
		'643004'  => 50, //Naturligtvis Ansiktskräm normal hy 50ml
		'643001'  => 160, //Naturligtvis Ansiktstvätt 160 ml
		'100046'  => 250, //Moringapulver 250g
		'100045'  => 250, //Kelppulver 250
		'100044'  => 125, //Ekologiskt Guaranapulver 125g
		'100042'  => 125, //Ginko Bilobapulver 125g
		'100043'  => 125, //Schisandrapulver 125g
		'100047'  => 125, //Baobabpulver 125g
		'100029'  => 250  //Shiitakepulver (250g)	
	);
	return $bag_sizes[$sku];
}

function getForwarder($order) {
	$tot_weight;
	foreach ( $order->get_items() as $item_id => $item_data ) {
		$product = $order->get_product_from_item( $item_data );
		$tot_weight = $tot_weight + (getBagSize($product->get_sku()) * getQty($item_data['Storlek'], $item_data['qty'], $product->get_sku()));		
	}
	if ($tot_weight > 1750) {
		return "ASPO";
	} else {
		return "PAE";
	}
}



