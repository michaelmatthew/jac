<?php
/*
   Plugin Name: Jigoshop Abandoned Carts (jac)
   Plugin URI: http://wordpress.org/extend/plugins/jigoshop-abandoned-carts/
   Version: 0.1
   Author: Michael Matthew
   Description: Capture Abandoned Carts in Jigoshop
   Text Domain: jigoshop-abandoned-carts
   License: GPLv3
  */

  add_action('admin_menu', 'jac_admin_actions');
	
	function jac_admin_actions()
	{
		$count = count(get_posts(array('numberposts'=> -1, 'post_status' => 'publish', 'post_type' => 'jac_cart', 'orderby'=> 'modified','order' => 'DESC')));
		add_menu_page( 'Abandoned Carts', "Abandoned [<span class=\"count\">{$count}</span>]", 'manage_options', __FILE__, 'jac_admin', null, '55.9' );
	}
	
	function jac_admin()
	{
		if (isset($_GET['action']))
		{
			if ($_GET['action'] === 'trash')
			{
				jac_trash_cart($_GET['id']);
			}
		}
		?>
		<h1>Jigoshop Abandoned Carts (jac) [<span class="count-down"></span>]</h1>
		
		<div id="jac-admin-results">
			<?= do_action('get_jac_admin_table'); ?>
		</div>
		<?
		do_action('jac_admin_css');
		do_action('jac_admin_javascript');
	}
				

	
	
	
	add_action('get_jac_admin_table', 'get_jac_admin_table');

	
	function get_jac_admin_table()
	{
		$posts = get_posts(array('numberposts'=> -1, 'post_status' => 'publish', 'post_type' => 'jac_cart', 'orderby'=> 'modified','order' => 'DESC'));
	?>
		<table class="widefat">
			<thead>
				<tr>
					<th class="manage-column column-cb check-column"></th>
					<th>User Data</th>
					<th>Last Updated</th>
					<th>Value</th>
					<th>Items</th>
				</tr>
			</thead>
			<tbody>
				<? foreach ($posts as $post) : ?>
					<?
					$userdata = get_userdata($post->post_author);
					$cart_contents = get_post_meta($post->ID, 'cart_contents', true);
					$cart_contents = is_array($cart_contents) ? $cart_contents : array();
					$total = get_post_meta($post->ID, 'total', true);
					$session_id = get_post_meta($post->ID, 'session_id', true);
					
					$age = floor((time() - strtotime($post->post_modified_gmt)) / 60);
					
					if ($age < 60)
					{
						$age .= " Min(s)";
					}
					else if ($age < 1440)
					{
						$age = round($age / 60) . " Hour(s)";
					}
					else
					{
						$age = floor(($age / 60) / 24) . "Day(s)";
					}

					$last_updated = date('y-m-d H:i:s', strtotime($post->post_modified));
					$count = is_array($cart_contents) ? count($cart_contents) : "";
					$phone = get_user_meta($userdata->ID, 'billing-phone', true);
					$email = get_user_meta($userdata->ID, 'billing-email', true);
					$ip = get_post_meta($post->ID, 'ip', true);
					$last_page = get_post_meta($post->ID, 'last_page', true);
					$user_agent = get_post_meta($post->ID, 'user_agent', true);
					
					$checkout_data = get_post_meta($post->ID, 'checkout_data', true);
					
					#var_dump($post);
					
					$name = strlen($userdata->first_name) ? "{$userdata->first_name} {$userdata->last_name}" : null;
					
					if (is_null($name))
					{
						if (strlen($checkout_data['first_name']))
						{
							$name = "{$checkout_data['first_name']} {$checkout_data['last_name']}";
						}
						else
						{
							$name = "Anonymous";
						}
					}
					
					$phone = strlen($checkout_data['phone']) ? $checkout_data['phone'] : $phone;
					$email = strlen($checkout_data['email']) ? $checkout_data['email'] : $email;
					
					
					?>
					<tr>
						<td class="manage-column">
							<span class="trash"><a class="submitdelete" title="Move this item to the Trash" href="/wp-admin/admin.php?page=jigoshop-abandoned-carts/jigoshop-abandoned-carts.php&action=trash&id=<?= $post->ID?>" data-post-id="<?= $post->ID?>">Trash</a></span>
						</td>
						<td>
							<strong><?= $name ?></strong> [<?= $userdata->user_login ? $userdata->user_login : "guest" ?>]<br />
							<?= $email ? "<a href='mailto:{$email}'>{$email}</a><br />" : ""; ?>
							IP: <a href="http://whatismyipaddress.com/ip/<?= $ip ?>" target="_ip"><?= $ip ?></a><br />
							<?= $phone ? "Phone: {$phone}<br />" : "" ?>
							Last page: <a href="<?= $last_page ?>" target="_shop"><?= $last_page ?></a><br />
							<small class="browser-data"><?= $user_agent ?></small>
						</td>
						<td>
						<?= $last_updated ?><br />
						<?= $age ?>
						</td>
						<td><?= $total ?></td>
						<td>
							<? #var_dump($item)?>
							<table width="100%">
								<thead>
									<tr>
										<th width="100">SKU</th>
										<th width="50">Qty</th>
										<th width="*">Title</th>
										<th width="50">Instk</th>
									</tr>
								</thead>
								<tbody>
								<? foreach ($cart_contents as $item) :?>
								<tr>
									<td><?= $item['data']->sku ?></td>
									<td><?= $item['quantity'] ?></td>
									<td><?= $item['data']->post->post_title ?></td>
									<td><?= $item['data']->post->stock ?></td>
								</tr>
								<? endforeach ?>
								</tbody>
							</table>
						</td>
					</tr>
				<? endforeach ?>
			</tbody>
		</table>
		
	<?
	}
	
	
	
	add_action('wp_ajax_get_jac_admin_table', 'ajax_get_jac_admin_table');
	
	function ajax_get_jac_admin_table()
	{
		do_action('get_jac_admin_table');
		die();
	}
	

	
	add_action('wp_footer', 'jac_sync_cart');

	function jac_sync_cart()
	{
		#print "SYNC ";
		if (function_exists('is_jigoshop') && is_jigoshop() || isset($_POST['jac_ajax']))
		{
			$jac_cart_posts = get_posts(array('numberposts'=> -1, 'post_status' => 'publish', 'post_type' => 'jac_cart', 'meta_key' => 'session_id', 'meta_value'=>session_id()));
		
			if (count($jac_cart_posts))
			{
				$post = array_pop($jac_cart_posts);
				jac_update_cart($post->ID);
			}
			else
			{
				jac_add_cart();
			}
		}
	}
	
	function jac_add_cart()
	{
		if (count(jigoshop_cart::$cart_contents))
		{
			$post_id = null;
			$post_title = isset($_POST['post_title']) ? utf8_encode($_POST['post_title']) : "";
			$post_content = isset($_POST['post_content']) ? utf8_encode($_POST['post_content']) : "";

			$post_data = array
			(
				'post_title' => $post_title,
				'post_status' => 'publish',
				'post_date' => date('Y-m-d : H:i:s'),
				'post_author' => get_current_user_id(),
				'post_type' => 'jac_cart'
			);
		
		
			if($post_id = wp_insert_post($post_data))
			{
				jac_update_cart($post_id);
			}
		}
	}
	
	function jac_update_cart($post_id)
	{
		if (count(jigoshop_cart::$cart_contents))
		{
			$post_data = array('ID'=>$post_id, 'post_author' => get_current_user_id());

			wp_update_post($post_data);
		
			$checkout_data = get_post_meta($post_id, 'checkout_data', true);
		
			$checkout_data = is_array($checkout_data) ? $checkout_data : array('first_name'=>'', 'last_name'=>'', 'phone'=>'', 'email'=>'');
		
			$checkout_data['first_name'] = isset($_POST['first_name']) ? $_POST['first_name'] : $checkout_data['first_name'];
			$checkout_data['last_name'] = isset($_POST['last_name']) ? $_POST['last_name'] : $checkout_data['last_name'];
			$checkout_data['phone'] = isset($_POST['phone']) ? $_POST['phone'] : $checkout_data['phone'];
			$checkout_data['email'] = isset($_POST['email']) ? $_POST['email'] : $checkout_data['email'];
		
			$post_meta_data = array
			(
				'cart_contents' => jigoshop_cart::$cart_contents,
				'total' => jigoshop_cart::get_cart_total(),
				'session_id' => session_id(),
				'ip' => $_SERVER['REMOTE_ADDR'],
				'last_page' => $_SERVER['REQUEST_URI'],
				'checkout_data' => $checkout_data,
				'user_agent' => $_SERVER['HTTP_USER_AGENT']
			);
			foreach($post_meta_data as $key=>$value)
			{
				update_post_meta($post_id, $key, $value);
			}
			return $post_id;
		}
		else
		{
			return jac_trash_cart($post_id);
		}
	}
	
	
	add_action('wp_ajax_jac_trash_cart', 'ajax_jac_trash_cart');
	
	function ajax_jac_trash_cart()
	{
		if (jac_trash_cart($_REQUEST['post_id']))
		{
			print "ok";
		}
		die();
	}
	
	
	
	function jac_trash_cart($post_id)
	{
		$post = get_post($post_id);
		if (isset($post->post_type) && $post->post_type === 'jac_cart')
		{
			if (session_id() === get_post_meta($post->ID, 'session_id', true) || is_admin())
			{
				return wp_trash_post($post_id);
			}
			else
    		{
    			return false;
    		}
		}
		else
		{
			return false;
		}
	}
	
	

	
	add_action('wp_ajax_jac_update_cart_checkout_data', 'jac_update_cart_checkout_data');
	add_action('wp_ajax_nopriv_jac_update_cart_checkout_data', 'jac_update_cart_checkout_data');
	function jac_update_cart_checkout_data()
	{
		jac_sync_cart();
		die();
	}
	
	
	add_action('jigoshop_new_order', 'jac_complete_session_cart');
	
	function jac_trash_session_cart()
	{
		$jac_cart_posts = get_posts(array('numberposts'=> -1, 'post_status' => 'any', 'post_type' => 'jac_cart', 'meta_key' => 'session_id', 'meta_value'=>session_id()));
		
		if (count($jac_cart_posts))
		{
			foreach($jac_cart_posts as $post)
			{
				jac_trash_cart($post->ID);
			}
		}
	}
	
	function jac_complete_session_cart()
	{
		$jac_cart_posts = get_posts(array('numberposts'=> -1, 'post_status' => 'any', 'post_type' => 'jac_cart', 'meta_key' => 'session_id', 'meta_value'=>session_id()));
		
		if (count($jac_cart_posts))
		{
			foreach($jac_cart_posts as $post)
			{
				update_post_meta($post->ID, 'complete', true);
				jac_trash_cart($post->ID);
			}
		}
	}
	
	add_action('admin_menu', 'jac_admin_menu_css');
	function jac_admin_menu_css()
	{
		?>
		<style>
			#toplevel_page_jigoshop-abandoned-carts-jigoshop-abandoned-carts > a > .wp-menu-image
			{
				background: url('/wp-content/plugins/jigoshop/assets/images/icons/menu_icons.png') no-repeat;
				background-position: -70px -32px !important;
			}
		</style>
		<?
	}
	
	add_action( 'jac_admin_css', 'jac_admin_css' );
	
	function jac_admin_css()
	{
	?>
	<style media='print'>
		#adminmenu, #adminmenuback, #wpadminbar, .update-nag, #jac-admin-results .manage-column, #jac-admin-results .browser-data, #wpbody-content > h1 > .count-down
		{
			display: none;
		}
		#wpcontent
		{
			margin: 0px;
			padding: 0px;
			width: auto;
		}
		#wpbody-content > h1
		{
			margin: 0px;
			padding: 0px;
			font-size: 15px;
			line-height: 20px;
		}

	</style>
	<?
	}
	
	add_action( 'jac_admin_javascript', 'jac_admin_javascript' );

	function jac_admin_javascript() {
	?>
	<script type="text/javascript" >
	jQuery(document).ready(function($) {

		var refresh_secs = 30;
		var countdown_timer;
		var refresh_timer;
		
		function refresh_jac_admin_table()
		{
			$(".count-down").text("~");
			clearTimeout(refresh_timer);
			var data = {
				action: 'get_jac_admin_table'
			};

			$.post(ajaxurl, data, function(response)
			{
				$("#jac-admin-results").html(response);
				refresh_timer = setTimeout(function(){refresh_jac_admin_table()}, refresh_secs * 1000);
				$("#toplevel_page_jigoshop-abandoned-carts-jigoshop-abandoned-carts > a > .wp-menu-name > .count").text($("#jac-admin-results > table > tbody> tr").length);
				
				countDown(refresh_secs);
			});
		}

		refresh_timer = setTimeout(function(){refresh_jac_admin_table()}, refresh_secs * 1000);
		
		function countDown(secs)
		{
			if (secs >= 0)
			{
				$(".count-down").text(secs);
				countdown_timer = setTimeout(function(){countDown(secs-1)}, 1000);
			}
		}
		
		countDown(refresh_secs);
		
		$(".count-down").click(function()
		{
			refresh_secs = 5;
			clearTimeout(refresh_timer);
			clearTimeout(countdown_timer);
			
			refresh_jac_admin_table();
		});
		
		
		$("#jac-admin-results .submitdelete").click(function(evt)
		{
			evt.preventDefault();
			var post_id = $(this).data("post-id");
			jac_trash_cart(post_id);
		});
		
		
		function jac_trash_cart(post_id)
		{

			var data = {
				action: 'jac_trash_cart',
				post_id: post_id
			};

			$.post(ajaxurl, data, function(response)
			{
				if (response == 'ok')
				{
					clearTimeout(refresh_timer);
					clearTimeout(countdown_timer);
					refresh_jac_admin_table();
				}
			});
		}
		
		refresh_jac_trash_cart();
		
	});
	</script>
	<?php
	}
	
	add_action( 'jigoshop_review_order_after_submit', 'jac_checkout_javascript' );

	function jac_checkout_javascript() {
	?>
	<script type="text/javascript" >
	jQuery(document).ready(function($) {

		$("form.checkout input").live('change', function()
		{
			updateJacCartCheckoutData($(this))
		});
		
		function updateJacCartCheckoutData($input)
		{
			
			var first_name = $("#billing-first_name").val();
			var last_name = $('#billing-last_name').val();
			var phone = $('#billing-phone').val();
			var email = $('#billing-email').val();
			
			var data =
			{
				action: 'jac_update_cart_checkout_data',
				first_name: first_name,
				last_name: last_name,
				phone: phone,
				email: email,
				jac_ajax: true
			};

			$.post('<?php echo admin_url('admin-ajax.php'); ?>', data, function(response)
			{});
		}

	});
	</script>
	<?php
	}

?>
