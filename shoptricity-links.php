<?php
/**
 Plugin Name: Shoptricity Links
 Plugin URI: http://www.shoptricity.com/
 Description: This plugin checks for merchant links and rewrites them as Shoptricity links.

 Version: 1.1
 Author: Joshua Odmark
 Author URI: http://www.shoptricity.com
*/

add_action( 'wp_footer', 'shopt_insert_footer_js' );

if(!function_exists('shopt_insert_footer_js')):
	function shopt_insert_footer_js(){
		$unique_token = get_option('shoptricity_unique_token');
		$file_name = "shoptricity_redirects";
		$check = shopt_links_check_file_cache($file_name);
		if($check):
			$remote_links = $check;
		else:
			$remote_links = shopt_get_approved_redirects();
			shopt_links_write_file_cache(dirname(__FILE__)."/".$file_name,$remote_links);
		endif;
		$links = explode("\n",$remote_links);
		$new_links = implode(";;;", $links);
		echo '<script type="text/javascript" src="'.plugin_dir_url(__FILE__).'shoptricity-links.js"></script>';
		?>
		<script type="text/javascript">
		var shoptricity_links = {
			domain: 'http://<?php echo $_SERVER['HTTP_HOST']; ?>',
			unique_token: '<?php echo (strlen($unique_token > 0)) ? $unique_token : "shoptricityLinks"; ?>',
			links: '<?php echo $new_links; ?>',
			_list: new Array(),
			getRedirectUrl : function(url) {
				var redirectUrl = false;
				for(i=0;i<this._list.length;i++) {
					var redirect = this._list[i];
					var rx = new Redirect();
					var parts = redirect.split(",,,");
					if(parts.length == 8){
						rx._init(parts[0],parts[1],parts[2],parts[3],parts[4],parts[5],parts[6],parts[7]);
						var result = rx.getMatch(url);
						if(result.isMatch){
							redirectUrl = "http://www.shoptricity.com/partner?id="+result.ID+"&shopt_affiliate="+this.unique_token+"&url="+encodeURIComponent(url);
						}
					}
				}
				return redirectUrl;
			},
			check_links: function(){
				var current_domain = this.getTLD(this.domain);
				var allLinks = document.links;
				for (var i=0; i<allLinks.length; i++) {
				  var href = allLinks[i].href;
				  if((href.indexOf("http://") != -1 || href.indexOf("https://") != -1) && href.indexOf(current_domain) == -1){
				  	if(this._list.length==0){
				  		this._list = this.links.split(";;;");
				  	}
				  	var redirectUrl = this.getRedirectUrl(href);
						if(redirectUrl != false){
							allLinks[i].href = redirectUrl;
						}
				  }
				}
			},
			getTLD : function(url){
				var parts = url.split("/");
				var domain_parts = parts[2].split(".");
				var domain = domain_parts[(domain_parts.length-2)]+"."+domain_parts[(domain_parts.length-1)];
				return domain;
			},
		}
		
		shoptricity_links.check_links();
		</script>
		<?php
	}
endif;

if(!function_exists('shopt_get_approved_redirects')):
	function shopt_get_approved_redirects(){
		$links = file_get_contents("http://www.shoptricity.com/api/get_approved_redirects.php");
		return $links; 
	}
endif;

if(is_admin()):
	add_action('admin_menu', 'shopt_links_menu');
	add_action('admin_notices', 'shopt_links_check');
endif;

if(!function_exists('shopt_links_menu')):
	function shopt_links_menu(){
		add_menu_page( __( 'Shoptricity Links', 'Shoptricity Links' ), __( '<span style="font-size:12px;">'.__('Shoptricity Links').'</span>', 'Shoptricity Links' ), 8, 'shopt_links_settings', 'shopt_links_settings');
	}
endif;

if(!function_exists('shopt_links_settings')):
	function shopt_links_settings(){
		if(isset($_POST['data'])):
			$username = $_POST['data']['username'];
			$check = explode("|", file_get_contents('http://www.shoptricity.com/api/check-register.php?username='.urlencode($username)));
			if($check[0]=="INVALID"||$check[0]=="FALSE"):
				echo "<p>The username you entered was not found. Please go to Shoptricity.com to register or select another username.</p>";
			elseif($check[0]=="TRUE"):
				$unique_token = $check[1];
				if(strlen($check[1]) > 0):
					update_option("shoptricity_unique_token", $check[1]);
					update_option("shoptricity_username", $username);
					echo "<p>You have successfully configured the plugin!</p>";
				else:
					echo "<p>We could not locate the appropriate information with that username. Please contact support@shoptricity.com.</p>";
				endif;	
			else:
				echo "<p>An error occurred, please try again.</p>";
			endif;
		endif;
		$unique_token = get_option('shoptricity_unique_token');
		$username = get_option('shoptricity_username');
		?>
		<style>
		.logo{
			float: left;
			width: 250px;
		}
		.container{
			margin-left: 250px;
		}
		.container p{
			font-size: 1em;
		}
		form p{
			margin: 10px 0px;
		}
		label{
			float: left;
			width: 250px;
			font-size: 1em;
			padding: 5px 0px;
			font-weight: bold;
		}
		input[type="text"], select{
			width: 400px;
			border: 1px solid #333;
			padding: 5px;
		}
		input[type="submit"]{
			padding: 5px 15px;
			font-size: 1.2em;
			margin-top: 10px;
		}
		</style>
		<div class="logo">
			<a href="http://www.shoptricity.com?ref=wp-shopt-links" target="_blank"><img src="<?php echo plugin_dir_url(__FILE__)."shoptricity-links-logo.png"; ?>" border="0"></a>
		</div>
		<div class="container">
			<h1>Settings</h1>
			<p>This page allows you to configure your Shoptricity username for the Shoptricity Links plugin.</p>
			<p>If you do not already have an account at Shoptricity.com, <a href="http://www.shoptricity.com/create-an-account?ref=wp-shopt-links" target="_blank">click here to register</a>. To view your earnings, please visit Shoptricity.com.</p>
			<form action="" method="POST">
				<p>
					<label>Shoptricity Username:</label>
					<input type="text" name="data[username]" value="<?php echo $username; ?>" />
				</p>
				<p>
					<input type="submit" value="Update" />
				</p>
			</form>
		</div>
		<?php
	}
endif;

if(!function_exists('shopt_links_check')):
	function shopt_links_check(){
		$unique_token = get_option('shoptricity_unique_token');
		if(strlen($unique_token) == 0):
			shopt_show_admin_message("Please enter your username on the <a href='admin.php?page=shopt_links_settings'>Shoptricity Links</a> page to complete activation.", true);
		endif;
	}
endif;

if(!function_exists('shopt_show_admin_message')):
	function shopt_show_admin_message($message, $error_message=false){
		if ($error_message):
			echo '<div id="message" class="error">';
		else:
			echo '<div id="message" class="updated fade">';
		endif;
	
		echo "<p><strong>$message</strong></p></div>";
	}
endif;

function shopt_links_check_file_cache($file){
	$data = false;
	$path = dirname(__FILE__)."/";
	$filepath = $path.$file;
	if (file_exists($filepath)):
		$timestamp = filemtime($filepath);
		if(!shopt_links_is_file_cache_expired($timestamp)):
			$data = file_get_contents($filepath);
		endif;
	endif;
	
	return $data;
}

function shopt_links_write_file_cache($file,$data){
	$fh = fopen($file, 'w') or die("can't open file");
	fwrite($fh, $data);
	fclose($fh);
}

function shopt_links_is_file_cache_expired($timestamp){
	$expiration_length = 3; //In days
	
	$expiration = strtotime($expiration_length." days");
	
	if($timestamp >= $expiration):
		return true;
	else:
		return false;
	endif;
}
?>